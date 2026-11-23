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

/**
 * Deterministic checks on grade categories, items, calculations and modules.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules {
    /** @var array Rule ID to severity and treatment. */
    public const CATALOGUE = [
        'root' => ['error', 'technical'],
        'parent' => ['error', 'technical'],
        'categorycycle' => ['error', 'technical'],
        'total' => ['error', 'technical'],
        'empty' => ['info', 'review'],
        'aggregation' => ['error', 'technical'],
        'droprules' => ['warning', 'review'],
        'weight' => ['warning', 'review'],
        'weightszero' => ['warning', 'review'],
        'itemcategory' => ['error', 'technical'],
        'rootactivity' => ['info', 'review'],
        'range' => ['error', 'technical'],
        'scale' => ['error', 'technical'],
        'duplicate' => ['error', 'technical'],
        'unlinked' => ['error', 'technical'],
        'missingactivitygrade' => ['info', 'review'],
        'hidden' => ['info', 'review'],
        'hiddenmismatch' => ['warning', 'repair'],
        'hiddenreview' => ['warning', 'review'],
        'reference' => ['error', 'technical'],
        'formula' => ['warning', 'review'],
        'formulalimit' => ['info', 'review'],
        'dependency' => ['error', 'technical'],
        'pending' => ['info', 'review'],
    ];

    /**
     * Build a report from the course configuration only.
     *
     * @param snapshot $book Course snapshot
     * @return array
     */
    public static function diagnose(snapshot $book): array {
        $findings = [];
        $report = static function (string $rule, string $target, int $id, array $evidence = []) use (&$findings): void {
            $findings[] = ['rule' => $rule, 'target' => $target, 'id' => $id, 'evidence' => $evidence];
        };
        self::categories($book, $report);
        self::items($book, $report);
        $graph = self::calculations($book, $report);
        return [
            'counts' => [
                'categories' => count($book->categories),
                'items' => count($book->items),
                'activities' => count($book->activities),
                'findings' => count($findings),
            ],
            'findings' => $findings,
            'relationships' => self::relationships($book, $graph),
        ];
    }

    /**
     * Audit category hierarchy and effective aggregation configuration.
     *
     * @param snapshot $book Course snapshot
     * @param callable $report Collector
     */
    private static function categories(snapshot $book, callable $report): void {
        global $CFG;
        require_once($CFG->dirroot . '/grade/lib.php');

        $roots = 0;
        $graph = [];
        $known = \grade_helper::get_aggregation_strings();
        foreach ($book->categories as $id => $category) {
            $id = (int)$id;
            $parent = (int)$category->parent;
            $roots += (int)($parent === 0);
            if ($parent && !isset($book->categories[$parent])) {
                $report('parent', 'category', $id, ['category' => $parent]);
            }
            $graph[$id] = $parent && isset($book->categories[$parent]) ? [$parent] : [];
            $totals = $book->totals[$id] ?? [];
            $expectedtype = $parent ? 'category' : 'course';
            if (count($totals) !== 1 || $book->items[$totals[0]]->itemtype !== $expectedtype) {
                $report('total', 'category', $id, ['count' => count($totals)]);
            }
            $children = $book->children[$id] ?? [];
            if (!$children && $parent) {
                $report('empty', 'category', $id);
            }
            $effective = clone $category;
            $effective->apply_forced_settings();
            $method = (int)$effective->aggregation;
            if (!isset($known[$method]) || !isset($known[(int)$category->aggregation])) {
                $report('aggregation', 'category', $id, ['aggregation' => (int)$category->aggregation, 'effective' => $method]);
            }
            if ($effective->droplow && $effective->keephigh) {
                $report('droprules', 'category', $id, [
                    'droplow' => (int)$effective->droplow, 'keephigh' => (int)$effective->keephigh,
                ]);
            }
            self::weights($book, $id, $method, $children, $report);
        }
        if ($roots !== 1 && ($book->categories || $book->items)) {
            $report('root', 'course', 0, ['count' => $roots]);
        }
        foreach (self::blocked($graph) as $id) {
            $report('categorycycle', 'category', $id);
        }
    }

    /**
     * Review algebraic/override weights for weighted mean and Natural aggregation.
     *
     * @param snapshot $book Course snapshot
     * @param int $id Category ID
     * @param int $method Effective aggregation
     * @param array $children Grade item IDs
     * @param callable $report Collector
     */
    private static function weights(snapshot $book, int $id, int $method, array $children, callable $report): void {
        if (!in_array($method, [GRADE_AGGREGATE_SUM, GRADE_AGGREGATE_WEIGHTED_MEAN], true)) {
            return;
        }
        $checkable = 0;
        $sum = 0.0;
        foreach (self::gradable($book, $id, $children) as $itemid) {
            $item = $book->items[$itemid];
            $natural = $method === GRADE_AGGREGATE_SUM;
            if ($natural && (!$item->weightoverride || (float)$item->aggregationcoef > 0)) {
                continue;
            }
            $weight = (float)($natural ? $item->aggregationcoef2 : $item->aggregationcoef);
            if ($weight < 0 || ($natural && $weight > 1)) {
                $report('weight', 'item', $itemid, ['category' => $id, 'weight' => (string)$weight, 'aggregation' => $method]);
            }
            $checkable++;
            $sum += $weight;
        }
        if ($method === GRADE_AGGREGATE_WEIGHTED_MEAN && $checkable && $sum <= 0) {
            $report('weightszero', 'category', $id, ['count' => $checkable, 'weight' => (string)$sum]);
        }
    }

    /**
     * Inspect items and correlate them with available course modules.
     *
     * @param snapshot $book Course snapshot
     * @param callable $report Collector
     */
    private static function items(snapshot $book, callable $report): void {
        $scales = [];
        $identities = [];
        foreach ($book->links as $key => $ids) {
            foreach ($ids as $id) {
                $identities[$key][(int)$book->items[$id]->itemnumber][] = $id;
            }
        }
        foreach ($book->items as $id => $item) {
            $id = (int)$id;
            $parent = $book->categories[$item->categoryid] ?? null;
            if (!in_array($item->itemtype, ['course', 'category'], true) && !$parent) {
                $report('itemcategory', 'item', $id, ['category' => (int)$item->categoryid]);
            }
            if ((int)$item->gradetype === GRADE_TYPE_VALUE && (float)$item->grademax <= (float)$item->grademin) {
                $report('range', 'item', $id, ['min' => $item->grademin, 'max' => $item->grademax]);
            }
            if ((int)$item->gradetype === GRADE_TYPE_SCALE) {
                $scaleid = (int)$item->scaleid;
                $scales[$scaleid] ??= $scaleid ? (\grade_scale::fetch(['id' => $scaleid]) ?: false) : false;
                $scale = $scales[$scaleid];
                if (!$scale || ($scale->courseid && $scale->courseid != $item->courseid)) {
                    $report('scale', 'item', $id);
                }
            }
            if ((int)$item->hidden === 1 || (int)$item->hidden > time()) {
                $report('hidden', 'item', $id, ['hidden' => (int)$item->hidden]);
            }
            if ($item->needsupdate && ($item->calculation || in_array($item->itemtype, ['course', 'category'], true))) {
                $report('pending', 'item', $id);
            }
            if ($item->itemtype !== 'mod') {
                continue;
            }
            $key = snapshot::activitykey((string)$item->itemmodule, (int)$item->iteminstance);
            $cm = $book->activities[$key] ?? null;
            if (!$cm || $cm->deletioninprogress) {
                $report('unlinked', 'item', $id);
                continue;
            }
            if ($parent && !$parent->parent) {
                $report('rootactivity', 'item', $id, ['activity' => (int)$cm->id]);
            }
            if (!$cm->visible && !(int)$item->hidden && (int)$item->gradetype !== GRADE_TYPE_NONE) {
                $same = count($identities[$key][(int)$item->itemnumber]);
                $rule = $parent && $same === 1 && repair::eligible($item) ? 'hiddenmismatch' : 'hiddenreview';
                $report($rule, 'item', $id, ['activity' => (int)$cm->id]);
            }
        }
        foreach ($identities as $buckets) {
            foreach ($buckets as $same) {
                if (count($same) > 1) {
                    $report('duplicate', 'item', $same[0], ['count' => count($same), 'related' => $same]);
                }
            }
        }
        foreach ($book->activities as $key => $cm) {
            if (empty($book->links[$key]) && !$cm->deletioninprogress) {
                $report('missingactivitygrade', 'activity', (int)$cm->id);
            }
        }
    }

    /**
     * Identify unresolved references, static formula errors and blocked dependency chains.
     *
     * @param snapshot $book Course snapshot
     * @param callable $report Collector
     * @return array Grade dependency graph
     */
    private static function calculations(snapshot $book, callable $report): array {
        global $CFG;
        require_once($CFG->libdir . '/mathslib.php');
        $graph = [];
        foreach ($book->items as $id => $item) {
            $id = (int)$id;
            if (!$item->calculation) {
                $categoryid = (int)$item->iteminstance;
                $graph[$id] = in_array($item->itemtype, ['course', 'category'], true) && isset($book->categories[$categoryid])
                    ? self::gradable($book, $categoryid, $book->children[$categoryid] ?? [])
                    : [];
                continue;
            }
            $formula = (string)$item->calculation;
            if (strlen($formula) > 2048) {
                $report('formulalimit', 'item', $id);
                $graph[$id] = [];
                continue;
            }
            $formula = preg_replace_callback('/\[\[([^\[\]]+)\]\]/', static function (array $match) use ($book): string {
                $ids = $book->idnumbers[$match[1]] ?? [];
                return count($ids) === 1 ? '##gi' . $ids[0] . '##' : $match[0];
            }, $formula);
            preg_match_all('/##gi(\d+)##/', $formula, $refs);
            $ids = array_values(array_unique(array_map('intval', $refs[1])));
            $resolved = array_values(array_filter($ids, static fn(int $ref): bool => isset($book->items[$ref])));
            $graph[$id] = $resolved;
            $unresolved = substr_count($formula, '[[') + count($ids) - count($resolved);
            if ($unresolved) {
                $report('reference', 'item', $id, ['count' => $unresolved]);
            } else if (!in_array($id, $ids, true) && !self::validformula($book, $formula, $ids)) {
                $report('formula', 'item', $id);
            }
        }
        foreach (self::blocked($graph) as $id) {
            $report('dependency', 'item', $id, ['related' => array_slice($graph[$id], 0, 10)]);
        }
        return $graph;
    }

    /**
     * Validate stored syntax with the Moodle math evaluator and structural test values.
     *
     * @param snapshot $book Course snapshot
     * @param string $formula Normalized calculation
     * @param array $ids Resolved inputs
     * @return bool
     */
    private static function validformula(snapshot $book, string $formula, array $ids): bool {
        if (!str_starts_with($formula, '=')) {
            return false;
        }
        $params = [];
        foreach ($ids as $id) {
            $params["gi$id"] = $book->items[$id]->grademax;
        }
        $native = new \calc_formula(preg_replace('/##(gi\d+)##/', '$1', $formula), $params);
        return $native->evaluate() !== false;
    }

    /**
     * Persist the identifiers and dependencies of course-local records.
     *
     * @param snapshot $book Course snapshot
     * @param array $graph Grade item dependencies
     * @return array
     */
    private static function relationships(snapshot $book, array $graph): array {
        $result = [];
        foreach ($book->items as $id => $item) {
            $key = snapshot::activitykey((string)$item->itemmodule, (int)$item->iteminstance);
            $categoryid = in_array($item->itemtype, ['course', 'category'], true) ? $item->iteminstance : $item->categoryid;
            $result[] = [
                'item' => (int)$id,
                'category' => isset($book->categories[$categoryid]) ? (int)$categoryid : 0,
                'activity' => $item->itemtype === 'mod' ? (int)($book->activities[$key]->id ?? 0) : 0,
                'dependencies' => $graph[$id],
            ];
        }
        return $result;
    }

    /**
     * Apply core scale and outcome participation settings to immediate category children.
     *
     * @param snapshot $book Course snapshot
     * @param int $categoryid Category ID
     * @param array $children Grade item IDs
     * @return array
     */
    private static function gradable(snapshot $book, int $categoryid, array $children): array {
        global $CFG;
        $category = clone $book->categories[$categoryid];
        $category->apply_forced_settings();
        return array_values(array_filter($children, static function (int $id) use ($book, $category, $CFG): bool {
            $item = $book->items[$id];
            $numeric = (int)$item->gradetype === GRADE_TYPE_VALUE;
            $scale = !empty($CFG->grade_includescalesinaggregation) && (int)$item->gradetype === GRADE_TYPE_SCALE;
            return ($numeric || $scale) && (empty($CFG->enableoutcomes) || $category->aggregateoutcomes || !$item->outcomeid);
        }));
    }

    /**
     * Return nodes in a cycle or dependent on one, using a linear iterative topological pass.
     *
     * @param array $graph Item to prerequisite IDs
     * @return array
     */
    private static function blocked(array $graph): array {
        $remaining = [];
        $dependents = [];
        $ready = [];
        foreach ($graph as $id => $inputs) {
            $remaining[$id] = count($inputs);
            if (!$inputs) {
                $ready[] = $id;
            }
            foreach ($inputs as $input) {
                $dependents[$input][] = $id;
            }
        }
        while ($ready) {
            $id = array_pop($ready);
            unset($remaining[$id]);
            foreach ($dependents[$id] ?? [] as $dependent) {
                if (--$remaining[$dependent] === 0) {
                    $ready[] = $dependent;
                }
            }
        }
        return array_keys($remaining);
    }
}
