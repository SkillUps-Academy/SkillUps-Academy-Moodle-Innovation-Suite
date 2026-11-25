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
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['academic'] = 'Academic communication';
$string['accepted'] = 'Accepted by channel';
$string['activity'] = 'Activity message';
$string['actor'] = 'Actor';
$string['any'] = 'Any';
$string['approve'] = 'Approve and queue';
$string['attempts'] = 'Attempts';
$string['audiencelimit'] = 'Review the site recipient limit or specify a narrower audience.';
$string['audit'] = 'History';
$string['automation'] = 'Scheduled task';
$string['back'] = 'Back';
$string['batchsize'] = 'Recipients per background task';
$string['batchsize_help'] = 'Each Adhoc Task handles between 1 and 100 recipients.';
$string['body'] = 'Message';
$string['bothnotice'] = 'Moodle notification and email';
$string['busy'] = 'A batch or edit is running. Try again shortly.';
$string['campus'] = 'Campus announcement';
$string['campusid'] = 'Campus';
$string['cancel'] = 'Cancel communication';
$string['cancelled'] = 'Cancelled';
$string['catalog'] = 'Templates and settings';
$string['channel'] = 'Channel';
$string['channelunavailable'] = 'Channel unavailable';
$string['cohortid'] = 'Cohort';
$string['complete'] = 'Complete';
$string['confirmationhelp'] = 'Confirm to save this transition. Previewing recipients does not send any messages.';
$string['confirmoperation'] = 'I confirm this operation';
$string['confirmrequired'] = 'Confirm the operation before saving.';
$string['connectorname'] = 'Installed connector ({$a})';
$string['connectorrejected'] = 'Connector rejected the request before handoff.';
$string['connectors'] = 'Authorized connector classes';
$string['connectors_help'] = 'Optional. Enter one installed Moodle connector class per line. Leave blank to disable external connectors.';
$string['courseid'] = 'Course';
$string['created'] = 'Draft created';
$string['delivered'] = 'Delivery confirmed';
$string['disabledchannel'] = 'The selected connector was disabled.';
$string['draft'] = 'Draft';
$string['duplicate'] = 'That name is already in use in this catalogue.';
$string['edit'] = 'Edit';
$string['editcampaign'] = 'Edit draft';
$string['edited'] = 'Draft edited';
$string['emailnotice'] = 'Email through Moodle';
$string['empty'] = 'No records match the current view.';
$string['emptytarget'] = 'The current filters have no eligible recipients.';
$string['eventtransition'] = 'Communication campaign transition';
$string['failed'] = 'Failed';
$string['failure'] = 'Outcome detail';
$string['filter'] = 'Apply filter';
$string['finished'] = 'Processing completed';
$string['groupid'] = 'Group';
$string['handoffunknown'] = 'The attempt has no reliable handoff outcome.';
$string['immediate'] = 'As soon as approved';
$string['incompatible'] = 'The selected campus and program map to different category branches.';
$string['institutional'] = 'Institutional notice';
$string['interrupted'] = 'Previous process ended during handoff.';
$string['invalid'] = 'Check the required fields and their permitted lengths.';
$string['invalidgroup'] = 'Select a group within the selected course and your authorized group scope.';
$string['invalidmembers'] = 'One or more accounts are invalid, inactive or outside this scope.';
$string['kind'] = 'Communication type';
$string['maxrecipients'] = 'Maximum recipients per campaign';
$string['maxrecipients_help'] = 'The limit applies before review. Supported range is 1 to 50,000.';
$string['messageprovider:bothnotice'] = 'Institutional notifications and email';
$string['messageprovider:emailnotice'] = 'Institutional email notifications';
$string['messageprovider:webnotice'] = 'Institutional Moodle notifications';
$string['metrichelp'] = 'Acceptance records a handoff. Delivery is counted only when a connector confirms it. Read and open activity is unavailable.';
$string['moodlerejected'] = 'Moodle rejected the message before recording it.';
$string['name'] = 'Name';
$string['newcampaign'] = 'New communication';
$string['newentry'] = 'Add entry';
$string['nothingtoretry'] = 'There are no eligible failures to retry.';
$string['operation'] = 'Operation';
$string['operational'] = 'Operational alert';
$string['pending'] = 'Pending';
$string['period'] = 'Academic period notice';
$string['pluginname'] = 'SkillUps Communication Center';
$string['preview'] = 'Preview recipients';
$string['privacy:camp'] = 'Campaign content and editorial actor references.';
$string['privacy:field:authorid'] = 'Account that last prepared the template.';
$string['privacy:field:body'] = 'Editorial message content.';
$string['privacy:field:creator'] = 'Account that created or submitted a record.';
$string['privacy:field:messageid'] = 'Reference to a native notification.';
$string['privacy:field:reviewer'] = 'Account that approved a campaign.';
$string['privacy:field:state'] = 'Processing result.';
$string['privacy:field:subject'] = 'Editorial message subject.';
$string['privacy:field:userid'] = 'Recipient or operation actor account.';
$string['privacy:log'] = 'Actor and campaign transitions.';
$string['privacy:member'] = 'Membership in explicit recipient sets.';
$string['privacy:message'] = 'Approved messages handed to Moodle Messaging.';
$string['privacy:recipient'] = 'Recipient identity and processing status.';
$string['privacy:segment'] = 'Managed audience definitions.';
$string['privacy:template'] = 'Template and author references.';
$string['processed'] = 'Processed';
$string['program'] = 'Program announcement';
$string['programid'] = 'Program';
$string['queued'] = 'Queued';
$string['recipient'] = 'Recipient';
$string['recipients'] = 'Recipients and outcomes';
$string['reference'] = 'Moodle reference';
$string['reminder'] = 'Reminder';
$string['retry'] = 'Retry eligible failures';
$string['return'] = 'Return to draft';
$string['review'] = 'In review';
$string['roleid'] = 'Role';
$string['running'] = 'Processing';
$string['saved'] = 'Operation confirmed.';
$string['scopechanged'] = 'Authorization or membership changed';
$string['scopeid'] = 'Institutional scope';
$string['segmenthelp'] = 'Map a program or campus to a category. For a custom set, enter account usernames from the current scope.';
$string['segmentkind'] = 'Segment type';
$string['segments'] = 'Institutional segments';
$string['selectorhelp'] = 'Selected filters are intersected. A group requires its course. Leave scheduling disabled for dispatch after approval.';
$string['sendat'] = 'Scheduled date and time';
$string['sending'] = 'Handing off';
$string['set'] = 'Custom account set';
$string['setid'] = 'Custom set';
$string['settings'] = 'Communication Center settings';
$string['site'] = 'Site-wide scope';
$string['siteadmins'] = 'Moodle site administrators';
$string['skillupscommunication:approve'] = 'Approve and retry scoped communications';
$string['skillupscommunication:compose'] = 'Compose and submit communications for review';
$string['skillupscommunication:configure'] = 'Manage templates and institutional segments';
$string['skillupscommunication:view'] = 'View scoped communication history';
$string['skipped'] = 'Skipped';
$string['stale'] = 'The communication or its audience changed. Reload and review it again.';
$string['state'] = 'Status';
$string['subject'] = 'Subject';
$string['submit'] = 'Submit for review';
$string['targetcontext'] = 'Mapped Moodle category';
$string['targetcount'] = 'Eligible recipients ({$a})';
$string['taskrecover'] = 'Recover pending communication campaigns';
$string['templates'] = 'Templates';
$string['unavailable'] = 'This record or operation is unavailable in your scope.';
$string['uncertain'] = 'Uncertain outcome';
$string['unknownuser'] = 'Account unavailable';
$string['updated'] = 'Last update';
$string['usernames'] = 'One Moodle username per line';
$string['usetemplate'] = 'Use template';
$string['webnotice'] = 'Moodle notification';
