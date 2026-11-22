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

namespace report_skillupsinsight;

/**
 * Shared configuration, labels, locking and audit.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {
    /** Moodle component and table prefix. */
    public const C = 'report_skillupsinsight';
    /** Moodle capability prefix. */
    public const CAP = 'report/skillupsinsight';

    /**
     * Moodle language string.
     */
    public static function str(string $key, mixed $data = null): string {
        return get_string($key, self::C, $data);
    }

    /**
     * Report URL.
     */
    public static function url(string $page, array $params = []): \moodle_url {
        return new \moodle_url('/report/skillupsinsight/' . $page . '.php', $params);
    }

    /**
     * Return a JSON document that fails closed if serialization fails.
     */
    public static function json(array $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Obtain the cross-process lock for a report operation.
     */
    public static function lock(string $key, int $timeout = 0): ?\core\lock\lock {
        return \core\lock\lock_config::get_lock_factory(self::C)->get_lock($key, $timeout) ?: null;
    }

    /**
     * Return a lock or throw a localized error.
     */
    public static function acquire(string $key): \core\lock\lock {
        return self::lock($key, 5) ?? throw new \moodle_exception('busy', self::C);
    }

    /**
     * Emit structured audit without results or search content.
     */
    public static function audit(\context $context, string $action, int $period): void {
        \report_skillupsinsight\event\report_accessed::create([
            'context' => $context, 'other' => ['action' => $action, 'period' => $period],
        ])->trigger();
    }
}
