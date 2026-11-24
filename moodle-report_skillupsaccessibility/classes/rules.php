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

namespace report_skillupsaccessibility;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Check bounded HTML fragments with explicit static accessibility rules.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules {
    /** @var string Increment whenever static check behavior changes. */
    public const VERSION = '2026.11.2';

    /** @var array Severity, classification and WCAG 2.2 reference for each rule. */
    public const CATALOG = [
        'imgalt' => ['high', 'confirmed', '1.1.1'],
        'emptylink' => ['high', 'confirmed', '2.4.4'],
        'genericlink' => ['medium', 'review', '2.4.4'],
        'headskip' => ['medium', 'review', '1.3.1 / 2.4.6'],
        'tableheaders' => ['medium', 'review', '1.3.1'],
        'formlabel' => ['high', 'confirmed', '3.3.2 / 4.1.2'],
        'placeholder' => ['medium', 'review', '3.3.2'],
        'frametitle' => ['medium', 'confirmed', '4.1.2'],
        'videocaptions' => ['high', 'review', '1.2.2'],
        'audiotranscript' => ['medium', 'review', '1.2.1'],
        'contrast' => ['high', 'review', '1.4.3'],
        'positivetab' => ['medium', 'review', '2.4.3'],
        'movingmarkup' => ['medium', 'review', '2.2.2'],
        'docxalt' => ['high', 'review', '1.1.1'],
        'docxheading' => ['medium', 'review', '1.3.1 / 2.4.6'],
        'pdfstructure' => ['medium', 'review', '1.3.1 / 1.3.2'],
    ];

