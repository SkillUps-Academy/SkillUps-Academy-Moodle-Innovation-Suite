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

use core\context;
use tool_dataprivacy\api;
use tool_dataprivacy\data_request;

/**
 * Read only a safe subset of native data requests and retention policies.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bridge {
    /** @var array Native types represented as dedicated internal categories. */
    public const TYPES = [api::DATAREQUEST_TYPE_EXPORT => 'export', api::DATAREQUEST_TYPE_DELETE => 'delete'];

    /** @var array Status mapping for the supported native lifecycle. */
    public const STATUSES = [
        -2 => 'missing', -1 => 'unlinked',
        api::DATAREQUEST_STATUS_PENDING => 'pending',
        api::DATAREQUEST_STATUS_PREPROCESSING => 'preprocessing',
        api::DATAREQUEST_STATUS_AWAITING_APPROVAL => 'awaiting',
        api::DATAREQUEST_STATUS_APPROVED => 'approved',
        api::DATAREQUEST_STATUS_PROCESSING => 'processing',
        api::DATAREQUEST_STATUS_COMPLETE => 'complete',
        api::DATAREQUEST_STATUS_CANCELLED => 'cancelled',
        api::DATAREQUEST_STATUS_REJECTED => 'rejected',
        api::DATAREQUEST_STATUS_DOWNLOAD_READY => 'ready',
        api::DATAREQUEST_STATUS_EXPIRED => 'expired',
        api::DATAREQUEST_STATUS_DELETED => 'deleted',
    ];

    /**
     * Read a request without invoking the native dashboard's expiration routines.
     *
     * @param int $id Native reference
     * @return array|null
     */
    public static function read(int $id): ?array {
        try {
            return self::summary(api::get_request($id));
        } catch (\dml_missing_record_exception $missing) {
            return null;
        }
    }

    /**
     * Retrieve one read-only page of native export and deletion references.
     *
     * @param int $page Page index
     * @return array
     */
    public static function inbox(int $page): array {
        access::require();
        $conditions = 'type IN (:export, :erase)';
        $params = ['export' => api::DATAREQUEST_TYPE_EXPORT, 'erase' => api::DATAREQUEST_TYPE_DELETE];
        $source = data_request::get_records_select($conditions, $params, 'id DESC', '*', $page * 25, 25);
        return [array_map(self::summary(...), $source), data_request::count_records_select($conditions, $params)];
    }

    /**
     * Resolve an approved kind of native review context.
     *
     * @param int $id Moodle context
     * @param int $userid Optional subject to constrain user-level contexts
     * @return context|null
     */
    public static function context(int $id, int $userid = 0): ?context {
        $context = $id ? context::instance_by_id($id, IGNORE_MISSING) : \context_system::instance();
        $levels = [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE, CONTEXT_USER];
        if (!$context || !in_array($context->contextlevel, $levels, true)) {
            return null;
        }
        return $context->contextlevel !== CONTEXT_USER || ($userid && $context->instanceid == $userid) ? $context : null;
    }

    /**
     * Fetch the effective native retention purpose and whether it is protected.
     *
     * @param context $context Review context
     * @return array|null
     */
    public static function retention(context $context): ?array {
        $purpose = api::get_effective_context_purpose($context);
        if (!$purpose) {
            return null;
        }
        try {
            $period = api::format_retention_period(new \DateInterval($purpose->get('retentionperiod')));
        } catch (\Exception $error) {
            $period = access::text('notrecorded');
        }
        return ['name' => $purpose->get('name'), 'period' => $period, 'protected' => (bool)$purpose->get('protected')];
    }

    /**
     * Resolve a native privacy administration page.
     *
     * @param string $page Native page
     * @return \moodle_url
     */
    public static function url(string $page): \moodle_url {
        return new \moodle_url('/admin/tool/dataprivacy/' . $page . '.php');
    }

    /**
     * Produce only allowlisted tracking data.
     *
     * @param data_request $request Native record
     * @return array
     */
    private static function summary(data_request $request): array {
        return [
            'id' => (int)$request->get('id'), 'userid' => (int)$request->get('userid'),
            'kind' => self::TYPES[$request->get('type')] ?? '',
            'status' => (int)$request->get('status'), 'created' => (int)$request->get('timecreated'),
        ];
    }
}
