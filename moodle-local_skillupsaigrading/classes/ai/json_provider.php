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

namespace local_skillupsaigrading\ai;

/**
 * Shared Moodle HTTP transport for JSON chat providers.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class json_provider implements provider {
    /**
     * Send a bounded request with TLS verification and without redirects.
     */
    protected function post(array $config, array $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $options = [];
        if ($config['allowloopback']) {
            $curl->set_security(new loopback_security($config['url']));
            $options['CURLOPT_PROXY'] = '';
            $options['CURLOPT_NOPROXY'] = '*';
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($config['token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $config['token'];
        }
        $curl->setHeader($headers);
        $body = '';
        $receive = static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 262144) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        };
        $result = $curl->post($config['url'], json_encode($payload, JSON_THROW_ON_ERROR), $options + [
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_CONNECTTIMEOUT' => 8,
            'CURLOPT_TIMEOUT' => 45,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_WRITEFUNCTION' => $receive,
        ]);
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        if ($status === 429 && !$curl->get_errno()) {
            throw new provider_failure(true);
        }
        if ($status !== 200 || $curl->get_errno()) {
            throw new provider_failure();
        }
        $data = json_decode($body !== '' ? $body : (is_string($result) ? $result : ''), true);
        if (!is_array($data)) {
            throw new provider_failure();
        }
        return $data;
    }

    /**
     * Validate normalized text and metering fields.
     */
    protected function result(mixed $text, mixed $input, mixed $output): array {
        if (!is_string($text) || trim($text) === '') {
            throw new provider_failure();
        }
        return [
            'text' => \core_text::substr($text, 0, 24000),
            'input' => is_int($input) && $input >= 0 ? min($input, 100000000) : 0,
            'output' => is_int($output) && $output >= 0 ? min($output, 100000000) : 0,
        ];
    }
}
