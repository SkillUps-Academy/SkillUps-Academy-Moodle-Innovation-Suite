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

/**
 * Consult public verification records using one-way, high-entropy identifiers.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class verification {
    /**
     * Return only fields authorized for a public response.
     *
     * @param string $code Hexadecimal verification secret
     * @return array|null
     */
    public static function lookup(string $code): ?array {
        global $DB;
        if (!get_config(access::C, 'publicverification') || !preg_match('/^[0-9a-f]{48}$/D', $code)) {
            return null;
        }
        $issues = store::ISSUE;
        $templates = store::TEMPLATE;
        $revisions = store::REVISION;
        $issue = $DB->get_record_sql(
            "SELECT i.id, i.status, i.tokenhash, i.pdfhash, i.snapshot, r.publicmeta
               FROM {{$issues}} i
               JOIN {{$templates}} t ON t.id = i.templateid AND t.courseid = i.courseid
               JOIN {{$revisions}} r ON r.templateid = t.id AND r.revision = t.revision
              WHERE i.tokenhash = :code",
            ['code' => hash('sha256', $code)]
        );
        if (!$issue) {
            return null;
        }
        $result = [
            'status' => $issue->status,
            'fingerprint' => $issue->pdfhash,
            'reference' => strtoupper(substr($issue->tokenhash, 0, 16)),
        ];
        $snapshot = store::snapshot($issue);
        if ($issue->publicmeta && $snapshot['publicmeta']) {
            $result += [
                'title' => $snapshot['design']['title'],
                'issuer' => $snapshot['fields']['issuer'],
                'issuedate' => $snapshot['fields']['issuedate'],
            ];
        }
        return $result;
    }
}
