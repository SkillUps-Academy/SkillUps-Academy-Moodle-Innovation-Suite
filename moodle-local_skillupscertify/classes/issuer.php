<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_skillupscertify;

use local_skillupscertify\credential\pdf;
use local_skillupscertify\evidence\moodle;
use moodle_exception;
use stdClass;
use stored_file;

/**
 * Issue, revoke and replace credentials with immutable document bytes.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issuer {
    /** @var string Files are served only by the credential download endpoint. */
    public const AREA = 'issued';

    /** @var string PDF filename within an item's private file area. */
    public const FILENAME = 'certificate.pdf';

    /**
     * Publish a new credential or return the active credential on repeat issuance.
     *
     * @param stdClass $course Course
     * @param int $templateid Template
     * @param int $userid Subject
     * @param int $groupid Workflow group
     * @param int $revisionid Pinned revision or zero for current
     * @param int $previousid Credential explicitly replaced
     * @param int $batchitemid Pending batch recipient, if applicable
     * @return array|null Record, created flag, or null if current evidence is insufficient
     */
    public static function issue(
        stdClass $course,
        int $templateid,
        int $userid,
        int $groupid = 0,
        int $revisionid = 0,
        int $previousid = 0,
        int $batchitemid = 0,
    ): ?array {
        $context = \context_course::instance($course->id);
        require_capability(access::CAP . 'issue', $context);
        if ($previousid) {
            require_capability(access::CAP . 'revoke', $context);
        }
        if (!access::sees($course, $userid, $groupid)) {
            access::deny();
        }
        return store::atomic(self::holder_lock($course->id, $userid), static function () use (
            $course,
            $templateid,
            $userid,
            $groupid,
            $context,
            $revisionid,
            $previousid,
            $batchitemid,
        ): ?array {
            global $DB;
            if (
                $batchitemid && !$DB->record_exists(store::ITEM, [
                'id' => $batchitemid, 'courseid' => $course->id, 'userid' => $userid, 'state' => 'queued',
                ])
            ) {
                return null;
            }
            if (!access::sees($course, $userid, $groupid)) {
                return null;
            }
            $template = store::scoped(store::TEMPLATE, $course->id, $templateid);
            if (!$template->enabled) {
                return null;
            }
            $criteria = ['courseid' => $course->id, 'templateid' => $templateid, 'userid' => $userid];
            $records = $DB->get_records(store::ISSUE, $criteria, 'id DESC', '*', 0, 1);
            $latest = reset($records) ?: null;
            if ($previousid && (!$latest || $latest->id != $previousid)) {
                throw new moodle_exception('notlatest', access::C);
            }
            if (!$previousid && $latest) {
                return $latest->status === 'valid' ? [$latest, false] : null;
            }
            $revision = store::revision($template, $revisionid);
            $holder = access::participant($course, $userid);
            $now = time();
            $proof = $holder ? (new moodle())->proof($course, $revision, $userid, $now) : null;
            if (!$proof || $proof['time'] <= 0 || $proof['time'] > $now) {
                return null;
            }
            $token = bin2hex(random_bytes(24));
            $snapshot = self::claims($course, $holder, $revision, $proof, $now, $token);
            $verification = access::url('verify', ['code' => $token])->out(false);
            $document = (new pdf())->render($snapshot, $verification);
            if (!str_starts_with($document, '%PDF-') || strlen($document) > 3 * 1024 * 1024) {
                throw new moodle_exception('pdfinvalid', access::C);
            }
            if ($latest && $latest->status === 'valid') {
                self::close($latest, 'replaced', '');
            }
            $issueid = $DB->insert_record(store::ISSUE, $criteria + [
                'revisionid' => $revision->id,
                'tokenhash' => hash('sha256', $token),
                'activekey' => hash('sha256', "$course->id:$templateid:$userid"),
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'pdfhash' => hash('sha256', $document),
                'status' => 'valid', 'supersedes' => $previousid, 'timeissued' => $now,
            ]);
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id, 'component' => access::C, 'filearea' => self::AREA,
                'itemid' => $issueid, 'filepath' => '/', 'filename' => self::FILENAME,
            ], $document);
            store::audit($course->id, $previousid ? 'reissue' : 'issue', $issueid);
            return [$DB->get_record(store::ISSUE, ['id' => $issueid], '*', MUST_EXIST), true];
        });
    }

    /**
     * Revoke a current credential and retain its original document.
     *
     * @param stdClass $course Course
     * @param int $id Credential ID
     * @param string $reason Private administrative note
     */
    public static function revoke(stdClass $course, int $id, string $reason): void {
        require_capability(access::CAP . 'revoke', \context_course::instance($course->id));
        $issue = store::scoped(store::ISSUE, $course->id, $id);
        if (!access::sees($course, $issue->userid)) {
            access::deny();
        }
        $reason = trim(clean_param($reason, PARAM_TEXT));
        if ($reason === '' || \core_text::strlen($reason) > 240) {
            throw new moodle_exception('reasonrequired', access::C);
        }
        store::atomic(self::holder_lock($course->id, $issue->userid), static function () use ($course, $id, $reason): void {
            $issue = store::scoped(store::ISSUE, $course->id, $id);
            if ($issue->status === 'valid') {
                self::close($issue, 'revoked', $reason);
            }
        });
    }

    /**
     * Return the same persistent bytes assigned at issuance.
     *
     * @param stdClass $issue Registry row
     * @return stored_file
     */
    public static function file(stdClass $issue): stored_file {
        $context = \context_course::instance($issue->courseid);
        $file = get_file_storage()->get_file($context->id, access::C, self::AREA, $issue->id, '/', self::FILENAME);
        if (!$file || !hash_equals($issue->pdfhash, hash('sha256', $file->get_content()))) {
            throw new moodle_exception('pdfinvalid', access::C);
        }
        return $file;
    }

    /**
     * Derive the lock shared with privacy deletion for one recipient.
     *
     * @param int $courseid Course
     * @param int $userid User
     * @return string
     */
    public static function holder_lock(int $courseid, int $userid): string {
        return "holder:$courseid:$userid";
    }

    /**
     * Freeze Moodle display data and PDF labels once.
     *
     * @param stdClass $course Issuing course
     * @param stdClass $holder Recipient
     * @param stdClass $revision Template revision
     * @param array $proof Completion evidence
     * @param int $now Timestamp
     * @param string $token Verification token
     * @return array
     */
    private static function claims(
        stdClass $course,
        stdClass $holder,
        stdClass $revision,
        array $proof,
        int $now,
        string $token,
    ): array {
        $design = json_decode($revision->design, true, 16, JSON_THROW_ON_ERROR);
        return [
            'schema' => 1,
            'design' => $design,
            'evidence' => $proof,
            'publicmeta' => (bool)$revision->publicmeta,
            'fields' => [
                'recipient' => fullname($holder),
                'course' => trim(clean_param($course->fullname, PARAM_TEXT)),
                'issuer' => $design['issuer'],
                'issuedate' => userdate($now),
                'completiondate' => userdate($proof['time']),
                'serial' => strtoupper(substr(hash('sha256', $token), 0, 16)),
            ],
        ];
    }

    /**
     * Retire a credential without touching its issued snapshot or File API document.
     *
     * @param stdClass $issue Registry row
     * @param string $status Terminal status
     * @param string $reason Private note
     */
    private static function close(stdClass $issue, string $status, string $reason): void {
        global $DB;
        $DB->update_record(store::ISSUE, [
            'id' => $issue->id, 'activekey' => null, 'status' => $status, 'reason' => $reason, 'timerevoked' => time(),
        ]);
        store::audit($issue->courseid, $status, $issue->id);
    }
}
