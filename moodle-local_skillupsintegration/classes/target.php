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

use stdClass;

/**
 * Plan and apply only managed user and course fields via Moodle APIs.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class target {
    /**
     * Inspect a candidate without mutating Moodle.
     *
     * @param stdClass $source Connector
     * @param string $entity User or course
     * @param string $key Hashed remote identity
     * @param array $data Canonical record
     * @return array Decision, reason, fingerprint and native ID
     */
    public static function inspect(stdClass $source, string $entity, string $key, array $data): array {
        global $DB, $CFG;
        $link = $DB->get_record(store::LINK, ['sourceid' => $source->id, 'entity' => $entity, 'externalkey' => $key]);
        $isuser = $entity === 'user';
        $table = $isuser ? 'user' : 'course';
        if (!$link) {
            if ($isuser) {
                $exists = $DB->record_exists('user', ['username' => $data['username'], 'mnethostid' => $CFG->mnet_localhost_id]) ||
                    $DB->record_exists_select('user', 'deleted = 0 AND ' . $DB->sql_equal('email', ':email', false), [
                        'email' => $data['email'],
                    ]);
            } else {
                $exists = $DB->record_exists_select('course', $DB->sql_equal('shortname', ':shortname', false), [
                    'shortname' => $data['shortname'],
                ]);
            }
            return $exists ? ['conflict', 'collision', null, 0] : ['create', null, null, 0];
        }
        $local = $DB->get_record($table, ['id' => $link->targetid]);
        if (!$local) {
            return ['conflict', 'missing', null, 0];
        }
        $targetid = (int)$local->id;
        if ($isuser) {
            if ($local->deleted || $local->suspended || $local->mnethostid != $CFG->mnet_localhost_id || is_siteadmin($targetid)) {
                return ['conflict', 'protected', null, $targetid];
            }
            if ($local->username !== $data['username'] || \core_text::strtolower($local->email) !== $data['email']) {
                return ['conflict', 'identity', null, $targetid];
            }
        } else if ($local->category != $source->categoryid || $targetid === SITEID || $local->shortname !== $data['shortname']) {
            return ['conflict', 'identity', null, $targetid];
        }
        $current = self::finger($entity, (array)$local);
        if (hash_equals($current, self::finger($entity, $data))) {
            return ['same', null, $current, $targetid];
        }
        if ($source->policy === 'preserve' && !hash_equals($link->finger, $current)) {
            return ['conflict', 'localchange', $current, $targetid];
        }
        return ['update', null, $current, $targetid];
    }

    /**
     * Apply one immutable planned row, rechecking authority and local state under a short shared lock.
     *
     * @param stdClass $source Connector
     * @param stdClass $item Staged immutable item
     * @param int $actor Approved operator
     */
    public static function apply(stdClass $source, stdClass $item, int $actor): void {
        global $DB;
        store::locked('target:' . $item->entity, static function () use ($DB, $source, $item, $actor): void {
            $data = json_decode($item->payload, true, 8, JSON_THROW_ON_ERROR);
            [$decision, $reason, $finger, $id] = self::inspect($source, $item->entity, $item->externalkey, $data);
            if ($decision !== $item->decision || $finger !== $item->finger) {
                $DB->update_record(store::ITEM, ['id' => $item->id, 'state' => 'conflict', 'errorcode' => $reason ?? 'changed']);
                return;
            }
            if (!self::permitted($source, $item->entity, $decision, $id, $actor)) {
                $DB->update_record(store::ITEM, ['id' => $item->id, 'state' => 'failed', 'errorcode' => 'permission']);
                return;
            }
            store::transaction(static function () use ($DB, $source, $item, $data, $decision, $id, $actor): void {
                $run = $DB->get_record(store::RUN, ['id' => $item->runid]);
                $active = $DB->record_exists(store::SRC, ['id' => $source->id, 'revision' => $source->revision, 'enabled' => 1]);
                if (
                    !$active || !$run || $run->state !== 'applying' || (int)$run->approverid !== $actor ||
                    !$DB->record_exists(store::ITEM, ['id' => $item->id, 'state' => 'planned'])
                ) {
                    return;
                }
                $target = self::write($source, $item->entity, $decision, $id, $data);
                $link = ['sourceid' => $source->id, 'entity' => $item->entity, 'externalkey' => $item->externalkey];
                $fingerprint = self::finger($item->entity, $data);
                if ($decision === 'create') {
                    $DB->insert_record(store::LINK, $link + ['targetid' => $target, 'finger' => $fingerprint]);
                } else {
                    $DB->set_field(store::LINK, 'finger', $fingerprint, $link);
                }
                $DB->update_record(store::ITEM, [
                    'id' => $item->id, 'state' => 'applied', 'userid' => $item->entity === 'user' ? $target : 0,
                ]);
            });
        });
    }

    /**
     * Build a platform-independent digest of the fields this connector controls.
     *
     * @param string $entity Managed kind
     * @param array $values Canonical or native fields
     * @return string
     */
    private static function finger(string $entity, array $values): string {
        $fields = $entity === 'user' ? ['firstname', 'lastname'] : ['fullname'];
        $ordered = array_map(static fn(string $key): string => (string)$values[$key], $fields);
        return hash('sha256', json_encode($ordered, JSON_THROW_ON_ERROR));
    }

    /**
     * Recheck the original approver against the native target context.
     *
     * @param stdClass $source Connector
     * @param string $entity Managed kind
     * @param string $decision Create or update
     * @param int $id Native identity, if it exists
     * @param int $actor Approver
     * @return bool
     */
    private static function permitted(stdClass $source, string $entity, string $decision, int $id, int $actor): bool {
        if (!access::allowed($actor, 'apply')) {
            return false;
        }
        if ($entity === 'user') {
            return has_capability('moodle/user:' . $decision, \context_system::instance(), $actor);
        }
        $context = $decision === 'create' ? \context_coursecat::instance($source->categoryid, IGNORE_MISSING) :
            \context_course::instance($id, IGNORE_MISSING);
        return $context && has_capability('moodle/course:' . $decision, $context, $actor);
    }

    /**
     * Use Moodle APIs for managed objects. No roles, authentication changes or enrolments are imported.
     *
     * @param stdClass $source Connector
     * @param string $entity Managed kind
     * @param string $decision Create or update
     * @param int $id Existing native ID
     * @param array $data Canonical values
     * @return int
     */
    private static function write(stdClass $source, string $entity, string $decision, int $id, array $data): int {
        global $CFG;
        if ($entity === 'user') {
            $modern = method_exists(\core\user::class, 'create_user');
            if (!$modern) {
                require_once($CFG->dirroot . '/user/lib.php');
            }
            if ($decision === 'create') {
                $record = (object)($data + ['auth' => 'nologin', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id]);
                return $modern ? \core\user::create_user($record, false) : (int)user_create_user($record, false);
            }
            $record = (object)['id' => $id, 'firstname' => $data['firstname'], 'lastname' => $data['lastname']];
            if ($modern) {
                \core\user::update_user($record, false);
            } else {
                user_update_user($record, false);
            }
            return $id;
        }
        require_once($CFG->dirroot . '/course/lib.php');
        if ($decision === 'create') {
            $defaults = get_config('moodlecourse');
            return (int)create_course((object)($data + [
                'category' => $source->categoryid, 'visible' => 0, 'format' => $defaults->format,
                'summary' => '', 'summaryformat' => FORMAT_PLAIN,
            ]))->id;
        }
        update_course((object)['id' => $id, 'fullname' => $data['fullname']]);
        return $id;
    }
}
