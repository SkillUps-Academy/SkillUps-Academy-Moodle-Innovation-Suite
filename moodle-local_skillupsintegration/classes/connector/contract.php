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
 * SIS and HR gateways using the documented SkillUps JSON page contract.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class contract extends base {
    /**
     * Identify the configured authentication model.
     *
     * {@inheritdoc}
     */
    public function authentication(): string {
        return 'auth_contract';
    }

    /**
     * Declare the supported integration commands.
     *
     * {@inheritdoc}
     */
    public function capabilities(): array {
        return ['user', 'course', 'pull', 'push'];
    }

    /**
     * Fetch a validated page of provider records.
     *
     * {@inheritdoc}
     */
    public function page(string $entity, string $cursor): array {
        $url = $this->options['endpoint'];
        http_transport::validate_custom($url);
        $keys = vault::read($this->source->secretref);
        if (vault::field($keys, 'audience') !== http_transport::hostname($url)) {
            throw new problem('credential');
        }
        $query = ['limit' => 100];
        if ($cursor !== '') {
            $query['cursor'] = $cursor;
        }
        $path = $entity === 'user' ? '/users' : '/courses';
        $out = $this->http->json($url . $path . '?' . http_build_query($query), token: vault::field($keys, 'bearer'));
        return $this->result($out['items'] ?? null, $out['next_cursor'] ?? '');
    }
}
