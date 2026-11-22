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
 * Language strings for SkillUps Course Quality Inspector.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['alreadyqueued'] = 'An audit is already queued or was requested within the last minute.';
$string['apply'] = 'Apply';
$string['auditdetails'] = 'Audit results';
$string['auditid'] = 'Audit ID';
$string['backaudit'] = 'Back to audit results';
$string['batchhistory'] = 'Batch results';
$string['batchlimit'] = 'The category or selection exceeds 50 courses. Choose a subcategory or submit a list of up to 50 IDs.';
$string['batchqueued'] = 'Queued {$a->scheduled} audits. Skipped {$a->skipped} because of recent or active requests.';
$string['busy'] = 'The course audit is busy. Try again shortly.';
$string['catalog'] = 'Rule catalog';
$string['categoryhelp'] = 'Audit up to 50 courses from this category and its subcategories.';
$string['categoryid'] = 'Course category ID';
$string['comparison'] = 'Compare with an earlier audit';
$string['comparisonpolicy'] = 'Audit policies differ. Compare the individual findings and their configured rules.';
$string['compliance'] = 'Latest audit evidence';
$string['compliancehelp'] = 'Correction status is set by an operator. Evidence is updated by a later audit with the rule enabled.';
$string['condition'] = 'Condition';
$string['condition_completion'] = 'Completion is enabled and the course has no completion criteria.';
$string['condition_dates'] = 'An opening, due or closing date in the course, assignment or quiz is later than its successor.';
$string['condition_emptycontent'] = 'A Moodle Page has neither displayed text nor media.';
$string['condition_emptycourse'] = 'No activities or labels with content are present.';
$string['condition_grade'] = 'A numeric grade item\'s minimum is at or above its maximum.';
$string['condition_hidden'] = 'A module is explicitly hidden in the course configuration.';
$string['condition_instructions'] = 'A visible assignment or quiz has an empty description.';
$string['condition_links'] = 'A supported editor references a missing Moodle attachment or contains a malformed link or executable URI.';
$string['condition_quizgrade'] = 'The quiz has a positive maximum grade and no positive total across its questions.';
$string['condition_resources'] = 'A File or Folder resource has no stored files, or has a local file with zero bytes.';
$string['condition_rubric'] = 'An assignment uses the rubric method and has no form available for grading.';
$string['condition_sections'] = 'A non-general section contains no visible modules or substantive summary.';
$string['count'] = 'Count';
$string['course'] = 'Course';
$string['courseaudits'] = 'Course audit history';
$string['courseid'] = 'Course ID';
$string['courselimit'] = 'The course exceeds the current limit of 2000 activities and resources.';
$string['coursepicker'] = 'Courses you can inspect';
$string['csv'] = 'Download CSV';
$string['dashboard'] = 'Site dashboard';
$string['dashboardhelp'] = 'Open a course audit or enter a category ID to request a batch.';
$string['delta_absent'] = 'No longer detected';
$string['delta_new'] = 'New findings';
$string['delta_present'] = 'Still detected';
$string['disabled'] = 'Disabled';
$string['duedate'] = 'Target date';
$string['editplan'] = 'Edit corrective plan';
$string['eventaudit'] = 'Course quality audit operation';
$string['evidence'] = 'Evidence';
$string['evidence_allowsubmissionsfromdate'] = 'Submissions open';
$string['evidence_attachments'] = 'Stored attachments';
$string['evidence_badlinks'] = 'Invalid links';
$string['evidence_count'] = 'Activities with content';
$string['evidence_cutoffdate'] = 'Cut-off date';
$string['evidence_duedate'] = 'Due date';
$string['evidence_emptyfiles'] = 'Empty local files';
$string['evidence_enddate'] = 'Course end';
$string['evidence_max'] = 'Maximum grade';
$string['evidence_min'] = 'Minimum grade';
$string['evidence_missingfiles'] = 'Missing embedded attachments';
$string['evidence_number'] = 'Section number';
$string['evidence_questionsum'] = 'Total question marks';
$string['evidence_startdate'] = 'Course start';
$string['evidence_timeclose'] = 'Quiz closes';
$string['evidence_timeopen'] = 'Quiz opens';
$string['evidence_tracked'] = 'Activities with completion enabled';
$string['excel'] = 'Download Excel';
$string['field'] = 'Field';
$string['findings'] = 'Findings';
$string['history'] = 'Audit history';
$string['invalidaction'] = 'The requested operation is unavailable.';
$string['invalidbatch'] = 'Enter numeric course IDs separated by commas or spaces.';
$string['invalidplan'] = 'Enter a valid state and a note up to 4000 characters.';
$string['kind'] = 'Finding type';
$string['kind_advisory'] = 'Design recommendations';
$string['kind_all'] = 'All types';
$string['kind_objective'] = 'Verified findings';
$string['latestaudit'] = 'View latest completed audit';
$string['license_community'] = 'Community edition';
$string['license_enterprise'] = 'Enterprise edition';
$string['license_expired'] = 'License expired';
$string['license_grace'] = 'License grace period';
$string['license_professional'] = 'Professional edition';
$string['licenseexpires'] = 'Expires {$a}';
$string['licenseinstallation'] = 'Installation identifier {$a}';
$string['licensing'] = 'Licensing';
$string['maintain'] = 'Maintain and recover course quality audits';
$string['mode_category'] = 'Entire category and subcategories';
$string['mode_selected'] = 'Selected courses in this category';
$string['noaudits'] = 'No audits are available. Request an audit to begin.';
$string['nocourses'] = 'There are no authorized courses to audit in that scope.';
$string['nofindings'] = 'No findings matched this filter.';
$string['noplans'] = 'No corrective plans exist for this course.';
$string['norules'] = 'The current institutional policy has no enabled rules.';
$string['notes'] = 'Action notes';
$string['notesprompt'] = 'Record the correction you intend to make. Keep personal student information out of these notes.';
$string['opencategory'] = 'Open category';
$string['partiallinks'] = 'Link checks were incomplete for {$a} activities because an editor field exceeded the supported length.';
$string['plans'] = 'Corrective plans';
$string['plansaved'] = 'Corrective plan saved.';
$string['pluginname'] = 'SkillUps Course Quality Inspector';
$string['policychanged'] = 'The engine version changed while the audit was pending. Request a new audit.';
$string['privacy:metadata:notes'] = 'Action notes supplied by the last plan author.';
$string['privacy:metadata:plan'] = 'Stores the last plan author and their action notes.';
$string['privacy:metadata:run'] = 'Records the user who requested an audit and the request date.';
$string['privacy:metadata:time'] = 'Time of audit submission or plan edit.';
$string['privacy:metadata:userid'] = 'Moodle operator user ID.';
$string['privacy:path'] = 'Course quality inspection';
$string['progress_denied'] = 'The requester no longer has permission to audit this course.';
$string['progress_failed'] = 'The audit failed. Check the native task log or request a new audit.';
$string['progress_queued'] = 'Moodle cron will process this audit.';
$string['progress_running'] = 'Moodle is inspecting the course configuration.';
$string['queue'] = 'Request audit';
$string['queuedcount'] = 'Audit queued for {$a} course.';
$string['recommend_completion'] = 'Define criteria if the institution expects Moodle to track completion of the course.';
$string['recommend_dates'] = 'Check the affected dates and restore their intended order.';
$string['recommend_emptycontent'] = 'Add the planned text or media to the page.';
$string['recommend_emptycourse'] = 'Add the resources or activities students need to begin.';
$string['recommend_grade'] = 'Correct the grade item\'s minimum and maximum in the gradebook.';
$string['recommend_hidden'] = 'Confirm whether the resource should remain hidden or be made visible.';
$string['recommend_instructions'] = 'Consider explaining expectations and submission or assessment criteria.';
$string['recommend_links'] = 'Open the activity editor and repair the affected links or attachments.';
$string['recommend_quizgrade'] = 'Review the question list and the marks assigned to each question.';
$string['recommend_resources'] = 'Check the resource and upload or replace the affected files.';
$string['recommend_rubric'] = 'Complete and mark the rubric ready in Advanced grading.';
$string['recommend_sections'] = 'Add a description or materials, or review whether the section is needed.';
$string['recommendation'] = 'Recommendation';
$string['refresh'] = 'Refresh status';
$string['requested'] = 'Requested at';
$string['retention'] = 'Audit history retention in days';
$string['retentionhelp'] = 'Keep study-independent audit results for 30 to 730 days. The default is 180.';
$string['rule'] = 'Rule';
$string['rule_completion'] = 'Course completion criteria';
$string['rule_dates'] = 'Inverted dates';
$string['rule_emptycontent'] = 'Page without content';
$string['rule_emptycourse'] = 'Empty course';
$string['rule_grade'] = 'Invalid grade range';
$string['rule_hidden'] = 'Hidden activity or resource';
$string['rule_instructions'] = 'Activity instructions';
$string['rule_links'] = 'Locally verified link problem';
$string['rule_quizgrade'] = 'Quiz scoring incomplete';
$string['rule_resources'] = 'Missing or empty attached files';
$string['rule_rubric'] = 'Active rubric unavailable';
$string['rule_sections'] = 'Visible section without material';
$string['ruleid'] = 'Rule ID';
$string['saveplan'] = 'Save plan';
$string['scope'] = 'Audit scope';
$string['scopefailure'] = 'The requested audit, course or category is unavailable.';
$string['score'] = 'Quality score';
$string['scoredelta'] = 'Previous score {$a->before}. Current score {$a->after}. Change {$a->change} points.';
$string['scorehelp'] = 'The score applies one penalty per verified rule with findings. Design recommendations are reported separately.';
$string['selectedids'] = 'Course IDs separated by commas';
$string['selectedbatch'] = 'Audit a list of courses';
$string['settings'] = 'Course quality policy';
$string['severity'] = 'Severity';
$string['severity_critical'] = 'Critical';
$string['severity_high'] = 'High';
$string['severity_low'] = 'Low';
$string['severity_medium'] = 'Medium';
$string['severityfor'] = 'Severity for {$a}';
$string['skillupsquality:batch'] = 'Request course audits by category or batch';
$string['skillupsquality:plan'] = 'Edit corrective course plans';
$string['skillupsquality:run'] = 'Request a course quality audit';
$string['skillupsquality:view'] = 'View full course audit evidence';
$string['state'] = 'State';
$string['state_absent'] = 'Not detected';
$string['state_accepted'] = 'Accepted exception';
$string['state_addressed'] = 'Correction applied';
$string['state_denied'] = 'Authorization lost';
$string['state_done'] = 'Completed';
$string['state_failed'] = 'Failed';
$string['state_overdue'] = 'Overdue';
$string['state_planned'] = 'Planned';
$string['state_present'] = 'Detected';
$string['state_queued'] = 'Queued';
$string['state_running'] = 'Running';
$string['state_unknown'] = 'Verification pending';
$string['state_working'] = 'In progress';
$string['target'] = 'Target';
$string['target_course'] = 'Course ID {$a}';
$string['target_gradeitem'] = 'Grade item ID {$a}';
$string['target_module'] = 'Activity ID {$a}';
$string['target_section'] = 'Section ID {$a}';
$string['unverifiedcount'] = 'Activities with incomplete link checks';
$string['value'] = 'Value';
