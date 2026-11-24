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

namespace report_skillupssecurity\control;

/**
 * Inspect the effective values of a fixed list of security-related Moodle settings.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class configuration implements rule {
    /** @var array Setting names explicitly eligible for administrative event sampling. */
    public const SETTINGS = [
        'wwwroot', 'debug', 'debugdisplay', 'guestloginbutton', 'autologinguests', 'forcelogin',
        'registerauth', 'passwordpolicy', 'minpasswordlength', 'defaultuserroleid', 'guestroleid',
        'defaultfrontpageroleid', 'insecurewwwroot', 'cookiesecure', 'siteadmins',
    ];

    /** @var array Check identifiers for the configuration family. */
    public const RULES = ['https', 'debug', 'guest', 'registration', 'passwordpolicy', 'login'];

    /**
     * Report only policy states, never configuration contents.
     *
     * @param int $now Scan timestamp
     * @return array
     */
    public function evaluate(int $now): array {
        global $CFG;
        $source = (string)$CFG->wwwroot;
        $checks = [
            'https' => [parse_url($source, PHP_URL_SCHEME) !== 'https', 'risk', 'high', 'wwwroot'],
            'debug' => [!empty($CFG->debugdisplay) && (int)$CFG->debug > 0, 'risk', 'high', 'debug, debugdisplay'],
            'guest' => [
                !empty($CFG->autologinguests) || !empty($CFG->guestloginbutton),
                'risk', 'medium', 'guestloginbutton, autologinguests',
            ],
            'registration' => [!empty($CFG->registerauth), 'risk', 'medium', 'registerauth'],
            'passwordpolicy' => [empty($CFG->passwordpolicy), 'risk', 'medium', 'passwordpolicy'],
            'login' => [empty($CFG->forcelogin), 'guide', 'low', 'forcelogin'],
        ];
        $findings = [];
        foreach ($checks as $id => [$flag, $kind, $level, $setting]) {
            if ($flag) {
                $findings[] = observation::make($id, $kind, $level, evidence: ['setting' => $setting]);
            }
        }
        return ['findings' => $findings, 'coverage' => array_fill_keys(self::RULES, true), 'inventory' => []];
    }
}
