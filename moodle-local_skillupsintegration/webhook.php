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
 * Receive a signed source event over HTTPS without a Moodle session.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

use local_skillupsintegration\ingress;
use local_skillupsintegration\problem;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
$servermethod = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
try {
    if (!str_starts_with($CFG->wwwroot, 'https://')) {
        throw new problem('signature');
    }
    if ($servermethod !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        echo json_encode(['error' => 'method']);
        exit;
    }
    $contenttype = strtolower(trim(explode(';', $headers['content-type'] ?? '', 2)[0]));
    if ($contenttype !== 'application/json') {
        http_response_code(415);
        echo json_encode(['error' => 'contenttype']);
        exit;
    }
    $body = file_get_contents('php://input', false, null, 0, ingress::LIMIT + 1);
    $result = ingress::webhook(
        optional_param('sourceid', 0, PARAM_INT),
        $headers['x-skillups-timestamp'] ?? '',
        $headers['x-skillups-signature'] ?? '',
        $body === false ? '' : $body,
    );
    http_response_code($result['replayed'] ? 200 : 202);
    echo json_encode($result);
} catch (problem $failure) {
    http_response_code(match ($failure->reason) {
        'signature', 'permission', 'unavailable' => 403,
        'limit' => 413,
        'busy' => 429,
        'replay' => 409,
        default => 400,
    });
    echo json_encode(['error' => $failure->reason]);
} catch (\Throwable $failure) {
    http_response_code(500);
    echo json_encode(['error' => 'processing']);
}
