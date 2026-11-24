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

namespace local_skillupsintegration;

use stdClass;

/**
 * Materialise a safe, immutable review decision from one record.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class staging {
    /**
     * Evaluate and stage one row. Duplicate remote identities block the whole duplicated identity.
     *
     * @param stdClass $source Connector
     * @param int $run Simulation
     * @param string $entity Record kind
     * @param mixed $fields Source values
     * @param array $defaults Provider mapping
     * @param array $mapping User transforms by entity
     * @param int $offset Stable ordinal for invalid rows
     */
    public static function record(
        stdClass $source,
        int $run,
        string $entity,
        mixed $fields,
        array $defaults,
        array $mapping,
        int $offset,
    ): void {
        global $DB;
        $allowed = ($entity === 'user' && $source->users) || ($entity === 'course' && $source->courses);
        $key = hash('sha256', "invalid:$offset");
        $data = null;
        $finger = null;
        $subject = null;
        $userid = 0;
        $decision = 'invalid';
        $error = $allowed ? 'record' : 'entity';
        if ($allowed && is_array($fields)) {
            try {
                [$key, $data] = mapping::normalize($entity, $fields, $defaults, $mapping[$entity] ?? [], (int)$source->id);
                [$decision, $error, $finger, $native] = target::inspect($source, $entity, $key, $data);
                if ($entity === 'user') {
                    $subject = hash('sha256', $data['email']);
                    $userid = $native;
                    if (!$userid) {
                        $where = 'deleted = 0 AND ' . $DB->sql_equal('email', ':email', false);
                        $users = $DB->get_records_select('user', $where, ['email' => $data['email']], 'id', 'id', 0, 2);
                        $userid = count($users) === 1 ? (int)array_key_first($users) : 0;
                    }
                }
            } catch (problem $failure) {
                $error = $failure->reason;
            }
        }
        $entity = in_array($entity, ['user', 'course'], true) ? $entity : 'unknown';
        $criteria = ['runid' => $run, 'entity' => $entity, 'externalkey' => $key];
        if ($old = $DB->get_record(store::ITEM, $criteria)) {
            $DB->update_record(store::ITEM, [
                'id' => $old->id, 'decision' => 'conflict', 'state' => 'conflict', 'errorcode' => 'duplicate',
            ]);
            return;
        }
        $state = match ($decision) {
            'invalid' => 'failed', 'conflict' => 'conflict', 'same' => 'same', default => 'planned',
        };
        $DB->insert_record(store::ITEM, $criteria + [
            'decision' => $decision, 'state' => $state, 'errorcode' => $error, 'finger' => $finger, 'userid' => $userid,
            'subject' => $subject, 'payload' => $data ? json_encode($data, JSON_THROW_ON_ERROR) : null,
        ]);
    }
}
