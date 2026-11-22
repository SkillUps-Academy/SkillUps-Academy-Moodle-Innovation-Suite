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
 * SkillUps Flow Automation local skillupsflow.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['activate'] = 'Activate revision';
$string['activated'] = 'Revision activated.';
$string['active'] = 'Active';
$string['actor'] = 'Actor ID';
$string['actorunavailable'] = 'The author of this revision cannot execute workflows.';
$string['addnode'] = 'Add node';
$string['all'] = 'All statuses';
$string['and'] = 'All (AND)';
$string['approvals'] = 'Approvals';
$string['approvals30'] = 'Awaiting approval · 30 days';
$string['approvalsintro'] = 'Review pending decisions from revisions authored by other users.';
$string['approve'] = 'Approve';
$string['body'] = 'Plain-text notification';
$string['busy'] = 'This workflow is busy. Try again shortly.';
$string['cancelconfirm'] = 'Completed actions remain applied. Confirm cancellation to stop future steps.';
$string['cancelrun'] = 'Cancel this run';
$string['canvas'] = 'Workflow canvas';
$string['completed30'] = 'Completed · 30 days';
$string['confirmcancel'] = 'Confirm cancellation';
$string['count'] = 'Count';
$string['courseid'] = 'Course ID';
$string['dailymax'] = 'Daily runs per workflow';
$string['dailymaxdesc'] = 'Maximum admissions per UTC day, between 1 and 5000. Each workflow also has a 100-run unfinished limit.';
$string['dashboard'] = 'Workflow dashboard';
$string['dashboardintro'] = 'Publish workflow revisions and monitor execution from one place.';
$string['date'] = 'Date';
$string['decisionsaved'] = 'Decision recorded.';
$string['decisionunavailable'] = 'This approval is unavailable or cannot be decided by this account.';
$string['delete'] = 'Remove node';
$string['down'] = 'Move down';
$string['downloadsample'] = 'Download approval template';
$string['draft'] = 'Latest revision';
$string['due'] = 'Due';
$string['editconflict'] = 'Another editor saved a revision first. Reopen the workflow before saving.';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Expired';
$string['edition_grace'] = 'Offline grace period';
$string['edition_professional'] = 'Professional';
$string['editor'] = 'Workflow builder';
$string['editorintro'] = 'Add and move nodes, then choose their destinations in the inspector. Save a revision before activating it.';
$string['emptyapprovals'] = 'No approvals are currently available to you.';
$string['emptyflows'] = 'Create a workflow or import a template to get started.';
$string['emptyport'] = 'End this route';
$string['emptyruns'] = 'No runs match the selected criteria.';
$string['entry'] = 'Entry node';
$string['eventflowchanged'] = 'SkillUps workflow configuration changed';
$string['export'] = 'Export template';
$string['exportstats'] = 'Export aggregate CSV';
$string['failed30'] = 'Failed · 30 days';
$string['filter'] = 'Filter';
$string['history'] = 'Run history';
$string['historyintro'] = 'Inspect past and pending executions and their recorded transitions.';
$string['import'] = 'Import template';
$string['imported'] = 'Template imported as a draft.';
$string['inspector'] = 'Node inspector';
$string['invaliddefinition'] = 'Check the fields, entry point and connections. Every node must be reachable and cycles are prohibited.';
$string['label'] = 'Node label';
$string['left'] = 'Move left';
$string['limitnodes'] = 'The workflow has reached 40 nodes.';
$string['messageprovider:workflow'] = 'SkillUps workflow notifications';
$string['messagetitle'] = 'SkillUps workflow notification';
$string['minutes'] = 'Minutes';
$string['name'] = 'Workflow name';
$string['newflow'] = 'New workflow';
$string['node'] = 'Node';
$string['node_approval'] = 'Human approval';
$string['node_condition'] = 'Condition';
$string['node_delay'] = 'Wait';
$string['node_enrol'] = 'Enrol user manually';
$string['node_notify'] = 'Send notification';
$string['node_stop'] = 'End workflow';
$string['node_suspend'] = 'Set account suspension';
$string['node_visibility'] = 'Set course visibility';
$string['nothingselected'] = 'Select a node to configure it.';
$string['notificationfailed'] = 'Moodle could not confirm notification delivery.';
$string['one'] = '1 · Suspended account or visible course';
$string['operator'] = 'Combine conditions';
$string['or'] = 'Any (OR)';
$string['outcome_actorunavailable'] = 'Execution account unavailable';
$string['outcome_applied'] = 'Action applied';
$string['outcome_approved'] = 'Approved';
$string['outcome_awaiting'] = 'Awaiting a decision';
$string['outcome_cancelled'] = 'Cancelled';
$string['outcome_completed'] = 'Completed';
$string['outcome_delayed'] = 'Wait scheduled';
$string['outcome_expired'] = 'Approval expired';
$string['outcome_failed'] = 'Attempts exhausted';
$string['outcome_forbidden'] = 'Permission denied';
$string['outcome_invalidstate'] = 'Invalid execution state';
$string['outcome_no'] = 'False branch';
$string['outcome_notificationfailed'] = 'Unconfirmed delivery';
$string['outcome_queued'] = 'Enqueued';
$string['outcome_rejected'] = 'Rejected';
$string['outcome_retry'] = 'Retry scheduled';
$string['outcome_sending'] = 'Delivery attempt recorded';
$string['outcome_uncertain'] = 'Delivery uncertain; no automatic resend';
$string['outcome_yes'] = 'True branch';
$string['pause'] = 'Pause';
$string['paused'] = 'Workflow paused.';
$string['pluginname'] = 'SkillUps Flow Automation';
$string['port_error'] = 'On error';
$string['port_next'] = 'Next node';
$string['port_no'] = 'If false, rejected or expired';
$string['port_yes'] = 'If true or approved';
$string['privacy:actor'] = 'Reviewer or administrator ID.';
$string['privacy:course'] = 'Associated course identifier.';
$string['privacy:definition'] = 'Workflow graph and its configuration.';
$string['privacy:flow'] = 'Workflow revisions include an author and can include configured target IDs.';
$string['privacy:log'] = 'Step transitions and human decisions.';
$string['privacy:outcome'] = 'Step outcome.';
$string['privacy:run'] = 'Workflow runs tied to target accounts.';
$string['privacy:status'] = 'Execution state.';
$string['privacy:time'] = 'Time when the record was created or updated.';
$string['privacy:userid'] = 'Target or author account ID.';
$string['privacy:version'] = 'Immutable workflow revisions, except authorized privacy erasure.';
$string['protectedtarget'] = 'The selected target or role is protected.';
$string['published'] = 'Active revision';
$string['reject'] = 'Reject';
$string['renew'] = 'Use this installation identifier when requesting a signed entitlement from SkillUps Academy.';
$string['requiresjs'] = 'Enable JavaScript to use the visual constructor.';
$string['result'] = 'Result';
$string['retentiondays'] = 'Run retention in days';
$string['retentiondaysdesc'] = 'Remove terminal runs and their step logs after 2 to 365 days.';
$string['revision'] = 'Revision';
$string['revisions'] = 'Revision history';
$string['right'] = 'Move right';
$string['roleid'] = 'Assignable role ID';
$string['run'] = 'Run now';
$string['rununavailable'] = 'This workflow is paused or has reached its admission limit.';
$string['saved'] = 'Revision saved.';
$string['saverevision'] = 'Save revision';
$string['settings'] = 'Workflow settings';
$string['skillupsflow:approve'] = 'Decide workflow approvals';
$string['skillupsflow:manage'] = 'Manage and execute SkillUps workflows';
$string['start'] = 'Start time (browser timezone)';
$string['state_approval'] = 'Awaiting approval';
$string['state_cancelled'] = 'Cancelled';
$string['state_completed'] = 'Completed';
$string['state_failed'] = 'Failed';
$string['state_queued'] = 'Queued or delayed';
$string['state_sending'] = 'Delivery pending confirmation';
$string['status'] = 'Status';
$string['subscription'] = 'Subscription';
$string['subscriptiondetails'] = 'Installation and renewal';
$string['targethint'] = 'Use 0 to inherit the run\'s user or course.';
$string['targetunavailable'] = 'A target is unavailable or ineligible for this action.';
$string['taskdispatch'] = 'Schedule and recover SkillUps workflows';
$string['taskretention'] = 'Expire SkillUps workflow history';
$string['templatefile'] = 'Template file (.json)';
$string['templates'] = 'Templates';
$string['templatesintro'] = 'Import a JSON template as a draft and review its destinations before activating.';
$string['test_activeuser'] = 'Target account is active';
$string['test_enrolled'] = 'Target user has an active enrolment';
$string['test_hascourse'] = 'Target course exists';
$string['test_hasuser'] = 'Target user exists';
$string['test_visiblecourse'] = 'Target course is visible';
$string['tests'] = 'Conditions';
$string['transitions'] = 'Execution log';
$string['trigger'] = 'Trigger';
$string['trigger_course_completed'] = 'Course completed';
$string['trigger_course_created'] = 'Course created';
$string['trigger_interval'] = 'Fixed interval';
$string['trigger_manual'] = 'Manual run';
$string['trigger_once'] = 'One-time schedule';
$string['trigger_user_created'] = 'User created';
$string['trigger_user_enrolment_created'] = 'User enrolled';
$string['triggerhint'] = 'For events, nonzero IDs restrict which events match. For manual and scheduled runs, they supply target IDs.';
$string['up'] = 'Move up';
$string['userid'] = 'User ID';
$string['value'] = 'Value';
$string['workflows'] = 'Workflows';
$string['zero'] = '0 · Active account or hidden course';
