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
 * English strings for SkillUps Privacy Manager.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accessretention'] = 'Retention of dashboard audit events in days';
$string['accessretentionhelp'] = 'Scheduled cleanup of privileged dashboard and export access logs. Effective range 30–730 days.';
$string['activecount'] = 'Active cases';
$string['actor'] = 'Operator';
$string['archived'] = 'This privacy case is no longer available.';
$string['assess'] = 'Review data retention';
$string['busy'] = 'The register is busy. Try the operation again.';
$string['casedata'] = 'Case details';
$string['casedetail'] = 'Privacy case #{$a}';
$string['caseid'] = 'Case ID';
$string['componentsearch'] = 'Component or extension name';
$string['confirmdecision'] = 'Confirm internal decision';
$string['deadline'] = 'Operational deadline';
$string['decision'] = 'Record a case decision';
$string['decisionhelp'] = 'Internal approval authorizes referral. Review all affected systems and conservation obligations for requests involving deletion or anonymization.';
$string['duedays'] = 'Default operational deadline in days';
$string['duedayshelp'] = 'Initial internal deadline for new cases. Effective range 1–365 days.';
$string['dueonly'] = 'Overdue only';
$string['edition'] = 'Edition';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Expired entitlement';
$string['edition_grace'] = 'Offline grace period';
$string['edition_professional'] = 'Professional';
$string['empty'] = 'No records match this view.';
$string['eventcaseactivity'] = 'Privacy register operation recorded';
$string['evidence'] = 'Download evidence CSV';
$string['extended'] = 'Include revision and reconciliation timestamps';
$string['filter'] = 'Apply filters';
$string['heldcount'] = 'Active retention holds';
$string['history'] = 'Case history';
$string['intakehelp'] = 'Use Moodle IDs. Review scope can be the site, a category, a course or the subject’s user context. Use Moodle requests to link an existing native export or deletion request.';
$string['invalidcase'] = 'Check the subject, case type and review context.';
$string['invaliddecision'] = 'This decision is unavailable for the current case.';
$string['invalidroute'] = 'Choose an authorized privacy officer and a valid deadline.';
$string['inventoryhelp'] = 'This inventory shows the technical declarations published by installed Moodle extensions. Review their data practices separately.';
$string['kind'] = 'Request type';
$string['kind_access'] = 'Access';
$string['kind_anonymize'] = 'Anonymization';
$string['kind_delete'] = 'Deletion';
$string['kind_export'] = 'Export';
$string['licenseend'] = 'License expiry';
$string['messagebody'] = 'An assigned case is approaching or has passed its operational deadline. Open the privacy register to review your cases.';
$string['messageprovider:deadline'] = 'Privacy case deadline notifications';
$string['messagesubject'] = 'Privacy case deadline';
$string['metadata_declared'] = 'Privacy declaration available';
$string['metadata_incomplete'] = 'Incomplete declaration';
$string['metadata_nodata'] = 'Declares no personal data';
$string['mine'] = 'Assigned to me';
$string['native_approved'] = 'Approved in Moodle';
$string['native_awaiting'] = 'Awaiting approval';
$string['native_cancelled'] = 'Cancelled';
$string['native_complete'] = 'Complete';
$string['native_deleted'] = 'Deletion completed';
$string['native_expired'] = 'Download expired';
$string['native_missing'] = 'Reference unavailable';
$string['native_pending'] = 'Pending';
$string['native_preprocessing'] = 'Preprocessing';
$string['native_processing'] = 'Processing';
$string['native_ready'] = 'Ready for download';
$string['native_rejected'] = 'Rejected';
$string['native_unknown'] = 'Unknown status';
$string['native_unlinked'] = 'Standalone case';
$string['nativehelp'] = 'Moodle remains responsible for native request decisions, processing and file delivery.';
$string['nativeid'] = 'Moodle request';
$string['nativeinvalid'] = 'The Moodle reference does not match the subject and case type.';
$string['nativelinked'] = 'This Moodle request is already tracked.';
$string['nativepending'] = 'Confirm completion in the linked Moodle request before closing this case.';
$string['nativestate'] = 'Moodle status';
$string['newstate'] = 'New internal status';
$string['nopurpose'] = 'Moodle has no effective purpose for this selection.';
$string['notice_failed'] = 'Failed';
$string['notice_sending'] = 'Sending';
$string['notice_sent'] = 'Sent';
$string['notice_skipped'] = 'Skipped';
$string['notice_uncertain'] = 'Delivery uncertain';
$string['notifyenabled'] = 'Enable Moodle deadline notifications';
$string['notifyenabledhelp'] = 'Send a generic warning and overdue notice to the assigned officer when eligible.';
$string['notrecorded'] = 'Unavailable';
$string['op_created'] = 'Case registered';
$string['op_export'] = 'Evidence downloaded';
$string['op_notified'] = 'Reminder processed';
$string['op_refresh'] = 'Manual status refresh';
$string['op_retention'] = 'Retention assessment recorded';
$string['op_routed'] = 'Assignment or deadline changed';
$string['op_synced'] = 'Moodle status changed';
$string['op_transition'] = 'Decision recorded';
$string['op_viewaudit'] = 'Access audit viewed';
$string['op_viewcase'] = 'Case viewed';
$string['op_viewintake'] = 'Registration form viewed';
$string['op_viewinventory'] = 'Inventory viewed';
$string['op_viewnative'] = 'Native requests viewed';
$string['op_viewpolicy'] = 'Retention policy viewed';
$string['op_viewregistry'] = 'Register viewed';
$string['op_viewreport'] = 'Report viewed';
$string['opencase'] = 'Open tracked case';
$string['opennative'] = 'Open Moodle data requests';
$string['openpolicy'] = 'Open Moodle data registry';
$string['operation'] = 'Operation';
$string['overduecount'] = 'Overdue cases';
$string['owner'] = 'Responsible officer user ID';
$string['pluginname'] = 'SkillUps Privacy Manager';
$string['policyhelp'] = 'Look up the effective Moodle purpose for a site, category or course context.';
$string['previous'] = 'Previous';
$string['privacy:metadata:actorid'] = 'User who performed the operation.';
$string['privacy:metadata:archived'] = 'Whether the case is unavailable to operators.';
$string['privacy:metadata:audit'] = 'History of privileged accesses and decisions.';
$string['privacy:metadata:case'] = 'Internal privacy request case.';
$string['privacy:metadata:caseid'] = 'Reference to the internal case.';
$string['privacy:metadata:checkedat'] = 'When Moodle status was last checked.';
$string['privacy:metadata:contextid'] = 'Moodle scope for the retention review.';
$string['privacy:metadata:createdby'] = 'Officer who registered the case.';
$string['privacy:metadata:due'] = 'Operational deadline.';
$string['privacy:metadata:kind'] = 'Institutional request type.';
$string['privacy:metadata:level'] = 'Reminder urgency tier.';
$string['privacy:metadata:message'] = 'Generic case deadline notifications sent through Moodle messaging.';
$string['privacy:metadata:nativeid'] = 'Reference to the native request.';
$string['privacy:metadata:nativestatus'] = 'Latest observed Moodle request status.';
$string['privacy:metadata:note'] = 'Brief restricted decision justification.';
$string['privacy:metadata:notice'] = 'Deadline notification delivery record.';
$string['privacy:metadata:operation'] = 'Operation recorded for accountability.';
$string['privacy:metadata:ownerid'] = 'Officer responsible for the case.';
$string['privacy:metadata:previous'] = 'Previous operational value.';
$string['privacy:metadata:priorid'] = 'Previously responsible officer.';
$string['privacy:metadata:result'] = 'Resulting operational value.';
$string['privacy:metadata:retention'] = 'Internal retention assessment.';
$string['privacy:metadata:revision'] = 'Case revision used for concurrency.';
$string['privacy:metadata:state'] = 'Internal workflow status.';
$string['privacy:metadata:status'] = 'Reminder delivery outcome.';
$string['privacy:metadata:targetid'] = 'New responsible officer.';
$string['privacy:metadata:timecreated'] = 'When the record was written.';
$string['privacy:metadata:timemodified'] = 'When the case was last edited.';
$string['privacy:metadata:userid'] = 'The subject or notification recipient.';
$string['protected'] = 'A protected or unavailable review context prevents clearance for this case.';
$string['protection'] = 'Protected in Moodle';
$string['purpose'] = 'Moodle purpose';
$string['reason'] = 'Reason';
$string['reasonrequired'] = 'Enter a reason between 1 and 300 characters.';
$string['refresh'] = 'Refresh Moodle status';
$string['register'] = 'Register case';
$string['registered'] = 'Recorded on';
$string['reminders'] = 'Reminders';
$string['reporthelp'] = 'Counts reflect the current operational register and exclude removed cases. The report documents internal activity.';
$string['restricted'] = 'Access requires a Moodle site privacy officer or administrator with the corresponding SkillUps capability.';
$string['result'] = 'Result';
$string['retention'] = 'Retention assessment';
$string['retention_cleared'] = 'Cleared for referral';
$string['retention_held'] = 'Retention hold';
$string['retention_pending'] = 'Assessment pending';
$string['retentionperiod'] = 'Retention period';
$string['retentionrequired'] = 'Record a retention clearance before approving this referral.';
$string['reviewcontext'] = 'Review context ID (0 for the site)';
$string['routing'] = 'Assignment and deadline';
$string['saveassessment'] = 'Save assessment';
$string['saved'] = 'The case was updated.';
$string['skillupsprivacy:decide'] = 'Review retention, decide cases and read privileged access logs';
$string['skillupsprivacy:export'] = 'Download privacy operation evidence';
$string['skillupsprivacy:inventory'] = 'View Privacy API declarations and retention';
$string['skillupsprivacy:manage'] = 'Register and route privacy cases';
$string['skillupsprivacy:view'] = 'View the privacy register';
$string['stale'] = 'This case changed or was closed. Reload it before continuing.';
$string['state'] = 'Internal status';
$string['state_approved'] = 'Approved for referral';
$string['state_closed'] = 'Closed';
$string['state_open'] = 'Open';
$string['state_rejected'] = 'Rejected';
$string['state_review'] = 'In review';
$string['subject'] = 'Subject user ID';
$string['systemactor'] = 'System or anonymized operator';
$string['taskreconcile'] = 'Reconcile privacy cases and send deadline notifications';
$string['track'] = 'Track request';
$string['updaterouting'] = 'Save assignment';
$string['view_audit'] = 'Access audit';
$string['view_cases'] = 'Case register';
$string['view_inventory'] = 'Data inventory';
$string['view_native'] = 'Moodle requests';
$string['view_policy'] = 'Retention policy';
$string['view_reports'] = 'Reports';
$string['warningdays'] = 'Deadline warning window in days';
$string['warningdayshelp'] = 'Warn owners when an active case is due within this window. Effective range 1–30 days.';
