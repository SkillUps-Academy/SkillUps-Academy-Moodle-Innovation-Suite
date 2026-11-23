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

namespace local_skillupsgradebookguardian;

use context_course;
use grade_item;
use moodle_exception;
use stdClass;

/**
 * Preview, approve and reverse a narrow, grade-preserving visibility correction.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repair {
    /**
     * Save a preview from a specific finding in an authorized report.
     *
     * @param stdClass $course Course record
     * @param int $diagnosisid Parent report ID
     * @param int $itemid Grade item ID
     * @return int Preview ID
     */
    public static function preview(stdClass $course, int $diagnosisid, int $itemid): int {
        $context = context_course::instance($course->id);
        access::require($context, 'diagnose');
        if (!access::repairsenabled()) {
            throw new moodle_exception('repairdisabled', access::COMPONENT);
        }
        return ledger::locked((int)$course->id, static function () use ($course, $diagnosisid, $itemid): int {
            $report = ledger::get((int)$course->id, $diagnosisid, 'diagnosis');
            foreach ($report->payload['findings'] as $finding) {
                if ($finding['rule'] === 'hiddenmismatch' && $finding['id'] === $itemid) {
                    $before = self::state($course, $itemid);
                    if ($before['hidden'] !== 0 || $before['visible']) {
                        throw new moodle_exception('stale', access::COMPONENT);
                    }
                    $existing = ledger::history((int)$course->id, 'preview', 0)[1];
                    foreach ($existing as $entry) {
                        if ((int)$entry->parentid === $diagnosisid && time() - (int)$entry->timecreated < 60) {
                            $previous = ledger::get((int)$course->id, (int)$entry->id, 'preview');
                            if ($previous->payload['before'] === $before) {
                                return (int)$entry->id;
                            }
                        }
                    }
                    return ledger::append(
                        (int)$course->id,
                        'preview',
                        ['before' => $before, 'after' => array_replace($before, ['hidden' => 1])],
                        $diagnosisid
                    );
                }
            }
            throw new moodle_exception('notrepairable', access::COMPONENT);
        });
    }

    /**
     * Approve or reverse a preview only if the live item still matches the reviewed state.
     *
     * @param stdClass $course Course record
     * @param int $previewid Preview ID
     * @param bool $revert Whether this approval is a recovery
     * @return int Decision ID
     */
    public static function apply(stdClass $course, int $previewid, bool $revert = false): int {
        global $DB;
        $context = context_course::instance($course->id);
        access::require($context, 'approve');
        if (!access::canrepair($context)) {
            throw new moodle_exception('repairdisabled', access::COMPONENT);
        }
        $courseid = (int)$course->id;
        return ledger::locked($courseid, static function () use ($DB, $course, $courseid, $previewid, $revert): int {
            $preview = ledger::get($courseid, $previewid, 'preview');
            $appliedid = ledger::decision($courseid, $previewid, 'apply');
            $revertedid = ledger::decision($courseid, $previewid, 'revert');
            if ($revert ? (!$appliedid || $revertedid) : ($appliedid || (int)$preview->timecreated <= time() - DAYSECS)) {
                throw new moodle_exception('stale', access::COMPONENT);
            }
            $before = $preview->payload['before'];
            $after = $preview->payload['after'];
            if ($revert) {
                $applied = ledger::get($courseid, $appliedid, 'apply');
                [$before, $after] = [$applied->payload['after'], $before];
            }
            $live = self::state($course, $before['item']);
            if ($before !== $live) {
                throw new moodle_exception('stale', access::COMPONENT);
            }
            $tx = $DB->start_delegated_transaction();
            try {
                $outcome = grade_update(
                    access::COMPONENT,
                    $courseid,
                    'mod',
                    $live['module'],
                    $live['instance'],
                    $live['number'],
                    null,
                    ['hidden' => $after['hidden']]
                );
                $persisted = self::state($course, $live['item']);
                if (
                    $outcome !== GRADE_UPDATE_OK ||
                    $persisted !== array_replace($after, ['modified' => $persisted['modified']])
                ) {
                    throw new moodle_exception('repairfailed', access::COMPONENT);
                }
                $id = ledger::append(
                    $courseid,
                    $revert ? 'revert' : 'apply',
                    ['before' => $live, 'after' => $persisted],
                    $previewid
                );
                $tx->allow_commit();
                return $id;
            } catch (\Throwable $failure) {
                $tx->rollback($failure);
            }
        });
    }

    /**
     * Check grade-item attributes on which an assisted visibility change relies.
     *
     * @param grade_item $item Grade item
     * @return bool
     */
    public static function eligible(grade_item $item): bool {
        return $item->itemtype === 'mod' && $item->itemmodule && $item->itemnumber !== null &&
            !$item->locked && !$item->calculation && (int)$item->gradetype === GRADE_TYPE_VALUE &&
            (float)$item->grademax > (float)$item->grademin;
    }

    /**
     * Return an eligible existing item and the exact state guarded by the approval.
     *
     * @param stdClass $course Course record
     * @param int $itemid Grade item ID
     * @return array
     */
    private static function state(stdClass $course, int $itemid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $item = grade_item::fetch(['id' => $itemid, 'courseid' => $course->id]);
        if (!$item || !self::eligible($item)) {
            throw new moodle_exception('notrepairable', access::COMPONENT);
        }
        $related = grade_item::fetch_all([
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => $item->itemmodule,
            'iteminstance' => $item->iteminstance, 'itemnumber' => $item->itemnumber,
        ]) ?: [];
        $cm = get_fast_modinfo($course)->get_instances_of($item->itemmodule)[$item->iteminstance] ?? null;
        if (
            count($related) !== 1 || !$cm || $cm->deletioninprogress || $item->is_locked() ||
            !$item->categoryid
        ) {
            throw new moodle_exception('notrepairable', access::COMPONENT);
        }
        if (!\grade_category::fetch(['id' => $item->categoryid, 'courseid' => $course->id])) {
            throw new moodle_exception('notrepairable', access::COMPONENT);
        }
        return [
            'item' => $itemid,
            'module' => $item->itemmodule,
            'instance' => (int)$item->iteminstance,
            'number' => (int)$item->itemnumber,
            'activity' => (int)$cm->id,
            'visible' => (int)$cm->visible,
            'category' => (int)$item->categoryid,
            'hidden' => (int)$item->hidden,
            'modified' => (int)$item->timemodified,
            'schema' => hash('sha256', json_encode([
                $item->itemname, $item->idnumber, $item->locked, $item->locktime, $item->outcomeid, $item->gradetype,
                $item->grademin, $item->grademax, $item->scaleid, $item->aggregationcoef,
                $item->aggregationcoef2, $item->weightoverride, $item->multfactor, $item->plusfactor,
            ], JSON_THROW_ON_ERROR)),
        ];
    }
}
