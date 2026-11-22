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
 * Administrative settings and deployment-owned provider destinations.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /**
     * Supported connector slots.
     */
    public const IDS = ['ollama', 'openai', 'compatible'];

    /**
     * Read effective configuration or return null if the connector is unavailable.
     */
    public static function config(string $id): ?array {
        global $CFG;
        if (
            !in_array($id, self::IDS, true) || !get_config('local_skillupsaigrading', 'enableai') ||
                !get_config('local_skillupsaigrading', $id . '_enabled')
        ) {
            return null;
        }
        $deployment = $CFG->local_skillupsaigrading_providers[$id] ?? [];
        $url = $id === 'openai' ? 'https://api.openai.com/v1/chat/completions' : ($deployment['url'] ?? '');
        $parsed = parse_url($url) ?: [];
        $loopback = $id === 'ollama' && in_array($parsed['host'] ?? '', ['127.0.0.1', '[::1]'], true);
        if (!$loopback && !get_config('local_skillupsaigrading', 'allowexternal')) {
            return null;
        }
        if (
            empty($parsed['host']) || isset($parsed['user']) || isset($parsed['pass']) ||
            isset($parsed['fragment']) || isset($parsed['query']) ||
            !in_array($parsed['scheme'] ?? '', $loopback ? ['http', 'https'] : ['https'], true) ||
            preg_match('/[\x00-\x20\x7f\\\\]/', $url)
        ) {
            return null;
        }
        $model = trim((string)get_config('local_skillupsaigrading', $id . '_model'));
        $version = trim((string)get_config('local_skillupsaigrading', $id . '_modelversion'));
        $env = $deployment['tokenenv'] ?? '';
        $token = $env !== '' && preg_match('/\A[A-Z_][A-Z0-9_]*\z/D', $env) ? (string)getenv($env) : '';
        if (
            $model === '' || strlen($model) > 200 || $version === '' || strlen($version) > 120 ||
            ($token !== '' && preg_match('/[\x00-\x20\x7f]/', $token)) ||
            (($id === 'openai' || $env !== '') && $token === '')
        ) {
            return null;
        }
        $config = [
            'url' => $url,
            'model' => $model,
            'modelversion' => $version,
            'external' => !$loopback,
            'allowloopback' => $loopback && ($parsed['path'] ?? '') === '/api/chat' &&
                ($deployment['allowloopback'] ?? false) === true,
            'temperature' => max(0, min(2, (float)get_config('local_skillupsaigrading', $id . '_temperature'))),
            'output' => max(64, min(4096, (int)get_config('local_skillupsaigrading', $id . '_output'))),
        ];
        $config['revision'] = hash('sha256', serialize($config));
        $config['token'] = $token;
        return $config;
    }

    /**
     * Instantiate the appropriate protocol connector.
     */
    public static function connector(string $id): provider {
        return match ($id) {
            'ollama' => new ollama(),
            'openai', 'compatible' => new chat_completions(),
            default => throw new \coding_exception('Unknown grading connector'),
        };
    }
}
