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

use local_skillupsintegration\vault;

/**
 * Google Directory and Classroom adapter using delegated OAuth refresh tokens.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class google extends base {
    /**
     * Identify the configured authentication model.
     *
     * {@inheritdoc}
     */
    public function authentication(): string {
        return 'auth_google';
    }

    /**
     * Return provider mappings for the requested entity.
     *
     * {@inheritdoc}
     */
    public function defaults(string $entity): array {
        return $entity === 'user' ? [
            'firstname' => 'name.givenName', 'lastname' => 'name.familyName', 'email' => 'primaryEmail',
        ] : ['fullname' => 'name'];
    }

    /**
     * Fetch a validated page of provider records.
     *
     * {@inheritdoc}
     */
    public function page(string $entity, string $cursor): array {
        $keys = vault::read($this->source->secretref);
        $token = $this->http->json('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => vault::field($keys, 'clientid'),
            'client_secret' => vault::field($keys, 'clientsecret'),
            'refresh_token' => vault::field($keys, 'refresh'),
        ]);
        $bearer = vault::field($token, 'access_token');
        if ($entity === 'user') {
            $url = 'https://admin.googleapis.com/admin/directory/v1/users';
            $scope = $this->options['endpoint'];
            $params = ($scope === 'my_customer' ? ['customer' => $scope] : ['domain' => $scope]) + ['maxResults' => 100];
            $params['fields'] = 'nextPageToken,users(id,name,primaryEmail,suspended)';
            $key = 'users';
        } else {
            $url = 'https://classroom.googleapis.com/v1/courses';
            $params = ['pageSize' => 100, 'courseStates' => 'ACTIVE', 'fields' => 'nextPageToken,courses(id,name,section)'];
            $key = 'courses';
        }
        if ($cursor !== '') {
            $params['pageToken'] = $cursor;
        }
        $out = $this->http->json($url . '?' . http_build_query($params), token: $bearer);
        [$records, $next] = $this->result($out[$key] ?? [], $out['nextPageToken'] ?? '');
        if ($entity === 'user') {
            $records = array_values(array_filter($records, static fn($row): bool => !is_array($row) || empty($row['suspended'])));
        }
        return [$records, $next];
    }
}