    /**
     * Parse HTML locally. Network access and external entity substitution are disabled.
     *
     * @param string $html Authored HTML fragment
     * @return array Asset result and issues
     */
    public static function html(string $html): array {
        if (strlen($html) > 2097152) {
            return self::result(false, 'oversize');
        }
        $dom = new DOMDocument();
        $parsed = $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        if (!$parsed) {
            return self::result(false, 'unreadable');
        }
        $xp = new DOMXPath($dom);
        $issues = [];
        $counts = [];
        $heading = 0;
        $labels = [];
        foreach ($xp->query('//label[@for]') as $label) {
            if (trim($label->textContent) !== '' && $label->getAttribute('for') !== '' && self::visible($label)) {
                $labels[$label->getAttribute('for')] = true;
            }
        }
        $nodes = $xp->query('//body//*');
        if ($nodes->length > 12000) {
            return self::result(false, 'oversize');
        }
        foreach ($nodes as $node) {
            $tag = $node->tagName;
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            $where = $tag . '[' . $counts[$tag] . ']';
            if (!self::visible($node)) {
                continue;
            }
            $aria = self::aria($node);
            switch ($tag) {
                case 'img':
                    $decorative = in_array($node->getAttribute('role'), ['none', 'presentation'], true);
                    if (!$node->hasAttribute('alt') && !$aria && !$decorative) {
                        $issues[] = self::issue('imgalt', $where);
                    }
                    break;
                case 'a':
                    if (trim($node->getAttribute('href')) === '') {
                        break;
                    }
                    $text = self::text($xp, $node);
                    if ($text === '' && !$aria && trim($node->getAttribute('title')) === '') {
                        $issues[] = self::issue('emptylink', $where);
                    } else if (
                        !$aria && preg_match('/^(click here|here|read more|more|aquí|aqui|clic aquí|leer más|más)[.!]?$/iuD', $text)
                    ) {
                        $issues[] = self::issue('genericlink', $where);
                    }
                    break;
                case 'table':
                    if (
                        !in_array($node->getAttribute('role'), ['none', 'presentation'], true) &&
                        $xp->query('./thead/tr|./tbody/tr|./tfoot/tr|./tr', $node)->length > 1 &&
                        $xp->query(
                            './thead/tr/th|./tbody/tr/th|./tfoot/tr/th|./tr/th|.//*[@role="columnheader" or @role="rowheader"]',
                            $node
                        )->length === 0 &&
                        $xp->query('./tbody/tr/td[2]|./tr/td[2]', $node)->length > 0
                    ) {
                        $issues[] = self::issue('tableheaders', $where);
                    }
                    break;
                case 'input':
                case 'textarea':
                case 'select':
                case 'button':
                    $type = strtolower($node->getAttribute('type'));
                    if ($tag === 'input' && in_array($type, ['hidden', 'submit', 'reset', 'button'], true)) {
                        break;
                    }
                    $wrapped = $xp->query('ancestor::label[normalize-space(.)!=""]', $node)->length > 0;
                    $named = $aria || $wrapped || isset($labels[$node->getAttribute('id')]) ||
                        trim($node->getAttribute('title')) !== '' || ($tag === 'button' && self::text($xp, $node) !== '') ||
                        ($type === 'image' && trim($node->getAttribute('alt')) !== '');
                    if (!$named) {
                        $issues[] = self::issue($node->hasAttribute('placeholder') ? 'placeholder' : 'formlabel', $where);
                    }
                    break;
                case 'iframe':
                    if (!$aria && trim($node->getAttribute('title')) === '') {
                        $issues[] = self::issue('frametitle', $where);
                    }
                    break;
                case 'video':
                    if (
                        !$xp->query(
                            './track[translate(@kind,"CAPTIONS","captions")="captions" and normalize-space(@src)!=""]',
                            $node
                        )->length
                    ) {
                        $issues[] = self::issue('videocaptions', $where);
                    }
                    break;
                case 'audio':
                    $container = $node->parentNode;
                    $near = $container instanceof DOMElement ? self::text($xp, $container) : '';
                    if (!preg_match('/\b(transcript|transcription|transcripci[oó]n)\b/iu', $near)) {
                        $issues[] = self::issue('audiotranscript', $where);
                    }
                    break;
                case 'blink':
                case 'marquee':
                    $issues[] = self::issue('movingmarkup', $where);
                    break;
            }
            if (preg_match('/^h([1-6])$/D', $tag, $match)) {
                $next = (int)$match[1];
                if ($heading && $next > $heading + 1) {
                    $issues[] = self::issue('headskip', $where, "$heading → $next");
                }
                $heading = $next;
            }
            if (preg_match('/^[+]?[1-9][0-9]*$/D', trim($node->getAttribute('tabindex')))) {
                $issues[] = self::issue('positivetab', $where);
            }
            $contrast = self::inline_contrast($node, $xp);
            if ($contrast !== null) {
                $issues[] = self::issue('contrast', $where, $contrast);
            }
        }
        return self::result(true, '', $issues);
    }

