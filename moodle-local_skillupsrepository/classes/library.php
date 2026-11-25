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

namespace local_skillupsrepository;

use stdClass;

/**
 * Write immutable resource revisions and manage publication.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class library {
    /** @var array Audience policies supported by the library. */
    public const AUDIENCES = ['private', 'institution', 'public'];
    /** @var array Licenses presented in the contribution form. */
    public const LICENSES = [
        'arr' => 'allrightsreserved', 'cc0' => 'public', 'ccby' => 'cc-4.0',
        'ccbysa' => 'cc-sa-4.0', 'ccbync' => 'cc-nc-4.0', 'ccbyncsa' => 'cc-nc-sa-4.0',
    ];

    /**
     * Save a new version of an institutional resource.
     *
     * @param int $categoryid Owning Moodle category
     * @param int $id Existing asset or 0
     * @param stdClass $input Form values
     * @return int Asset ID
     */
    public static function save(int $categoryid, int $id, stdClass $input): int {
        global $DB, $USER;
        return store::write($categoryid, static function () use ($DB, $USER, $categoryid, $id, $input): int {
            if (!access::can('contribute', $categoryid) && !access::can('moderate', $categoryid)) {
                access::fail();
            }
            $asset = $id ? store::get(store::ASSET, $id) : null;
            if ($asset && ($asset->categoryid != $categoryid || !access::internal($asset) || $asset->archived)) {
                access::fail();
            }
            $previous = $asset ? store::get(store::REVISION, (int)$asset->currentid) : null;
            if ($previous && ($asset->generation != $input->generation || $previous->state === 'pending')) {
                access::fail('stale');
            }
            $audience = $asset->audience ?? $input->audience;
            if (
                !in_array($audience, self::AUDIENCES, true) || (!$asset && $audience === 'public' && !access::public_enabled()) ||
                !isset(self::LICENSES[$input->license])
            ) {
                access::fail('invalid');
            }
            $version = (object)[
                'number' => ($previous->number ?? 0) + 1, 'authorid' => $USER->id, 'reviewerid' => 0, 'state' => 'draft',
                'license' => $input->license, 'allowcopy' => (int)!empty($input->allowcopy),
                'allowdownload' => (int)!empty($input->allowdownload), 'created' => time(), 'published' => 0, 'reviewed' => 0,
                'sourceurl' => null, 'filename' => '', 'mimetype' => '', 'bytes' => 0,
            ];
            foreach (
                [
                'title' => 180, 'description' => 12000, 'collection' => 120, 'area' => 120, 'program' => 120,
                'level' => 80, 'credit' => 180, 'tags' => 255, 'keywords' => 255,
                ] as $field => $limit
            ) {
                $value = trim($input->$field ?? '');
                if (($field === 'title' && $value === '') || \core_text::strlen($value) > $limit) {
                    access::fail('invalid');
                }
                $version->$field = $value;
            }
            if ($version->allowcopy && (!$version->allowdownload || $version->license === 'arr')) {
                access::fail('copylicense');
            }
            $file = empty($input->payload) ? null : files::draft((int)$input->payload);
            $address = trim($input->sourceurl ?? '');
            if ($address !== '' && $file) {
                access::fail('onepayload');
            }
            if ($address !== '') {
                $parts = parse_url($address);
                if (
                    strlen($address) > 2048 || !filter_var($address, FILTER_VALIDATE_URL) ||
                    !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true) ||
                    empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                ) {
                    access::fail('badurl');
                }
                $version->sourceurl = $address;
                $version->kind = 'link';
            } else if ($file) {
                [$version->kind, $version->mimetype] = files::validate($file);
            } else if ($previous && $file = files::stored($asset, $previous)) {
                $version->kind = $previous->kind;
                $version->mimetype = $previous->mimetype;
            } else {
                access::fail('onepayload');
            }
            if ($file) {
                $version->filename = $file->get_filename();
                $version->bytes = $file->get_filesize();
            }
            if (!$asset) {
                $asset = (object)[
                    'categoryid' => $categoryid, 'ownerid' => $USER->id, 'audience' => $audience,
                    'currentid' => 0, 'publishedid' => 0, 'archived' => 0, 'generation' => 0, 'uses' => 0, 'updated' => time(),
                ];
                $asset->id = $DB->insert_record(store::ASSET, $asset);
            }
            $version->assetid = $asset->id;
            $version->id = $DB->insert_record(store::REVISION, $version);
            if ($file) {
                $context = \context_coursecat::instance($categoryid);
                get_file_storage()->create_file_from_storedfile([
                    'contextid' => $context->id, 'component' => access::COMPONENT, 'filearea' => files::AREA,
                    'itemid' => $version->id, 'filepath' => '/', 'filename' => $version->filename,
                    'mimetype' => $version->mimetype, 'userid' => $USER->id, 'author' => $version->credit,
                    'license' => self::LICENSES[$version->license],
                ], $file);
            }
            $asset->currentid = $version->id;
            $asset->generation++;
            $asset->updated = time();
            $DB->update_record(store::ASSET, $asset);
            store::audit((int)$asset->id, (int)$version->id, 'created');
            return (int)$asset->id;
        });
    }

    /**
     * Transition the current draft, review a submission or change distribution.
     *
     * @param int $id Asset ID
     * @param int $generation Expected asset generation
     * @param string $action Transition
     * @param string $note Review note or target audience
     */
    public static function change(int $id, int $generation, string $action, string $note = ''): void {
        global $DB, $USER;
        $categoryid = (int)store::get(store::ASSET, $id)->categoryid;
        store::write($categoryid, static function () use ($DB, $USER, $id, $generation, $action, $note, $categoryid): void {
            $asset = store::get(store::ASSET, $id);
            $version = store::get(store::REVISION, (int)$asset->currentid);
            $contributor = access::internal($asset) && access::can('contribute', $categoryid);
            $moderator = access::can('moderate', $categoryid);
            if (($action === 'submit' && !$contributor && !$moderator) || ($action !== 'submit' && !$moderator)) {
                access::fail();
            }
            if ($asset->generation != $generation) {
                access::fail('stale');
            }
            $note = trim($note);
            if (\core_text::strlen($note) > 2000) {
                access::fail('invalid');
            }
            if ($action === 'submit' && !$asset->archived && $version->state === 'draft') {
                $version->state = 'pending';
            } else if (in_array($action, ['approve', 'reject'], true) && !$asset->archived && $version->state === 'pending') {
                if ($action === 'reject' && $note === '') {
                    access::fail('reviewrequired');
                }
                $version->state = $action === 'approve' ? 'approved' : 'rejected';
                $version->reviewed = time();
                $version->reviewerid = $USER->id;
                $version->reviewnote = $note;
                if ($action === 'approve') {
                    $version->published = $version->reviewed;
                    $asset->publishedid = $version->id;
                }
            } else if ($action === 'archive' && !$asset->archived) {
                $asset->archived = 1;
            } else if ($action === 'restore' && $asset->archived) {
                $asset->archived = 0;
            } else if (
                $action === 'audience' && in_array($note, self::AUDIENCES, true) &&
                ($note !== 'public' || access::public_enabled())
            ) {
                $asset->audience = $note;
            } else {
                access::fail('invalidtransition');
            }
            $asset->generation++;
            $asset->updated = time();
            $DB->update_record(store::ASSET, $asset);
            $DB->update_record(store::REVISION, $version);
            store::audit($id, (int)$version->id, $action);
        });
    }
}
