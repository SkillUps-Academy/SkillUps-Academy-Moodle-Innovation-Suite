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
 * Limit an explicitly authorized local Ollama exception to one exact URL.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class loopback_security extends \core\files\curl_security_helper {
    /**
     * Bind this client to the deployment-owned local endpoint.
     */
    public function __construct(
        /** @var string Deployment-owned Ollama URL. */
        private readonly string $endpoint,
    ) {
    }

    /**
     * Deny any URL other than the exact configured loopback chat endpoint.
     */
    public function url_is_blocked($urlstring, $notused = null): bool {
        if ($urlstring !== $this->endpoint) {
            return true;
        }
        $parts = parse_url($urlstring);
        return !in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true) ||
            !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
            ($parts['path'] ?? '') !== '/api/chat' ||
            isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass']);
    }

    /**
     * Use cURL's literal IP directly, with no DNS resolution or pinning.
     *
     * @return array cURL RESOLVE entries.
     */
    public function get_resolve_info(): array {
        return [];
    }
}
