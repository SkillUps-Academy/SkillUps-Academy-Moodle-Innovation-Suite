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

/**
 * Resolve course pages for browser scenarios.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupscertify\access;

// Behat can load this class before Moodle configuration.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat page resolution.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_skillupscertify extends behat_base {
    /**
     * Resolve a registry page using the shortname fixture.
     *
     * @param string $type Page name
     * @param string $identifier Course shortname
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;
        if ($type === 'registry') {
            $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
            return access::url('index', ['courseid' => $courseid]);
        }
        return parent::resolve_page_instance_url($type, $identifier);
    }
}
