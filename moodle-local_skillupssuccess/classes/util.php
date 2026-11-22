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

namespace local_skillupssuccess;

/**
 * Component primitives and audit.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {
    /** Component name. */
    public const C = 'local_skillupssuccess';
    /** Capability namespace. */
    public const CAP = 'local/skillupssuccess';

    /**
     * Localized text.
     */
    public static function str(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Construct an internal Moodle URL.
     */
    public static function url(string $path, array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupssuccess/' . $path . '.php', $params);
    }

    /**
     * Stable UTF-8 JSON.
     */
    public static function json(array $input): string {
        return json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Allocate one Moodle cross-process lock.
     */
    public static function lock(int $courseid, int $seconds = 5): ?\core\lock\lock {
        return \core\lock\lock_config::get_lock_factory(self::C)->get_lock('course_' . $courseid, $seconds) ?: null;
    }

    /**
     * Fail locally without displaying sensitive inputs.
     */
    public static function fail(string $code): never {
        throw new \moodle_exception($code, self::C);
    }

    /**
     * Audit operations using a course context and a non-sensitive record reference.
     */
    public static function audit(\context $context, string $action, int $reference = 0): void {
        \local_skillupssuccess\event\case_accessed::create([
            'context' => $context, 'other' => ['operation' => $action, 'reference' => $reference],
        ])->trigger();
    }
}
