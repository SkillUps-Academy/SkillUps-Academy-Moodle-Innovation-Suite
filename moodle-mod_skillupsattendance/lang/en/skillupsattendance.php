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
 * English strings for SkillUps Attendance Pro.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['absences'] = 'Allowed absences before warning';
$string['addsession'] = 'Add session';
$string['alerts'] = 'Enable attendance risk notifications';
$string['alerts_help'] = 'Send private Moodle notices when an eligible learner reaches a new risk threshold. Set the consecutive or total rule to zero to disable it.';
$string['allparticipants'] = 'Whole activity';
$string['approve'] = 'Approve justification';
$string['audit'] = 'Attendance history';
$string['auditevidence'] = 'Export audit evidence';
$string['backtosession'] = 'Back to session';
$string['badcredential'] = 'The access code or QR is invalid or expired.';
$string['badfile'] = 'Attach up to three valid PDF, PNG or JPEG files within the course upload limit.';
$string['badgroup'] = 'No group is available for your permissions in this activity.';
$string['badmark'] = 'Check the selected students and attendance states.';
$string['badpin'] = 'A valid access code must contain 6 to 32 ASCII letters or digits.';
$string['badreason'] = 'Check the configured reason and the length of the explanation.';
$string['badschedule'] = 'Check the dates, session settings and group permissions.';
$string['busy'] = 'The activity is busy. Try again shortly.';
$string['calendar'] = 'Attendance calendar';
$string['cancelsession'] = 'Cancel session';
$string['checkin'] = 'Check in';
$string['checkout'] = 'Allow student check-out';
$string['closedwindow'] = 'This registration or finalization is unavailable at the current time.';
$string['confirm'] = 'Confirm the action before continuing.';
$string['confirmcancel'] = 'I confirm cancellation and exclusion from attendance calculations.';
$string['confirmcheckin'] = 'Confirm attendance';
$string['confirmgrade'] = 'I authorize publishing the complete activity percentage snapshot, independent of the current report filters.';
$string['consecutive'] = 'Consecutive absence warning threshold';
$string['coursewide'] = 'Use accessible activities in this course';
$string['decision'] = 'Record decision';
$string['decisiondate'] = 'Decision date';
$string['departure'] = 'Exit';
$string['duration'] = 'Duration';
$string['early'] = 'Minutes before start to open registration';
$string['edition'] = 'Edition';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Expired';
$string['edition_grace'] = 'Offline grace period';
$string['edition_professional'] = 'Professional';
$string['editsession'] = 'Edit or reschedule session';
$string['empty'] = 'There are no matching records.';
$string['emptysessions'] = 'There are no visible sessions in this month.';
$string['ends'] = 'End';
$string['entry'] = 'Entry';
$string['eventattendancechange'] = 'Attendance operation recorded';
$string['evidence'] = 'Private evidence';
$string['export_csv'] = 'Export CSV';
$string['export_excel'] = 'Export Excel';
$string['extra'] = 'Extraordinary session';
$string['filter'] = 'Apply filters';
$string['finalize'] = 'Finalize session';
$string['frequency'] = 'Recurrence';
$string['frequency_day'] = 'Daily';
$string['frequency_week'] = 'Weekly';
$string['grace'] = 'Minutes after end before closing registration';
$string['gradelocked'] = 'Moodle did not permit updating the grade item.';
$string['gradepublished'] = 'Published {$a->published} grades. Skipped {$a->skipped} locked or overridden grades.';
$string['grades'] = 'Optional Gradebook publication';
$string['grades_help'] = 'Allow an authorized operator to publish a percentage snapshot from the report. Each publication requires confirmation.';
$string['group'] = 'Group';
$string['invalidpolicy'] = 'Enter a whole number within the supported range.';
$string['just_approved'] = 'Approved';
$string['just_pending'] = 'Pending review';
$string['just_rejected'] = 'Rejected';
$string['justifications'] = 'Justifications';
$string['justunavailable'] = 'This justification cannot be submitted or reviewed in its current state.';
$string['late'] = 'Minutes after start before a check-in is late';
$string['limited'] = 'Too many attempts. Wait five minutes before retrying.';
$string['matchedstudents'] = 'Students in this report';
$string['messageprovider:attendancewarning'] = 'Attendance risk notifications';
$string['method'] = 'Registration method';
$string['method_code'] = 'Access code';
$string['method_manual'] = 'Teacher records attendance';
$string['method_open'] = 'Student self check-in';
$string['method_qr'] = 'Temporary QR code';
$string['minimum'] = 'Minimum attendance percentage';
$string['modality'] = 'Delivery mode';
$string['modality_hybrid'] = 'Hybrid';
$string['modality_online'] = 'Online';
$string['modality_onsite'] = 'In person';
$string['modulename'] = 'SkillUps Attendance Pro';
$string['modulename_help'] = 'Schedule sessions, record attendance, review evidence and report results within the Moodle course.';
$string['modulenameplural'] = 'SkillUps Attendance Pro activities';
$string['month'] = 'Calendar month';
$string['myattendance'] = 'My attendance';
$string['newpin'] = 'Access code';
$string['newpin_help'] = 'Use 6 to 32 ASCII letters or digits. An empty value on edit keeps the previous code when the method stays the same.';
$string['noentry'] = 'A valid check-in is required before recording an exit.';
$string['nogrades'] = 'There are no eligible, editable grades to publish.';
$string['norate'] = 'Not available';
$string['occurrences'] = 'Occurrences including the first session';
$string['op_cancelled'] = 'Session cancelled';
$string['op_checkout'] = 'Exit recorded';
$string['op_created'] = 'Session created';
$string['op_decided'] = 'Justification decided';
$string['op_edited'] = 'Session settings revised';
$string['op_exported'] = 'Evidence exported';
$string['op_finalized'] = 'Session finalized';
$string['op_marked'] = 'Attendance recorded';
$string['op_published'] = 'Grades published';
$string['op_requested'] = 'Justification submitted';
$string['op_rescheduled'] = 'Session rescheduled';
$string['op_warned'] = 'Private warning delivered';
$string['op_warningfailed'] = 'Private warning could not be delivered';
$string['operation'] = 'Operation';
$string['operator'] = 'Operator';
$string['percent'] = 'Attendance percentage';
$string['periodfrom'] = 'First date';
$string['periodto'] = 'Last date';
$string['pluginadministration'] = 'Attendance administration';
$string['pluginname'] = 'SkillUps Attendance Pro';
$string['policy'] = 'Academic attendance rules';
$string['previousstate'] = 'Previous status';
$string['privacy:field:active'] = 'Whether the warning is active.';
$string['privacy:field:actorid'] = 'User who performed the operation.';
$string['privacy:field:afterstate'] = 'Resulting outcome.';
$string['privacy:field:beforestate'] = 'Previous outcome.';
$string['privacy:field:ends'] = 'Session end time.';
$string['privacy:field:expires'] = 'Expiration time of the counter.';
$string['privacy:field:failures'] = 'Attempt count.';
$string['privacy:field:groupid'] = 'Moodle group for the session.';
$string['privacy:field:modality'] = 'Session delivery method.';
$string['privacy:field:modified'] = 'Last modification time.';
$string['privacy:field:name'] = 'Session name.';
$string['privacy:field:operation'] = 'Recorded action.';
$string['privacy:field:reason'] = 'Configured reason selected by the student.';
$string['privacy:field:response'] = 'The reviewer’s explanation.';
$string['privacy:field:reviewer'] = 'Teacher who reviewed the request.';
$string['privacy:field:rulekey'] = 'Threshold type that generated a warning.';
$string['privacy:field:sessionid'] = 'Session reference.';
$string['privacy:field:source'] = 'How the result was recorded.';
$string['privacy:field:starts'] = 'Session start time.';
$string['privacy:field:state'] = 'Recorded workflow state.';
$string['privacy:field:statement'] = 'The student’s explanation.';
$string['privacy:field:teacherid'] = 'Instructor responsible for the session.';
$string['privacy:field:timecreated'] = 'Creation time.';
$string['privacy:field:timeexit'] = 'Time of the student’s exit.';
$string['privacy:field:timein'] = 'Time of the student’s entrance.';
$string['privacy:field:timereviewed'] = 'Review time.';
$string['privacy:field:userid'] = 'Student identifier.';
$string['privacy:files'] = 'Private justification evidence stored in Moodle files.';
$string['privacy:messages'] = 'Generic attendance notices sent through Moodle messaging.';
$string['privacy:table:alert'] = 'Attendance risk notification state.';
$string['privacy:table:audit'] = 'Attendance operation history.';
$string['privacy:table:fail'] = 'Temporary access-code or QR attempt counter.';
$string['privacy:table:just'] = 'Justification submission, evidence and decision.';
$string['privacy:table:mark'] = 'Attendance outcome and entrance or exit time.';
$string['privacy:table:session'] = 'Session schedule and instructor association.';
$string['publish'] = 'Publish authorized grades';
$string['qrhelp'] = 'Refresh the image between check-ins. A QR code is accepted for up to two minutes inside the registration window.';
$string['reason'] = 'Reason';
$string['reasonhealth'] = 'Health or personal circumstances';
$string['reasoninstitution'] = 'Institutional activity';
$string['reasonlabel'] = 'Decision reason';
$string['reasonother'] = 'Other authorized reason';
$string['reasons'] = 'Justification reason list';
$string['reasons_help'] = 'List up to ten reasons, one per line, up to 100 characters each. Leave blank to use the built-in list.';
$string['recorded'] = 'A session with attendance records cannot change its dates or group.';
$string['refreshqr'] = 'Refresh QR';
$string['reject'] = 'Reject justification';
$string['report'] = 'Attendance report';
$string['reporthelp'] = 'Counts cover finalized sessions. Present and late count as attendance. Excused results are excluded from the percentage. Course-wide views apply the current activity thresholds to the authorized results.';
$string['response'] = 'Decision explanation';
$string['result'] = 'Result';
$string['reviewer'] = 'Reviewer';
$string['reviewrequest'] = 'Justification details';
$string['risk'] = 'Risk indicator';
$string['risk_absences'] = 'Absence limit exceeded';
$string['risk_consecutive'] = 'Consecutive absences';
$string['risk_minimum'] = 'Below the minimum rate';
$string['riskonly'] = 'Students with a risk indicator only';
$string['roster'] = 'Student roster';
$string['saved'] = 'Attendance information saved.';
$string['savemarks'] = 'Save attendance';
$string['savesession'] = 'Save session';
$string['scanqr'] = 'Scan the current QR code shown by the instructor to check in.';
$string['session_cancelled'] = 'Cancelled';
$string['session_final'] = 'Finalized';
$string['session_scheduled'] = 'Scheduled';
$string['sessionid'] = 'Session ID';
$string['sessionname'] = 'Session name';
$string['sessionqr'] = 'Session check-in QR';
$string['sessions'] = 'Sessions';
$string['skillupsattendance:addinstance'] = 'Add an Attendance Pro activity';
$string['skillupsattendance:audit'] = 'See scoped attendance history';
$string['skillupsattendance:export'] = 'Export scoped attendance reports';
$string['skillupsattendance:mark'] = 'Mark, correct and finalize authorized sessions';
$string['skillupsattendance:publish'] = 'Authorize a Gradebook snapshot';
$string['skillupsattendance:recordown'] = 'Record own attendance and submit justifications';
$string['skillupsattendance:report'] = 'See scoped attendance reports';
$string['skillupsattendance:review'] = 'Review justifications and their evidence';
$string['skillupsattendance:sessions'] = 'Manage attendance sessions and display access QR';
$string['skillupsattendance:view'] = 'View attendance activity';
$string['stale'] = 'The session changed or was closed. Reload it before continuing.';
$string['starts'] = 'Start';
$string['state_absent'] = 'Absent';
$string['state_excused'] = 'Excused';
$string['state_late'] = 'Late';
$string['state_pending'] = 'Pending';
$string['state_present'] = 'Present';
$string['statement'] = 'Student explanation';
$string['status'] = 'Status';
$string['student'] = 'Student';
$string['studentsatrisk'] = 'Students with a risk indicator';
$string['submitjust'] = 'Submit justification';
$string['system'] = 'System';
$string['taskmaintain'] = 'Finalize Attendance Pro sessions and send risk warnings';
$string['teacherid'] = 'Instructor user ID';
$string['teacherid_help'] = 'Choose a Moodle user with attendance marking permission for the selected group. An empty value uses the current instructor.';
$string['unavailable'] = 'This attendance record is unavailable for your scope.';
$string['userid'] = 'Student user ID';
$string['warningbody'] = 'An attendance indicator needs your attention. Open the activity to review your records and contact your instructor if needed.';
$string['warningtitle'] = 'Review your attendance';
$string['when'] = 'Recorded on';
