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

namespace tool_skillupscoursefactory;

/**
 * Create and restore only the destination recorded for this request.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provisioner {
    /**
     * Initialize an unpublished destination and record its identity in the same database transaction.
     */
    public static function prepare(\stdClass $row, array $data): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $lock = support::lock('allocate', 8);
        if (!$lock) {
            throw new \moodle_exception('busy', support::C);
        }
        try {
            validator::unique($data);
            $tx = $DB->start_delegated_transaction();
            try {
                $fields = self::coursefields($data, 0);
                $fields['visible'] = 0;
                if ($data['source']) {
                    $fields['format'] = get_course($data['source'])->format;
                }
                $course = create_course((object)$fields);
                $DB->update_record(jobs::ROW, [
                    'id' => $row->id, 'courseid' => $course->id, 'phase' => $data['source'] ? 'prepared' : 'restored',
                ]);
                $tx->allow_commit();
            } catch (\Throwable $error) {
                $tx->rollback($error);
            }
            return (int)$course->id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Use the same native import-mode controllers used by Moodle course upload.
     */
    public static function restore(\stdClass $row, array $data, int $actor): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $backup = new \backup_controller(
            \backup::TYPE_1COURSE,
            $data['source'],
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $actor
        );
        $key = $backup->get_backupid();
        $restore = null;
        try {
            $users = $backup->get_plan()->get_setting('users');
            if ($users->get_value()) {
                $users->set_value(false);
            }
            $backup->execute_plan();
            $restore = new \restore_controller(
                $key,
                (int)$row->courseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $actor,
                \backup::TARGET_CURRENT_ADDING
            );
            if ($restore->get_status() === \backup::STATUS_REQUIRE_CONV) {
                $restore->convert();
            }
            if (!$restore->execute_precheck()) {
                $precheck = $restore->get_precheck_results();
                if (!empty($precheck['errors'])) {
                    throw new \moodle_exception('restorecheck', support::C);
                }
            }
            validator::authorize($data, $actor);
            self::destination($row, $data, $actor);
            if ($DB->get_field(jobs::JOB, 'cancelled', ['id' => $row->jobid])) {
                throw new \moodle_exception('cancelledrow', support::C);
            }
            // Do not replay restore on this target if the process dies past this durable boundary.
            $DB->set_field(jobs::ROW, 'phase', 'restoring', ['id' => $row->id]);
            $restore->execute_plan();
            $DB->set_field(jobs::ROW, 'phase', 'restored', ['id' => $row->id]);
        } finally {
            if ($restore) {
                $restore->destroy();
            }
            $backup->destroy();
            \backup_helper::delete_backup_dir($key);
        }
    }

    /**
     * Check that the tracked destination has not been moved or independently edited.
     */
    public static function destination(\stdClass $row, array $data, int $actor): \stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $row->courseid]);
        if (
            !$course || (int)$course->category !== $data['category'] || $course->shortname !== $data['shortname']
                || (string)$course->idnumber !== $data['idnumber'] || $course->visible
        ) {
            throw new \moodle_exception('targetchanged', support::C);
        }
        $context = \context_course::instance((int)$course->id);
        if (
            !has_capability('moodle/course:update', $context, $actor)
                || ($data['source'] && !has_capability('moodle/restore:restorecourse', $context, $actor))
        ) {
            throw new \moodle_exception('denied', support::C);
        }
        return $course;
    }

    /**
     * Apply enrollments and publish the course together.
     */
    public static function finish(\stdClass $row, array $data, int $actor): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = self::destination($row, $data, $actor);
        $context = \context_course::instance((int)$course->id);
        if ($data['visible'] && !has_capability('moodle/course:visibility', $context, $actor)) {
            throw new \moodle_exception('denied', support::C);
        }
        validator::check_staff($data, $context, $actor);
        $tx = $DB->start_delegated_transaction();
        try {
            if ($data['staff']) {
                self::enrol($course, $context, $data['staff'], $actor);
            }
            $fields = self::coursefields($data, (int)$course->id);
            $field = $data['periodfield'];
            if ($field !== '' && $data['period'] !== '') {
                $handler = \core_course\customfield\course_handler::create();
                $eligible = false;
                foreach ($handler->get_fields() as $definition) {
                    if (
                        $definition->get('shortname') === $field && $definition->get('type') === 'text'
                            && $handler->can_edit($definition, (int)$course->id)
                    ) {
                        $eligible = true;
                        break;
                    }
                }
                if (!$eligible) {
                    throw new \moodle_exception('periodunavailable', support::C);
                }
                $fields['customfield_' . $field] = $data['period'];
            }
            update_course((object)$fields);
            $DB->update_record(jobs::ROW, ['id' => $row->id, 'phase' => 'done', 'state' => 'done', 'errorcode' => '']);
            $tx->allow_commit();
        } catch (\Throwable $error) {
            $tx->rollback($error);
        }
    }

    /**
     * Use the existing enabled Manual enrolment instance.
     */
    private static function enrol(\stdClass $course, \context_course $context, array $staff, int $actor): void {
        $plugin = enrol_get_plugin('manual');
        $instances = array_filter(enrol_get_instances($course->id, true), static fn($item) => $item->enrol === 'manual');
        $instance = reset($instances);
        if (
            !$plugin || count($instances) !== 1 || !$plugin->allow_enrol($instance)
                || !has_capability('enrol/manual:enrol', $context, $actor)
        ) {
            throw new \moodle_exception('manualmissing', support::C);
        }
        foreach ($staff as $person) {
            $plugin->enrol_user($instance, $person['userid'], $person['role'], 0, 0, ENROL_USER_ACTIVE);
        }
    }

    /**
     * Build only supported Core course fields.
     */
    private static function coursefields(array $data, int $courseid): array {
        $fields = array_intersect_key($data, array_flip(['fullname', 'shortname', 'category', 'idnumber',
            'startdate', 'enddate', 'visible']));
        if ($courseid) {
            $fields['id'] = $courseid;
        } else {
            $fields += ['summary' => '', 'summaryformat' => FORMAT_HTML,
                'format' => get_config('moodlecourse', 'format') ?: 'topics'];
        }
        return $fields;
    }
}
