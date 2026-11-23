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

namespace local_skillupshelpdesk;

use core_text;
use moodle_exception;
use stdClass;

/**
 * Manage controlled support vocabulary and knowledge content.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /** @var string[] Allowed catalog kinds. */
    public const KINDS = ['category', 'campus', 'priority', 'severity', 'status', 'article', 'template'];

    /**
     * Fetch the recorded values for one catalog.
     *
     * @param string $kind Catalog name
     * @param bool $active Exclude inactive records
     * @return stdClass[]
     */
    public static function records(string $kind, bool $active = true): array {
        global $DB;
        self::validate_kind($kind);
        $where = ['kind' => $kind];
        if ($active) {
            $where['active'] = 1;
        }
        return $DB->get_records(storage::CATALOG, $where, 'id ASC');
    }

    /**
     * Fetch one record with type validation.
     *
     * @param string $kind Catalog name
     * @param int $id Entry ID
     * @param bool $active Require active record
     * @return stdClass
     */
    public static function entry(string $kind, int $id, bool $active = true): stdClass {
        global $DB;
        self::validate_kind($kind);
        $where = ['id' => $id, 'kind' => $kind];
        if ($active) {
            $where['active'] = 1;
        }
        $entry = $DB->get_record(storage::CATALOG, $where);
        if (!$entry) {
            throw new moodle_exception('invalidcatalog', access::COMPONENT);
        }
        return $entry;
    }

    /**
     * Return a localized name for seeded entries or the institutional name.
     *
     * @param stdClass $entry Catalog record
     * @return string
     */
    public static function label(stdClass $entry): string {
        return $entry->name !== '' ? $entry->name : access::text('seed_' . $entry->code);
    }

    /**
     * Build options for forms that select a catalog value.
     *
     * @param string $kind Catalog name
     * @return array ID to label
     */
    public static function options(string $kind): array {
        return array_map([self::class, 'label'], self::records($kind));
    }

    /**
     * Require a known kind before using it in a lookup.
     *
     * @param string $kind Catalog name
     */
    private static function validate_kind(string $kind): void {
        if (!in_array($kind, self::KINDS, true)) {
            throw new moodle_exception('invalidcatalog', access::COMPONENT);
        }
    }

    /**
     * Save a catalog edit with role, size and initial-status safeguards.
     *
     * @param string $kind Catalog name
     * @param int $id Entry ID, or zero
     * @param stdClass $data Form input
     * @return int
     */
    public static function save(string $kind, int $id, stdClass $data): int {
        global $DB;
        access::require(access::can('configure'));
        self::validate_kind($kind);
        $record = [
            'kind' => $kind, 'name' => trim($data->name), 'active' => (int)!empty($data->active),
            'body' => in_array($kind, ['article', 'template'], true) ? trim($data->body ?? '') : '',
            'initialstate' => (int)($kind === 'status' && !empty($data->initialstate)),
            'terminal' => (int)($kind === 'status' && !empty($data->terminal)),
            'responsegoal' => $kind === 'priority' ? (int)$data->responsegoal : 0,
            'resolutiongoal' => $kind === 'priority' ? (int)$data->resolutiongoal : 0,
            'timemodified' => time(),
        ];
        if (
            $record['name'] === '' || core_text::strlen($record['name']) > 255 || core_text::strlen($record['body']) > 12000 ||
            ($record['initialstate'] && (!$record['active'] || $record['terminal'])) ||
            ($kind === 'priority' && ($record['responsegoal'] < 1 || $record['resolutiongoal'] < $record['responsegoal'] ||
                $record['resolutiongoal'] > 10080))
        ) {
            throw new moodle_exception('invalidcatalog', access::COMPONENT);
        }
        return storage::locked('catalog', static function () use ($DB, $kind, $id, $record): int {
            $old = $id ? self::entry($kind, $id, false) : null;
            if ($old && $old->initialstate && !$record['initialstate']) {
                throw new moodle_exception('initialrequired', access::COMPONENT);
            }
            if (!$id && $DB->count_records(storage::CATALOG, ['kind' => $kind]) >= 500) {
                throw new moodle_exception('cataloglimit', access::COMPONENT);
            }
            if ($old && $kind === 'status' && (int)$old->terminal !== $record['terminal']) {
                throw new moodle_exception('statusinuse', access::COMPONENT);
            }
            if ($old && $old->active && !$record['active']) {
                $required = in_array($kind, ['category', 'campus', 'priority', 'severity'], true);
                $criteria = ['kind' => $kind, 'active' => 1];
                if ($kind === 'status' && $old->terminal) {
                    $required = true;
                    $criteria['terminal'] = 1;
                }
                if ($required && $DB->count_records(storage::CATALOG, $criteria) === 1) {
                    throw new moodle_exception('requiredcatalog', access::COMPONENT);
                }
            }
            return storage::transaction(static function () use ($DB, $id, $record): int {
                if ($record['initialstate']) {
                    $DB->set_field(storage::CATALOG, 'initialstate', 0, ['kind' => 'status']);
                }
                if ($id) {
                    $DB->update_record(storage::CATALOG, ['id' => $id] + $record);
                    $saved = $id;
                } else {
                    $saved = (int)$DB->insert_record(storage::CATALOG, $record + ['code' => bin2hex(random_bytes(16))]);
                }
                storage::audit(0, 'catalog', ['entry' => $saved]);
                return $saved;
            });
        });
    }
}
