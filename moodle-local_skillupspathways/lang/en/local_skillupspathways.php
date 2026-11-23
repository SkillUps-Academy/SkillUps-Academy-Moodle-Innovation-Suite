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
 * English strings for the pathway designer.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addstep'] = 'Add step';
$string['allgroups'] = 'All accessible groups';
$string['applygroup'] = 'Filter by group';
$string['busy'] = 'This pathway is being updated. Try again shortly.';
$string['cataloglimit'] = 'This course has reached the pathway limit.';
$string['certificate'] = 'Allow certification integrations to verify completion';
$string['changeversion'] = 'Move participants to the published version';
$string['competencyrequired'] = 'Course competency proficiency required';
$string['completed'] = 'Completed';
$string['completiondisabled'] = 'Enable native completion for the selected course or activity before using it here.';
$string['confirmmigration'] = 'I confirm the change of version for the selected participants.';
$string['csvregistered'] = 'Participants';
$string['current'] = 'Published version';
$string['cycle'] = 'The prerequisites would create a cycle.';
$string['description'] = 'Description';
$string['designer'] = 'Pathway designer';
$string['down'] = 'Move down';
$string['draftchanged'] = 'Another editor saved this draft. Reload before editing again.';
$string['draftonly'] = 'Draft without a published version';
$string['draftsaved'] = 'Draft saved.';
$string['edgelimit'] = 'The pathway can have up to 150 connections.';
$string['edition'] = 'Version';
$string['editorhint'] = 'Add steps, select their prerequisites, and drag cards or use arrow keys to arrange the diagram. Obtain course and activity IDs from their Moodle URLs.';
$string['enabled'] = 'Make the published pathway available';
$string['exportcsv'] = 'Export aggregate CSV';
$string['field_all'] = 'Complete all prerequisites (AND)';
$string['field_any'] = 'Complete any prerequisite (OR)';
$string['field_cm'] = 'Course module ID (0 for a course)';
$string['field_competency'] = 'Competency ID (0 to omit)';
$string['field_completion'] = 'Require Moodle completion';
$string['field_course'] = 'Course ID';
$string['field_grade'] = 'Minimum grade percentage (-1 to omit)';
$string['field_join'] = 'Prerequisite rule';
$string['field_required'] = 'Required to complete the pathway';
$string['field_title'] = 'Step title';
$string['group'] = 'Group';
$string['history'] = 'Progress history';
$string['inprogress'] = 'In progress';
$string['invalidcompetency'] = 'The selected competency must be linked to its destination course.';
$string['invalidgraph'] = 'Review the pathway structure and step settings.';
$string['javascriptneeded'] = 'Enable JavaScript to use the visual editor.';
$string['lastchecked'] = 'Last checked {$a}';
$string['lastreview'] = 'Last review';
$string['learnerpage'] = 'Learning pathway';
$string['license_community'] = 'Community';
$string['license_enterprise'] = 'Enterprise';
$string['license_expired'] = 'Expired';
$string['license_grace'] = 'Grace period';
$string['license_professional'] = 'Professional';
$string['licenseexpiry'] = 'Entitlement expires {$a}';
$string['licensestatus'] = 'Plan {$a}';
$string['mandatoryrequired'] = 'A published pathway needs at least one required step.';
$string['map'] = 'Pathway diagram';
$string['migrationdone'] = 'Participant moved to the published version.';
$string['migrationhistory'] = 'Version changed';
$string['minpercent'] = 'Minimum grade: {$a}%';
$string['moved'] = 'Step moved.';
$string['nativecompletion'] = 'Moodle completion required';
$string['navigation'] = 'Pathway navigation';
$string['nextsteps'] = 'Recommended next steps';
$string['nochoices'] = 'Add another step to configure prerequisites.';
$string['nonext'] = 'There are no steps available to advance right now.';
$string['nopaths'] = 'No pathways are available in this course.';
$string['noregistrations'] = 'No participants match this selection.';
$string['nosteps'] = 'Add a step to start building the pathway.';
$string['notavailable'] = 'This pathway or destination is unavailable to you.';
$string['operationrecorded'] = 'Pathway management operation recorded';
$string['optionalstep'] = 'Optional';
$string['pathcompleted'] = 'Pathway completed';
$string['pathhistory'] = 'Pathway status';
$string['pathname'] = 'Pathway name';
$string['pathways'] = 'Learning pathways';
$string['paused'] = 'Paused';
$string['pluginname'] = 'SkillUps Learning Path Designer';
$string['prereq_all'] = 'Complete all: {$a}';
$string['prereq_any'] = 'Complete any one: {$a}';
$string['prerequisites'] = 'Prerequisites';
$string['privacy:metadata:audit'] = 'Administrative changes to a pathway.';
$string['privacy:metadata:completedat'] = 'Time of verified completion.';
$string['privacy:metadata:history'] = 'Changes in a participant’s pathway status.';
$string['privacy:metadata:newstate'] = 'New status.';
$string['privacy:metadata:nodekey'] = 'Step identifier.';
$string['privacy:metadata:oldstate'] = 'Previous status.';
$string['privacy:metadata:operation'] = 'Type of administrative change.';
$string['privacy:metadata:passed'] = 'Completed required step count.';
$string['privacy:metadata:pathid'] = 'Pathway identifier.';
$string['privacy:metadata:progress'] = 'Pathways assigned to participants and their current progress.';
$string['privacy:metadata:requiredcount'] = 'Required step count.';
$string['privacy:metadata:states'] = 'Current steps and their status.';
$string['privacy:metadata:timechecked'] = 'Time of last verification.';
$string['privacy:metadata:timecreated'] = 'Time recorded.';
$string['privacy:metadata:userid'] = 'Moodle user identifier.';
$string['privacy:metadata:versionid'] = 'Identifier of the assigned version.';
$string['progress'] = 'Progress';
$string['progresssaved'] = 'Progress checked.';
$string['publish'] = 'Publish version';
$string['published'] = 'Version published.';
$string['refresh'] = 'Refresh progress';
$string['refreshtask'] = 'Refresh SkillUps pathway progress';
$string['registered'] = 'Participants';
$string['remove'] = 'Remove step';
$string['reports'] = 'Progress reports';
$string['requiredstep'] = 'Required';
$string['restrictedstep'] = 'Restricted step';
$string['right'] = 'Move right';
$string['savedraft'] = 'Save draft';
$string['score'] = 'Required steps completed {$a->done} of {$a->total}';
$string['skillupspathways:manage'] = 'Design and publish learning pathways';
$string['skillupspathways:report'] = 'View pathway reports';
$string['skillupspathways:view'] = 'View learning pathways';
$string['start'] = 'Start pathway';
$string['state_blocked'] = 'Prerequisites pending';
$string['state_complete'] = 'Complete';
$string['state_incomplete'] = 'In progress';
$string['state_initial'] = 'Initial state';
$string['state_migrated'] = 'Version updated';
$string['state_pinned'] = 'Version assigned';
$string['state_ready'] = 'Ready to continue';
$string['state_unavailable'] = 'Unavailable';
$string['step'] = 'Step';
$string['stephistory'] = 'Step status';
$string['stepmax'] = 'The pathway can have up to 60 steps.';
$string['stepoptions'] = 'Step settings';
$string['steps'] = 'Pathway steps';
$string['steptitle'] = 'New step';
$string['student'] = 'Participant';
$string['versionhistory'] = 'Published versions';
$string['versionpinned'] = 'Your assigned version remains in effect when a new version is published.';
$string['versionvalue'] = 'Version {$a}';
