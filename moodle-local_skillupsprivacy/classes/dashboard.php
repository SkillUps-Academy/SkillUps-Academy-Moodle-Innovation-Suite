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

namespace local_skillupsprivacy;

/**
 * Build paginated oversight views and non-identifying reports.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard {
    /** @var int Page size. */
    public const PAGE = 25;
    /** @var string Active workflow states. */
    public const ACTIVE = "state IN ('open', 'review', 'approved')";

    /**
     * Filter non-archived cases.
     *
     * @param array $filters Validated filters
     * @param int $page Page index
     * @return array
     */
    public static function cases(array $filters, int $page): array {
        global $DB, $USER;
        $where = ['archived = 0'];
        $params = [];
        foreach (['kind', 'state', 'caseid'] as $key) {
            if (!empty($filters[$key])) {
                $field = $key === 'caseid' ? 'id' : $key;
                $where[] = "$field = :$key";
                $params[$key] = $filters[$key];
            }
        }
        if (!empty($filters['dueonly'])) {
            $where[] = self::ACTIVE . ' AND due <= :now';
            $params['now'] = time();
        }
        if (!empty($filters['mine'])) {
            $where[] = 'ownerid = :owner';
            $params['owner'] = $USER->id;
        }
        $conditions = implode(' AND ', $where);
        $offset = $page * self::PAGE;
        $cases = $DB->get_records_select(store::CASES, $conditions, $params, 'due ASC, id ASC', '*', $offset, self::PAGE);
        foreach ($cases as $case) {
            $case->url = access::url('case', ['id' => $case->id])->out(false);
            $case->due = userdate($case->due);
            foreach (['kind', 'state', 'retention'] as $key) {
                $case->$key = access::text($key . '_' . $case->$key);
            }
        }
        return [array_values($cases), $DB->count_records_select(store::CASES, $conditions, $params)];
    }

    /**
     * Count active, overdue, held and categorised cases.
     *
     * @return array
     */
    public static function statistics(): array {
        global $DB;
        $table = store::CASES;
        $stats = [];
        foreach (workflow::KINDS as $kind) {
            $stats[$kind] = array_fill_keys(workflow::STATES, 0);
        }
        $records = $DB->get_recordset_sql("SELECT kind, state, COUNT(id) AS total FROM {{$table}} WHERE archived = 0
            GROUP BY kind, state");
        try {
            foreach ($records as $row) {
                if (isset($stats[$row->kind][$row->state])) {
                    $stats[$row->kind][$row->state] = (int)$row->total;
                }
            }
        } finally {
            $records->close();
        }
        $rows = [];
        foreach ($stats as $kind => $counts) {
            $rows[] = ['type' => access::text('kind_' . $kind), 'counts' => array_values($counts)];
        }
        $active = $DB->count_records_select($table, 'archived = 0 AND ' . self::ACTIVE);
        return ['totals' => $rows, 'legend' => self::options('state', workflow::STATES), 'summary' => [
            ['label' => access::text('activecount'), 'count' => $active],
            ['label' => access::text('overduecount'), 'count' => $DB->count_records_select(
                $table,
                'archived = 0 AND due <= :cutoff AND ' . self::ACTIVE,
                ['cutoff' => time()]
            )],
            ['label' => access::text('heldcount'), 'count' => $DB->count_records_select(
                $table,
                "archived = 0 AND retention = 'held' AND " . self::ACTIVE
            )],
        ]];
    }

    /**
     * Flatten native data declarations for installed extensions.
     *
     * @param string $filter Component search
     * @param int $page Page index
     * @return array
     */
    public static function inventory(string $filter, int $page): array {
        access::require('inventory');
        $rows = [];
        $registry = new \tool_dataprivacy\metadata_registry();
        foreach ($registry->get_registry_metadata() as $group) {
            if ($group['plugin_type_raw'] === 'core') {
                continue;
            }
            foreach ($group['plugins'] as $entry) {
                if (
                    $filter !== '' && \core_text::strpos(
                        \core_text::strtolower($entry['component'] . ' ' . $entry['raw_component']),
                        \core_text::strtolower($filter)
                    ) === false
                ) {
                    continue;
                }
                $status = match (true) {
                    !$entry['compliant'] || !empty($entry['userlistnoncompliance']) => 'incomplete',
                    isset($entry['nullprovider']) => 'nodata',
                    default => 'declared',
                };
                $entry['assessment'] = access::text('metadata_' . $status);
                $rows[$entry['raw_component']] = $entry;
            }
        }
        ksort($rows);
        return [array_slice(array_values($rows), $page * self::PAGE, self::PAGE), count($rows)];
    }

    /**
     * Build localised select options.
     *
     * @param string $prefix Label prefix
     * @param array $values Values
     * @param string $current Selected value
     * @return array
     */
    public static function options(string $prefix, array $values, string $current = ''): array {
        return array_map(static fn(string $value): array => [
            'value' => $value, 'label' => access::text($prefix . '_' . $value), 'selected' => $value === $current,
        ], $values);
    }

    /**
     * Format a native status.
     *
     * @param int $status Native status
     * @return string
     */
    public static function native_status(int $status): string {
        return access::text('native_' . (bridge::STATUSES[$status] ?? 'unknown'));
    }
}
