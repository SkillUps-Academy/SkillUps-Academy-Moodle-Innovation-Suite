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

namespace local_skillupscopilot\ai;

/**
 * OpenAI and OpenAI-compatible chat APIs.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chat_completions extends json_provider {
    /**
     * Generate via a Chat Completions endpoint.
     */
    public function generate(array $messages, array $config): array {
        $request = [
            'model' => $config['model'],
            'messages' => $messages,
            'stream' => false,
            'temperature' => $config['temperature'],
            'max_completion_tokens' => $config['output'],
        ];
        if ($config['url'] === 'https://api.openai.com/v1/chat/completions') {
            $request['store'] = false;
        }
        $response = $this->post($config, $request);
        return $this->result(
            $response['choices'][0]['message']['content'] ?? null,
            $response['usage']['prompt_tokens'] ?? null,
            $response['usage']['completion_tokens'] ?? null
        );
    }
}
