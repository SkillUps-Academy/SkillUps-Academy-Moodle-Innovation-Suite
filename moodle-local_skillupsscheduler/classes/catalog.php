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

namespace local_skillupsscheduler;

/**
 * Manage campus catalogs and explicit user-to-campus assignments.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /** @var array Catalog type to table mapping. */
    public const TYPES = [
        'site' => store::SITE, 'cycle' => store::CYCLE, 'room' => store::ROOM,
        'block' => store::BLOCK, 'grant' => store::GRANT,
    ];

    /**
     * Local date.
     *
     * @param string $date Date
     * @param string $zone IANA timezone name
     * @return \DateTimeImmutable
     */
    public static function local_date(string $date, string $zone): \DateTimeImmutable {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone($zone));
        if (!$day || $day->format('Y-m-d') !== $date) {
            scope::fail('invaliddate');
        }
        return $day;
    }

    /**
     * Minute.
     *
     * @param string $value Value
     * @return int
     */
    public static function minute(string $value): int {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D', $value, $match)) {
            scope::fail('invalidtime');
        }
        return ((int)$match[1] * 60) + (int)$match[2];
    }

    /**
     * Time label.
     *
     * @param int $minutes Minutes
     * @return string
     */
    public static function time_label(int $minutes): string {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Local time.
     *
     * @param string $date Date
     * @param string $value Value
     * @param string $zone IANA timezone name
     * @return \DateTimeImmutable
     */
    public static function local_time(string $date, string $value, string $zone): \DateTimeImmutable {
        self::local_date($date, $zone);
        self::minute($value);
        $datetime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $value", new \DateTimeZone($zone));
        if (!$datetime || $datetime->format('Y-m-d H:i') !== "$date $value") {
            scope::fail('invalidtime');
        }
        // DST folds have two UTC interpretations for the same wall time.
        $instant = $datetime->getTimestamp();
        $transitions = $datetime->getTimezone()->getTransitions($instant - DAYSECS, $instant + DAYSECS);
        if ($transitions) {
            $wall = $datetime->getTimestamp() + $datetime->getOffset();
            $previous = null;
            foreach ($transitions as $transition) {
                $offset = $transition['offset'];
                $epoch = $transition['ts'];
                if ($previous !== null && $offset < $previous && $wall >= $epoch + $offset && $wall < $epoch + $previous) {
                    scope::fail('invalidtime');
                }
                $previous = $offset;
            }
        }
        return $datetime;
    }

    /**
     * Validate and persist a scheduling change.
     *
     * @param string $type Type
     * @param \stdClass $input Submitted values
     * @return int
     */
    public static function save(string $type, \stdClass $input): int {
        global $DB;
        if (!scope::admin() || !isset(self::TYPES[$type])) {
            scope::fail();
        }
        return store::atomic(static function () use ($DB, $type, $input): int {
            $table = self::TYPES[$type];
            $old = !empty($input->id) ? store::get($table, (int)$input->id) : null;
            $record = new \stdClass();
            if ($type !== 'site') {
                $site = store::get(store::SITE, (int)$input->siteid);
                if ($old && $old->siteid != $site->id) {
                    scope::fail();
                }
                $record->siteid = $site->id;
            }
            if ($type !== 'grant') {
                $record->name = trim($input->name);
                if ($record->name === '' || \core_text::strlen($record->name) > 100) {
                    scope::fail('invalidinput');
                }
            }
            switch ($type) {
                case 'site':
                    $record->categoryid = (int)$input->categoryid;
                    $record->timezone = $input->timezone;
                    if (
                        !\context_coursecat::instance($record->categoryid, IGNORE_MISSING) ||
                        !in_array($record->timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)
                    ) {
                        scope::fail('invalidinput');
                    }
                    if (
                        $old && ($old->categoryid != $record->categoryid || $old->timezone !== $record->timezone) &&
                        $DB->record_exists(store::SLOT, ['siteid' => $old->id])
                    ) {
                        scope::fail('cataloginuse');
                    }
                    break;
                case 'cycle':
                    $record->startday = self::local_date($input->startday, $site->timezone)->format('Y-m-d');
                    $record->endday = self::local_date($input->endday, $site->timezone)->format('Y-m-d');
                    if ($record->endday < $record->startday) {
                        scope::fail('invaliddate');
                    }
                    $record->parentid = (int)($input->parentid ?? 0);
                    if ($record->parentid) {
                        $parent = store::get(store::CYCLE, $record->parentid);
                        if (
                            $parent->siteid != $site->id || $parent->parentid || ($old && $parent->id == $old->id) ||
                            $record->startday < $parent->startday || $record->endday > $parent->endday
                        ) {
                            scope::fail('invalidparent');
                        }
                    }
                    if ($old) {
                        foreach ($DB->get_records(store::CYCLE, ['parentid' => $old->id]) as $child) {
                            if ($record->parentid || $child->startday < $record->startday || $child->endday > $record->endday) {
                                scope::fail('cataloginuse');
                            }
                        }
                    }
                    break;
                case 'room':
                    $record->kind = $input->kind;
                    $record->capacity = (int)$input->capacity;
                    $record->url = trim($input->url ?? '');
                    if (
                        !in_array($record->kind, ['physical', 'virtual'], true) || $record->capacity < 0 ||
                        ($record->kind === 'physical' && !$record->capacity) ||
                        ($old && $old->kind !== $record->kind)
                    ) {
                        scope::fail('invalidinput');
                    }
                    if ($record->kind === 'virtual') {
                        $parts = parse_url($record->url);
                        if (
                            !filter_var($record->url, FILTER_VALIDATE_URL) || strtolower($parts['scheme'] ?? '') !== 'https' ||
                            empty($parts['host']) || isset($parts['user']) || \core_text::strlen($record->url) > 1024
                        ) {
                            scope::fail('invalidurl');
                        }
                    } else {
                        $record->url = '';
                    }
                    break;
                case 'block':
                    $record->shiftname = trim($input->shiftname);
                    $record->weekday = (int)$input->weekday;
                    $record->beginmin = self::minute($input->begin);
                    $record->endmin = self::minute($input->finish);
                    if (
                        $record->shiftname === '' || \core_text::strlen($record->shiftname) > 100 || $record->weekday < 1 ||
                        $record->weekday > 7 || $record->endmin <= $record->beginmin
                    ) {
                        scope::fail('invalidtime');
                    }
                    break;
                case 'grant':
                    $record->userid = (int)$input->userid;
                    if (
                        !$DB->record_exists('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]) ||
                        isguestuser($record->userid)
                    ) {
                        scope::fail('invalidinput');
                    }
                    $existing = $DB->get_field($table, 'id', (array)$record);
                    return $existing ? (int)$existing : (int)$DB->insert_record($table, $record);
            }
            if ($old) {
                $record->id = $old->id;
                self::check_published($type, $record);
                $DB->update_record($table, $record);
                return (int)$old->id;
            }
            return $DB->insert_record($table, $record);
        });
    }

    /**
     * Revoke.
     *
     * @param int $id Record ID
     */
    public static function revoke(int $id): void {
        global $DB;
        if (!scope::admin()) {
            scope::fail();
        }
        store::atomic(static function () use ($DB, $id): void {
            $DB->delete_records(store::GRANT, ['id' => $id]);
        });
    }

    /**
     * Check published.
     *
     * @param string $type Type
     * @param \stdClass $record Record
     */
    private static function check_published(string $type, \stdClass $record): void {
        global $DB;
        $clauses = [
            'cycle' => 'cycleid = :ref',
            'room' => '(roomid = :ref OR virtualid = :second)',
            'block' => 'blockid = :ref',
        ];
        if (!isset($clauses[$type])) {
            return;
        }
        $params = ['ref' => $record->id, 'status' => 'published', 'now' => time()];
        if ($type === 'room') {
            $params['second'] = $record->id;
        }
        $rows = $DB->get_recordset_select(store::SLOT, $clauses[$type] . ' AND status = :status AND ends > :now', $params);
        try {
            foreach ($rows as $session) {
                $issue = ['cycle' => 'outsidecycle', 'room' => 'overcapacity', 'block' => 'outsideblock'][$type];
                if (in_array($issue, conflicts::constraints($session, [$type => $record]), true)) {
                    scope::fail('cataloginuse');
                }
            }
        } finally {
            $rows->close();
        }
    }
}
