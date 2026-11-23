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

namespace local_skillupsgamification\integration;

/**
 * Discover the optional trusted local integration.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class learning_path {
    /**
     * Return a compatible local provider if the companion plugin exposes contract v1.
     *
     * @return milestone_provider|null
     */
    public static function provider(): ?milestone_provider {
        $providers = [
            \local_skillupspathways\integration\gamification_provider::class,
            \local_skillupslearningpath\integration\gamification_provider::class,
        ];
        foreach ($providers as $class) {
            if (
                class_exists($class) && is_subclass_of($class, milestone_provider::class) &&
                defined($class . '::CONTRACT_VERSION') && constant($class . '::CONTRACT_VERSION') === 1
            ) {
                return new $class();
            }
        }
        return null;
    }
}
