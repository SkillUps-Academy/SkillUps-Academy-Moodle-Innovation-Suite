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

namespace report_skillupsmonitor\check;

use core\check\result;
use report_skillupsmonitor\store;
use report_skillupsmonitor\util;

/**
 * Moodle native status check that only reads the last local sample.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class heartbeat extends \core\check\check {
    /**
     * Localized name.
     */
    public function get_name(): string {
        return util::str('heartbeat');
    }

    /**
     * Never run background or remote checks on the native status page.
     */
    public function get_result(): result {
        $sample = store::latest();
        if (!$sample) {
            return new result(result::UNKNOWN, util::str('nosamples'));
        }
        if (time() - $sample->captured > util::policy()['stale']) {
            return new result(result::WARNING, util::str('stalesample'));
        }
        return new result(result::OK, util::str('freshsample'));
    }
}
