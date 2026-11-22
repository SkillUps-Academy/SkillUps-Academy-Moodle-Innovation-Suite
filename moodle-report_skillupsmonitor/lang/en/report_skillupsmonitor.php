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
 * Strings for SkillUps System Monitor.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['acknowledge'] = 'Mark as reviewed';
$string['acknowledged'] = 'The incident review was recorded.';
$string['acktime'] = 'Reviewed at';
$string['activeaccounts'] = 'Accounts with Moodle access recorded in the past 24 hours';
$string['adhoc'] = 'Ad hoc';
$string['alertchecks'] = 'Checks with notifications';
$string['alertchecksdesc'] = 'Select which incident categories send a notification when they change state.';
$string['allchecks'] = 'All checks';
$string['apply'] = 'Apply filters';
$string['availabilityhelp'] = 'Availability uses observed reachable and unavailable slots. Maintenance and missing observations are reported separately. Coverage includes observed maintenance.';
$string['availabilitysummary'] = 'Internal availability and coverage';
$string['backlist'] = 'Return to incidents';
$string['badrequest'] = 'The monitoring request is invalid or unavailable.';
$string['busy'] = 'The monitor is updating. Try again shortly.';
$string['capturedlabel'] = 'Last observation {$a}';
$string['check'] = 'Check';
$string['check_adhoc'] = 'Ad hoc task queue';
$string['check_components'] = 'Core component installation';
$string['check_coverage'] = 'Observation coverage gap';
$string['check_cron'] = 'Cron heartbeat';
$string['check_failures'] = 'Recent failed task attempts';
$string['check_latency'] = 'Internal check latency';
$string['check_readiness'] = 'Moodle database readiness';
$string['check_scheduled'] = 'Scheduled task queue';
$string['checks'] = 'Checks from the last scheduled observation';
$string['component'] = 'Component';
$string['component_delete'] = 'Pending removal';
$string['component_disabled'] = 'Disabled';
$string['component_downgrade'] = 'Older code detected';
$string['component_missing'] = 'Files missing';
$string['component_new'] = 'Pending installation';
$string['component_nodb'] = 'Present without database metadata';
$string['component_upgrade'] = 'Upgrade pending';
$string['component_uptodate'] = 'Up to date';
$string['componentnotice'] = 'Component status describes Moodle installation and configuration. Use service probes for independent runtime checks.';
$string['components'] = 'Core components';
$string['configuration'] = 'Monitor settings';
$string['configuration_croncli'] = 'Cron restricted to CLI';
$string['configuration_cronconfigured'] = 'Cron enabled in Moodle';
$string['configuration_debughidden'] = 'Debug display disabled';
$string['configuration_https'] = 'Canonical HTTPS URL';
$string['configuration_logreader'] = 'Standard Moodle log reader available';
$string['configuration_maintenancemode'] = 'Maintenance active';
$string['configuration_scrapeconfigured'] = 'External monitoring enabled with token digest configured';
$string['configuration_tasklogger'] = 'Native database task logger available';
$string['countfloor'] = 'At least {$a}';
$string['courses'] = 'Courses excluding the site home';
$string['cronminutes'] = 'Cron warning threshold in minutes';
$string['cronminutesdesc'] = 'Maximum age of the last cron start before warning, between 2 and 120 minutes.';
$string['cronunobserved'] = 'Moodle has not recorded a cron start.';
$string['dashboard'] = 'Dashboard';
$string['delivery'] = 'Notification delivery';
$string['detected'] = 'Incident declared';
$string['diagnostics'] = 'Diagnostics';
$string['dueminutes'] = 'Task overdue threshold in minutes';
$string['dueminutesdesc'] = 'Grace after the scheduled start, between 1 and 120 minutes.';
$string['duration'] = 'Duration';
$string['error_emailfailed'] = 'Email delivery failed';
$string['error_scandataerror'] = 'Antivirus data scan error';
$string['error_scanfileerror'] = 'Antivirus file scan error';
$string['errorlimits'] = 'Diagnostics shows up to 50 results per list. Moodle permissions govern access to task and event details.';
$string['eventcaption'] = 'Selected Moodle error events from the last 24 hours';
$string['eventmonitor'] = 'Monitor operation';
$string['events'] = 'Authorized error events';
$string['eventunavailable'] = 'The standard Moodle log reader is unavailable.';
$string['failures'] = 'Observations to declare an incident';
$string['failuresdesc'] = 'Consecutive warnings or errors, between 1 and 12.';
$string['firstobserved'] = 'First observed';
$string['freshsample'] = 'The most recent observation is within the configured age.';
$string['heartbeat'] = 'SkillUps System Monitor observation';
$string['history'] = 'Availability history';
$string['history_available'] = 'Observed availability percent';
$string['history_coverage'] = 'Coverage percent';
$string['history_down'] = 'Unavailable observations';
$string['history_expected'] = 'Five-minute intervals expected';
$string['history_maintenance'] = 'Maintenance observations';
$string['history_p95us'] = '95th percentile of internal check latency in microseconds';
$string['history_p95us_raw'] = 'Internal check latency in microseconds';
$string['history_unknown'] = 'Unknown or unobserved intervals';
$string['history_up'] = 'Reachable observations';
$string['hours'] = 'Period';
$string['hourslabel'] = 'Last {$a} hours';
$string['incidentdetail'] = 'Incident details';
$string['incidents'] = 'Incidents';
$string['internalnotice'] = 'Internal observations cover Moodle and its database. Use an independent probe for user-facing availability and an external agent for CPU, memory, disk or system services.';
$string['intervalend'] = 'Period end UTC exclusive';
$string['intervalstart'] = 'Period start UTC';
$string['invalidwindow'] = 'Select an available reporting period.';
$string['lastcronlabel'] = 'Last cron start {$a}';
$string['latencyms'] = 'Internal latency threshold in milliseconds';
$string['latencymsdesc'] = 'Warn when the Moodle-to-database check exceeds this duration, between 10 and 10000 ms.';
$string['licensecommunity'] = 'Community edition';
$string['licenseenterprise'] = 'Enterprise edition';
$string['licenseexpired'] = 'Expired license';
$string['licenseexpires'] = 'Expires {$a}';
$string['licensegrace'] = 'License grace period';
$string['licenseinstallation'] = 'Installation identifier {$a}';
$string['licenseprofessional'] = 'Professional edition';
$string['licensestatus'] = 'License status';
$string['livecron'] = 'Cron heartbeat';
$string['messageprovider:incident'] = 'Operational monitoring incidents';
$string['metric'] = 'Metric';
$string['milliseconds'] = '{$a} ms';
$string['monitorcomponents'] = 'Check core components';
$string['monitorcomponentsdesc'] = 'Read the installation and enablement status of a fixed list of Moodle components.';
$string['nativetasklogs'] = 'Open Moodle task logs';
$string['noevents'] = 'No matching error events found in the last 24 hours.';
$string['noincidents'] = 'No incidents match these filters.';
$string['nosamples'] = 'Waiting for the first scheduled observation.';
$string['noscheduledissues'] = 'No overdue or retrying scheduled tasks were found.';
$string['notaskerrors'] = 'No failed task attempts found in the last 24 hours.';
$string['notice_coverage'] = 'Monitoring observations resumed for {$a}';
$string['notice_opened'] = 'Monitoring incident opened for {$a}';
$string['notice_recovered'] = 'Monitoring incident recovered for {$a}';
$string['noticebody'] = 'Open the incident report in Moodle for more details.';
$string['notifyenabled'] = 'Enable incident notifications';
$string['notifyenableddesc'] = 'Deliver incident transitions through the Moodle messaging subsystem.';
$string['observed'] = 'Observed at';
$string['openincidents'] = 'Open incidents';
$string['overdue'] = 'Overdue';
$string['overview'] = 'Monitoring overview';
$string['percent'] = '{$a}%';
$string['periodlabel'] = 'From {$a->from} to {$a->until}, excluding the end.';
$string['pluginname'] = 'SkillUps System Monitor';
$string['privacy:metadata:incident'] = 'Incidents retain the author and timestamp of a review until erased or expired.';
$string['privacy:metadata:messaging'] = 'Moodle messaging handles incident notifications sent to designated operators.';
$string['privacy:metadata:notice'] = 'Delivery records for authorized incident recipients.';
$string['privacy:metadata:phase'] = 'Incident transition that created the notification.';
$string['privacy:metadata:recipients'] = 'Site configuration stores designated notification recipient IDs.';
$string['privacy:metadata:state'] = 'Delivery state of the notification.';
$string['privacy:metadata:time'] = 'Time of the personal action or delivery record.';
$string['privacy:metadata:userid'] = 'User ID associated with a notification or incident review.';
$string['privacy:path'] = 'Operational monitoring';
$string['probelatency'] = 'Internal check latency';
$string['queue_adhocdue'] = 'Overdue ad hoc tasks';
$string['queue_adhocfailed'] = 'Ad hoc tasks with retry delay';
$string['queue_disabled'] = 'Disabled scheduled tasks';
$string['queue_due'] = 'Overdue scheduled tasks';
$string['queue_enabled'] = 'Enabled scheduled tasks';
$string['queue_failed'] = 'Scheduled tasks with retry delay';
$string['queue_running'] = 'Running scheduled tasks';
$string['queuesummary'] = 'Moodle task queues';
$string['queuewarn'] = 'Overdue task count threshold';
$string['queuewarndesc'] = 'Warn when the overdue ad hoc queue reaches this count, between 1 and 1000.';
$string['readiness'] = 'Internal Moodle readiness';
$string['recentfailures'] = 'Failed task attempts in the last 15 minutes';
$string['recipients'] = 'Incident recipients';
$string['recipientsdesc'] = 'Enter up to 20 Moodle user IDs separated by commas or spaces. Each recipient must have access to the site monitor.';
$string['recovered'] = 'Recovery recorded';
$string['recoveries'] = 'Observations to confirm recovery';
$string['recoveriesdesc'] = 'Consecutive healthy observations, between 1 and 12.';
$string['retention'] = 'Retention in days';
$string['retentiondesc'] = 'Retention for samples and closed incidents, between 30 and 365 days.';
$string['retrying'] = 'Retry delay';
$string['sampletimeline'] = 'Recorded observations';
$string['schedulecaption'] = 'Scheduled tasks overdue or retrying';
$string['scheduled'] = 'Scheduled';
$string['scrapeenabled'] = 'Allow authenticated external monitoring';
$string['scrapeenableddesc'] = 'Requires canonical HTTPS and a server environment variable containing the bearer token SHA-256 digest.';
$string['seconds'] = '{$a} seconds';
$string['severity'] = 'Severity';
$string['skillupsmonitor:manage'] = 'Acknowledge monitoring incidents';
$string['skillupsmonitor:view'] = 'View site monitoring reports';
$string['stale'] = 'Observation gap threshold in minutes';
$string['staledesc'] = 'Age at which the monitor shows stale data, between 10 and 1440 minutes.';
$string['stalesample'] = 'The latest scheduled observation is stale. Check cron and external availability.';
$string['state'] = 'State';
$string['status_ambiguous'] = 'Dispatch unconfirmed';
$string['status_claimed'] = 'Dispatch in progress';
$string['status_down'] = 'Unavailable internally';
$string['status_error'] = 'Error';
$string['status_failed'] = 'Dispatch failed';
$string['status_maintenance'] = 'Maintenance';
$string['status_ok'] = 'Healthy';
$string['status_open'] = 'Open';
$string['status_pending'] = 'Pending';
$string['status_recovered'] = 'Recovered';
$string['status_sent'] = 'Accepted by messaging';
$string['status_skipped'] = 'Skipped';
$string['status_unknown'] = 'Unknown';
$string['status_up'] = 'Reachable internally';
$string['status_warning'] = 'Warning';
$string['summarycsv'] = 'Download summary CSV';
$string['taskclass'] = 'Task class';
$string['taskerrorcaption'] = 'Failed task attempts from the last 24 hours';
$string['tasklogunavailable'] = 'The default Moodle task database logger is unavailable.';
$string['tasknotify'] = 'Deliver SkillUps monitoring notifications';
$string['taskpurge'] = 'Purge expired SkillUps monitoring observations';
$string['tasksample'] = 'Collect SkillUps monitoring observations';
$string['tasksdetail'] = 'Task diagnostics';
$string['timelinecsv'] = 'Download timeline CSV';
$string['type'] = 'Type';
$string['usage'] = 'Moodle activity indicators';
$string['value'] = 'Value';
$string['view'] = 'Incident status';
$string['view_all'] = 'All incidents';
