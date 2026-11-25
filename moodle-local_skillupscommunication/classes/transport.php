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

namespace local_skillupscommunication;

/**
 * Send content through Moodle or an explicitly allowlisted installed connector.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transport {
    /**
     * Return configured available channels with localized labels.
     *
     * @return array channel identifiers and labels
     */
    public static function channels(): array {
        $options = [];
        foreach (['webnotice', 'emailnotice', 'bothnotice'] as $channel) {
            $options[$channel] = get_string($channel, access::C);
        }
        foreach (self::classes() as $channel => $class) {
            $options[$channel] = get_string('connectorname', access::C, $class);
        }
        return $options;
    }

    /**
     * Return eligible installed classes. The site allowlist controls selection and execution.
     *
     * @return array Channel keys and class names
     */
    private static function classes(): array {
        $enabled = preg_split('/\R/', (string)get_config(access::C, 'connectors'), -1, PREG_SPLIT_NO_EMPTY);
        $classes = [];
        foreach ($enabled as $line) {
            $class = trim($line, " \t\\");
            $namespace = explode('\\', $class);
            if (
                count($namespace) < 2 || !preg_match('/^[a-z][a-z0-9]*_[a-z][a-z0-9_]*$/D', $namespace[0]) ||
                !\core_component::get_component_directory($namespace[0]) || !is_subclass_of($class, connector::class)
            ) {
                continue;
            }
            $classes['external' . hash('sha256', $class)] = $class;
        }
        return $classes;
    }

    /**
     * Send an individual recipient without disclosing other target accounts.
     *
     * @param \stdClass $campaign Frozen campaign
     * @param \stdClass $user Active target account
     * @return array State, message id and categorical outcome
     */
    public static function send(\stdClass $campaign, \stdClass $user): array {
        global $CFG;
        if (str_starts_with($campaign->channel, 'external')) {
            $class = self::classes()[$campaign->channel] ?? null;
            if (!$class) {
                return ['retry', 0, 'disabledchannel'];
            }
            $key = hash('sha256', $CFG->wwwroot . ':' . $campaign->id . ':' . $user->id);
            $result = (new $class())->send((int)$user->id, $campaign->subject, $campaign->body, $key);
            return match ($result) {
                'accepted', 'delivered' => [$result, 0, ''],
                'retry' => ['retry', 0, 'connectorrejected'],
                default => ['uncertain', 0, 'handoffunknown'],
            };
        }
        $message = new \core\message\message();
        $message->component = access::C;
        $message->name = $campaign->channel;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $campaign->subject;
        $message->fullmessage = $campaign->body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($campaign->body));
        $message->smallmessage = \core_text::substr($campaign->body, 0, 255);
        $message->notification = 1;
        if ($id = message_send($message)) {
            return ['accepted', (int)$id, ''];
        }
        if (!empty($message->savedmessageid)) {
            return ['uncertain', (int)$message->savedmessageid, 'handoffunknown'];
        }
        return ['retry', 0, 'moodlerejected'];
    }
}
