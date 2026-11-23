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
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Active';
$string['allgroups'] = 'All accessible groups';
$string['allstatuses'] = 'All statuses';
$string['alreadyissued'] = 'A valid certificate already exists for this template and recipient.';
$string['auditdays'] = 'Audit log retention in days';
$string['auditdaysdesc'] = 'Minimum 30 days. This setting affects the local audit log.';
$string['backtemplates'] = 'Back to templates';
$string['batch_cancelled'] = 'Cancelled';
$string['batch_finished'] = 'Finished';
$string['batch_queued'] = 'Queued';
$string['batchdays'] = 'Closed batch retention in days';
$string['batchdaysdesc'] = 'Minimum 7 days. This setting affects batch records and individual outcomes.';
$string['batchheading'] = 'Issuance batch';
$string['batchnumber'] = 'Batch {$a}';
$string['batchpending'] = 'This course already has three queued batches.';
$string['batchsize'] = 'Choose a group with between 1 and {$a} enrolled learners.';
$string['body'] = 'Certificate wording';
$string['bulk'] = 'Group issuance';
$string['bulkhelp'] = 'The batch checks each learner’s eligibility before issuing a PDF.';
$string['busy'] = 'The record is being updated. Try again shortly.';
$string['code'] = 'Verification code';
$string['color'] = 'Accent color';
$string['color_green'] = 'Forest green';
$string['color_navy'] = 'Navy blue';
$string['color_wine'] = 'Burgundy';
$string['comparepdf'] = 'Select a PDF to compare';
$string['confirmbatch'] = 'I authorize issuance for eligible learners in this group.';
$string['confirmreissue'] = 'I authorize a replacement and understand that it retires an earlier valid credential.';
$string['confirmrevoke'] = 'I authorize revocation of this certificate.';
$string['confirmsingle'] = 'I authorize issuance for this learner.';
$string['defaultbody'] = 'Has successfully completed {course}.';
$string['download'] = 'Download PDF';
$string['edition'] = 'Revision {$a}';
$string['enabled'] = 'Enable this template for issuing certificates';
$string['eventrecorded'] = 'Certificate operation recorded';
$string['evidence'] = 'Eligibility evidence';
$string['export'] = 'Export CSV';
$string['fields'] = 'Available fields';
$string['filelimit'] = 'Select a PDF of up to 3 MB.';
$string['filter'] = 'Apply filters';
$string['fingerprint'] = 'PDF fingerprint (SHA-256)';
$string['fingerprinthelp'] = 'Compare the complete file to detect alterations or a copied QR. Your browser processes the file locally.';
$string['group'] = 'Group';
$string['history'] = 'Issuance history';
$string['housekeeping'] = 'Maintain SkillUps Certify operational records';
$string['inactive'] = 'Inactive';
$string['ineligible'] = 'The recipient does not meet the current conditions for this template.';
$string['invaliddesign'] = 'Review the required design fields, field lengths and placeholders.';
$string['invalidsource'] = 'Select an available source and provide a pathway ID if needed.';
$string['issued'] = 'Certificate issued.';
$string['issuedate'] = 'Issue date';
$string['issueheading'] = 'Issue certificates';
$string['issuer'] = 'Issuing institution';
$string['issuesingle'] = 'Issue certificate';
$string['item_existing'] = 'Already issued';
$string['item_issued'] = 'Issued';
$string['item_queued'] = 'Pending';
$string['item_skipped'] = 'Ineligible or unavailable';
$string['label'] = 'Template name';
$string['landscape'] = 'Landscape';
$string['license_community'] = 'Community';
$string['license_enterprise'] = 'Enterprise';
$string['license_expired'] = 'Expired';
$string['license_grace'] = 'Grace period';
$string['license_professional'] = 'Professional';
$string['licenseexpiry'] = 'Entitlement expires {$a}';
$string['licensestatus'] = 'Plan {$a}';
$string['match'] = 'The file matches the original archived PDF.';
$string['mine'] = 'My certificates';
$string['mismatch'] = 'The file does not match the original archived PDF.';
$string['navigation'] = 'Certificate navigation';
$string['newtemplate'] = 'New template';
$string['noactive'] = 'Create or enable a certificate template to begin issuing.';
$string['nobatches'] = 'There are no batches in this scope.';
$string['norecords'] = 'No certificates or recipients match this selection.';
$string['notemplates'] = 'There are no certificate templates for this course.';
$string['notlatest'] = 'Only the most recent edition can be reissued.';
$string['notverified'] = 'No credential could be verified using this code.';
$string['orientation'] = 'Page orientation';
$string['pathid'] = 'Learning Path itinerary ID';
$string['pdfinvalid'] = 'The certificate PDF is missing, altered or invalid.';
$string['pluginname'] = 'SkillUps Certify & Verify';
$string['portrait'] = 'Portrait';
$string['previewpdf'] = 'Preview PDF';
$string['privacy:metadata:action'] = 'Kind of operation performed.';
$string['privacy:metadata:audit'] = 'Log of issuance, revocation, export and template operations.';
$string['privacy:metadata:batch'] = 'Issuance batches and operators.';
$string['privacy:metadata:batchid'] = 'Batch identifier.';
$string['privacy:metadata:courseid'] = 'The issuing course.';
$string['privacy:metadata:files'] = 'The plugin uses course-context Moodle files to retain the original certificate PDFs.';
$string['privacy:metadata:issue'] = 'Issued credentials and their verification status.';
$string['privacy:metadata:issueid'] = 'Credential identifier.';
$string['privacy:metadata:item'] = 'Recipient outcomes in batch issuance.';
$string['privacy:metadata:objectid'] = 'Internal reference for the operation.';
$string['privacy:metadata:pdfhash'] = 'A fingerprint of the archived PDF.';
$string['privacy:metadata:reason'] = 'An administrative revocation note.';
$string['privacy:metadata:snapshot'] = 'The issued design, name, dates and proof at issue time.';
$string['privacy:metadata:state'] = 'Workflow state.';
$string['privacy:metadata:status'] = 'The current credential verification state.';
$string['privacy:metadata:tasks'] = 'The task service holds the operator and batch reference for asynchronous issuance.';
$string['privacy:metadata:timecreated'] = 'When the record was added.';
$string['privacy:metadata:timeissued'] = 'When the certificate was issued.';
$string['privacy:metadata:timemodified'] = 'When the record was last updated.';
$string['privacy:metadata:timerevoked'] = 'When the certificate was retired.';
$string['privacy:metadata:timeupdated'] = 'When a recipient outcome was last updated.';
$string['privacy:metadata:tokenhash'] = 'One-way lookup hash for the verification code.';
$string['privacy:metadata:userid'] = 'The Moodle user identifier.';
$string['publicmeta'] = 'Publish the certificate title, issuer and issue date in the QR verifier';
$string['publicverification'] = 'Enable public QR verification';
$string['publicverificationdesc'] = 'Anyone holding a verification link can see the status, reference and PDF fingerprint. Template publication settings control additional certificate fields.';
$string['queued'] = 'Issuance batch queued.';
$string['reason'] = 'Revocation reason (private)';
$string['reasonrequired'] = 'Enter a reason of up to 240 characters.';
$string['recentbatches'] = 'Recent issuance batches';
$string['recipient'] = 'Recipient';
$string['record'] = 'Certificate details';
$string['reference'] = 'Reference';
$string['refresh'] = 'Refresh progress';
$string['registry'] = 'Certificate registry';
$string['reissue'] = 'Reissue certificate';
$string['reissued'] = 'A replacement certificate was issued.';
$string['reissuehelp'] = 'A replacement uses the active template and rechecks eligibility.';
$string['revision'] = 'Revision';
$string['revoke'] = 'Revoke certificate';
$string['revokedate'] = 'Retirement date';
$string['revokedmessage'] = 'The certificate has been revoked.';
$string['samplename'] = 'Sample Recipient';
$string['sampleonly'] = 'Preview only';
$string['signer'] = 'Signatory name or role (optional)';
$string['single'] = 'Individual issuance';
$string['skillupscertify:export'] = 'Export certificate records';
$string['skillupscertify:issue'] = 'Issue student certificates';
$string['skillupscertify:manage'] = 'Manage certificate templates';
$string['skillupscertify:revoke'] = 'Revoke or replace certificates';
$string['skillupscertify:viewregistry'] = 'View the private certificate registry';
$string['source_course'] = 'Moodle course completion';
$string['source_manual'] = 'Manual authorization for an active learner';
$string['source_pathway'] = 'Verified Learning Path completion';
$string['staledesign'] = 'Another operator saved a new revision. Reload before editing.';
$string['startbatch'] = 'Queue issuance batch';
$string['status'] = 'Status';
$string['status_replaced'] = 'Replaced';
$string['status_revoked'] = 'Revoked';
$string['status_valid'] = 'Valid';
$string['template'] = 'Template';
$string['templatelimit'] = 'This course has reached the template limit.';
$string['templates'] = 'Certificate templates';
$string['templatesaved'] = 'The template revision was saved.';
$string['title'] = 'Certificate title';
$string['unavailable'] = 'This certificate or operation is unavailable to you.';
$string['username'] = 'Moodle username';
$string['verificationdisabled'] = 'Public verification is disabled on this site.';
$string['verify'] = 'Verify a certificate';
$string['verifybutton'] = 'Verify';
$string['visualpreview'] = 'Layout preview';
