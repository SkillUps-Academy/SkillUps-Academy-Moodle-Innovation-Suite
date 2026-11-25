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

namespace local_skillupsrepository;

/**
 * Authorize repository operations in the owning Moodle category.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Component name. */
    public const COMPONENT = 'local_skillupsrepository';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsrepository:';

    /**
     * Check library access for the current user.
     *
     * @param string $action Capability suffix
     * @param int $categoryid Owning category
     * @return bool
     */
    public static function can(string $action, int $categoryid): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        $category = \core_course_category::get($categoryid, IGNORE_MISSING);
        return $category && has_capability(self::CAP . $action, $category->get_context());
    }

    /**
     * Check whether the author or moderator may inspect unpublished versions.
     *
     * @param \stdClass $asset Repository asset
     * @return bool
     */
    public static function internal(\stdClass $asset): bool {
        global $USER;
        return (isloggedin() && !isguestuser() && $asset->ownerid == $USER->id)
            || self::can('moderate', (int)$asset->categoryid);
    }

    /**
     * Check the selected version and its distribution policy.
     *
     * @param \stdClass $asset Repository asset
     * @param \stdClass $version Requested revision
     * @return bool
     */
    public static function view(\stdClass $asset, \stdClass $version): bool {
        if ($version->assetid != $asset->id || !\context_coursecat::instance($asset->categoryid, IGNORE_MISSING)) {
            return false;
        }
        if (self::internal($asset)) {
            return true;
        }

        return !$asset->archived && $version->id == $asset->publishedid && $version->state === 'approved' &&
            (($asset->audience === 'public' && self::public_category((int)$asset->categoryid))
                || ($asset->audience !== 'private' && self::can('view', (int)$asset->categoryid)));
    }

    /**
     * Check file download rights independently of preview rights.
     *
     * @param \stdClass $asset Repository asset
     * @param \stdClass $version Requested revision
     * @return bool
     */
    public static function download(\stdClass $asset, \stdClass $version): bool {
        return self::view($asset, $version) && (self::internal($asset) ||
            ($version->allowdownload && (($asset->audience === 'public' && self::public_category((int)$asset->categoryid)) ||
                self::can('download', (int)$asset->categoryid))));
    }

    /**
     * Check institutional authorization for a detached copy in a course.
     *
     * @param \stdClass $asset Repository asset
     * @param \stdClass $version Requested revision
     * @return bool
     */
    public static function reuse(\stdClass $asset, \stdClass $version): bool {
        return !$asset->archived && $asset->audience !== 'private' && $version->id == $asset->publishedid &&
            $version->state === 'approved' && $version->allowcopy && self::view($asset, $version) &&
            self::can('reuse', (int)$asset->categoryid);
    }

    /**
     * Check whether the local public catalogue is enabled.
     *
     * @return bool
     */
    public static function public_enabled(): bool {
        global $CFG;
        return empty($CFG->forcelogin) && (bool)get_config(self::COMPONENT, 'allowpublic');
    }

    /**
     * Check whether public access is allowed for this category.
     *
     * @param int $categoryid Owning category ID
     * @return bool
     */
    private static function public_category(int $categoryid): bool {
        $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        return self::public_enabled() && $category && $category->visible;
    }

    /**
     * Throw a generic error that does not expose unpublished resource metadata.
     *
     * @param string $key Language key
     */
    public static function fail(string $key = 'unavailable'): never {
        throw new \moodle_exception($key, self::COMPONENT);
    }
}
