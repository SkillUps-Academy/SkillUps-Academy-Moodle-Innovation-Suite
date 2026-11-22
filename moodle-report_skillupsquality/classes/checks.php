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

namespace report_skillupsquality;

/**
 * Read-only checks for course structure, resources and assessment setup.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checks {
    /**
     * No non-deleting activity or readable label is present.
     */
    public static function emptycourse(inspection $view): iterable {
        foreach ($view->modules as $cm) {
            if ($cm->modname !== 'label' || inspection::substantive($view->records('label')[$cm->instance]->intro ?? '')) {
                return;
            }
        }
        yield $view->evidence('course', $view->course->id, ['count' => 0]);
    }

    /**
     * Empty draft text within a supported module can be improved.
     */
    public static function instructions(inspection $view): iterable {
        foreach (['assign', 'quiz'] as $type) {
            foreach ($view->modules as $cm) {
                if ($cm->modname !== $type || !$cm->visible) {
                    continue;
                }
                $record = $view->records($type)[$cm->instance] ?? null;
                if ($record && !inspection::substantive($record->intro)) {
                    yield $view->evidence('module', $cm->id);
                }
            }
        }
    }

    /**
     * Find Moodle pages with no displayed content.
     */
    public static function emptycontent(inspection $view): iterable {
        foreach ($view->modules as $cm) {
            if ($cm->modname === 'page') {
                $record = $view->records('page')[$cm->instance] ?? null;
                if ($record && !inspection::substantive($record->content)) {
                    yield $view->evidence('module', $cm->id);
                }
            }
        }
    }

    /**
     * Inventory intentionally hidden settings separately from confirmed defects.
     */
    public static function hidden(inspection $view): iterable {
        foreach ($view->modules as $cm) {
            if (!$cm->visible) {
                yield $view->evidence('module', $cm->id);
            }
        }
    }

    /**
     * Check date order in assignments and quizzes.
     */
    public static function dates(inspection $view): iterable {
        $pairs = [
            'assign' => [
                ['allowsubmissionsfromdate', 'duedate'], ['allowsubmissionsfromdate', 'cutoffdate'], ['duedate', 'cutoffdate'],
            ],
            'quiz' => [['timeopen', 'timeclose']],
        ];
        foreach ($view->modules as $cm) {
            foreach ($pairs[$cm->modname] ?? [] as [$start, $end]) {
                $record = $view->records($cm->modname)[$cm->instance] ?? null;
                if ($record && $record->$start > 0 && $record->$end > 0 && $record->$start > $record->$end) {
                    yield $view->evidence('module', $cm->id, [$start => (int)$record->$start, $end => (int)$record->$end]);
                    break;
                }
            }
        }
        if ($view->course->enddate && $view->course->startdate > $view->course->enddate) {
            yield $view->evidence('course', $view->course->id, [
                'startdate' => (int)$view->course->startdate, 'enddate' => (int)$view->course->enddate,
            ]);
        }
    }

    /**
     * Identify contentless visible sections when the format uses sections.
     */
    public static function sections(inspection $view): iterable {
        if (!course_get_format($view->course)->uses_sections()) {
            return;
        }
        $occupied = [];
        foreach ($view->modules as $cm) {
            if ($cm->visible) {
                $occupied[$cm->sectionnum] = true;
            }
        }
        foreach ($view->modinfo->get_section_info_all() as $section) {
            if (
                $section->section > 0 && $section->visible && empty($occupied[$section->section])
                    && !inspection::substantive($section->summary)
            ) {
                yield $view->evidence('section', $section->id, ['number' => (int)$section->section]);
            }
        }
    }

    /**
     * Report missing course criteria when completion tracking is enabled.
     */
    public static function completion(inspection $view): iterable {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($view->course);
        if (!$completion->is_enabled()) {
            return;
        }
        $tracked = 0;
        foreach ($view->modules as $cm) {
            $tracked += $completion->is_enabled($cm) ? 1 : 0;
        }
        if (!$completion->has_criteria()) {
            yield $view->evidence('course', $view->course->id, ['tracked' => $tracked]);
        }
    }

    /**
     * Validate grade ranges using grade item definitions only.
     */
    public static function grade(inspection $view): iterable {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        foreach (\grade_item::fetch_all(['courseid' => $view->course->id]) ?: [] as $item) {
            if ((int)$item->gradetype === GRADE_TYPE_VALUE && (float)$item->grademin >= (float)$item->grademax) {
                yield $view->evidence('gradeitem', $item->id, ['min' => (float)$item->grademin, 'max' => (float)$item->grademax]);
            }
        }
    }

    /**
     * Flag grading configured for a quiz with no positive total for questions.
     */
    public static function quizgrade(inspection $view): iterable {
        foreach ($view->modules as $cm) {
            if ($cm->modname === 'quiz') {
                $record = $view->records('quiz')[$cm->instance] ?? null;
                if ($record && (float)$record->grade > 0 && (float)$record->sumgrades <= 0) {
                    yield $view->evidence('module', $cm->id, ['max' => (float)$record->grade, 'questionsum' => 0]);
                }
            }
        }
    }

    /**
     * Check readiness of active assignment rubrics without creating a grading area.
     */
    public static function rubric(inspection $view): iterable {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        foreach ($view->modules as $cm) {
            if ($cm->modname !== 'assign') {
                continue;
            }
            $manager = get_grading_manager($cm->context, 'mod_assign', 'submissions');
            if ($manager->get_active_method() === 'rubric' && !$manager->get_controller('rubric')->is_form_available()) {
                yield $view->evidence('module', $cm->id);
            }
        }
    }

    /**
     * Check storage metadata for core Folder and File resources.
     */
    public static function resources(inspection $view): iterable {
        $storage = get_file_storage();
        foreach ($view->modules as $cm) {
            if (!in_array($cm->modname, ['resource', 'folder'], true)) {
                continue;
            }
            $files = $storage->get_area_files($cm->context->id, 'mod_' . $cm->modname, 'content', 0, 'id', false);
            $empty = 0;
            foreach ($files as $file) {
                if (!$file->is_external_file() && $file->get_filesize() === 0) {
                    $empty++;
                }
            }
            if (!$files || $empty) {
                yield $view->evidence('module', $cm->id, ['attachments' => count($files), 'emptyfiles' => $empty]);
            }
        }
    }

    /**
     * Report editor links and attachments with locally verifiable problems.
     */
    public static function links(inspection $view): iterable {
        $types = ['assign', 'quiz', 'page', 'label', 'url', 'resource', 'folder'];
        foreach ($view->modules as $cm) {
            if (!in_array($cm->modname, $types, true)) {
                continue;
            }
            $record = $view->records($cm->modname)[$cm->instance] ?? null;
            if (!$record) {
                continue;
            }
            $context = $cm->context->id;
            $component = 'mod_' . $cm->modname;
            $texts = [$record->intro ?? '', $record->content ?? '', $record->externalurl ?? ''];
            foreach ($texts as $text) {
                if (\core_text::strlen($text) >= inspection::EDITOR_LIMIT) {
                    $view->unverified[inspection::fingerprint('links', 'module', $cm->id)] = true;
                    break;
                }
            }
            $counts = inspection::embedded($record->intro ?? '', $context, $component, 'intro');
            if ($cm->modname === 'page' && (string)$record->contentformat === FORMAT_HTML) {
                foreach (inspection::embedded($record->content, $context, $component, 'content') as $key => $amount) {
                    $counts[$key] += $amount;
                }
            }
            if (
                $cm->modname === 'url' && \core_text::strlen($record->externalurl) < inspection::EDITOR_LIMIT
                    && (trim($record->externalurl) === '' || inspection::malformed($record->externalurl))
            ) {
                $counts['badlinks']++;
            }
            if (array_sum($counts)) {
                yield $view->evidence('module', $cm->id, $counts);
            }
        }
    }
}
