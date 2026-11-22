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

namespace local_skillupsaigrading\activity;

use local_skillupsaigrading\access;
use local_skillupsaigrading\extraction\document;

/**
 * Moodle Assignment adapter. Grading APIs are accessed read-only.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assign implements adapter {
    /** @var \assign Native assignment. */
    private \assign $activity;
    /** @var \context_module Moodle module context. */
    public readonly \context_module $context;
    /** @var \cm_info Module metadata. */
    public readonly \cm_info $cm;
    /** @var \stdClass Course record. */
    public readonly \stdClass $course;

    /**
     * Resolve the activity under the marker's own access rules.
     */
    public function __construct(int $cmid, string $capability = 'view', bool $browser = false) {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign', 0, $USER->id);
        if ($browser) {
            require_login($course, false, $cm);
        }
        $this->cm = $cm;
        $this->course = $course;
        $this->context = \context_module::instance($cmid);
        if (!$cm->uservisible || !empty($cm->deletioninprogress)) {
            access::fail('accessdenied');
        }
        require_capability('mod/assign:grade', $this->context);
        require_capability(access::CAP . ':view', $this->context);
        require_capability(access::CAP . ':' . $capability, $this->context);
        if (!$browser && !can_access_course($course, $USER, '', true)) {
            access::fail('accessdenied');
        }
        $this->activity = new \assign($this->context, $cm, $course);
        if ($this->activity->is_blind_marking() || $this->activity->get_instance()->teamsubmission) {
            access::fail('unsupportedassignment');
        }
    }

    /**
     * Return the native module metadata.
     */
    public function module(): \cm_info {
        return $this->cm;
    }

    /**
     * Return the native module context.
     */
    public function context(): \context_module {
        return $this->context;
    }

    /**
     * Return the parent course.
     */
    public function course(): \stdClass {
        return $this->course;
    }

    /**
     * Enforce the assignment's visibility, group and marker allocation rules.
     */
    public function visible(int $userid): bool {
        global $USER;
        $subject = \core_user::get_user($userid);
        if (
            !$subject || $subject->deleted || $subject->suspended ||
                !is_enrolled($this->context, $subject, 'mod/assign:submit', true) ||
                !$this->activity->can_view_submission($userid)
        ) {
            return false;
        }
        $availability = new \core_availability\info_module($this->cm);
        if (!$availability->filter_user_list([$userid => $subject])) {
            return false;
        }
        if (
            (int)groups_get_activity_groupmode($this->cm) === SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $this->context)
        ) {
            $allowed = groups_get_activity_allowed_groups($this->cm, $USER->id);
            $membership = groups_get_all_groups($this->course->id, $userid, $this->cm->groupingid);
            if (!array_intersect_key($allowed, $membership)) {
                return false;
            }
        }
        $instance = $this->activity->get_instance();
        if (
            !empty($instance->markingworkflow) && !empty($instance->markingallocation) &&
                !has_capability('mod/assign:manageallocations', $this->context)
        ) {
            return $this->is_allocated((int)$USER->id, $userid);
        }
        return true;
    }

    /**
     * Check marker assignment using the native API for this Moodle version.
     */
    private function is_allocated(int $marker, int $student): bool {
        if (method_exists($this->activity, 'get_marker_allocations')) {
            $allocations = $this->activity->get_marker_allocations($student, false);
            return in_array($marker, array_map('intval', array_column($allocations, 'marker')), true);
        }
        if (method_exists($this->activity, 'is_user_allocated_marker')) {
            return $this->activity->is_user_allocated_marker($marker, $student);
        }
        return (int)($this->activity->get_user_flags($student, false)->allocatedmarker ?? 0) === $marker;
    }

    /**
     * List native participants after applying target authorization.
     */
    public function participants(): array {
        $group = groups_get_activity_group($this->cm, true);
        $participants = $this->activity->list_participants($group, false, false, true);
        return array_filter($participants, fn($subject) => $this->visible($subject->id));
    }

    /**
     * Implements the adapter contract for Moodle Assignment.
     */
    public function submission(int $userid): \stdClass {
        if (!$this->visible($userid)) {
            access::fail('accessdenied');
        }
        $submission = $this->activity->get_user_submission($userid, false);
        if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            access::fail('nosubmission');
        }
        return $submission;
    }

    /**
     * Implements the adapter contract for Moodle Assignment.
     */
    public function rubric(): array {
        $manager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        if ($manager->get_active_method() !== 'rubric') {
            access::fail('norubric');
        }
        $controller = $manager->get_controller('rubric');
        if (!$controller->is_form_available()) {
            access::fail('norubric');
        }
        $criteria = [];
        foreach ($controller->get_definition()->rubric_criteria as $criterion) {
            $levels = [];
            foreach ($criterion['levels'] as $level) {
                $levels[] = [
                    'id' => (int)$level['id'],
                    'description' => self::plain($level['definition'], 500),
                    'score' => (float)$level['score'],
                ];
            }
            if (!$levels || count($levels) > 15) {
                access::fail('norubric');
            }
            $criteria[] = [
                'id' => (int)$criterion['id'],
                'description' => self::plain($criterion['description'], 500),
                'levels' => $levels,
            ];
        }
        if (!$criteria || count($criteria) > 20 || !is_finite((float)$controller->get_min_max_score()['maxscore'])) {
            access::fail('norubric');
        }
        return ['criteria' => $criteria, 'lockzero' => !empty($controller->get_options()['lockzeropoints'])];
    }

    /**
     * Convert formatted native text into bounded plain text.
     */
    public static function plain(string $content, int $limit): string {
        return \core_text::substr(trim(html_to_text($content, 0, false)), 0, $limit);
    }

    /**
     * Resolve the official submission plugin file list without accepting arbitrary file IDs.
     */
    private function files(\stdClass $submission, int $userid): array {
        $plugin = $this->activity->get_submission_plugin_by_type('file');
        if (!$plugin || !$plugin->is_enabled()) {
            return [];
        }
        $allowed = [];
        foreach ($plugin->get_files($submission, \core_user::get_user($userid)) as $file) {
            if (
                in_array(strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)), document::EXTENSIONS, true) &&
                    $file->get_filesize() > 0 && $file->get_filesize() <= document::MAX_BYTES && !$file->is_external_file()
            ) {
                $allowed[$file->get_id()] = $file;
            }
        }
        return $allowed;
    }

    /**
     * Return only eligible text and file sources for this attempt.
     */
    public function sources(int $userid): array {
        $submission = $this->submission($userid);
        $plugin = $this->activity->get_submission_plugin_by_type('onlinetext');
        $sources = [];
        if ($plugin && $plugin->is_enabled() && trim($plugin->get_editor_text('onlinetext', $submission->id))) {
            $sources[0] = get_string('onlinetext', access::C);
        }
        foreach ($this->files($submission, $userid) as $id => $file) {
            $sources[$id] = $file->get_filename();
        }
        return $sources;
    }

    /**
     * Resolve and fingerprint the authorized evidence without running a parser.
     */
    public function source(int $userid, int $sourceid): array {
        $submission = $this->submission($userid);
        if ($sourceid === 0) {
            $plugin = $this->activity->get_submission_plugin_by_type('onlinetext');
            $content = $plugin && $plugin->is_enabled() ? $plugin->get_editor_text('onlinetext', $submission->id) : '';
            if (!trim($content)) {
                access::fail('invaliddocument');
            }
            return [$submission, hash('sha256', $content), $content];
        }
        $file = $this->files($submission, $userid)[$sourceid] ?? null;
        if (!$file) {
            access::fail('invaliddocument');
        }
        return [$submission, hash('sha256', $file->get_contenthash()), $file];
    }

    /**
     * Implements the adapter contract for Moodle Assignment.
     */
    public function extract(int $userid, int $sourceid): array {
        [$submission, $signature, $content] = $this->source($userid, $sourceid);
        $text = $sourceid ? document::extract($content) : self::plain($content, document::MAX_TEXT);
        if ($text === '') {
            access::fail('emptydocument');
        }
        return [$submission, $signature, $text];
    }

    /**
     * Implements the adapter contract for Moodle Assignment.
     */
    public function human_percent(int $userid, \stdClass $submission): ?float {
        $current = $this->submission($userid);
        if ((int)$current->id !== (int)$submission->id || (int)$current->attemptnumber !== (int)$submission->attemptnumber) {
            return null;
        }
        if (method_exists($this->activity, 'is_using_multiple_marking') && $this->activity->is_using_multiple_marking()) {
            return null;
        }
        $item = $this->activity->get_grade_item();
        if (!$item || (int)$item->gradetype !== GRADE_TYPE_VALUE || $item->grademax <= $item->grademin) {
            return null;
        }
        $attemptgrade = $this->activity->get_user_grade($userid, false, $current->attemptnumber);
        $final = $item->get_final($userid);
        if (
            !$attemptgrade || !$final || $final->overridden || $final->finalgrade === null ||
                $attemptgrade->grade < 0 || abs($attemptgrade->grade - $final->finalgrade) > 0.00001
        ) {
            return null;
        }
        return 100.0 * ($final->finalgrade - $item->grademin) / ($item->grademax - $item->grademin);
    }
}
