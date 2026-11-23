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
 * Language strings for Smart Enrollment Manager.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Operation';
$string['action_enrol'] = 'Enroll or add a role';
$string['action_invalid'] = 'Unresolved input';
$string['action_reactivate'] = 'Reactivate enrollment';
$string['action_role'] = 'Add role to existing enrollment';
$string['action_suspend'] = 'Suspend enrollment';
$string['activeenrolment'] = 'Enrollment status active';
$string['allrows'] = 'Show all rows';
$string['applyfilter'] = 'Apply filters';
$string['attemptlimit'] = 'The row reached its retry limit.';
$string['attempts'] = 'Attempts';
$string['batchsize'] = 'Row limit for this batch';
$string['busy'] = 'Another enrollment operation is active. Try again shortly.';
$string['canceljob'] = 'Cancel pending work';
$string['changed'] = 'The enrollment changed after simulation. Submit a new simulation for this row.';
$string['confirm'] = 'Confirm valid rows';
$string['count'] = 'Count';
$string['courseid'] = 'Course ID';
$string['cronhelp'] = 'Moodle cron processes the batch from the scheduled time.';
$string['csv'] = 'Download CSV';
$string['defaultmethod'] = 'Use manual if no instance is specified';
$string['denied'] = 'This record or operation is unavailable to the current user.';
$string['dispatch'] = 'Dispatch due enrollment batches';
$string['duplicate'] = 'Another valid row targets the same user and enrollment instance.';
$string['emptyimport'] = 'The batch has no valid rows ready to confirm.';
$string['errors'] = 'Errors';
$string['eventoperation'] = 'Enrollment batch operation';
$string['excel'] = 'Download Excel';
$string['executionerror'] = 'Moodle could not apply this row. Check the site and retry it if appropriate.';
$string['expires'] = 'Expires {$a}';
$string['forecastchange'] = 'Requested change is pending';
$string['forecastnoop'] = 'Current state already matches the request';
$string['formula'] = 'Use literal values in the first worksheet. Formula and error cells are not accepted.';
$string['groupfailed'] = 'Moodle could not add the group member. This row was rolled back.';
$string['groupid'] = 'Group ID';
$string['groupinactive'] = 'Reactivate the enrollment before adding a group in a new batch.';
$string['groupname'] = 'Group ID {$a}';
$string['history'] = 'Batch history';
$string['immediate'] = 'Next cron run';
$string['import'] = 'Import CSV or XLSX';
$string['importdesc'] = 'Review the plans and errors before authorizing a batch.';
$string['importlimit'] = 'The number of rows exceeds the chosen or permitted batch size.';
$string['installationid'] = 'Installation ID {$a}';
$string['instanceid'] = 'Enrollment instance ID';
$string['invalidaction'] = 'The requested operation is no longer available.';
$string['invalidcourse'] = 'Choose one valid course reference.';
$string['invalidfactory'] = 'The referenced Course Factory result is unavailable. Use a completed authorized row.';
$string['invalidfile'] = 'The upload is not a supported CSV or XLSX file, or exceeds a safety limit.';
$string['invalidgroup'] = 'The group is unavailable, belongs to another course or cannot be managed.';
$string['invalidheader'] = 'Use only documented column names and include the action header.';
$string['invalidmethod'] = 'Choose one enabled native enrollment instance that supports the action.';
$string['invalidrole'] = 'The selected role is unavailable or cannot be assigned in this course.';
$string['invalidrow'] = 'Check the requested action and supported fields.';
$string['invalidschedule'] = 'Choose an execution date within one year.';
$string['invaliduser'] = 'Choose an existing, eligible local Moodle user.';
$string['job'] = 'Batch';
$string['jobdetails'] = 'Enrollment batch details';
$string['jobid'] = 'Batch ID';
$string['jobref'] = 'Batch {$a}';
$string['license'] = 'License status';
$string['license_community'] = 'Community edition';
$string['license_enterprise'] = 'Enterprise edition';
$string['license_expired'] = 'License expired';
$string['license_grace'] = 'License grace period';
$string['license_professional'] = 'Professional edition';
$string['line'] = 'Row';
$string['manual'] = 'Manual enrollment';
$string['maxbatch'] = 'Maximum rows per batch';
$string['maxbatchdesc'] = 'Set the maximum number of rows accepted by a simulated batch.';
$string['method'] = 'Enrollment method';
$string['methodname'] = 'Method {$a}';
$string['missingenrolment'] = 'No enrollment in this instance';
$string['modified'] = 'Last update';
$string['noenrolment'] = 'The selected method has no enrollment for this user.';
$string['nohistory'] = 'There are no enrollment batches yet.';
$string['norows'] = 'There are no visible rows for this filter.';
$string['onlyerrors'] = 'Show only errors';
$string['pluginname'] = 'SkillUps Smart Enrollment Manager';
$string['preview'] = 'Run simulation';
$string['previewhelp'] = 'Review the current state and requested effects. Confirm to process valid rows.';
$string['privacy:detail'] = 'The authorized requested action and enrollment evidence.';
$string['privacy:enrol'] = 'Moodle manages the resulting course enrollments.';
$string['privacy:job'] = 'Stores batch ownership and scheduling information.';
$string['privacy:operator'] = 'The user who submitted the batch.';
$string['privacy:participant'] = 'The user affected by the enrollment request.';
$string['privacy:row'] = 'Stores the identity and outcome of each processed row.';
$string['privacy:time'] = 'Time when operational metadata was recorded or modified.';
$string['purge'] = 'Purge expired enrollment metadata';
$string['reason'] = 'Result or error';
$string['reporthelp'] = 'Filter the operation ledger by course, user or batch ID. Reports show actions handled by this plugin.';
$string['reports'] = 'Enrollment reports';
$string['results'] = 'Enrollment results';
$string['retention'] = 'History retention in days';
$string['retentiondesc'] = 'Keep completed operations from 30 to 730 days. Drafts expire after seven days.';
$string['retry'] = 'Retry this row';
$string['role'] = 'Role ID or short name';
$string['rolename'] = 'Role ID {$a}';
$string['runat'] = 'Scheduled start';
$string['self'] = 'Self enrollment';
$string['settings'] = 'Smart Enrollment Manager settings';
$string['sheet'] = 'CSV or XLSX file';
$string['sheethelp'] = 'Use comma-delimited UTF-8 CSV or the first XLSX worksheet. Include action, a user reference and a course reference.';
$string['shortname'] = 'Course short name';
$string['simulation'] = 'Simulation evidence';
$string['single'] = 'Simulate one record';
$string['singledesc'] = 'Check one enrollment, role or status change.';
$string['singlehelp'] = 'Enter one user reference and one course reference. Role accepts a Moodle role ID or short name.';
$string['skillupsenrollment:manage'] = 'View and cancel site enrollment batches';
$string['skillupsenrollment:operate'] = 'Simulate and run enrollment operations';
$string['state'] = 'State';
$string['state_applied'] = 'Applied';
$string['state_cancelled'] = 'Cancelled';
$string['state_done'] = 'Processed';
$string['state_draft'] = 'Simulation';
$string['state_failed'] = 'Failed';
$string['state_invalid'] = 'Invalid';
$string['state_noop'] = 'Already satisfied';
$string['state_pending'] = 'Ready';
$string['state_queued'] = 'Queued';
$string['stopping'] = 'Pending actions are being cancelled.';
$string['submitted'] = 'The batch was updated.';
$string['summary'] = 'Batch summary';
$string['suspendedenrolment'] = 'Enrollment status suspended';
$string['userid'] = 'User ID';
$string['username'] = 'Username';
