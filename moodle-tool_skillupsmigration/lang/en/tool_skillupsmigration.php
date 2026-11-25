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
 * English language strings.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Operation';
$string['activities'] = 'Include only these source activity IDs';
$string['activitydiff'] = 'Activity differences';
$string['activitylist'] = 'Activities and resources';
$string['actor'] = 'Operator ID';
$string['allocated'] = 'Hidden destination allocated';
$string['archivebounds'] = 'Archive limits or required storage bounds were exceeded.';
$string['archiveexpired'] = 'The verified staging file expired. Prepare the migration again.';
$string['archiveformat'] = 'The archive is not a supported native Moodle backup.';
$string['archiveio'] = 'Could not read or write the verified archive.';
$string['archivemismatch'] = 'Backup policy or metadata does not match the approved source.';
$string['archivepath'] = 'The archive contains an unsafe path.';
$string['archivesize'] = 'Archive size';
$string['audit'] = 'Audit trail';
$string['back'] = 'Back';
$string['backupfailed'] = 'The source failed to produce a usable native backup.';
$string['badrequest'] = 'The source request or byte range is invalid.';
$string['blocked'] = 'Blocked';
$string['blockedbatch'] = 'Resolve all preparation blockers before confirming execution.';
$string['browseorigin'] = 'Check and browse {$a}';
$string['busy'] = 'A migration worker is busy. Try later.';
$string['cancel'] = 'Cancel remaining work';
$string['cancelhelp'] = 'Cancel pending phases and keep any existing hidden destinations available for review.';
$string['cancelled'] = 'Cancelled';
$string['categoryid'] = 'Destination category';
$string['checkcount'] = 'Errors ({$a->errors}) and warnings ({$a->warnings})';
$string['complete'] = 'Batch completed';
$string['configuration'] = 'Preserve completion and group mode';
$string['confirmoperation'] = 'I confirm this operation';
$string['confirmrequired'] = 'Confirm before proceeding.';
$string['courses'] = 'Source course ID, new short name per line';
$string['created'] = 'Migration created';
$string['credential'] = 'The origin token is missing from its configured environment variable.';
$string['cronrunning'] = 'Moodle cron is processing this job. Reload this page to check progress.';
$string['datedifference'] = 'Course date differences';
$string['dates'] = 'Preserve course dates';
$string['deferred'] = 'Retry deferred';
$string['detail'] = 'Detail';
$string['difference'] = 'Differences found';
$string['done'] = 'Restored and verified';
$string['draft'] = 'Draft';
$string['duplicate'] = 'A source or destination short name is duplicated or already used.';
$string['empty'] = 'No records match this view.';
$string['endpoint'] = 'The registry endpoint is invalid or unsafe.';
$string['execute'] = 'Confirm restore';
$string['executehelp'] = 'Moodle creates new hidden courses. Confirm to allow the native restore process to begin.';
$string['extrasections'] = 'Additional destination sections';
$string['fileintegrity'] = 'A length or checksum does not match the expected artifact.';
$string['files'] = 'Include course files';
$string['groups'] = 'Groups and groupings';
$string['history'] = 'Migration history';
$string['identifier'] = 'ID';
$string['issues'] = 'Issue';
$string['job'] = 'Migration';
$string['jobnumber'] = 'Migration {$a}';
$string['missingplugin'] = 'A required destination plugin is missing or lacks backup support.';
$string['missingsections'] = 'Missing sections';
$string['name'] = 'Name';
$string['nativeprecheck'] = 'Moodle Restore reported warnings or errors.';
$string['newersource'] = 'The source Moodle version is newer than the destination.';
$string['newjob'] = 'New migration';
$string['next'] = 'Next page';
$string['nothingtoretry'] = 'There are no recoverable rows without a destination course.';
$string['olderplugin'] = 'The installed destination plugin is older than the source plugin.';
$string['opencourse'] = 'Open hidden destination course';
$string['operationfailed'] = 'The operation failed. Review the task and migration logs.';
$string['origin'] = 'Authorized origin';
$string['originmismatch'] = 'The origin identity changed or does not match the configured site.';
$string['originready'] = 'Origin and migration service verified. Moodle {$a}.';
$string['planhelp'] = 'Enter up to ten lines as course ID,shortname. Explicit section, activity and account choices apply to single-course plans. Separate IDs with commas. A mapping line uses source username,destination username,source role,destination role.';
$string['planned'] = 'Planned';
$string['pluginname'] = 'SkillUps Course Migration';
$string['preflight'] = 'Preparing simulation';
$string['privacy:actor'] = 'Moodle account associated with the operation.';
$string['privacy:artifact'] = 'Source account and private course artifact metadata.';
$string['privacy:audit'] = 'Operators associated with migration actions.';
$string['privacy:files'] = 'Private origin backups held for authorized transfer.';
$string['privacy:item'] = 'Explicit account mappings for a migration.';
$string['privacy:job'] = 'Local operators and migration campaigns.';
$string['privacy:options'] = 'Operator-supplied selection and account mappings.';
$string['questiontypes'] = 'Question types';
$string['ready'] = 'Verified for execution';
$string['registryempty'] = 'The server registry has no authorized origins.';
$string['remoteaccess'] = 'The native Moodle service refused the request. Check permissions and the token configuration.';
$string['remoteconnection'] = 'Could not establish the authorized HTTPS request.';
$string['remoteresponse'] = 'The origin returned an unexpected response.';
$string['restored'] = 'Native restore finished';
$string['restoreerror'] = 'The native restore did not finish successfully.';
$string['restoreinterrupted'] = 'A previous native restore may have written data. Review the hidden course before continuing.';
$string['restoring'] = 'Inside native restore';
$string['retry'] = 'Retry recoverable preparation';
$string['retryhelp'] = 'Only blocked rows without a destination course can start a new preparation. Existing courses are preserved.';
$string['review'] = 'Simulation completed';
$string['running'] = 'Restoring authorized courses';
$string['sectionlist'] = 'Sections';
$string['sections'] = 'Include only these source section IDs';
$string['selection'] = 'Check the selected course IDs, section IDs, activity IDs and batch limits.';
$string['servicemissing'] = 'The token does not expose the expected source service or protocol.';
$string['simulate'] = 'Run simulation';
$string['simulatehelp'] = 'The source generates a backup and the destination verifies its integrity and compatibility. This phase does not create a course.';
$string['skillupsmigration:export'] = 'Inventory and export authorized source courses';
$string['skillupsmigration:manage'] = 'Plan and execute course migrations';
$string['sourcecat'] = 'Source category';
$string['sourcechanged'] = 'The source inventory changed. Prepare a new migration.';
$string['staff'] = 'Explicit account and role mappings';
$string['stale'] = 'The migration changed. Reload and review the current status.';
$string['state'] = 'Status';
$string['supported'] = 'Native backup support';
$string['targetchanged'] = 'The tracked hidden destination was changed or is unavailable.';
$string['taskwatch'] = 'Recover and clean course migrations';
$string['topology'] = 'The archive topology differs from the selected inventory.';
$string['transfer'] = 'Transferring archive';
$string['type'] = 'Type';
$string['unavailable'] = 'The course, artifact or operation is outside your scope.';
$string['uncertain'] = 'Requires manual review';
$string['unsupported'] = 'Backup support unavailable';
$string['unsupportedactivity'] = 'An activity does not support native backup at the source.';
$string['updated'] = 'Last update';
$string['usersmap'] = 'An explicit account or role mapping is invalid or not assignable.';
$string['value'] = 'Value';
$string['verification'] = 'Post-restore verification';
$string['verified'] = 'Selected content matches';
$string['verify'] = 'Verifying destination';
$string['waiting'] = 'Waiting for source backup';
