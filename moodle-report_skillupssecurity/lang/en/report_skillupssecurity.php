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
 * English strings.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['about_debug'] = 'Debug output is configured to be displayed by Moodle.';
$string['about_defaultrole'] = 'A default role allows a selected administrative capability in its native scope.';
$string['about_guest'] = 'The guest login button or automatic guest entry is enabled.';
$string['about_https'] = 'Moodle\'s configured URL lacks the HTTPS scheme.';
$string['about_inactive'] = 'This active account meets the configured inactivity threshold.';
$string['about_login'] = 'Moodle does not require login for all general site pages.';
$string['about_passwordpolicy'] = 'The local Moodle password policy is disabled.';
$string['about_privilegedinactive'] = 'A privileged account meets the configured inactivity threshold.';
$string['about_registration'] = 'A self-registration method is configured in Moodle.';
$string['about_suspendedprivilege'] = 'A suspended account retains a site administrator designation or qualifying role grants.';
$string['about_unusualscope'] = 'A teaching, learner or guest archetype has an explicit system assignment.';
$string['account'] = 'Account';
$string['accountlimit'] = 'Inactive account sample limit';
$string['accountlimitdesc'] = 'Maximum affected accounts examined in one scan. Accepted range 100–5000.';
$string['action_capabilities'] = 'Role capabilities edited';
$string['action_configuration'] = 'Sensitive setting changed';
$string['action_exportevents'] = 'Activity log exported';
$string['action_exportfindings'] = 'Findings exported';
$string['action_exportinventory'] = 'Privilege inventory exported';
$string['action_impersonation'] = 'Logged in as another user';
$string['action_queuescan'] = 'Scan queued';
$string['action_review'] = 'Case reviewed';
$string['action_roleassigned'] = 'Role assigned';
$string['action_rolecreated'] = 'Role created';
$string['action_roledeleted'] = 'Role deleted';
$string['action_rolepolicy'] = 'Role relationship edited';
$string['action_roleunassigned'] = 'Role unassigned';
$string['action_roleupdated'] = 'Role updated';
$string['action_viewalerts'] = 'Alert deliveries opened';
$string['action_viewdashboard'] = 'Dashboard opened';
$string['action_viewdetail'] = 'Finding opened';
$string['action_viewevents'] = 'Activity log opened';
$string['action_viewfindings'] = 'Findings opened';
$string['action_viewinventory'] = 'Privilege inventory opened';
$string['action_viewroles'] = 'Role matrix opened';
$string['active'] = 'Observed';
$string['actor'] = 'Actor';
$string['alert_failed'] = 'Delivery failed';
$string['alert_sending'] = 'Dispatch claimed';
$string['alert_sent'] = 'Accepted by Moodle messaging';
$string['alert_skipped'] = 'Skipped or rejected';
$string['alert_unknown'] = 'Delivery uncertain';
$string['alert_waiting'] = 'Pending';
$string['alertbody'] = 'Open the protected Security Center report to review the latest changes.';
$string['alerts'] = 'Alert deliveries';
$string['alertsubject'] = 'Security Center has new findings';
$string['all'] = 'All';
$string['alreadyqueued'] = 'A security scan is queued or was requested recently.';
$string['archetype'] = 'Role archetype';
$string['assignmentlimit'] = 'Explicit role assignment limit';
$string['assignmentlimitdesc'] = 'Maximum system, category and course assignments sampled. Accepted range 100–10000.';
$string['busy'] = 'Another audit is updating records. Try again shortly.';
$string['capabilities'] = 'Selected capabilities';
$string['complete'] = 'Evaluated within defined scope';
$string['context'] = 'Context';
$string['coverage'] = 'Control coverage';
$string['coveragewarning'] = 'This audit covers selected Moodle signals. Its results alone cannot establish the security of the site.';
$string['dashboard'] = 'Security overview';
$string['date'] = 'Date';
$string['detail'] = 'Finding detail';
$string['empty'] = 'There are no records for this selection.';
$string['eventaccessed'] = 'Security Center report operation';
$string['events'] = 'Administrative activity';
$string['evidence'] = 'Evidence';
$string['export'] = 'Export evidence CSV';
$string['exporthelp'] = 'Includes up to 10,000 records in this selection.';
$string['filter'] = 'Apply filters';
$string['finding'] = 'Finding';
$string['findings'] = 'Findings';
$string['firstseen'] = 'First observed';
$string['fix_debug'] = 'Use private diagnostics and disable displayed debugging for production traffic.';
$string['fix_defaultrole'] = 'Review the specified role and permission definition against least privilege.';
$string['fix_guest'] = 'Confirm that guest entry is required and audit native course guest access separately.';
$string['fix_https'] = 'Review HTTPS termination and the canonical URL with the infrastructure owner.';
$string['fix_inactive'] = 'Confirm the owner\'s current needs and assess whether the account should remain active.';
$string['fix_login'] = 'Review expected public content and enable forced login where appropriate.';
$string['fix_passwordpolicy'] = 'Confirm which login methods use local passwords and apply the institution\'s policy.';
$string['fix_privilegedinactive'] = 'Review the grants with the account owner and remove obsolete privileges through Moodle.';
$string['fix_registration'] = 'Confirm the registration policy and review approval and monitoring controls.';
$string['fix_suspendedprivilege'] = 'Review the retained assignments before restoring the account.';
$string['fix_unusualscope'] = 'Verify the assignment\'s purpose and use the smallest context that meets the requirement.';
$string['history'] = 'Review history';
$string['inactivedays'] = 'Account inactivity threshold';
$string['inactivedaysdesc'] = 'Days since the most recent recorded site access, or account creation if none was recorded. Accepted range 7–730.';
$string['invalidreview'] = 'Select a valid status and provide a reason of at most 250 characters.';
$string['inventory'] = 'Privilege inventory';
$string['inventorydate'] = 'Inventory collected {$a}';
$string['inventorypartial'] = 'The assignment limit was reached. This inventory is partial.';
$string['kind_confirmed'] = 'Confirmed finding';
$string['kind_guide'] = 'Recommendation';
$string['kind_risk'] = 'Risk configuration';
$string['kind'] = 'Evidence class';
$string['lastaccess'] = 'Last access recorded';
$string['lastscan'] = 'Latest scan';
$string['lastseen'] = 'Last observed';
$string['messageprovider:risk'] = 'Security findings notification';
$string['metadata'] = 'Metadata';
$string['navigation'] = 'Security report navigation';
$string['none'] = 'None';
$string['noscan'] = 'Pending first scan';
$string['noscansyet'] = 'No coverage data has been collected yet.';
$string['notifyenabled'] = 'Enable Moodle notifications';
$string['notifyenableddesc'] = 'Recipients receive a generic message when new findings meet the selected priority.';
$string['notifyseverity'] = 'Minimum notification priority';
$string['notifyseveritydesc'] = 'High or medium. Medium also includes high-priority evidence.';
$string['notrecorded'] = 'Unavailable';
$string['operation'] = 'Operation';
$string['partial'] = 'Partial coverage';
$string['plan_community'] = 'Community';
$string['plan_enterprise'] = 'Enterprise';
$string['plan_expired'] = 'Expired';
$string['plan_grace'] = 'Grace period';
$string['plan_professional'] = 'Professional';
$string['plan'] = 'Plan {$a}';
$string['pluginname'] = 'SkillUps Security Center';
$string['presence'] = 'Observation status';
$string['privacy:metadata:action'] = 'Record type';
$string['privacy:metadata:activity'] = 'Administrative activity';
$string['privacy:metadata:actorid'] = 'Moodle actor';
$string['privacy:metadata:capabilities'] = 'Selected permissions';
$string['privacy:metadata:contextid'] = 'Moodle context';
$string['privacy:metadata:detail'] = 'Allowed administrative metadata';
$string['privacy:metadata:evidence'] = 'Selected facts observed by the control';
$string['privacy:metadata:finding'] = 'Finding evidence';
$string['privacy:metadata:findingid'] = 'Finding reference';
$string['privacy:metadata:firstseen'] = 'Date first observed';
$string['privacy:metadata:history'] = 'Case review history';
$string['privacy:metadata:lastaccess'] = 'Last access reported by Moodle';
$string['privacy:metadata:lastseen'] = 'Date last observed';
$string['privacy:metadata:message'] = 'Moodle messages';
$string['privacy:metadata:note'] = 'Review reason';
$string['privacy:metadata:objectid'] = 'Referenced operation';
$string['privacy:metadata:outbox'] = 'Alert delivery';
$string['privacy:metadata:privilege'] = 'Privilege inventory';
$string['privacy:metadata:reviewstate'] = 'Case review status';
$string['privacy:metadata:roleid'] = 'Role identifier';
$string['privacy:metadata:ruleid'] = 'Control that generated the evidence';
$string['privacy:metadata:scanid'] = 'Scan identifier';
$string['privacy:metadata:state'] = 'Delivery state';
$string['privacy:metadata:subjectid'] = 'Affected Moodle account';
$string['privacy:metadata:suspended'] = 'Account suspension status';
$string['privacy:metadata:timecreated'] = 'Creation time';
$string['privacy:metadata:timesent'] = 'Send attempt time';
$string['privacy:metadata:userid'] = 'Observed or notified Moodle account';
$string['queued'] = 'A security scan was queued.';
$string['recipients'] = 'Recipient Moodle user IDs';
$string['recipientsdesc'] = 'Enter up to 20 IDs separated by commas. Each recipient needs permission to view the report.';
$string['recommendation'] = 'Recommended action';
$string['reference'] = 'Reference';
$string['resolved'] = 'No longer observed';
$string['retention'] = 'Administrative evidence retention';
$string['retentiondesc'] = 'Days to retain events, previous snapshots and resolved findings. Accepted range 7–730.';
$string['reviewnote'] = 'Reason for review decision';
$string['reviewsaved'] = 'The review was recorded.';
$string['reviewstate'] = 'Review status';
$string['role'] = 'Role';
$string['roleid'] = 'Role ID';
$string['roles'] = 'Role audit';
$string['rolesintro'] = 'Selected permissions are evaluated in the system context. The inventory shows qualifying assignments and the context in which each was inspected.';
$string['rule_debug'] = 'Displayed PHP debugging';
$string['rule_defaultrole'] = 'Default role with sensitive permissions';
$string['rule_guest'] = 'Guest entry options enabled';
$string['rule_https'] = 'Canonical URL uses HTTP';
$string['rule_inactive'] = 'Account without recent recorded access';
$string['rule_login'] = 'Review site login policy';
$string['rule_passwordpolicy'] = 'Moodle password policy disabled';
$string['rule_privilegedinactive'] = 'Privileged account without recent access';
$string['rule_registration'] = 'Self-registration configured';
$string['rule_suspendedprivilege'] = 'Suspended account with recorded privilege';
$string['rule_unusualscope'] = 'Unusual system role assignment';
$string['runscan'] = 'Queue security scan';
$string['savereview'] = 'Save review';
$string['scanref'] = 'Observation scan ID';
$string['setting'] = 'Setting';
$string['settings'] = 'Security Center policy';
$string['severity_high'] = 'High';
$string['severity_low'] = 'Low';
$string['severity_medium'] = 'Medium';
$string['severity'] = 'Priority';
$string['shortname'] = 'Short name';
$string['signature'] = 'Correlation fingerprint';
$string['siteadmin'] = 'Site administrator';
$string['skillupssecurity:export'] = 'Export authorized evidence';
$string['skillupssecurity:inventory'] = 'Read identifiable audit evidence';
$string['skillupssecurity:review'] = 'Record security finding reviews';
$string['skillupssecurity:run'] = 'Queue security scans';
$string['skillupssecurity:view'] = 'View Security Center reports';
$string['snapshots'] = 'Historical inventories';
$string['state_accepted'] = 'Risk accepted';
$string['state_detected'] = 'Detected';
$string['state_new'] = 'New';
$string['state_recurred'] = 'Observed again';
$string['state_resolved'] = 'No longer observed';
$string['state_reviewing'] = 'Under review';
$string['stream'] = 'Event source';
$string['stream_native'] = 'Native Moodle events';
$string['stream_report'] = 'Security Center operations';
$string['subject'] = 'Subject';
$string['suspended'] = 'Suspended';
$string['systemactor'] = 'System';
$string['taskcollect'] = 'Collect Security Center evidence';
$string['taskmaintain'] = 'Dispatch Security Center alerts and maintain retention';
$string['threshold'] = 'Threshold in days';
$string['timecreated'] = 'Creation date';
$string['unsupportedexport'] = 'Export is unavailable for this view.';
