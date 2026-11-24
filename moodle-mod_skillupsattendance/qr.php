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
 * Render a teacher-only rotating QR image for the open session.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\marks;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

$activity = activity::open(required_param('id', PARAM_INT));
$activity->require('sessions');
$sid = required_param('session', PARAM_INT);
$record = store::session($activity, $sid);
$allowed = $activity->staff_group($record->groupid) && session::is_open($record, time());
if (!$allowed || $record->method !== 'qr' || !$record->qrkey) {
    activity::fail('closedwindow');
}
require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');
$target = $activity->url('checkin', ['session' => $sid, 'qr' => marks::token($record, time())]);
$qr = new TCPDF2DBarcode($target->out(false), 'QRCODE,M');
$symbol = imagecreatefromstring($qr->getBarcodePngData(6, 6));
$width = imagesx($symbol);
$height = imagesy($symbol);
$canvas = imagecreatetruecolor($width + 48, $height + 48);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
imagecopy($canvas, $symbol, 24, 24, 0, 0, $width, $height);
header('Content-Type: image/png');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
imagepng($canvas);
