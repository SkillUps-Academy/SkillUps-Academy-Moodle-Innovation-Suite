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
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['abstained'] = 'No signal with known outcome';
$string['accuracy'] = 'Accuracy';
$string['action'] = 'Action';
$string['actor'] = 'Author';
$string['aggregationhelp'] = 'Counts are grouped into bands and small cells are suppressed. Compare error measures only for the same version, cohort dates and outcome protocol. Differences are descriptive.';
$string['allrisks'] = 'All rule classifications';
$string['allstates'] = 'All states';
$string['assess'] = 'Independent outcome assessment';
$string['assessmenthelp'] = 'Review course evidence from the listed 14-day window. Determine whether there was an academic support need using independent evidence. Do not use the alert or case status to choose the label.';
$string['assessmentqueue'] = 'Available outcome assessments';
$string['assessmentwindow'] = 'Review window {$a->start} to {$a->end}.';
$string['assessone'] = 'Assess outcome';
$string['assign'] = 'Assign or remove tutor';
$string['assigned'] = 'Assigned to a tutor';
$string['assignment'] = 'Assignment';
$string['back'] = 'Student support dashboard';
$string['brier'] = 'Brier score';
$string['busy'] = 'This course is processing academic support data. Try again shortly.';
$string['caseheading'] = 'Support case for {$a}';
$string['caseoutcomes'] = 'Support process outcomes';
$string['cases'] = 'Cases';
$string['closedcase'] = 'Reopen this case before adding another support intervention.';
$string['cohort'] = 'Observation cohort';
$string['cohortrange'] = '{$a->begin} to {$a->end} (UTC)';
$string['contact'] = 'Record contact';
$string['contributions'] = 'Model contributions to log-odds (association)';
$string['courses'] = 'Courses';
$string['currentpending'] = 'Current missing work (first 20)';
$string['due'] = 'Window ended';
$string['effectivedeadline'] = 'Effective deadline';
$string['enabled'] = 'Enable scheduled signal collection';
$string['eventcase'] = 'Student Success operation audited';
$string['evidence'] = 'Observed factors';
$string['export'] = 'Export quality CSV';
$string['factor'] = 'Factor';
$string['feature_decline'] = 'Change from previous grade (points of decline)';
$string['feature_grade'] = 'Current numeric course total (%)';
$string['feature_inactive'] = 'Days since last recorded course access';
$string['feature_overdue'] = 'Missing individual assignments past effective deadlines';
$string['feature_participation'] = 'Available modules with recorded activity in 14 days';
$string['filter'] = 'Apply filters';
$string['fn'] = 'False negatives';
$string['fnr'] = 'False negative rate';
$string['followup'] = 'Record follow-up';
$string['fp'] = 'False positives';
$string['fpr'] = 'False positive rate';
$string['gradehistory'] = 'Recent course grade observations';
$string['group'] = 'Group';
$string['grouping'] = 'Grouping';
$string['high'] = 'High attention';
$string['history'] = 'Recent intervention history';
$string['identifiedcases'] = 'Student support cases';
$string['intervention'] = 'Record an intervention';
$string['invalidrequest'] = 'The submitted operation or value is invalid.';
$string['label'] = 'Record final assessment';
$string['learner'] = 'Learner';
$string['licensecommunity'] = 'Community';
$string['licenseenterprise'] = 'Enterprise';
$string['licenseexpired'] = 'Expired';
$string['licenseexpires'] = 'Expires {$a}';
$string['licensegrace'] = 'Offline grace period';
$string['licenseinstallation'] = 'Installation ID {$a}';
$string['licenseprofessional'] = 'Professional';
$string['licensestatus'] = 'Subscription status';
$string['limit_decline'] = 'Grade decline at least (percentage points)';
$string['limit_grade'] = 'Numeric course grade below (%)';
$string['limit_high'] = 'Triggered rules required for high attention';
$string['limit_inactive'] = 'Inactivity in days (at least)';
$string['limit_overdue'] = 'Overdue assignments (at least)';
$string['limit_participation'] = 'Recorded modules in 14 days (fewer than)';
$string['matched'] = 'Triggered at threshold {$a}';
$string['mincell'] = 'Minimum aggregate support';
$string['mincelldesc'] = 'From 5 to 100 records for disclosure thresholds. Counts are released in bands of five.';
$string['mode'] = 'Evaluation engine';
$string['modeldesc'] = 'Requires a validated frozen model in config.php and course opt-in. No training occurs in the plugin.';
$string['modelenabled'] = 'Enable approved statistical inference';
$string['monitor'] = 'Monitor';
$string['noaggregates'] = 'No aggregate groups meet the retention and minimum population conditions.';
$string['noassessments'] = 'No unlabelled outcomes are ready for assessment in this course.';
$string['nocases'] = 'No authorized cases match the current filters.';
$string['nopath'] = '—';
$string['noprediction'] = 'No statistical estimate was produced. The model may be disabled or require unavailable signals.';
$string['normal'] = 'No rule triggered';
$string['notes'] = 'Support notes (plain text)';
$string['observed'] = 'Observed or recorded';
$string['observedcounts'] = 'Coverage and observed classifications';
$string['open'] = 'Open';
$string['operation'] = 'Operation';
$string['outcome'] = 'Observed support need';
$string['outcomeno'] = 'No support need confirmed with sufficient evidence';
$string['outcomeunknown'] = 'Insufficient evidence';
$string['outcomeyes'] = 'Support need confirmed';
$string['outofscope'] = 'You do not have access to this academic support record or scope.';
$string['pending'] = 'Pending labels';
$string['plan'] = 'Record support plan';
$string['pluginname'] = 'SkillUps Student Success Predictor';
$string['policy'] = 'Course rules and activation';
$string['policyhelp'] = 'Enable scanning only for approved courses. Zero disables an individual rule. The high-attention threshold is the number of triggered rules.';
$string['precision'] = 'Precision';
$string['predictedalert'] = 'Above model threshold';
$string['predictiondetail'] = 'Model {$a->model} estimates {$a->probability}% for the defined outcome. Alert threshold {$a->threshold}%.';
$string['privacy:cases'] = 'Support cases and tutor assignments.';
$string['privacy:entries'] = 'Support actions and notes.';
$string['privacy:factors'] = 'Measured academic signals, assessment labels or derived outputs.';
$string['privacy:note'] = 'Staff-authored support note.';
$string['privacy:observations'] = 'Daily academic support observations.';
$string['privacy:path'] = 'SkillUps Student Success Predictor';
$string['privacy:samples'] = 'Frozen prospective predictions and independently assessed outcomes.';
$string['privacy:state'] = 'Human support case state.';
$string['privacy:time'] = 'Observation, activity or review time.';
$string['privacy:user'] = 'Learner or responsible staff reference.';
$string['programfield'] = 'Course program field';
$string['programfielddesc'] = 'Short name of a native single-line course field that viewers may access.';
$string['programs'] = 'Programs';
$string['qualitymetrics'] = 'Observed error and prediction quality';
$string['recall'] = 'Sensitivity';
$string['record'] = 'Save intervention';
$string['reopen'] = 'Reopen';
$string['reports'] = 'Student Success reports';
$string['resolved'] = 'Resolved';
$string['retention'] = 'Data retention in days';
$string['retentiondesc'] = 'From 45 to 730 days. Active cases with recent observations remain available.';
$string['reviewnotice'] = 'Use these academic signals to guide a human review. Consider missing evidence and individual context before deciding on support.';
$string['risk'] = 'Rule classification';
$string['ruleclass'] = 'Deterministic rules';
$string['rulesignal'] = 'Rule signal';
$string['save'] = 'Save course rules';
$string['saved'] = 'Changes saved.';
$string['scandisabled'] = 'Scheduled collection is paused for this course.';
$string['skillupssuccess:aggregate'] = 'View desensitized course and program summaries';
$string['skillupssuccess:assess'] = 'Assess outcomes in the quality cohort';
$string['skillupssuccess:configure'] = 'Configure early warning rules and course opt-in';
$string['skillupssuccess:learner'] = 'Include learner in academic support monitoring';
$string['skillupssuccess:manage'] = 'Manage course support cases and assign tutors';
$string['skillupssuccess:view'] = 'View assigned student support cases';
$string['stale'] = 'This record changed or the assessment window is not ready. Reload the page.';
$string['state'] = 'Case status';
$string['statistical'] = 'Statistical model';
$string['study'] = 'Collect prospective samples for outcome evaluation, including students with no alert';
$string['studydisabled'] = 'Prospective outcome assessment is disabled for this course.';
$string['supported'] = 'With interventions';
$string['taskretention'] = 'Expire old academic support records';
$string['taskscan'] = 'Collect academic support signals';
$string['tn'] = 'True negatives';
$string['total'] = 'Evaluable window records';
$string['tp'] = 'True positives';
$string['tutor'] = 'Tutor';
$string['unassigned'] = 'Unassigned';
$string['unknown'] = 'Unknown';
$string['unmapped'] = 'No program assigned';
$string['usemodel'] = 'Apply the site-approved statistical model in this course';
$string['value'] = 'Value';
$string['version'] = 'Version / fingerprint';
$string['watch'] = 'Review signal';
