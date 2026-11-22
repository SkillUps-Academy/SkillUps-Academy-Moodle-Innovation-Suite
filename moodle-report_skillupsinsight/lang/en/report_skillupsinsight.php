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
 * Canonical English strings.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['above'] = 'At or above target';
$string['actions'] = 'Actions';
$string['addsched'] = 'Add schedule or goal';
$string['alertsubject'] = 'Insight indicator reached its threshold';
$string['allprograms'] = 'All visible programs';
$string['applyfilters'] = 'Apply filters';
$string['awaiting'] = 'This period is waiting for its first published snapshot. Check after aggregation runs.';
$string['begins'] = 'Starts (UTC)';
$string['below'] = 'At or below target';
$string['breakdown'] = 'Breakdown of authorized courses';
$string['builderhelp'] = 'Select the indicators and dimensions for a private saved report. The owner’s current permissions apply each time it opens.';
$string['building'] = 'Calculating';
$string['busy'] = 'This operation is busy. Try again shortly.';
$string['captured'] = 'Published {$a}';
$string['capturedate'] = 'Snapshot publication';
$string['category'] = 'Category';
$string['compare'] = 'Comparison period';
$string['comparisoncoverage'] = 'Comparison coverage';
$string['configuration'] = 'SkillUps Insight configuration';
$string['constructor'] = 'Report builder';
$string['course'] = 'Course';
$string['coverage'] = 'Courses captured';
$string['coveragedetail'] = 'Coverage: {$a->current} of {$a->total} authorized courses.';
$string['createperiod'] = 'Create period';
$string['daily'] = 'Every day';
$string['dashboard'] = 'Dashboard';
$string['delete'] = 'Delete';
$string['deletehelp'] = 'Confirm deletion of this saved report and its schedules.';
$string['deletewatchhelp'] = 'Confirm deletion of this schedule or goal.';
$string['deltametric'] = '{$a} (change)';
$string['digest'] = 'Recurring report';
$string['digestsubject'] = 'Insight report available';
$string['dimension'] = 'Group';
$string['direction'] = 'Notification or target type';
$string['download'] = 'Download report';
$string['edit'] = 'Edit';
$string['ends'] = 'Ends (UTC)';
$string['eventreport'] = 'Institutional analytics accessed';
$string['exportlimit'] = 'This export exceeds {$a} rows. Narrow the filters or group the courses.';
$string['format'] = 'Format';
$string['freeze'] = 'Freeze snapshot';
$string['frozen'] = 'Frozen';
$string['goallabel'] = '{$a->metric} · {$a->direction} {$a->target}';
$string['group'] = 'Group by';
$string['history'] = 'Published snapshots';
$string['indicators'] = 'Indicators';
$string['institution'] = 'Institution';
$string['intervaldays'] = 'Frequency';
$string['invalidperiod'] = 'Select a valid, distinct academic period or published snapshot.';
$string['invalidrequest'] = 'The requested analytics operation is invalid.';
$string['lastcheck'] = 'Last checked {$a}';
$string['latest'] = 'Latest academic period';
$string['licensecommunity'] = 'Community';
$string['licenseenterprise'] = 'Enterprise';
$string['licenseexpired'] = 'Expired';
$string['licenseexpires'] = 'Expiration: {$a}';
$string['licensegrace'] = 'Offline grace period';
$string['licenseinstallation'] = 'Installation identifier: {$a}';
$string['licenseprofessional'] = 'Professional';
$string['licensestatus'] = 'Subscription status';
$string['limit'] = 'The maximum number of records for this account has been reached.';
$string['messageprovider:reportdue'] = 'Scheduled reports and indicator alerts';
$string['metric'] = 'Indicator';
$string['metric_academic'] = 'Mean numeric course grade';
$string['metric_active'] = 'Learners with recorded events';
$string['metric_completion'] = 'Course completion rate';
$string['metric_educators'] = 'Active educator enrollments';
$string['metric_enrolled'] = 'Active learner enrollments';
$string['metric_newlearners'] = 'New learner enrollments';
$string['metric_participation'] = 'Student participation';
$string['metric_teaching'] = 'Educators with teaching events';
$string['metricdelta'] = 'Change';
$string['metriclimits'] = 'Activity counts are Moodle events within the period. Counts across courses represent person–course pairs. A dash indicates missing, ineligible or suppressed data.';
$string['metricvalue'] = 'Value';
$string['monthly'] = 'Every 30 days';
$string['mypresets'] = 'My saved reports';
$string['myschedules'] = 'Saved schedules and goals';
$string['name'] = 'Name';
$string['nextcheck'] = 'Next check {$a}';
$string['nocmp'] = 'No comparison';
$string['nocourses'] = 'No courses in this scope match the selected filters.';
$string['noperiods'] = 'No academic periods have been configured.';
$string['noticemessage'] = 'Open your private report in Moodle to review its current data.';
$string['notificationfailed'] = 'Moodle could not deliver the scheduled Insight notification.';
$string['openreport'] = 'Open report';
$string['outofscope'] = 'You do not have access to this analytics scope.';
$string['overview'] = 'Summary';
$string['paused'] = 'Paused';
$string['pending'] = 'Pending';
$string['percent'] = '{$a}%';
$string['period'] = 'Period';
$string['periodadmin'] = 'Academic periods';
$string['periodhelp'] = 'Use UTC dates. Both the first and last selected dates are included. A period can span up to 731 days.';
$string['periods'] = 'Configured academic periods';
$string['pluginname'] = 'SkillUps Insight Analytics';
$string['points'] = '{$a} pp';
$string['priorcoverage'] = 'Comparison: {$a->current} of {$a->total} authorized courses.';
$string['priormetric'] = '{$a} (comparison)';
$string['privacy:metadata:preset'] = 'An owner’s saved report title and filters.';
$string['privacy:metadata:settings'] = 'Saved reporting preferences.';
$string['privacy:metadata:status'] = 'When the rule is due or was evaluated.';
$string['privacy:metadata:timecreated'] = 'When the report was created.';
$string['privacy:metadata:userid'] = 'The owner of the report or schedule.';
$string['privacy:metadata:watch'] = 'An owner’s recurring report schedule or threshold and evaluation status.';
$string['privacy:path'] = 'SkillUps Insight Analytics';
$string['program'] = 'Program';
$string['programfield'] = 'Course program field';
$string['programfielddesc'] = 'Shortname of a visible single-line Moodle course custom field.';
$string['reached'] = 'Target reached';
$string['ready'] = 'Ready';
$string['refresh'] = 'Queue refresh';
$string['retention'] = 'Snapshot history retention (days)';
$string['retentiondesc'] = 'Between 7 and 365 days. The latest published snapshot of each period is kept.';
$string['saved'] = 'Changes saved.';
$string['savepreset'] = 'Save report';
$string['schedulehelp'] = 'Choose a recurring report link or check a threshold. For an indicator threshold provide its target as a count or a percentage, depending on the indicator.';
$string['scheduling'] = 'Schedules and goals';
$string['skillupsinsight:educator'] = 'Count as an educator in analytics';
$string['skillupsinsight:export'] = 'Export authorized analytics';
$string['skillupsinsight:learner'] = 'Count as a learner in analytics';
$string['skillupsinsight:manage'] = 'Manage academic periods';
$string['skillupsinsight:personalise'] = 'Save personal analytics reports';
$string['skillupsinsight:schedule'] = 'Schedule reports and indicator watches';
$string['skillupsinsight:view'] = 'View authorized institutional analytics';
$string['smallcell'] = 'Minimum denominator';
$string['smallcelldesc'] = 'Suppress eligible indicators below this population, between 1 and 100. Enrollment and educator counts remain available.';
$string['status'] = 'Status';
$string['tablecompare'] = 'Previous {$a->prior} · Change {$a->delta}';
$string['target'] = 'Target';
$string['taskaggregate'] = 'Publish Insight analytics snapshots';
$string['taskdeliver'] = 'Evaluate Insight reports and indicator targets';
$string['toggle'] = 'Pause or resume';
$string['unauthorized'] = 'Authorization lost';
$string['unavailable'] = 'Data unavailable';
$string['unmapped'] = 'No program assigned';
$string['unreached'] = 'Target pending';
$string['weekly'] = 'Every 7 days';