    /**
     * Collect static accessible text hints without exposing source text in findings.
     *
     * @param DOMXPath $xp Parsed document
     * @param DOMElement $node Container
     * @return string
     */
    private static function text(DOMXPath $xp, DOMElement $node): string {
        $parts = [];
        foreach ($xp->query('.//text()|.//img[@alt]|.//*[@aria-label]', $node) as $child) {
            $element = $child instanceof DOMElement ? $child : $child->parentNode;
            if (
                !$element instanceof DOMElement || !self::visible($element) ||
                in_array($element->tagName, ['script', 'template', 'style'], true)
            ) {
                continue;
            }
            $parts[] = $child instanceof DOMElement ?
                ($child->getAttribute('aria-label') ?: $child->getAttribute('alt')) : $child->nodeValue;
        }
        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    /**
     * Exclude nodes explicitly hidden by markup or simple inline declarations.
     *
     * @param DOMElement $node Subject node
     * @return bool
     */
    private static function visible(DOMElement $node): bool {
        for ($parent = $node; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (
                $parent->hasAttribute('hidden') || strtolower($parent->getAttribute('aria-hidden')) === 'true' ||
                in_array($parent->tagName, ['template', 'script', 'noscript', 'style'], true) ||
                preg_match(
                    '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)\s*(?:!important\s*)?(?:;|$)/i',
                    $parent->getAttribute('style')
                )
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Detect explicit ARIA naming, which may reference the enclosing page.
     *
     * @param DOMElement $node Subject node
     * @return bool
     */
    private static function aria(DOMElement $node): bool {
        return trim($node->getAttribute('aria-label')) !== '' || trim($node->getAttribute('aria-labelledby')) !== '';
    }

    /**
     * Calculate WCAG contrast for an unambiguous pair of literal colors.
     *
     * @param DOMElement $node Styled element
     * @param DOMXPath $xp Parsed document
     * @return string|null Ratio and threshold when the pair is below the threshold
     */
    private static function inline_contrast(DOMElement $node, DOMXPath $xp): ?string {
        if (!$node->hasAttribute('style') || !$xp->query('./text()[normalize-space(.)!=""]', $node)->length) {
            return null;
        }
        $decl = [];
        foreach (explode(';', $node->getAttribute('style')) as $style) {
            $parts = explode(':', $style, 2);
            if (count($parts) === 2) {
                $decl[strtolower(trim($parts[0]))] = strtolower(trim($parts[1]));
            }
        }
        if (array_intersect(['background', 'background-image', 'opacity', 'mix-blend-mode', 'filter'], array_keys($decl))) {
            return null;
        }
        $front = self::luminance($decl['color'] ?? '');
        $back = self::luminance($decl['background-color'] ?? '');
        if ($front === null || $back === null) {
            return null;
        }
        $min = 4.5;
        if (preg_match('/^([0-9.]+)px$/D', $decl['font-size'] ?? '', $match)) {
            $bold = in_array($decl['font-weight'] ?? '', ['bold', '700', '800', '900'], true);
            if ($match[1] >= 24 || ($bold && $match[1] >= 18.667)) {
                $min = 3;
            }
        }
        $ratio = (max($front, $back) + .05) / (min($front, $back) + .05);
        return $ratio < $min ? sprintf('%.2f / %.1f', $ratio, $min) : null;
    }

    /**
     * Convert an opaque sRGB hex or rgb() color to relative luminance.
     *
     * @param string $color Literal CSS color
     * @return float|null
     */
    public static function luminance(string $color): ?float {
        if (preg_match('/^#([a-f\d])([a-f\d])([a-f\d])$/iD', $color, $match)) {
            $rgb = array_map(static fn($d) => hexdec($d . $d), array_slice($match, 1));
        } else if (preg_match('/^#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/iD', $color, $match)) {
            $rgb = array_map('hexdec', array_slice($match, 1));
        } else if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/iD', $color, $match)) {
            $rgb = array_map('intval', array_slice($match, 1));
        } else {
            return null;
        }
        if (max($rgb) > 255) {
            return null;
        }
        $linear = array_map(static function (int $value): float {
            $s = $value / 255;
            return $s <= .04045 ? $s / 12.92 : (($s + .055) / 1.055) ** 2.4;
        }, $rgb);
        return .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
    }

    /**
     * Create a finding containing only a structural locator and a computed fact.
     *
     * @param string $rule Rule ID
     * @param string $location Structural locator
     * @param string $fact Optional computed detail
     * @return array
     */
    public static function issue(string $rule, string $location, string $fact = ''): array {
        [$severity, $classification, $criterion] = self::CATALOG[$rule];
        return [
            'ruleid' => $rule, 'location' => $location, 'fact' => $fact, 'severity' => $severity,
            'classification' => $classification, 'criterion' => $criterion,
        ];
    }

    /**
     * Return an asset result.
     *
     * @param bool $checked Whether bounded static rules were applicable
     * @param string $reason Coverage exclusion label
     * @param array $issues Detected findings
     * @return array
     */
    public static function result(bool $checked, string $reason = '', array $issues = []): array {
        return ['checked' => $checked, 'reason' => $reason, 'issues' => $issues];
    }
}
