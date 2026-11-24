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
 * English strings for SkillUps Integration Hub.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addconnector'] = 'Add connector';
$string['allconnectors'] = 'All connectors';
$string['approve'] = 'Approve and apply';
$string['approvedby'] = 'Approving operator';
$string['audit'] = 'Operational audit';
$string['auditdays'] = 'Days to retain run and audit history';
$string['auditdayshelp'] = 'Controls operational history and the event deduplication window. Effective range 30–730 days.';
$string['auth_contract'] = 'Bearer and optional signed inbound events';
$string['auth_google'] = 'Delegated OAuth 2.0';
$string['auth_inbound'] = 'Moodle REST tokens or signed webhooks';
$string['auth_microsoft'] = 'Application OAuth 2.0';
$string['authorization'] = 'Application approval';
$string['authorize'] = 'Authorize';
$string['availability'] = 'Activation';
$string['categoryid'] = 'Destination category ID';
$string['categoryid_help'] = 'Enter an existing Moodle course category. Imported courses are hidden when created.';
$string['configintro'] = 'Choose the authorised external provider and Moodle course destination. Supply secrets through your server environment. The connector creates simulations before Moodle writes.';
$string['connector'] = 'Connector';
$string['connectors'] = 'Connectors';
$string['courses'] = 'Include courses';
$string['dashboard'] = 'Integration dashboard';
$string['date'] = 'Recorded on';
$string['decision'] = 'Planned action';
$string['decision_conflict'] = 'Conflict';
$string['decision_create'] = 'Create';
$string['decision_invalid'] = 'Invalid row';
$string['decision_same'] = 'No changes';
$string['decision_update'] = 'Update';
$string['disabled'] = 'Disabled';
$string['editconnector'] = 'Edit connector';
$string['edition'] = 'Edition';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Expired';
$string['edition_grace'] = 'Offline grace period';
$string['edition_professional'] = 'Professional';
$string['empty'] = 'There are no matching records.';
$string['emptysources'] = 'Add a connector to begin receiving or simulating records.';
$string['enabled'] = 'Enabled';
$string['endpoint'] = 'Provider endpoint or tenant';
$string['endpoint_help'] = 'For Google use my_customer or a Workspace domain. For Microsoft use the Entra tenant UUID. For SIS and HR use an approved HTTPS gateway base URL.';
$string['entity'] = 'Entity';
$string['entity_course'] = 'Course';
$string['entity_unknown'] = 'Unknown entity';
$string['entity_user'] = 'User';
$string['err_busy'] = 'This connector is busy or has too many pending simulations. Try again later.';
$string['err_changed'] = 'Moodle changed after the simulation was prepared.';
$string['err_collision'] = 'A Moodle record already uses this username, email or course short name.';
$string['err_config'] = 'Review the connector settings and its selected scopes.';
$string['err_confirm'] = 'Review the simulation and confirm approval.';
$string['err_credential'] = 'Provider authentication failed or its credentials are unavailable.';
$string['err_duplicate'] = 'The source supplied this identity more than once.';
$string['err_endpoint'] = 'The endpoint or continuation is outside the approved HTTPS origin.';
$string['err_entity'] = 'The entity type is unavailable for this connector.';
$string['err_identity'] = 'A protected identity field or course destination changed.';
$string['err_limit'] = 'A body, page or simulation exceeded its processing limits.';
$string['err_localchange'] = 'Moodle managed fields were edited since the last application.';
$string['err_mapping'] = 'A mapping contains an unsupported field, path or operation.';
$string['err_missing'] = 'A previously linked Moodle object is missing.';
$string['err_nothing'] = 'This simulation has no applicable changes.';
$string['err_permission'] = 'The actor is not authorized for this operation or destination.';
$string['err_processing'] = 'This operation could not be processed.';
$string['err_protected'] = 'The linked account is protected or unavailable.';
$string['err_record'] = 'A record has missing or invalid required fields.';
$string['err_replay'] = 'This event reference already belongs to a different payload.';
$string['err_response'] = 'The data does not match the expected JSON contract.';
$string['err_signature'] = 'The signed request could not be authenticated.';
$string['err_stale'] = 'The connector or simulation changed or expired. Obtain a current simulation before applying.';
$string['err_storage'] = 'A temporary database error interrupted this operation.';
$string['err_unavailable'] = 'The connector, run or selected operation is unavailable.';
$string['err_unreachable'] = 'The provider could not be reached over an approved TLS connection.';
$string['err_upstream'] = 'The external service rejected the request or is temporarily unavailable.';
$string['error'] = 'Error';
$string['evidence'] = 'Download audit evidence CSV';
$string['field_email'] = 'Email';
$string['field_firstname'] = 'First name';
$string['field_fullname'] = 'Course full name';
$string['field_lastname'] = 'Surname';
$string['field_shortname'] = 'Course short name';
$string['field_username'] = 'Username';
$string['health'] = 'Last connector check';
$string['history'] = 'Run history';
$string['items'] = 'Simulation rows';
$string['latency'] = '{$a} ms';
$string['mapping'] = 'Field mapping JSON';
$string['mapping_help'] = 'Configure entity and canonical field, then a source path and optional transforms. Available operations are trim, lower, upper and collapse. Empty uses provider defaults.';
$string['name'] = 'Connector name';
$string['nextretry'] = 'Next retry';
$string['op_approved'] = 'Application authorized';
$string['op_backoff'] = 'Retry scheduled';
$string['op_configured'] = 'Connector settings saved';
$string['op_done'] = 'Run completed';
$string['op_expired'] = 'Run expired';
$string['op_exported'] = 'Audit exported';
$string['op_failed'] = 'Run failed';
$string['op_partial'] = 'Run completed with issues';
$string['op_previewed'] = 'Simulation started';
$string['op_retried'] = 'Run retried';
$string['op_sealed'] = 'Simulation ready';
$string['op_superseded'] = 'Run superseded';
$string['operator'] = 'Operator ID';
$string['origin'] = 'Origin';
$string['origin_pull'] = 'External connector';
$string['origin_rest'] = 'Moodle REST service';
$string['origin_webhook'] = 'Signed webhook';
$string['pluginname'] = 'SkillUps Integration Hub';
$string['policy'] = 'Conflict policy';
$string['policy_help'] = 'You may preserve local edits or prefer new managed field values from the source. Both policies reject stale simulations, identity changes and collisions.';
$string['policy_preserve'] = 'Preserve Moodle edits';
$string['policy_source'] = 'Prefer source values on a new preview';
$string['privacy:external'] = 'Configured external providers can receive authentication requests and query parameters. Directory and course data are imported from them.';
$string['privacy:field:action'] = 'Operational event code.';
$string['privacy:field:actorid'] = 'The actor who submitted or changed the integration.';
$string['privacy:field:approverid'] = 'The operator approving native changes.';
$string['privacy:field:continuation'] = 'Temporary opaque provider continuation.';
$string['privacy:field:enabled'] = 'Whether the connector is active.';
$string['privacy:field:entity'] = 'Whether the record represents a user or course.';
$string['privacy:field:errorcode'] = 'A structured error code.';
$string['privacy:field:externalkey'] = 'Hash of the source identity.';
$string['privacy:field:finger'] = 'Digest of managed values at last application.';
$string['privacy:field:name'] = 'The integration\'s descriptive name.';
$string['privacy:field:options'] = 'Endpoint and mapping settings.';
$string['privacy:field:origin'] = 'The method of submission.';
$string['privacy:field:ownerid'] = 'Responsible account for signed ingestion.';
$string['privacy:field:payload'] = 'The mapped values intended for review.';
$string['privacy:field:pushuserid'] = 'Authorized Moodle REST account.';
$string['privacy:field:requesthash'] = 'Digest of the original event for idempotency.';
$string['privacy:field:requestkey'] = 'Hashed event idempotency reference.';
$string['privacy:field:runid'] = 'Simulation reference.';
$string['privacy:field:sourceid'] = 'Connector reference.';
$string['privacy:field:state'] = 'The workflow outcome.';
$string['privacy:field:subject'] = 'Hash of a staged account email.';
$string['privacy:field:targetid'] = 'ID of a Moodle user or course managed by the source.';
$string['privacy:field:timecreated'] = 'Time of recording.';
$string['privacy:field:type'] = 'The provider family.';
$string['privacy:field:userid'] = 'The user identified by a temporary record.';
$string['privacy:table:audit'] = 'Integration operation history.';
$string['privacy:table:item'] = 'Staged canonical user or course data and its decision.';
$string['privacy:table:link'] = 'Pseudonymous source identity linked to an object created by a connector.';
$string['privacy:table:run'] = 'Simulation and record of its application approval.';
$string['privacy:table:src'] = 'Connector configuration and authorized operational accounts.';
$string['proposed'] = 'Proposed fields';
$string['pushuserid'] = 'REST service user ID';
$string['pushuserid_help'] = 'A Moodle account with the integration ingestion capability. Leave empty when REST ingestion is disabled for this connector.';
$string['records'] = 'Source rows received';
$string['refresh'] = 'Refresh status';
$string['result'] = 'Result';
$string['retry'] = 'Retry run';
$string['reviewed'] = 'I reviewed the proposed changes and authorize this simulation to modify Moodle.';
$string['rundetail'] = 'Integration run #{$a}';
$string['runid'] = 'Run ID';
$string['saved'] = 'The integration was updated.';
$string['secretref'] = 'Credential environment reference';
$string['secretref_help'] = 'Enter the name of the provisioned SKILLUPS_HUB_ variable. On edit, leave empty to retain the current reference. Supply the actual keys outside Moodle.';
$string['simulate'] = 'Run simulation';
$string['skillupsintegration:apply'] = 'Approve and apply integration previews';
$string['skillupsintegration:ingest'] = 'Submit events for a designated REST connector';
$string['skillupsintegration:manage'] = 'Configure integration connectors';
$string['skillupsintegration:preview'] = 'Generate and examine integration previews';
$string['skillupsintegration:view'] = 'View integration status and audit';
$string['stagedays'] = 'Days to retain simulation data';
$string['stagedayshelp'] = 'Simulation payloads, cursors and item outcomes expire after this period. Effective range 1–30 days.';
$string['state_applied'] = 'Applied';
$string['state_applying'] = 'Applying';
$string['state_conflict'] = 'Conflict';
$string['state_done'] = 'Completed';
$string['state_expired'] = 'Expired';
$string['state_failed'] = 'Failed';
$string['state_healthy'] = 'Successful source contact';
$string['state_new'] = 'Awaiting first check';
$string['state_partial'] = 'Completed with issues';
$string['state_planned'] = 'Planned';
$string['state_queued'] = 'Queued';
$string['state_ready'] = 'Ready for review';
$string['state_same'] = 'Unchanged';
$string['state_superseded'] = 'Superseded';
$string['taskdispatch'] = 'Dispatch and clean up integration jobs';
$string['type'] = 'Provider type';
$string['type_google'] = 'Google Workspace';
$string['type_hr'] = 'Human resources systems';
$string['type_inbound'] = 'REST and webhooks';
$string['type_microsoft'] = 'Microsoft 365';
$string['type_sis'] = 'Academic systems';
$string['users'] = 'Include users';
$string['webhook'] = 'Accept signed webhooks';
