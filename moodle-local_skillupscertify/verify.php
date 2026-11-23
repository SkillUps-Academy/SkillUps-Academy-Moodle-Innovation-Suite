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
 * Public QR verification with a strictly limited response.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

use local_skillupscertify\access;
use local_skillupscertify\verification;

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private, max-age=0');
$url = access::url('verify');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_cacheable(false);
$PAGE->set_title(access::text('verify'));
$PAGE->set_heading(access::text('pluginname'));
$code = strtolower(optional_param('code', '', PARAM_RAW_TRIMMED));
$data = ['url' => $url->out(false), 'enabled' => (bool)get_config(access::C, 'publicverification')];
if ($data['enabled'] && $code !== '') {
    $result = verification::lookup($code);
    $data['unknown'] = !$result;
    if ($result) {
        $data['credential'] = $result + [
            'label' => access::text('status_' . $result['status']),
            'valid' => $result['status'] === 'valid',
        ];
        $PAGE->requires->js_call_amd(access::C . '/certify', 'checkFile', ['skillups-certify-fingerprint']);
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/verify', $data);
echo $OUTPUT->footer();
