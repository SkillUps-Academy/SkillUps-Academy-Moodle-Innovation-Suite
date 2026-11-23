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
 * English Helpdesk strings.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'You do not have access to this Helpdesk resource.';
$string['action_catalog'] = 'Catalog updated';
$string['action_comment'] = 'Public reply recorded';
$string['action_exportreport'] = 'Aggregate report exported';
$string['action_internalnote'] = 'Internal note recorded';
$string['action_manualescalation'] = 'Manual escalation recorded';
$string['action_notificationfailed'] = 'Notification delivery exhausted';
$string['action_opened'] = 'Ticket opened';
$string['action_rating'] = 'Satisfaction survey submitted';
$string['action_resolvebreach'] = 'Resolution deadline breached';
$string['action_responsebreach'] = 'First response deadline breached';
$string['action_triage'] = 'Classification updated';
$string['active'] = 'Active';
$string['adminhistory'] = 'Recent administrative activity';
$string['all'] = 'All';
$string['anonymous'] = 'Anonymous';
$string['applyfilters'] = 'Apply filters';
$string['assignedtome'] = 'Assigned to me';
$string['attachments'] = 'Attachments';
$string['attachments_help'] = 'Up to three PDF, PNG, JPEG or plain text files per message. The maximum is 5 MB per file or the lower site limit. Downloads require access to the ticket and the message.';
$string['busy'] = 'This record is being updated. Try again shortly.';
$string['cataloglimit'] = 'This catalog has reached its 500-record limit.';
$string['catalogsaved'] = 'Catalog entry saved.';
$string['catalogues'] = 'Support catalogs';
$string['comment'] = 'Reply';
$string['commentsaved'] = 'Message saved.';
$string['content'] = 'Content';
$string['conversation'] = 'Conversation';
$string['courseid'] = 'Course #{$a}';
$string['days'] = 'Last {$a} days';
$string['description'] = 'Describe the issue';
$string['details'] = 'Details';
$string['editentry'] = 'Edit entry';
$string['escalate'] = 'Escalate ticket';
$string['escalationrecent'] = 'This ticket was recently escalated. Wait 15 minutes before trying again.';
$string['escalationsaved'] = 'Escalation recorded.';
$string['escalationuserid'] = 'Escalation supervisor user ID';
$string['escalationuserid_desc'] = 'Set a Moodle user with the supervise capability. Zero uses the primary site administrator. Notifications contain generic wording and a portal link.';
$string['eventoperation'] = 'Helpdesk operation recorded';
$string['exportcsv'] = 'Download CSV';
$string['exportjson'] = 'Download JSON';
$string['firstdue'] = 'First response due';
$string['firstresponse'] = 'First staff response';
$string['goals'] = 'Response {$a->response} min · Resolution {$a->resolution} min';
$string['group'] = 'Group';
$string['groupby'] = 'Group report by';
$string['history'] = 'History';
$string['holidays'] = 'Holidays';
$string['holidays_desc'] = 'Use local dates in YYYY-MM-DD format separated by commas or line breaks. Up to 200 dates.';
$string['hours'] = 'Working hours';
$string['hours_desc'] = 'Use ISO weekdays, from 1 (Monday) to 7 (Sunday). Enter one line per working day, for example 1=09:00-12:00,13:00-17:00. Include at least seven hours per week.';
$string['inactive'] = 'Inactive';
$string['initialrequired'] = 'Choose another active initial state before disabling or changing this one.';
$string['initialstate'] = 'Initial state';
$string['internal'] = 'Staff-only note';
$string['internalnote'] = 'Internal note';
$string['invalidattachment'] = 'Use up to three local PDF, PNG, JPEG or plain text files within the upload limits. File contents must match their extension.';
$string['invalidbody'] = 'Enter a message of up to 8,000 characters.';
$string['invalidcatalog'] = 'The selected catalog record or its settings are invalid.';
$string['invalidholidays'] = 'Check the dates and use YYYY-MM-DD format.';
$string['invalidhours'] = 'Check weekday numbers, shift order, overlaps and weekly hours.';
$string['invalidrating'] = 'This ticket is ineligible for that rating or has already been rated.';
$string['invalidtitle'] = 'Enter a title of up to 180 characters.';
$string['kind_article'] = 'Articles';
$string['kind_assignee'] = 'Technician';
$string['kind_campus'] = 'Campus';
$string['kind_category'] = 'Category';
$string['kind_course'] = 'Course';
$string['kind_priority'] = 'Priority';
$string['kind_severity'] = 'Severity';
$string['kind_status'] = 'Status';
$string['kind_template'] = 'Response templates';
$string['knowledge'] = 'Knowledge base';
$string['license_community'] = 'Community';
$string['license_enterprise'] = 'Enterprise';
$string['license_expired'] = 'Expired';
$string['license_grace'] = 'Offline grace period';
$string['license_professional'] = 'Professional';
$string['licenseexpiry'] = 'Entitlement expiry {$a}';
$string['licensestatus'] = 'Subscription status — {$a}';
$string['loadtemplate'] = 'Load template into reply';
$string['manage'] = 'Administration';
$string['messageerased'] = 'Message content removed.';
$string['messageprovider:ticketupdate'] = 'Helpdesk updates';
$string['metric_closed'] = 'Resolved';
$string['metric_open'] = 'Open';
$string['metric_overdue'] = 'With SLA breach';
$string['metric_total'] = 'Total';
$string['modified'] = 'Last update';
$string['mytickets'] = 'My tickets';
$string['name'] = 'Name';
$string['newentry'] = 'Add entry';
$string['noarticles'] = 'No published articles match this search.';
$string['nocourse'] = 'No associated course';
$string['noentries'] = 'There are no entries in this catalog.';
$string['nohistory'] = 'No activity to show.';
$string['nomessages'] = 'No messages to show on this page.';
$string['noreport'] = 'No tickets match the report filters.';
$string['notickets'] = 'No tickets match these filters.';
$string['notificationbody'] = 'There is an update in SkillUps Helpdesk. Sign in to Moodle and open the support portal to review any tickets you can access.';
$string['notifications'] = 'Enable notifications';
$string['notifications_desc'] = 'Queue generic notices via Moodle messaging. Email is off by default in the provider and can follow site or user preferences.';
$string['notificationsubject'] = 'SkillUps Helpdesk update';
$string['notrecorded'] = 'Pending';
$string['openticket'] = 'Open ticket';
$string['overdue'] = 'SLA breached';
$string['period'] = 'Created within';
$string['pluginname'] = 'SkillUps Helpdesk';
$string['portallinks'] = 'Helpdesk navigation';
$string['privacy:field:action'] = 'Audit operation name.';
$string['privacy:field:assigneeid'] = 'Assigned technician identifier.';
$string['privacy:field:attempts'] = 'Number of delivery attempts.';
$string['privacy:field:availableat'] = 'Next delivery time.';
$string['privacy:field:body'] = 'Support message content.';
$string['privacy:field:courseid'] = 'Associated course identifier.';
$string['privacy:field:details'] = 'Structured data about the operation.';
$string['privacy:field:firstdue'] = 'First response deadline.';
$string['privacy:field:firstresponse'] = 'First response timestamp.';
$string['privacy:field:formerassignee'] = 'Former technician identifier.';
$string['privacy:field:internal'] = 'Staff-only visibility indicator.';
$string['privacy:field:newassignee'] = 'New technician identifier.';
$string['privacy:field:ratedat'] = 'Rating timestamp.';
$string['privacy:field:rating'] = 'Satisfaction rating.';
$string['privacy:field:requesterid'] = 'Requester identifier.';
$string['privacy:field:resolvebreach'] = 'Observed resolution breach timestamp.';
$string['privacy:field:resolvedat'] = 'Resolution timestamp.';
$string['privacy:field:resolvedue'] = 'Resolution deadline.';
$string['privacy:field:responsebreach'] = 'Observed first response breach timestamp.';
$string['privacy:field:ticketid'] = 'Related ticket identifier.';
$string['privacy:field:timecreated'] = 'Creation timestamp.';
$string['privacy:field:timemodified'] = 'Last update timestamp.';
$string['privacy:field:title'] = 'Ticket subject.';
$string['privacy:field:userid'] = 'Operator, author or recipient identifier.';
$string['privacy:files'] = 'Private ticket attachments.';
$string['privacy:table:audit'] = 'Support and configuration history.';
$string['privacy:table:outbox'] = 'Pending generic notice deliveries.';
$string['privacy:table:post'] = 'Support messages and staff notes.';
$string['privacy:table:ticket'] = 'Ticket requesters, assignments and SLA measurements.';
$string['processtask'] = 'Process Helpdesk SLA and notifications';
$string['purgetask'] = 'Purge expired Helpdesk tickets';
$string['ratedcount'] = 'Ratings';
$string['ratelimit'] = 'Too many recent submissions. Try again later.';
$string['ratingmean'] = 'Average rating';
$string['ratingsaved'] = 'Thank you for rating the support you received.';
$string['ratingvalue'] = 'Satisfaction rating {$a}/5';
$string['reports'] = 'Reports';
$string['requiredcatalog'] = 'This catalog must retain at least one usable option.';
$string['resolutiongoal'] = 'Resolution target in business minutes';
$string['resolvebreaches'] = 'Resolution breaches';
$string['resolvedat'] = 'Resolution recorded';
$string['resolvedticket'] = 'This resolved ticket is read-only. Open a new ticket if you need further support.';
$string['resolvedue'] = 'Resolution due';
$string['responsebreaches'] = 'First response breaches';
$string['responsegoal'] = 'First response target in business minutes';
$string['retention'] = 'Closed ticket retention in days';
$string['retention_desc'] = 'The daily task removes expired terminal tickets and attachments. Active tickets stay in the system.';
$string['satisfaction'] = 'How satisfied were you? 1 is lowest, 5 is highest.';
$string['savetriage'] = 'Save classification';
$string['search'] = 'Search';
$string['searchknowledge'] = 'Search articles';
$string['searchtitle'] = 'Search by title';
$string['seed_closed'] = 'Closed';
$string['seed_critical'] = 'Critical impact';
$string['seed_defaultcampus'] = 'Main campus';
$string['seed_general'] = 'General support';
$string['seed_major'] = 'High impact';
$string['seed_minor'] = 'Low impact';
$string['seed_normal'] = 'Normal';
$string['seed_open'] = 'Open';
$string['seed_progress'] = 'In progress';
$string['seed_resolved'] = 'Resolved';
$string['seed_technical'] = 'Technical issue';
$string['seed_urgent'] = 'Urgent';
$string['seed_waiting'] = 'Waiting for information';
$string['selectrating'] = 'Select your rating';
$string['selecttemplate'] = 'Select a response template';
$string['sendcomment'] = 'Send reply';
$string['sendsurvey'] = 'Submit rating';
$string['settings'] = 'Site settings';
$string['skillupshelpdesk:configure'] = 'Manage Helpdesk catalogs and administration';
$string['skillupshelpdesk:request'] = 'Open and view own support tickets';
$string['skillupshelpdesk:supervise'] = 'Supervise and assign all support tickets';
$string['skillupshelpdesk:work'] = 'Work on assigned support tickets';
$string['staffqueue'] = 'Technician queue';
$string['statusinuse'] = 'The resolution behavior of an existing status is fixed. Create a new status to use a different behavior.';
$string['systemactor'] = 'System';
$string['terminal'] = 'Resolves the ticket';
$string['ticketcreated'] = 'Ticket opened.';
$string['ticketnumber'] = 'Ticket #{$a}';
$string['tickets'] = 'Tickets';
$string['ticketupdated'] = 'Ticket updated.';
$string['ticketviews'] = 'Ticket views';
$string['timezone'] = 'SLA timezone';
$string['timezone_desc'] = 'The local timezone used for working hours and holidays when new SLA deadlines are set.';
$string['title'] = 'Title';
$string['triage'] = 'Classification and assignment';
$string['unassigned'] = 'Unassigned';
$string['unavailableuser'] = 'Assigned account unavailable';
$string['view'] = 'Ticket view';
