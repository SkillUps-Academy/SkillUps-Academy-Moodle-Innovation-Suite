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

namespace local_skillupsquestionstudio\bank;

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\assessment;
use local_skillupsquestionstudio\studio;

/**
 * Publish approved revisions through the same XML importer used by Moodle's question bank.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publisher {
    /**
     * Moodle module-context categories this author may publish into.
     */
    public static function options(int $courseid): array {
        global $DB;
        $options = [];
        foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
            if (
                !$cm->uservisible || !in_array($cm->modname, ['qbank', 'quiz'], true) ||
                    !has_capability('moodle/question:add', $cm->context)
            ) {
                continue;
            }
            foreach (
                $DB->get_records_select(
                    'question_categories',
                    'contextid = ? AND parent <> 0',
                    [$cm->context->id],
                    'sortorder,id',
                    'id,name'
                ) as $category
            ) {
                $options[$category->id] = $cm->name . ' · ' . $category->name;
            }
        }
        return $options;
    }

    /**
     * Resolve the module from the category and reject cross-course IDs.
     */
    public static function category(int $courseid, int $categoryid): array {
        global $DB;
        $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);
        $mods = get_fast_modinfo($courseid)->get_cms();
        $cm = $context->contextlevel === CONTEXT_MODULE ? ($mods[$context->instanceid] ?? null) : null;
        if (!$cm || !$cm->uservisible || !$category->parent || !in_array($cm->modname, ['qbank', 'quiz'], true)) {
            access::fail('invalidcategory');
        }
        require_login(get_course($courseid), false, $cm);
        require_capability('moodle/question:add', $context);
        return [$category, $context];
    }

    /**
     * Lock the project and commit Moodle writes with receipts so retries are idempotent.
     */
    public static function publish(\stdClass $project, int $variantid, int $categoryid, int $userid): int {
        global $DB, $CFG, $USER;
        if ((int)$USER->id !== $userid) {
            access::fail('accessdenied');
        }
        access::actor($userid, $project->courseid, 'publish');
        [$category, $context] = self::category($project->courseid, $categoryid);
        $lock = access::lock($project->id);
        $path = null;
        try {
            $variant = assessment::find($project->id, $variantid);
            $records = assessment::questions($project, $variant);
            $pending = [];
            foreach ($records as $revisionid => $data) {
                if (!$DB->record_exists(access::C . '_pub', ['revisionid' => $revisionid, 'categoryid' => $categoryid])) {
                    $pending[$revisionid] = $data;
                }
            }
            if (!$pending) {
                return 0;
            }
            require_once($CFG->libdir . '/questionlib.php');
            require_once($CFG->dirroot . '/question/format.php');
            require_once($CFG->dirroot . '/question/format/xml/format.php');
            $path = make_request_directory() . '/questions.xml';
            if (file_put_contents($path, xml::generate($pending)) === false) {
                access::fail('importfailed');
            }
            $format = new \qformat_xml();
            $format->setCategory($category);
            $format->setContexts([$context]);
            $format->setCourse(get_course($project->courseid));
            $format->setFilename($path);
            $format->setRealfilename('questions.xml');
            $format->setMatchgrades('error');
            $format->setCatfromfile(false);
            $format->setContextfromfile(false);
            $format->setStoponerror(true);
            $format->displayprogress = false;
            $transaction = $DB->start_delegated_transaction();
            ob_start();
            try {
                if (
                    !$format->importpreprocess() || !$format->importprocess() || !$format->importpostprocess() ||
                        count($format->questionids) !== count($pending)
                ) {
                    access::fail('importfailed');
                }
                foreach (array_keys($pending) as $index => $revisionid) {
                    $DB->insert_record(access::C . '_pub', (object)[
                        'revisionid' => $revisionid, 'categoryid' => $categoryid,
                        'questionid' => $format->questionids[$index], 'userid' => $userid, 'timecreated' => time(),
                    ]);
                }
                \core\event\questions_imported::create([
                    'contextid' => $context->id, 'other' => ['format' => 'xml', 'categoryid' => $categoryid],
                ])->trigger();
                studio::audit($project->id, 'published', $variantid);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            } finally {
                ob_end_clean();
            }
            return count($pending);
        } finally {
            if ($path && is_file($path)) {
                unlink($path);
            }
            $lock->release();
        }
    }
}
