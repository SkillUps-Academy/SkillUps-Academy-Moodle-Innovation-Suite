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
 * Save versioned connector settings and scope inbound credentials.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile {
    /** @var array Supported connector families. */
    public const TYPES = ['google', 'microsoft', 'sis', 'hr', 'inbound'];
    /** @var array Supported conflict resolution policies. */
    public const POLICIES = ['preserve', 'source'];

    /**
     * Validate and persist a connector.
     *
     * @param stdClass $input Validated Moodle form fields
     * @return int
     */
    public static function save(stdClass $input): int {
        global $DB, $USER;
        access::require('manage');
        $id = (int)$input->id;
        $type = $input->type;
        $name = trim($input->name);
        $policy = $input->policy;
        $category = (int)$input->categoryid;
        $enabled = empty($input->enabled) ? 0 : 1;
        $users = empty($input->users) ? 0 : 1;
        $courses = empty($input->courses) ? 0 : 1;
        $push = (int)$input->pushuserid;
        $webhook = empty($input->webhook) ? 0 : 1;
        $reference = trim($input->secretref ?? '');
        if ($id && $reference === '') {
            $reference = self::get($id)->secretref;
        }
        $options = self::options($type, $input);
        if (
            !in_array($type, self::TYPES, true) || !in_array($policy, self::POLICIES, true) ||
            $name === '' || \core_text::strlen($name) > 100 || (!$users && !$courses) ||
            ($courses && !$DB->record_exists('course_categories', ['id' => $category])) ||
            ($push && !access::allowed($push, 'ingest')) ||
            (($push || $webhook) && in_array($type, ['google', 'microsoft'], true)) ||
            (($enabled && ($type !== 'inbound' || $webhook)) && !vault::valid_reference($reference)) ||
            ($reference !== '' && !vault::valid_reference($reference)) ||
            ($enabled && $type === 'inbound' && !$push && !$webhook)
        ) {
            throw new problem('config');
        }
        if ($webhook && !access::allowed((int)$USER->id, 'preview')) {
            throw new problem('permission');
        }
        $record = [
            'name' => $name, 'type' => $type, 'ownerid' => $webhook ? $USER->id : 0,
            'categoryid' => $courses ? $category : 0, 'enabled' => $enabled, 'users' => $users, 'courses' => $courses,
            'pushuserid' => $push, 'webhook' => $webhook, 'secretref' => $reference, 'policy' => $policy,
            'options' => json_encode($options, JSON_THROW_ON_ERROR), 'timemodified' => time(),
        ];
        $save = static function () use ($DB, $USER, $input, $id, $record): int {
            return store::transaction(static function () use ($DB, $USER, $input, $id, $record): int {
                if ($id) {
                    $old = self::get($id);
                    if ($old->revision != $input->revision || $old->type !== $record['type']) {
                        throw new problem('stale');
                    }
                    $DB->update_record(store::SRC, $record + ['id' => $id, 'revision' => $old->revision + 1, 'health' => 'new']);
                } else {
                    $id = (int)$DB->insert_record(store::SRC, $record);
                }
                store::audit('configured', $id, actorid: (int)$USER->id);
                return $id;
            });
        };
        return $id ? store::locked("source:$id", $save) : $save();
    }

    /**
     * Load a connector, rejecting unknown identifiers.
     *
     * @param int $id Connector
     * @return stdClass
     */
    public static function get(int $id): stdClass {
        global $DB;
        $source = $DB->get_record(store::SRC, ['id' => $id]);
        if (!$source) {
            throw new problem('unavailable');
        }
        return $source;
    }

    /**
     * Return the connector options.
     *
     * @param stdClass $source Connector
     * @return array
     */
    public static function settings(stdClass $source): array {
        return json_decode($source->options, true, 16, JSON_THROW_ON_ERROR);
    }

    /**
     * Validate provider-specific options while excluding all credentials.
     *
     * @param string $type Connector family
     * @param stdClass $input Moodle form
     * @return array
     */
    private static function options(string $type, stdClass $input): array {
        $mapping = mapping::configuration($input->mapping);
        $value = trim($input->endpoint);
        if ($type === 'google') {
            if (!preg_match('/^(?:my_customer|(?:[a-z0-9][a-z0-9.-]{0,126}\.[a-z]{2,20}))$/D', $value)) {
                throw new problem('config');
            }
        } else if ($type === 'microsoft') {
            if (!preg_match('/^[\da-f]{8}(?:-[\da-f]{4}){3}-[\da-f]{12}$/Di', $value)) {
                throw new problem('config');
            }
        } else if (in_array($type, ['sis', 'hr'], true)) {
            http_transport::validate_custom($value);
        } else {
            $value = '';
        }
        return ['endpoint' => $value, 'mapping' => $mapping];
    }
}
