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
 * Language strings for SkillUps Course Factory.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Action';
$string['allrows'] = 'Show all rows';
$string['ambiguous'] = 'Restore may have partially completed. Review the hidden course in Moodle before deciding how to proceed.';
$string['assignment'] = 'Enroll user ID {$a->userid} with role ID {$a->role}';
$string['attemptlimit'] = 'The row reached its retry limit.';
$string['attempts'] = 'Attempts';
$string['batchsize'] = 'Batch size';
$string['busy'] = 'Another course operation is active. Try again shortly.';
$string['canceljob'] = 'Cancel pending work';
$string['cancelledrow'] = 'The row was stopped before further changes.';
$string['category'] = 'Destination category';
$string['configuration'] = 'Configuration';
$string['confirm'] = 'Confirm operation';
$string['coordinatorrole'] = 'Coordinator enrollment role';
$string['coordinators'] = 'Coordinator IDs separated by semicolons';
$string['count'] = 'Count';
$string['courseid'] = 'Course ID';
$string['created'] = 'Created at';
$string['cronhelp'] = 'Moodle cron dispatches the operation at the scheduled time.';
$string['csv'] = 'Download CSV';
$string['denied'] = 'The operation or its source or destination is unavailable to this user.';
$string['dispatch'] = 'Dispatch due course factory operations';
$string['duplicate'] = 'The short name or identifier is already in use.';
$string['emptycourse'] = 'Create an empty course';
$string['emptyimport'] = 'The operation has no valid rows ready to confirm.';
$string['enddate'] = 'End date (YYYY-MM-DD)';
$string['eventoperation'] = 'Course Factory operation';
$string['excel'] = 'Download Excel';
$string['executionerror'] = 'Moodle could not complete this stage. Verify the course and server resources before retrying.';
$string['expires'] = 'Expires {$a}';
$string['formula'] = 'Use literal values in the first worksheet. Formula and error cells are not accepted.';
$string['fullname'] = 'Course name';
$string['hidden'] = 'Keep hidden';
$string['history'] = 'Operation history';
$string['idnumber'] = 'ID number';
$string['immediate'] = 'Next cron run';
$string['import'] = 'Import CSV or XLSX';
$string['importdesc'] = 'Review a spreadsheet of courses before scheduling it.';
$string['importlimit'] = 'The number of rows exceeds the chosen or permitted import size.';
$string['installationid'] = 'Installation ID {$a}';
$string['invalidaction'] = 'The requested operation is no longer available.';
$string['invaliddate'] = 'Use valid calendar dates and put the end on or after the start date.';
$string['invalidfile'] = 'The upload is not a supported CSV or XLSX file, or exceeds a safety limit.';
$string['invalidheader'] = 'Use the documented column names once and include the three required columns.';
$string['invalidrow'] = 'Check required names, lengths, numeric IDs and visibility.';
$string['invalidschedule'] = 'Choose an execution date within one year.';
$string['invalidstaff'] = 'Check the users, enrollment permissions and site-configured roles.';
$string['invalidtemplate'] = 'Choose one permitted source or template.';
$string['job'] = 'Operation';
$string['jobdetails'] = 'Operation details';
$string['jobref'] = 'Operation {$a}';
$string['license'] = 'License status';
$string['license_community'] = 'Community edition';
$string['license_enterprise'] = 'Enterprise edition';
$string['license_expired'] = 'License expired';
$string['license_grace'] = 'License grace period';
$string['license_professional'] = 'Professional edition';
$string['line'] = 'Row';
$string['manualmissing'] = 'The destination must have one enabled Manual enrollment instance.';
$string['maxbatch'] = 'Maximum records per request';
$string['maxbatchdesc'] = 'Choose the maximum size supported by this installation.';
$string['nohistory'] = 'There are no operations yet.';
$string['norows'] = 'There are no rows for this view.';
$string['notemplate'] = 'No template selected';
$string['onlyerrors'] = 'Show only errors';
$string['period'] = 'Academic period';
$string['periodfield'] = 'Period course field';
$string['periodfielddesc'] = 'Optional shortname of a course text custom field. Without this setting the period is recorded in the operation history.';
$string['periodunavailable'] = 'The configured period field is missing, has a different type or cannot be edited.';
$string['phase'] = 'Stage';
$string['phase_done'] = 'Finished';
$string['phase_new'] = 'Awaiting creation';
$string['phase_prepared'] = 'Hidden course created';
$string['phase_restored'] = 'Ready for final setup';
$string['phase_restoring'] = 'Restore started';
$string['pluginname'] = 'SkillUps Course Factory';
$string['preview'] = 'Prepare preview';
$string['previewhelp'] = 'Review the destination and roles. Confirm to process valid rows. Invalid rows remain in this report.';
$string['privacy:enrol'] = 'Moodle manages the resulting course enrollments.';
$string['privacy:job'] = 'Records the identity of the person who submitted an operation.';
$string['privacy:row'] = 'Contains the requested enrollment roles and user IDs.';
$string['privacy:staff'] = 'User IDs and roles to enroll in the destination.';
$string['privacy:time'] = 'Time of the operation request.';
$string['privacy:userid'] = 'Operation requester ID.';
$string['published'] = 'Make visible';
$string['purge'] = 'Purge expired course factory metadata';
$string['reason'] = 'Result or error';
$string['removetemplate'] = 'Remove template reference';
$string['restorecheck'] = 'Moodle restore prechecks found blocking errors. Review the source and target configuration.';
$string['results'] = 'Course results';
$string['retention'] = 'History retention in days';
$string['retentiondesc'] = 'Completed jobs are retained from 30 to 730 days. Drafts expire after seven days.';
$string['retry'] = 'Retry this row';
$string['roledefault'] = 'Use the role default';
$string['runat'] = 'Scheduled start';
$string['savetemplate'] = 'Save template';
$string['scheduledfor'] = 'Scheduled start {$a}';
$string['settings'] = 'Course Factory settings';
$string['sheet'] = 'CSV or XLSX file';
$string['sheethelp'] = 'Use a UTF-8 CSV separated by commas or the first worksheet of an XLSX file. The required columns are fullname, shortname and category.';
$string['shortname'] = 'Short name';
$string['single'] = 'Create or clone a course';
$string['singledesc'] = 'Choose a source or create an empty course.';
$string['skillupscoursefactory:manage'] = 'Manage templates and view or stop site operations';
$string['skillupscoursefactory:operate'] = 'Preview and run course factory operations';
$string['source'] = 'Source course';
$string['sourceoverride'] = 'Source ID when it is outside the list';
$string['startdate'] = 'Start date (YYYY-MM-DD)';
$string['state'] = 'State';
$string['state_attention'] = 'Manual review required';
$string['state_cancelled'] = 'Cancelled';
$string['state_done'] = 'Completed';
$string['state_draft'] = 'Preview';
$string['state_failed'] = 'Failed';
$string['state_invalid'] = 'Invalid';
$string['state_pending'] = 'Ready';
$string['state_queued'] = 'Queued';
$string['stopping'] = 'Cancelling remaining work. A restore already in progress may finish.';
$string['submitted'] = 'The operation was updated.';
$string['summary'] = 'Operation summary';
$string['targetchanged'] = 'The tracked destination was renamed, moved, shown or removed. Review it before continuing.';
$string['teacherrole'] = 'Teacher enrollment role';
$string['teachers'] = 'Teacher IDs separated by semicolons';
$string['templatehelp'] = 'A template refers to an existing course. Each request uses the source content available when the operation runs.';
$string['templateid'] = 'Template ID';
$string['templatename'] = 'Template name';
$string['templates'] = 'Institutional templates';
$string['templatesaved'] = 'Institutional template saved.';
$string['visible'] = 'Show course after all steps succeed';
