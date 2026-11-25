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

namespace local_skillupsrepository;

/**
 * Search authorized version metadata using paginated Moodle DML.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /**
     * List Moodle categories authorized by the appropriate library capability.
     *
     * @param string $action Capability suffix
     * @return array
     */
    public static function categories(string $action): array {
        if (!isloggedin() || isguestuser()) {
            return [];
        }
        return \core_course_category::make_categories_list(access::CAP . $action);
    }

    /**
     * Return a page of searchable revisions with ACL and version resolution in SQL.
     *
     * @param array $filter Catalogue filters
     * @param int $page Zero-based page
     * @return array Records and total
     */
    public static function search(array $filter, int $page): array {
        global $DB, $USER;
        $asset = store::ASSET;
        $revision = store::REVISION;
        $params = [];
        $policy = [];
        $review = !empty($filter['moderation']);
        $allowed = array_keys(self::categories('view'));
        $moderators = array_keys(self::categories('moderate'));
        if (isloggedin() && !isguestuser()) {
            $params['owner'] = $USER->id;
            $policy[] = 'a.ownerid = :owner';
        }
        if ($moderators) {
            [$sql, $bindings] = $DB->get_in_or_equal($moderators, SQL_PARAMS_NAMED, 'modcat');
            $policy[] = "a.categoryid $sql";
            $params += $bindings;
        }
        if (!$review) {
            if (access::public_enabled()) {
                $policy[] = "(a.audience = 'public' AND c.visible = 1 AND a.archived = 0 AND v.state = 'approved')";
            }
            if ($allowed) {
                [$sql, $bindings] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'reader');
                $policy[] = "(a.categoryid $sql AND a.audience <> 'private' AND a.archived = 0 AND v.state = 'approved')";
                $params += $bindings;
            }
        }
        if (!$policy) {
            return [[], 0];
        }
        $current = $review ? 'a.currentid' : 'CASE WHEN a.publishedid > 0 THEN a.publishedid ELSE a.currentid END';
        $from = "FROM {{$asset}} a JOIN {{$revision}} v ON v.id = $current JOIN {course_categories} c ON c.id = a.categoryid";
        $conditions = ['(' . implode(' OR ', $policy) . ')'];
        if ($review && !empty($filter['state'])) {
            $conditions[] = 'v.state = :state';
            $params['state'] = $filter['state'];
        }
        if (!$review) {
            $conditions[] = 'a.archived = 0';
        }
        if (!empty($filter['categoryid'])) {
            $conditions[] = 'a.categoryid = :category';
            $params['category'] = $filter['categoryid'];
        }
        if (!empty($filter['kind'])) {
            $conditions[] = 'v.kind = :kind';
            $params['kind'] = $filter['kind'];
        }
        foreach (['title', 'description', 'tags', 'credit', 'collection', 'area', 'program', 'level', 'keywords'] as $field) {
            if (!empty($filter[$field])) {
                $conditions[] = $DB->sql_like('v.' . $field, ':' . $field, false);
                $params[$field] = '%' . $DB->sql_like_escape($filter[$field]) . '%';
            }
        }
        if (!empty($filter['extension'])) {
            $conditions[] = $DB->sql_like('v.filename', ':extension', false);
            $params['extension'] = '%.' . $DB->sql_like_escape(ltrim($filter['extension'], '.'));
        }
        $words = preg_split('/\s+/u', trim($filter['q'] ?? ''), 9, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $i => $word) {
            $matches = [];
            foreach (['title', 'description', 'tags', 'credit', 'keywords'] as $field) {
                $key = $field . $i;
                $matches[] = $DB->sql_like('v.' . $field, ':' . $key, false);
                $params[$key] = '%' . $DB->sql_like_escape($word) . '%';
            }
            $conditions[] = '(' . implode(' OR ', $matches) . ')';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $count = $DB->count_records_sql("SELECT COUNT(1) $from $where", $params);
        $order = 'v.created DESC, a.id DESC';
        if (($filter['sort'] ?? '') === 'popular') {
            $order = 'a.uses DESC, ' . $order;
        } else if (($filter['sort'] ?? '') === 'relevance' && $words) {
            $params['sorttitle'] = '%' . $DB->sql_like_escape($words[0]) . '%';
            $params['sorttags'] = $params['sorttitle'];
            $title = $DB->sql_like('v.title', ':sorttitle', false);
            $tags = $DB->sql_like('v.tags', ':sorttags', false);
            $order = "(CASE WHEN $title THEN 4 ELSE 0 END + CASE WHEN $tags THEN 2 ELSE 0 END) DESC, " . $order;
        }
        $select = 'a.id, a.audience, a.archived, a.uses, v.id AS revisionid, v.title, v.description, v.credit, v.kind,
                   v.collection, v.tags, v.state, v.license, v.created, v.published, c.name AS categoryname';
        return [$DB->get_records_sql("SELECT $select $from $where ORDER BY $order", $params, $page * 24, 24), $count];
    }
}
