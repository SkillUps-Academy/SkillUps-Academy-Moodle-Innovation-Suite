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

use grade_category;
use grade_item;
use moodle_exception;
use stdClass;

/**
 * Course-wide configuration obtained from Gradebook without regrading or normalization writes.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class snapshot {
    /** @var array Grade items keyed by id. */
    public readonly array $items;

    /** @var array Grade categories keyed by id. */
    public readonly array $categories;

    /** @var array Grading-capable activities and grade-linked activities keyed by module and instance. */
    public readonly array $activities;

    /** @var array Grade item IDs keyed by activity. */
    public readonly array $links;

    /** @var array Grade item IDs grouped by aggregate category. */
    public readonly array $children;

    /** @var array Grade total IDs grouped by category. */
    public readonly array $totals;

    /** @var array Grade item IDs grouped by course idnumber. */
    public readonly array $idnumbers;

    /**
     * Read a bounded, course-local gradebook snapshot.
     *
     * @param stdClass $course Course record
     */
    public function __construct(stdClass $course) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $limit = max(1000, min(10000, (int)(get_config(access::COMPONENT, 'scanlimit') ?: 5000)));
        $conditions = ['courseid' => $course->id];
        if ($DB->count_records('grade_items', $conditions) + $DB->count_records('grade_categories', $conditions) > $limit) {
            throw new moodle_exception('toolarge', access::COMPONENT, '', $limit);
        }
        $this->items = grade_item::fetch_all($conditions) ?: [];
        $this->categories = grade_category::fetch_all($conditions) ?: [];
        $links = [];
        $children = [];
        $totals = [];
        $idnumbers = [];
        foreach ($this->items as $item) {
            if ($item->itemtype === 'mod') {
                $links[self::activitykey((string)$item->itemmodule, (int)$item->iteminstance)][] = (int)$item->id;
            }
            if ($item->itemtype === 'course' || $item->itemtype === 'category') {
                $totals[(int)$item->iteminstance][] = (int)$item->id;
                $parent = (int)($this->categories[$item->iteminstance]->parent ?? 0);
            } else {
                $parent = (int)$item->categoryid;
            }
            $children[$parent][] = (int)$item->id;
            if ($item->idnumber !== null && $item->idnumber !== '') {
                $idnumbers[$item->idnumber][] = (int)$item->id;
            }
        }
        $activities = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            $key = self::activitykey($cm->modname, (int)$cm->instance);
            if (isset($links[$key]) || plugin_supports('mod', $cm->modname, FEATURE_GRADE_HAS_GRADE, false)) {
                $activities[$key] = $cm;
            }
            if (count($activities) + count($this->items) + count($this->categories) > $limit) {
                throw new moodle_exception('toolarge', access::COMPONENT, '', $limit);
            }
        }
        $this->links = $links;
        $this->activities = $activities;
        $this->children = $children;
        $this->totals = $totals;
        $this->idnumbers = $idnumbers;
    }

    /**
     * Build the identity that relates a Moodle activity to its grade items.
     *
     * @param string $module Component shortname
     * @param int $instance Activity instance id
     * @return string
     */
    public static function activitykey(string $module, int $instance): string {
        return $module . ':' . $instance;
    }
}
