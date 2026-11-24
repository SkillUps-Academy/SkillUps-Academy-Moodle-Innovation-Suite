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

namespace local_skillupsintegration\connector;

use local_skillupsintegration\http_transport;
use local_skillupsintegration\problem;
use local_skillupsintegration\vault;

/**
 * Microsoft Graph directory and education adapter with application OAuth.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class microsoft extends base {
    /**
     * Identify the configured authentication model.
     *
     * {@inheritdoc}
     */
    public function authentication(): string {
        return 'auth_microsoft';
    }

    /**
     * Return provider mappings for the requested entity.
     *
     * {@inheritdoc}
     */
    public function defaults(string $entity): array {
        return $entity === 'user' ? [
            'firstname' => 'givenName', 'lastname' => 'surname', 'email' => 'mail',
        ] : ['fullname' => 'displayName'];
    }

    /**
     * Fetch a validated page of provider records.
     *
     * {@inheritdoc}
     */
    public function page(string $entity, string $cursor): array {
        $keys = vault::read($this->source->secretref);
        $tenant = $this->options['endpoint'];
        if (!preg_match('/^[\da-f]{8}(?:-[\da-f]{4}){3}-[\da-f]{12}$/Di', $tenant)) {
            throw new problem('config');
        }
        $token = $this->http->json("https://login.microsoftonline.com/$tenant/oauth2/v2.0/token", [
            'grant_type' => 'client_credentials',
            'client_id' => vault::field($keys, 'clientid'),
            'client_secret' => vault::field($keys, 'clientsecret'),
            'scope' => 'https://graph.microsoft.com/.default',
        ]);
        $path = $entity === 'user' ? '/v1.0/users' : '/v1.0/education/classes';
        $select = $entity === 'user' ? 'id,givenName,surname,mail,userPrincipalName,accountEnabled' : 'id,displayName,mailNickname';
        $url = $cursor !== '' ? $cursor : 'https://graph.microsoft.com' . $path . '?' . http_build_query([
            '$top' => 100, '$select' => $select,
        ]);
        if (http_transport::hostname($url) !== 'graph.microsoft.com' || parse_url($url, PHP_URL_PATH) !== $path) {
            throw new problem('endpoint');
        }
        $out = $this->http->json($url, token: vault::field($token, 'access_token'));
        [$records, $next] = $this->result($out['value'] ?? null, $out['@odata.nextLink'] ?? '');
        if ($entity === 'user') {
            $records = array_values(array_filter($records, static fn($row): bool => !is_array($row) ||
                ($row['accountEnabled'] ?? true) !== false));
            foreach ($records as &$row) {
                if (is_array($row) && empty($row['mail'])) {
                    $row['mail'] = $row['userPrincipalName'] ?? '';
                }
            }
            unset($row);
        }
        return [$records, $next];
    }
}
