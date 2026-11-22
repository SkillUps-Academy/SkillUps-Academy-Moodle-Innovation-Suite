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

namespace local_skillupssuccess;

/**
 * Persist case lifecycle, immutable observations and a prospective quality cohort.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** Course policies. */
    public const POLICY = 'local_skillupssuccess_policy';
    /** Daily observations. */
    public const OBS = 'local_skillupssuccess_obs';
    /** Support cases. */
    public const CASES = 'local_skillupssuccess_case';
    /** Human intervention trail. */
    public const ENTRY = 'local_skillupssuccess_entry';
    /** Holdout assessment samples. */
    public const SAMPLE = 'local_skillupssuccess_sample';
    /** Prediction and outcome window. */
    public const HORIZON = 14 * DAYSECS;

    /**
     * Return the opt-in course policy or default thresholds with collection disabled.
     */
    public static function policy(int $courseid): \stdClass {
        global $DB;
        return $DB->get_record(self::POLICY, ['courseid' => $courseid]) ?: (object)[
            'id' => 0, 'courseid' => $courseid, 'definition' => util::json(rules::DEFAULTS), 'enabled' => 0,
            'study' => 0, 'usemodel' => 0, 'nextscan' => 0, 'lastuserid' => 0,
        ];
    }

    /**
     * Save a course policy behind the same lock the collector uses.
     */
    public static function configure(
        \stdClass $course,
        array $thresholds,
        bool $enabled,
        bool $study,
        bool $usemodel = false
    ): void {
        global $DB;
        $context = \context_course::instance($course->id);
        if (!scope::allowed($course, 'configure')) {
            util::fail('outofscope');
        }
        $lock = util::lock($course->id) ?? util::fail('busy');
        try {
            $policy = self::policy($course->id);
            $update = [
                'courseid' => $course->id, 'definition' => util::json(rules::config($thresholds)),
                'enabled' => (int)$enabled, 'study' => (int)($enabled && $study), 'usemodel' => (int)($enabled && $usemodel),
                'nextscan' => time(), 'lastuserid' => 0,
            ];
            if ($policy->id) {
                $DB->update_record(self::POLICY, $update + ['id' => $policy->id]);
            } else {
                $DB->insert_record(self::POLICY, $update);
            }
            util::audit($context, 'configure');
        } finally {
            $lock->release();
        }
    }

    /**
     * Bound work, resume by stable learner ID and make one observation per learner and UTC day.
     */
    public static function scan(int $courseid, int $limit = 25): void {
        global $DB;
        $lock = util::lock($courseid, 0);
        if (!$lock) {
            return;
        }
        try {
            $policy = self::policy($courseid);
            $now = time();
            if (!$policy->enabled || $policy->nextscan > $now) {
                return;
            }
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (
                !$course || !$course->visible || $course->startdate > $now ||
                    ($course->enddate && $course->enddate < $now)
            ) {
                $DB->update_record(self::POLICY, ['id' => $policy->id, 'lastuserid' => 0, 'nextscan' => $now + DAYSECS]);
                return;
            }
            [$sql, $params] = scope::population($course);
            $users = $DB->get_fieldset_sql(
                "SELECT cohort.id FROM ($sql) cohort WHERE cohort.id > :resume ORDER BY cohort.id",
                $params + ['resume' => $policy->lastuserid],
                0,
                $limit
            );
            $thresholds = rules::config(json_decode($policy->definition, true, 8, JSON_THROW_ON_ERROR));
            $model = $policy->usemodel ? predictor::configured() : null;
            foreach ($users as $userid) {
                $key = ['courseid' => $courseid, 'userid' => $userid, 'daykey' => intdiv($now, DAYSECS)];
                if (!$DB->record_exists(self::OBS, $key)) {
                    $features = evidence::collect($course, (int)$userid, $now);
                    $decision = rules::evaluate($features, $thresholds);
                    $prediction = predictor::score($features, $model);
                    self::record($key, $now, $features, $decision, $prediction, $model, (bool)$policy->study, $thresholds);
                }
                $DB->set_field(self::POLICY, 'lastuserid', $userid, ['id' => $policy->id]);
            }
            if (count($users) < $limit) {
                $DB->update_record(self::POLICY, ['id' => $policy->id, 'lastuserid' => 0,
                    'nextscan' => (intdiv($now, DAYSECS) + 1) * DAYSECS]);
            } else {
                $DB->set_field(self::POLICY, 'nextscan', $now + MINSECS, ['id' => $policy->id]);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Write observation, case pointer, and optional quality sample as one transition.
     */
    private static function record(
        array $key,
        int $time,
        array $features,
        array $decision,
        ?array $prediction,
        ?array $model,
        bool $study,
        array $thresholds
    ): void {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        $fields = [
            'risk' => $decision['risk'], 'ruleversion' => $decision['version'],
            'gradepct' => $features['grade'], 'captured' => $time,
            'probability' => $prediction['probability'] ?? null, 'modelalert' => $prediction['flag'] ?? -1,
            'modelversion' => $model['version'] ?? '', 'modelfingerprint' => $model['fingerprint'] ?? '',
        ];
        $obsid = $DB->insert_record(self::OBS, $key + $fields + [
            'factors' => util::json([
                'features' => $features, 'triggers' => $decision['triggers'], 'thresholds' => $thresholds,
                'prediction' => $prediction,
            ]),
        ]);
        $subject = ['courseid' => $key['courseid'], 'userid' => $key['userid']];
        $existing = $DB->get_record(self::CASES, $subject);
        $flag = in_array($decision['risk'], ['watch', 'high'], true);
        if ($existing) {
            $DB->update_record(self::CASES, ['id' => $existing->id, 'observationid' => $obsid, 'risk' => $decision['risk']]);
        } else if ($flag || ($prediction['flag'] ?? 0)) {
            $id = $DB->insert_record(self::CASES, $subject + [
                'observationid' => $obsid, 'risk' => $decision['risk'], 'state' => 'open', 'tutorid' => 0,
                'timecreated' => $time, 'timemodified' => $time,
            ]);
            util::audit(\context_course::instance($key['courseid']), 'openalert', $id);
        }
        if ($study) {
            $samplekey = $subject + ['cohort' => intdiv($time, self::HORIZON)];
            if (!$DB->record_exists(self::SAMPLE, $samplekey)) {
                $DB->insert_record(self::SAMPLE, $samplekey + [
                    'captured' => $time, 'due' => $time + self::HORIZON, 'ruleversion' => $fields['ruleversion'],
                    'rulealert' => $decision['risk'] === 'unknown' ? -1 : (int)$flag,
                    'probability' => $fields['probability'], 'modelalert' => $fields['modelalert'],
                    'modelversion' => $fields['modelversion'], 'modelfingerprint' => $fields['modelfingerprint'],
                ]);
            }
        }
        $tx->allow_commit();
    }

    /**
     * Authorize an identified case against current course, role, assignment and subject.
     */
    public static function case_for(\stdClass $course, int $id): \stdClass {
        global $DB, $USER;
        $row = $DB->get_record(self::CASES, ['id' => $id, 'courseid' => $course->id]);
        if (
            !$row || !scope::allowed($course, 'view') || !scope::learner($course, $row->userid) ||
                (!has_capability(util::CAP . ':manage', \context_course::instance($course->id)) && $row->tutorid != $USER->id)
        ) {
            util::fail('outofscope');
        }
        return $row;
    }

    /**
     * Record an explicit tutor assignment, state change or intervention note.
     */
    public static function intervene(\stdClass $course, int $id, int $revision, string $action, string $note, int $tutor): void {
        global $DB, $USER;
        $context = \context_course::instance($course->id);
        $lock = util::lock($course->id) ?? util::fail('busy');
        try {
            $case = self::case_for($course, $id);
            if ($case->revision != $revision) {
                util::fail('stale');
            }
            $note = trim(clean_param($note, PARAM_TEXT));
            if (
                \core_text::strlen($note) > 4000 || !in_array($action, ['assign', 'contact', 'plan', 'followup', 'monitor',
                    'resolved', 'reopen'], true) || ($action !== 'assign' && $note === '')
            ) {
                util::fail('invalidrequest');
            }
            if ($case->state === 'resolved' && !in_array($action, ['assign', 'reopen'], true)) {
                util::fail('closedcase');
            }
            if ($action === 'assign') {
                require_capability(util::CAP . ':manage', $context);
                if ($tutor) {
                    $staff = \core_user::get_user($tutor);
                    if (
                        !$staff || !scope::active($staff) || !is_enrolled($context, $tutor, '', true) ||
                            !scope::allowed($course, 'view', $tutor)
                    ) {
                        util::fail('outofscope');
                    }
                }
                $case->tutorid = $tutor;
            } else if (in_array($action, ['monitor', 'resolved', 'reopen'], true)) {
                $case->state = $action === 'reopen' ? 'open' : $action;
            }
            $tx = $DB->start_delegated_transaction();
            $entry = $DB->insert_record(self::ENTRY, [
                'caseid' => $id, 'userid' => $USER->id, 'action' => $action, 'note' => $note,
                'tutorid' => $action === 'assign' ? $tutor : 0, 'timecreated' => time(),
            ]);
            $case->revision = $entry;
            $case->timemodified = time();
            $DB->update_record(self::CASES, $case);
            util::audit($context, $action, $id);
            $tx->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Record one independent outcome only after its fixed observation window.
     */
    public static function assess(\stdClass $course, int $id, int $outcome): void {
        global $DB, $USER;
        if (!scope::allowed($course, 'assess') || !in_array($outcome, [0, 1, 2], true)) {
            util::fail('outofscope');
        }
        $lock = util::lock($course->id) ?? util::fail('busy');
        try {
            $sample = $DB->get_record(self::SAMPLE, ['id' => $id, 'courseid' => $course->id], '*', MUST_EXIST);
            if ($sample->outcome != -1 || $sample->due > time() || !self::policy($course->id)->study) {
                util::fail('stale');
            }
            if (!scope::learner($course, $sample->userid)) {
                util::fail('outofscope');
            }
            $DB->update_record(self::SAMPLE, ['id' => $id, 'outcome' => $outcome, 'assessorid' => $USER->id, 'reviewed' => time()]);
            util::audit(\context_course::instance($course->id), 'assess', $id);
        } finally {
            $lock->release();
        }
    }
}
