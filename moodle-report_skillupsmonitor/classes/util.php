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

namespace report_skillupsmonitor;

/**
 * Site authorization, data boundaries and tunable monitoring thresholds.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {
    /** Moodle component. */
    public const C = 'report_skillupsmonitor';
    /** Capability prefix. */
    public const CAP = 'report/skillupsmonitor';
    /** Internal sampling interval. */
    public const SLOT = 300;
    /** Bound native task activity counts and dashboard rows. */
    public const CAPCOUNT = 1000;

    /**
     * Component string.
     */
    public static function str(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Report-local URL.
     */
    public static function url(string $page, array $query = []): \moodle_url {
        return new \moodle_url('/report/skillupsmonitor/' . $page . '.php', $query);
    }

    /**
     * Constrain all monitoring limits, including values set through CLI.
     */
    public static function integer(string $key, int $default, int $min, int $max): int {
        $setting = get_config(self::C, $key);
        return $setting === false ? $default : max($min, min($max, (int)$setting));
    }

    /**
     * The configured thresholds travel together during one sample.
     */
    public static function policy(): array {
        return [
            'failures' => self::integer('failures', 2, 1, 12),
            'recoveries' => self::integer('recoveries', 2, 1, 12),
            'cronminutes' => self::integer('cronminutes', 5, 2, 120),
            'latencyms' => self::integer('latencyms', 500, 10, 10000),
            'dueminutes' => self::integer('dueminutes', 5, 1, 120),
            'queuewarn' => self::integer('queuewarn', 20, 1, self::CAPCOUNT),
            'stale' => self::integer('stale', 15, 10, 1440) * MINSECS,
            'monitorcomponents' => (bool)(get_config(self::C, 'monitorcomponents') === false ||
                get_config(self::C, 'monitorcomponents')),
        ];
    }

    /**
     * Return only unique valid site account IDs.
     */
    public static function recipients(): array {
        return get_config(self::C, 'notifyenabled') ? self::configured_recipients() : [];
    }

    /**
     * Parse configured account IDs independently of the notification switch.
     */
    public static function configured_recipients(): array {
        $setting = (string)get_config(self::C, 'recipients');
        if (strlen($setting) > 2000) {
            return [];
        }
        $ids = preg_split('/[\s,]+/', trim($setting), -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_map('intval', array_filter($ids, static fn($id) => ctype_digit($id) && strlen($id) <= 10 && (int)$id > 0));
        return array_slice(array_values(array_unique($ids)), 0, 20);
    }

    /**
     * Revalidate recipients before storing a notice and again before sending.
     */
    public static function valid_recipient(int $userid): bool {
        $user = \core_user::get_user($userid);
        return $user && !$user->deleted && !$user->suspended && $user->confirmed &&
            !isguestuser($user) && has_capability(self::CAP . ':view', \context_system::instance(), $userid);
    }

    /**
     * Check the configured message routing for this incident category.
     */
    public static function alerted(string $key): bool {
        $setting = get_config(self::C, 'alertchecks');
        return $setting === false || in_array($key, explode(',', (string)$setting), true);
    }

    /**
     * Common cluster lock for sampling and incident acknowledgement.
     */
    public static function lock(string $key, int $wait = 0): ?\core\lock\lock {
        return \core\lock\lock_config::get_lock_factory(self::C)->get_lock($key, $wait) ?: null;
    }

    /**
     * Stable JSON with malformed inputs rejected.
     */
    public static function json(array $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Emit only fixed operation names and operational record IDs.
     */
    public static function audit(string $operation, int $id = 0): void {
        \report_skillupsmonitor\event\monitor_operation::create([
            'context' => \context_system::instance(), 'other' => ['operation' => $operation, 'reference' => $id],
        ])->trigger();
    }
}
