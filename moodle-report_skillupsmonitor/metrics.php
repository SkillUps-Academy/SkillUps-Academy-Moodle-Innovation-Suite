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
 * Serve authorized Prometheus operational gauges.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use report_skillupsmonitor\collector;
use report_skillupsmonitor\gateway;
use report_skillupsmonitor\metrics;
use report_skillupsmonitor\store;
use report_skillupsmonitor\util;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Authenticate before bootstrapping Moodle.
require_once(__DIR__ . '/classes/gateway.php');
gateway::authorize();
define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState
// phpcs:ignore moodle.Files.RequireLogin.Missing -- Machine requests are stateless and bearer-authenticated above.
require_once(__DIR__ . '/../../config.php');

gateway::require_enabled();
$now = time();
$policy = util::policy();
$body = metrics::render(store::latest(), $now, $policy, collector::cron($now, $policy));
gateway::finish(200, $body, 'text/plain; version=0.0.4; charset=utf-8');
