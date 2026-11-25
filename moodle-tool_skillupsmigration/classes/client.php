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

namespace tool_skillupsmigration;

/**
 * Call only pinned, authorized HTTPS Moodle web service endpoints.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class client {
    /**
     * Return registered endpoints without reading token values.
     *
     * @return array Registry identifiers and labels
     */
    public static function choices(): array {
        global $CFG;
        $choices = [];
        foreach ((array)($CFG->tool_skillupsmigration_origins ?? []) as $key => $entry) {
            if (is_string($key) && preg_match('/^[a-z][a-z0-9_-]{0,47}$/D', $key) && is_array($entry)) {
                $choices[$key] = (string)($entry['label'] ?? $key);
            }
        }
        return $choices;
    }

    /**
     * Validate a controlled HTTPS base URL and static IPv4 pin without DNS resolution.
     *
     * @param array $entry Trusted environment registry entry
     * @return array URL, host, port and IP
     */
    public static function endpoint(array $entry): array {
        $url = rtrim((string)($entry['url'] ?? ''), '/');
        $parsed = parse_url($url);
        $ip = (string)($entry['ip'] ?? '');
        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';
        $port = (int)($parsed['port'] ?? 443);
        if (
            preg_match('/[\x00-\x20\x7f]/', $url) || ($parsed['scheme'] ?? '') !== 'https' ||
            !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]*$/D', $host) ||
            $host === 'localhost.localdomain' || str_ends_with($host, '.localhost') ||
            array_intersect(['user', 'pass', 'query', 'fragment'], array_keys((array)$parsed)) ||
            ($path !== '' && !preg_match('~^(/[A-Za-z0-9_-]+)+$~D', $path)) || $port < 1 || $port > 65535 ||
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            support::fail('endpoint');
        }
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $internal = preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip);
        // Also reject special-use IPv4 ranges that some PHP filters classify as global.
        $special = preg_match(
            '/^(0\.|100\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\.|192\.(0\.|88\.99\.)|'
                . '198\.(18|19|51\.100)\.|203\.0\.113\.|22[4-9]\.|23[0-9]\.|2[4-5][0-9]\.)/',
            $ip
        );
        if ($special || (!$public && (!$internal || empty($entry['allowinternal'])))) {
            support::fail('endpoint');
        }
        return [$url, $host, $port, $ip];
    }

    /**
     * Connect to native site-info and verify the explicit migration service.
     *
     * @param string $key Registry identifier
     * @return array Safe origin identity and version information
     */
    public static function probe(string $key): array {
        $site = self::post($key, 'core_webservice_get_site_info', []);
        $available = array_column($site['functions'] ?? [], 'name');
        if (!in_array('tool_skillupsmigration_source', $available, true)) {
            support::fail('servicemissing');
        }
        $entry = self::entry($key);
        [$url] = self::endpoint($entry);
        if (rtrim($site['siteurl'] ?? '', '/') !== $url) {
            support::fail('originmismatch');
        }
        $bridge = self::call($key, 'probe');
        if (($bridge['protocol'] ?? 0) !== support::PROTOCOL) {
            support::fail('servicemissing');
        }
        return ['release' => (string)($site['release'] ?? ''), 'version' => (int)($bridge['version'] ?? 0)];
    }

    /**
     * Call one registered action with JSON carried in a native Moodle REST POST.
     *
     * @param string $key Registry identifier
     * @param string $operation Bridge action
     * @param array $params Action payload
     * @return array Parsed response
     */
    public static function call(string $key, string $operation, array $params = []): array {
        $response = self::post($key, 'tool_skillupsmigration_source', [
            'operation' => $operation, 'payload' => support::encode($params),
        ]);
        if (!isset($response['data']) || !is_string($response['data'])) {
            support::fail('remoteresponse');
        }
        try {
            return support::decode($response['data']);
        } catch (\JsonException $error) {
            support::fail('remoteresponse');
        }
    }

    /**
     * Bind each job to its original registry address and transport pin.
     *
     * @param string $key Registry identifier
     * @return string Stable registry fingerprint
     */
    public static function identity(string $key): string {
        return hash('sha256', support::encode(self::endpoint(self::entry($key))));
    }

    /**
     * Resolve a server-managed configuration entry.
     *
     * @param string $key Registry identifier
     * @return array Endpoint configuration
     */
    private static function entry(string $key): array {
        global $CFG;
        if (!array_key_exists($key, self::choices())) {
            support::fail('endpoint');
        }
        return $CFG->tool_skillupsmigration_origins[$key];
    }

    /**
     * Perform one bounded HTTPS request with TLS checks, no redirects or network proxies.
     *
     * @param string $key Registry key
     * @param string $function Native Moodle web service function
     * @param array $params Request arguments
     * @return array JSON response
     */
    private static function post(string $key, string $function, array $params): array {
        $entry = self::entry($key);
        [$url, $host, $port, $ip] = self::endpoint($entry);
        $env = (string)($entry['tokenenv'] ?? '');
        $token = preg_match('/^[A-Z][A-Z0-9_]{1,95}$/D', $env) ? getenv($env) : false;
        if (!is_string($token) || !preg_match('/^[0-9a-f]{32,128}$/Di', $token)) {
            support::fail('credential');
        }
        $buffer = '';
        $curl = curl_init($url . '/webservice/rest/server.php');
        $options = [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($params + [
                'wstoken' => $token, 'wsfunction' => $function, 'moodlewsrestformat' => 'json',
            ], '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$buffer): int {
                if (strlen($buffer) + strlen($chunk) > 8388608) {
                    return 0;
                }
                $buffer .= $chunk;
                return strlen($chunk);
            },
        ];
        if (!empty($entry['cafile'])) {
            $options[CURLOPT_CAINFO] = (string)$entry['cafile'];
        }
        try {
            curl_setopt_array($curl, $options);
            $ok = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }
        if (!$ok || $status !== 200) {
            support::fail('remoteconnection');
        }
        try {
            $result = support::decode($buffer);
        } catch (\JsonException $error) {
            support::fail('remoteresponse');
        }
        if (isset($result['exception'])) {
            $code = $result['errorcode'] ?? '';
            support::fail(in_array($code, ['selection', 'sourcechanged', 'usersmap', 'badrequest', 'archivebounds'], true)
                ? $code : 'remoteaccess');
        }
        return $result;
    }
}
