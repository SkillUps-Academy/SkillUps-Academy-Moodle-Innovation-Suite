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

namespace local_skillupsintegration;

/**
 * Make bounded JSON requests with Moodle's HTTP helper.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class http_transport implements transport {
    /** @var int Largest accepted response body. */
    private const LIMIT = 2097152;

    /**
     * Validate an administrator-approved endpoint origin.
     *
     * @param string $url Configured URL
     */
    public static function validate_custom(string $url): void {
        global $CFG;
        $host = self::hostname($url);
        $allow = (array)($CFG->local_skillupsintegration_hosts ?? []);
        $parts = parse_url($url);
        if (!in_array($host, $allow, true) || isset($parts['query']) || str_ends_with($parts['path'] ?? '', '/')) {
            throw new problem('endpoint');
        }
    }

    /**
     * Validate TLS, origin syntax, public-host requirements and a bounded URL.
     *
     * @param string $url URL to validate
     * @return string
     */
    public static function hostname(string $url): string {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (
            strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) ||
            ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) ||
            !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/D', $host)
        ) {
            throw new problem('endpoint');
        }
        return $host;
    }

    /**
     * Perform a single request without following redirects or exposing response contents in errors.
     *
     * @param string $url Trusted URL
     * @param array|null $form URL-encoded POST body, or null for GET
     * @param string $token Bearer token
     * @return array
     */
    public function json(string $url, ?array $form = null, string $token = ''): array {
        global $CFG;
        self::hostname($url);
        require_once($CFG->libdir . '/filelib.php');
        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $curl = new \curl(['securityhelper' => new public_security(), 'proxy' => false, 'debug' => false]);
        $curl->setHeader($headers);
        $buffer = '';
        $overflow = false;
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_PROXY' => '',
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_WRITEFUNCTION' => static function ($handle, string $bytes) use (&$buffer, &$overflow): int {
                if (strlen($buffer) + strlen($bytes) > self::LIMIT) {
                    $overflow = true;
                    return 0;
                }
                $buffer .= $bytes;
                return strlen($bytes);
            },
        ];
        $response = $form === null ? $curl->get($url, [], $options) : $curl->post($url, http_build_query($form), $options);
        $code = (int)($curl->get_info()['http_code'] ?? 0);
        if ($overflow) {
            throw new problem('limit');
        }
        if (in_array($code, [408, 425, 429], true) || $code >= 500) {
            $headers = array_change_key_case($curl->getResponse(), CASE_LOWER);
            $retry = $headers['retry-after'] ?? '';
            $wait = is_scalar($retry) && ctype_digit((string)$retry) ? min(3600, (int)$retry) : 0;
            throw new problem('upstream', true, $wait);
        }
        if ($code === 0) {
            throw new problem('unreachable', true);
        }
        if ($code === 401 || $code === 403) {
            throw new problem('credential');
        }
        if ($code < 200 || $code > 299) {
            throw new problem('upstream');
        }
        $body = $buffer !== '' ? $buffer : (is_string($response) ? $response : '');
        $data = strlen($body) <= self::LIMIT ? json_decode($body, true, 32) : null;
        if (!is_array($data) || array_is_list($data)) {
            throw new problem('response');
        }
        return $data;
    }
}
