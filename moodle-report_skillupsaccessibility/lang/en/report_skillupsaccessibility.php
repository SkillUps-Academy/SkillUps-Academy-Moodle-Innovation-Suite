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

/**
 * SkillUps accessibility language strings.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['all'] = 'All';
$string['apply'] = 'Apply filters';
$string['assignee'] = 'Assigned reviewer';
$string['back'] = 'Back to course report';
$string['badperiod'] = 'Enter valid calendar dates in chronological order.';
$string['busy'] = 'An audit or review is already processing this course. Try again shortly.';
$string['cachecounts'] = 'New analyses {$a->changed}. Reused results {$a->reused}.';
$string['checked'] = 'Evaluated sources';
$string['classification'] = 'Finding type';
$string['comparefrom'] = 'Comparison from';
$string['compareto'] = 'Comparison to';
$string['complete'] = 'Completed';
$string['confirmed'] = 'Confirmed static errors';
$string['corrected'] = 'Corrected';
$string['course'] = 'Course';
$string['criterion'] = 'WCAG reference';
$string['date'] = 'Audit date';
$string['dismissed'] = 'Dismissed';
$string['download'] = 'Download CSV';
$string['externalfile'] = 'External repository reference';
$string['formatunsupported'] = 'Unsupported Moodle markup format';
$string['unreadable'] = 'Unreadable static source';
$string['oversize'] = 'Source exceeds the processing limit';
$string['pdfmanual'] = 'PDF requires manual structure review';
$string['unsupported'] = 'Unsupported activity or document format';
$string['empty'] = 'No entries in this selection.';
$string['eventaudit'] = 'Accessibility audit changed';
$string['evidence'] = 'Private review evidence';
$string['failed'] = 'Failed';
$string['findings'] = 'Findings';
$string['fix_audiotranscript'] = 'Verify that an accessible equivalent transcript is available and linked clearly.';
$string['fix_contrast'] = 'Measure the rendered foreground and background and adjust colors for the applicable text size and weight.';
$string['fix_docxalt'] = 'Review the drawing in Word and include an alternative or mark it decorative when appropriate.';
$string['fix_docxheading'] = 'Check document navigation and verify that built-in heading styles reflect its hierarchy.';
$string['fix_emptylink'] = 'Give the link text or an accessible name that identifies its destination or purpose.';
$string['fix_formlabel'] = 'Add a visible label and associate it with the control where possible.';
$string['fix_frametitle'] = 'Identify the frame using a meaningful title or an accessible name and review its embedded content.';
$string['fix_genericlink'] = 'Check the surrounding context and use text that identifies the destination where possible.';
$string['fix_headskip'] = 'Review heading hierarchy in the rendered page and use levels that reflect the section structure.';
$string['fix_imgalt'] = 'Provide a concise relevant alternative. Use an empty alt attribute for purely decorative images.';
$string['fix_movingmarkup'] = 'Review the duration and controls for motion, or replace the element with static accessible content.';
$string['fix_pdfstructure'] = 'Inspect tags, headings, alternatives and reading order using an accessible PDF checker and manual testing.';
$string['fix_placeholder'] = 'Provide a persistent label. Reserve the placeholder for supplementary guidance.';
$string['fix_positivetab'] = 'Check keyboard focus order and prefer natural DOM order.';
$string['fix_tableheaders'] = 'For data tables, add appropriate th cells and verify header associations. Review layout tables separately.';
$string['fix_videocaptions'] = 'Check the player and provide synchronized captions when required.';
$string['from'] = 'Period from';
$string['high'] = 'High';
$string['index'] = 'Static accessibility index';
$string['institution'] = 'Institutional accessibility report';
$string['interpretation'] = 'The index is the share of evaluated sources without confirmed static errors. Coverage and human review remain separate. It does not establish WCAG conformance.';
$string['interrupted'] = 'An interrupted request was superseded.';
$string['intervaldays'] = 'Days between scheduled audits';
$string['intervalhelp'] = 'Set a value between 1 and 90. The scheduled task queues due courses each hour.';
$string['invalidreview'] = 'Enter review notes and choose a valid state and authorized assignee.';
$string['inventoried'] = 'Inventoried sources';
$string['jobstatus'] = 'Latest request status';
$string['location'] = 'Location';
$string['markedfalse'] = 'Explicit PDF tag flag set to false';
$string['medium'] = 'Medium';
$string['nodata'] = 'Not assessed';
$string['notavailable'] = 'This report or finding is unavailable in your scope.';
$string['notes'] = 'Review notes';
$string['opensource'] = 'Open Moodle source';
$string['outside'] = 'Criteria outside automatic testing';
$string['outsidedetails'] = 'Evaluate keyboard and assistive technology navigation, focus order and visibility, zoom and reflow, text and caption quality, live media, PDF reading order, theme contrast, and third-party interfaces manually.';
$string['partial'] = 'This audit reached the maximum of 400 sources. Its results cover the sources collected so far.';
$string['pending'] = 'Open';
$string['pluginname'] = 'SkillUps Accessibility Checker';
$string['previous'] = 'Comparison period index';
$string['privacy:actorid'] = 'Account that authorized the audit.';
$string['privacy:assignee'] = 'Account assigned to the finding.';
$string['privacy:authorid'] = 'Author of the review.';
$string['privacy:files'] = 'Private review evidence stored through Moodle File API.';
$string['privacy:finding'] = 'An optional responsible account for a finding.';
$string['privacy:job'] = 'The actor associated with an audit job.';
$string['privacy:notes'] = 'Review notes provided by the author.';
$string['privacy:review'] = 'Review identity, assignments and review notes.';
$string['queuebulk'] = 'Queue scoped audits';
$string['queued'] = 'Queued';
$string['queuednotice'] = 'The request was added to the Moodle task queue.';
$string['queuescan'] = 'Queue course audit';
$string['recommendation'] = 'Correction guidance';
$string['recurring'] = 'Recurring rules in this selection';
$string['review'] = 'Needs human review';
$string['reviewhistory'] = 'Review history';
$string['revoked'] = 'The audit account or course access is no longer authorized.';
$string['rule'] = 'Rule';
$string['rule_audiotranscript'] = 'Audio without a nearby transcript indicator';
$string['rule_contrast'] = 'Low contrast in a literal inline color pair';
$string['rule_docxalt'] = 'Word drawing without detectable alternative metadata';
$string['rule_docxheading'] = 'Skipped Word heading style';
$string['rule_emptylink'] = 'Link without a detectable name';
$string['rule_formlabel'] = 'Control without a detectable label';
$string['rule_frametitle'] = 'Embedded frame without a detectable name';
$string['rule_genericlink'] = 'Generic link text';
$string['rule_headskip'] = 'Skipped heading level';
$string['rule_imgalt'] = 'Image without an alternative text attribute';
$string['rule_movingmarkup'] = 'Moving or blinking HTML element';
$string['rule_pdfstructure'] = 'PDF structural review required';
$string['rule_placeholder'] = 'Placeholder without a persistent label';
$string['rule_positivetab'] = 'Positive tabindex';
$string['rule_tableheaders'] = 'Table without detectable header cells';
$string['rule_videocaptions'] = 'Video without a captions track in the fragment';
$string['running'] = 'Running';
$string['saved'] = 'Review saved.';
$string['savereview'] = 'Save review';
$string['scanfailed'] = 'The scan did not complete. Review the Moodle task log.';
$string['scheduleuserhelp'] = 'Use an active account with system bulk permission and course author access. Zero disables scheduled scans.';
$string['scheduleuserid'] = 'Scheduled audit account ID';
$string['settings'] = 'Accessibility audit settings';
$string['severity'] = 'Severity';
$string['shortpartial'] = 'Partial audit';
$string['skillupsaccessibility:bulk'] = 'Queue and view category or institutional audits';
$string['skillupsaccessibility:export'] = 'Export scoped accessibility reports';
$string['skillupsaccessibility:review'] = 'Review findings and evidence';
$string['skillupsaccessibility:scan'] = 'Queue a course accessibility audit';
$string['skillupsaccessibility:view'] = 'View course accessibility audit';
$string['source'] = 'Source';
$string['state'] = 'Review status';
$string['structureseen'] = 'Structure tree marker detected';
$string['structureunknown'] = 'Structure tree marker unavailable in the inspected bytes';
$string['taskschedule'] = 'Queue scheduled accessibility audits';
$string['timeline'] = 'Audit timeline';
$string['to'] = 'Period to';
$string['unassigned'] = 'Unassigned or unavailable';
