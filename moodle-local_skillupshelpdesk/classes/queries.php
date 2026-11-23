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

use stdClass;

/**
 * Scoped queues, dashboard measures and aggregate reports.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queries {
    /** @var array Report dimensions and their fields from a fixed allowlist. */
    public const GROUPS = ['assignee' => 'assigneeid', 'campus' => 'campusid', 'course' => 'courseid', 'category' => 'categoryid'];

    /** @var array Fields supported by queue and report filters. */
    public const FILTERS = ['status', 'priority', 'severity', 'category', 'campus', 'course'];

    /**
     * Build current request filters with strict identifiers.
     *
     * @return array
     */
    public static function request_filters(): array {
        $values = [];
        foreach (self::FILTERS as $field) {
            $values[$field] = max(0, optional_param($field, 0, PARAM_INT));
        }
        $values['days'] = optional_param('days', 0, PARAM_INT);
        $values['days'] = in_array($values['days'], [7, 30, 90, 365], true) ? $values['days'] : 0;
        $view = optional_param('view', '', PARAM_ALPHA);
        $values['view'] = in_array($view, ['mine', 'assigned', 'unassigned'], true) ? $view : '';
        $values['q'] = \core_text::substr(trim(optional_param('q', '', PARAM_TEXT)), 0, 80);
        return $values;
    }

    /**
     * Build a visibility and dimension predicate. Technician reports cover assigned tickets.
     *
     * @param array $filter Validated filters
     * @param bool $assignedonly Enforce technician scope for reports
     * @return array
     */
    private static function criteria(array $filter, bool $assignedonly): array {
        global $DB, $USER;
        [$where, $params] = access::filter();
        if ($assignedonly) {
            access::require(access::can('supervise') || access::can('work'));
            if (!access::can('supervise')) {
                $where .= ' AND t.assigneeid = :reportactor';
                $params['reportactor'] = $USER->id;
            }
        }
        if (!$assignedonly) {
            $view = $filter['view'] ?? '';
            if ($view === 'mine') {
                $where .= ' AND t.requesterid = :queueactor';
                $params['queueactor'] = $USER->id;
            } else if ($view === 'assigned' && (access::can('work') || access::can('supervise'))) {
                $where .= ' AND t.assigneeid = :queueactor';
                $params['queueactor'] = $USER->id;
            } else if ($view === 'unassigned' && access::can('supervise')) {
                $where .= ' AND t.assigneeid = 0';
            }
        }
        foreach (self::FILTERS as $field) {
            if (!empty($filter[$field])) {
                $where .= " AND t.{$field}id = :$field";
                $params[$field] = $filter[$field];
            }
        }
        if (!empty($filter['days'])) {
            $where .= ' AND t.timecreated >= :cutoff';
            $params['cutoff'] = time() - $filter['days'] * DAYSECS;
        }
        if (!empty($filter['q'])) {
            $where .= ' AND ' . $DB->sql_like('t.title', ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($filter['q']) . '%';
        }
        return [$where, $params];
    }

    /**
     * Offer course filters only for tickets visible in the selected view.
     *
     * @param bool $reports Whether to use technician report scope
     * @return array Course IDs
     */
    public static function course_ids(bool $reports): array {
        global $DB;
        [$where, $params] = self::criteria([], $reports);
        $table = storage::TICKET;
        return array_keys($DB->get_records_sql(
            "SELECT DISTINCT t.courseid FROM {{$table}} t WHERE $where AND t.courseid > 0 ORDER BY t.courseid",
            $params,
            0,
            500
        ));
    }

    /**
     * Return paginator totals, tickets and summary counters for the active filter.
     *
     * @param array $filter Validated filters
     * @param int $page Result page
     * @return array
     */
    public static function queue(array $filter, int $page): array {
        global $DB;
        [$where, $params] = self::criteria($filter, false);
        $table = storage::TICKET;
        $now = time();
        $metrics = $DB->get_record_sql(
            "SELECT COUNT(*) AS totalcount,
                    SUM(CASE WHEN t.resolvedat = 0 THEN 1 ELSE 0 END) AS opencount,
                    SUM(CASE WHEN t.resolvedat > 0 THEN 1 ELSE 0 END) AS closedcount,
                    SUM(CASE WHEN t.responsebreach > 0 OR t.resolvebreach > 0 OR
                         (t.resolvedat = 0 AND ((t.firstresponse = 0 AND t.firstdue < :firstrun) OR t.resolvedue < :closerun))
                         THEN 1 ELSE 0 END) AS overduecount
               FROM {{$table}} t WHERE $where",
            $params + ['firstrun' => $now, 'closerun' => $now]
        );
        $rows = $DB->get_records_sql(
            "SELECT t.* FROM {{$table}} t WHERE $where ORDER BY t.timemodified DESC, t.id DESC",
            $params,
            $page * 25,
            25
        );
        return [$metrics, $rows];
    }

    /**
     * Run a grouped report with Moodle DML and optional pagination.
     *
     * @param string $dimension Report dimension
     * @param array $filter Active filters
     * @param int $offset Result offset
     * @param int $limit Number of rows, zero for streaming export
     * @return \moodle_recordset
     */
    public static function report(string $dimension, array $filter, int $offset = 0, int $limit = 0): \moodle_recordset {
        global $DB;
        [$where, $params] = self::criteria($filter, true);
        $field = self::GROUPS[$dimension] ?? self::GROUPS['assignee'];
        $table = storage::TICKET;
        return $DB->get_recordset_sql(
            "SELECT t.$field AS bucket, COUNT(*) AS totalcount,
                    SUM(CASE WHEN t.resolvedat = 0 THEN 1 ELSE 0 END) AS opencount,
                    SUM(CASE WHEN t.resolvedat > 0 THEN 1 ELSE 0 END) AS closedcount,
                    SUM(CASE WHEN t.responsebreach > 0 OR (t.firstresponse > t.firstdue) OR
                        (t.firstresponse = 0 AND ((t.resolvedat = 0 AND t.firstdue < :responseclock) OR
                                                   t.resolvedat > t.firstdue)) THEN 1 ELSE 0 END) AS responsecount,
                    SUM(CASE WHEN t.resolvebreach > 0 OR t.resolvedat > t.resolvedue OR
                        (t.resolvedat = 0 AND t.resolvedue < :resolveclock) THEN 1 ELSE 0 END) AS resolvecount,
                    SUM(CASE WHEN t.rating > 0 THEN 1 ELSE 0 END) AS ratedcount,
                    AVG(CASE WHEN t.rating > 0 THEN t.rating ELSE NULL END) AS ratingmean
               FROM {{$table}} t WHERE $where GROUP BY t.$field ORDER BY t.$field",
            $params + ['responseclock' => time(), 'resolveclock' => time()],
            $offset,
            $limit
        );
    }

    /**
     * Count matching aggregates for report pagination.
     *
     * @param string $dimension Report dimension
     * @param array $filter Active filters
     * @return int
     */
    public static function group_count(string $dimension, array $filter): int {
        global $DB;
        [$where, $params] = self::criteria($filter, true);
        $field = self::GROUPS[$dimension] ?? self::GROUPS['assignee'];
        $table = storage::TICKET;
        return (int)$DB->count_records_sql("SELECT COUNT(DISTINCT t.$field) FROM {{$table}} t WHERE $where", $params);
    }

    /**
     * Fetch a visible page of messages.
     *
     * @param stdClass $ticket Authorized ticket
     * @param int $page Result page
     * @return array
     */
    public static function messages(stdClass $ticket, int $page): array {
        global $DB;
        access::require(access::ticket($ticket));
        $conditions = ['ticketid' => $ticket->id];
        if (!access::staff($ticket)) {
            $conditions['internal'] = 0;
        }
        return [
            $DB->count_records(storage::POST, $conditions),
            $DB->get_records(storage::POST, $conditions, 'id DESC', '*', $page * 20, 20),
        ];
    }

    /**
     * Staff history can include private events. Requesters see public lifecycle only.
     *
     * @param stdClass $ticket Authorized ticket
     * @param int $page Result page
     * @return array
     */
    public static function history(stdClass $ticket, int $page): array {
        global $DB;
        access::require(access::ticket($ticket));
        $where = 'ticketid = :ticketid';
        $params = ['ticketid' => $ticket->id];
        if (!access::staff($ticket)) {
            [$sql, $keys] = $DB->get_in_or_equal(['opened', 'comment', 'triage', 'rating'], SQL_PARAMS_NAMED, 'publicaction');
            $where .= " AND action $sql";
            $params += $keys;
        }
        return [
            $DB->count_records_select(storage::AUDIT, $where, $params),
            $DB->get_records_select(storage::AUDIT, $where, $params, 'id DESC', '*', $page * 25, 25),
        ];
    }
}
