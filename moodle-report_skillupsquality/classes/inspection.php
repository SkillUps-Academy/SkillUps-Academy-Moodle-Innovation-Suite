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
 * Provider for bounded, course-owned configuration evidence.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inspection {
    /** Editor prefix bound in characters, including the truncation marker character. */
    public const EDITOR_LIMIT = 8193;
    /** @var \cm_info[] Modules included in the structural snapshot. */
    public readonly array $modules;
    /** @var \course_modinfo Modinfo used for sections. */
    public readonly \course_modinfo $modinfo;
    /** @var array Cached source records by native module type. */
    private array $instances = [];
    /** @var array Fingerprints for partially inspected link targets. */
    public array $unverified = [];
    /** @var \stdClass Audited course configuration. */
    public readonly \stdClass $course;

    /**
     * Build a read-only inspection for an already-authorized course.
     *
     * @param \stdClass $course Course configuration
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
        $this->modinfo = get_fast_modinfo($course, -1);
        $this->modules = array_filter(
            $this->modinfo->get_cms(),
            static fn(\cm_info $cm): bool => !$cm->deletioninprogress,
        );
    }

    /**
     * Retrieve only reviewed fields from supported module records.
     */
    public function records(string $type): array {
        global $DB;
        $fields = match ($type) {
            'assign' => 'allowsubmissionsfromdate,duedate,cutoffdate',
            'quiz' => 'timeopen,timeclose,grade,sumgrades',
            'page' => $DB->sql_substr('content', 1, self::EDITOR_LIMIT) . ' AS content,contentformat',
            'url' => $DB->sql_substr('externalurl', 1, self::EDITOR_LIMIT) . ' AS externalurl',
            'resource', 'folder', 'label' => '',
            default => throw new \coding_exception('Unsupported audit adapter'),
        };
        $fields = 'id,' . $DB->sql_substr('intro', 1, self::EDITOR_LIMIT) . ' AS intro' . ($fields ? ',' . $fields : '');
        if (!isset($this->instances[$type])) {
            $modules = array_filter($this->modules, static fn($cm) => $cm->modname === $type);
            $this->instances[$type] = [];
            foreach (array_chunk(array_map(static fn($cm) => $cm->instance, $modules), 300) as $ids) {
                [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'instance');
                $this->instances[$type] += $DB->get_records_select(
                    $type,
                    "course = :auditcourse AND id $in",
                    ['auditcourse' => $this->course->id] + $params,
                    '',
                    $fields,
                );
            }
        }
        return $this->instances[$type];
    }

    /**
     * Return normalized evidence without names, URLs, content or student data.
     */
    public function evidence(string $type, int $id, array $data = []): array {
        return ['type' => $type, 'id' => $id, 'values' => $data];
    }

    /**
     * Identify the same rule and target across audit revisions.
     */
    public static function fingerprint(string $rule, string $type, int $id): string {
        return hash('sha256', $rule . '|' . $type . '|' . $id);
    }

    /**
     * Identify displayed content such as text, an image or an embedded player.
     */
    public static function substantive(?string $html): bool {
        $html = $html ?? '';
        if (\core_text::strlen($html) >= self::EDITOR_LIMIT) {
            return true;
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\xc2\xa0", ' ', $text)) !== ''
            || (bool)preg_match('/<(?:img|video|audio|iframe|object|embed|math)\b/i', $html);
    }

    /**
     * Detect syntax and locally verifiable embedded file issues without network access.
     */
    public static function embedded(string $html, int $contextid, string $component, string $area): array {
        $totals = ['badlinks' => 0, 'missingfiles' => 0];
        // Ignore oversized editors instead of parsing a truncated attribute.
        if (!str_contains($html, '<') || \core_text::strlen($html) >= self::EDITOR_LIMIT) {
            return $totals;
        }
        $dom = new \DOMDocument();
        if (!$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $totals;
        }
        $storage = get_file_storage();
        $seen = [];
        foreach ((new \DOMXPath($dom))->query('//@href | //@src') as $attribute) {
            $value = trim($attribute->nodeValue);
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            if (str_starts_with($value, '@@PLUGINFILE@@/')) {
                $path = explode('/', explode('?', explode('#', substr($value, 15), 2)[0], 2)[0]);
                $path = array_map('rawurldecode', $path);
                foreach ($path as $segment) {
                    if ($segment === '' || $segment === '.' || $segment === '..' || strpbrk($segment, "/\\\x00") !== false) {
                        continue 2;
                    }
                }
                $name = array_pop($path);
                $folder = $path ? '/' . implode('/', $path) . '/' : '/';
                if (!$storage->get_file($contextid, $component, $area, 0, $folder, $name)) {
                    $totals['missingfiles']++;
                }
            } else if ($value !== '' && self::malformed($value)) {
                $totals['badlinks']++;
            }
        }
        return $totals;
    }

    /**
     * Flag malformed HTTP targets and unsafe executable URI schemes.
     */
    public static function malformed(string $target): bool {
        $parts = parse_url($target);
        if ($parts === false || preg_match('/[\x00-\x1f\x7f]/', $target)) {
            return true;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (in_array($scheme, ['javascript', 'vbscript'], true)) {
            return true;
        }
        return (in_array($scheme, ['http', 'https'], true) || str_starts_with($target, '//'))
            && (empty($parts['host']) || (bool)preg_match('/[\s\\\\]/', $parts['host']));
    }
}
