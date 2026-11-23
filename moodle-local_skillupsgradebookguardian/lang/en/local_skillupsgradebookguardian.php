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
 * English strings for Gradebook Guardian.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activities'] = 'Grade-linked or grading-capable activities';
$string['actor'] = 'Operator';
$string['advice_aggregation'] = 'Verify global configuration and the stored method before making any changes.';
$string['advice_categorycycle'] = 'Have the technical team review the hierarchy before running recalculation.';
$string['advice_dependency'] = 'Review the referenced items and category totals with technical staff before recalculating.';
$string['advice_droprules'] = 'Confirm the intended selection rule in the native category editor.';
$string['advice_duplicate'] = 'Compare the module and item number in a test environment before intervention.';
$string['advice_empty'] = 'Check whether this category is reserved for future grading.';
$string['advice_formula'] = 'Verify the expression in the native calculation editor with representative inputs.';
$string['advice_formulalimit'] = 'Review the full expression in the native calculation editor.';
$string['advice_hidden'] = 'Review visibility and any scheduled release in Moodle.';
$string['advice_hiddenmismatch'] = 'Check the intended release policy and simulate hiding the grade item if appropriate.';
$string['advice_hiddenreview'] = 'Review the grade item and activity visibility in Moodle. This item is not eligible for assisted repair.';
$string['advice_itemcategory'] = 'Ask the technical team to recover the category relationship through supported Moodle tooling.';
$string['advice_missingactivitygrade'] = 'Confirm whether grading is enabled in this activity. Some activity configurations do not produce grades.';
$string['advice_parent'] = 'Check the hierarchy after backup or restore. Escalate an invalid parent relationship.';
$string['advice_pending'] = 'Confirm the status in Moodle. The flag alone does not identify a calculation failure.';
$string['advice_range'] = 'Verify the original grade range in the activity or native item editor before applying any correction.';
$string['advice_reference'] = 'Check missing or ambiguous identifiers with technical staff before editing the formula.';
$string['advice_root'] = 'Ask the technical team to verify the root category and native course total.';
$string['advice_rootactivity'] = 'Verify whether the activity should be assigned to an explicit category.';
$string['advice_scale'] = 'Check the scale in the activity and ask technical staff to review the invalid association.';
$string['advice_total'] = 'Ask the technical team to reconcile the Moodle total item with its category.';
$string['advice_unlinked'] = 'Check the activity and pending deletion tasks. Escalate a persistent orphaned item.';
$string['advice_weight'] = 'Review the category method, manual weights and extra credit in Gradebook setup.';
$string['advice_weightszero'] = 'Verify which items should contribute to the category total and update weights in Moodle if needed.';
$string['after'] = 'Resulting state';
$string['all'] = 'All';
$string['anonymized'] = 'Anonymized';
$string['approve'] = 'Approve and hide grade item';
$string['approveundo'] = 'Approve restoration of original visibility';
$string['backhistory'] = 'Back to history';
$string['baseline'] = 'Baseline diagnosis number';
$string['before'] = 'Original state';
$string['busy'] = 'Another Guardian operation is running for this course. Try again shortly.';
$string['categories'] = 'Categories';
$string['change_added'] = 'New';
$string['change_changed'] = 'Evidence changed';
$string['change_resolved'] = 'Absent in this diagnosis';
$string['change_unchanged'] = 'Unchanged';
$string['comparison'] = 'Comparison';
$string['cooldown'] = 'Wait ten seconds between diagnoses of this course.';
$string['csv'] = 'Download CSV';
$string['date'] = 'Date';
$string['dependencies'] = 'Direct grade item dependencies';
$string['diagnose'] = 'Run diagnosis';
$string['emptyfindings'] = 'No findings match these filters.';
$string['emptyhistory'] = 'Run a diagnosis to see the course configuration and available actions.';
$string['emptyrelationships'] = 'No grade item relationships match this filter.';
$string['eventoperation'] = 'Gradebook Guardian operation';
$string['evidence'] = 'Evidence';
$string['expired'] = 'This simulation expired. Create another from the diagnosis.';
$string['field_activity'] = 'Activity ID';
$string['field_aggregation'] = 'Aggregation code';
$string['field_category'] = 'Category ID';
$string['field_count'] = 'Count';
$string['field_droplow'] = 'Drop lowest';
$string['field_effective'] = 'Effective aggregation code';
$string['field_hidden'] = 'Visibility';
$string['field_item'] = 'Grade item ID';
$string['field_keephigh'] = 'Keep highest';
$string['field_max'] = 'Maximum';
$string['field_min'] = 'Minimum';
$string['field_related'] = 'Related grade item IDs';
$string['field_weight'] = 'Weight';
$string['filter'] = 'Apply filters';
$string['finding'] = 'Finding';
$string['findings'] = 'Findings';
$string['history'] = 'Audit history';
$string['impact'] = 'Impact';
$string['impactdetail'] = 'One grade item visibility setting changes. Guardian submits no student grades and performs no recalculation. Moodle controls the visibility of individual grades and totals independently.';
$string['intro'] = 'Audit course gradebook configuration, review evidence and approve eligible repairs.';
$string['invalidaction'] = 'Invalid Gradebook Guardian operation.';
$string['items'] = 'Grade items';
$string['json'] = 'Download JSON';
$string['kind_apply'] = 'Approved repair';
$string['kind_diagnosis'] = 'Diagnosis';
$string['kind_preview'] = 'Simulation';
$string['kind_revert'] = 'Approved recovery';
$string['licenseexpires'] = 'Entitlement expiry {$a}';
$string['licensefeature'] = 'A signed entitlement is required for this export format.';
$string['licensestate_community'] = 'Community';
$string['licensestate_enterprise'] = 'Enterprise';
$string['licensestate_expired'] = 'Expired';
$string['licensestate_grace'] = 'Offline grace period';
$string['licensestate_professional'] = 'Professional';
$string['licensestatus'] = 'Subscription status — {$a}';
$string['nativegradebook'] = 'Gradebook setup';
$string['noevidence'] = 'See the affected record identifier.';
$string['notrepairable'] = 'This item is no longer eligible for assisted repair. Review the gradebook and the activity in Moodle.';
$string['operation'] = 'Operation';
$string['operatorid'] = 'User #{$a}';
$string['pluginname'] = 'SkillUps Gradebook Guardian';
$string['preview'] = 'Simulate repair';
$string['privacy:metadata:courseid'] = 'The course in which the operation occurred.';
$string['privacy:metadata:kind'] = 'The type of operation.';
$string['privacy:metadata:ledger'] = 'Course diagnoses, simulations and approved changes.';
$string['privacy:metadata:parentid'] = 'The related diagnosis or simulation identifier.';
$string['privacy:metadata:payload'] = 'Structural evidence and before and after settings.';
$string['privacy:metadata:timecreated'] = 'When the operation occurred.';
$string['privacy:metadata:userid'] = 'The operator responsible for the action.';
$string['purgetask'] = 'Remove expired Gradebook Guardian history';
$string['recommendation'] = 'Recommendation';
$string['recovery'] = 'Recovery';
$string['recoverydetail'] = 'After approval, an authorized manager can restore the original setting from the simulation while the audit is retained and the item configuration still matches the applied result.';
$string['related'] = 'Open related entry';
$string['relationships'] = 'Relationships';
$string['repairdisabled'] = 'Assisted repair is disabled or requires native Gradebook management permission.';
$string['repairenabled'] = 'Enable assisted visibility repairs';
$string['repairenabled_help'] = 'Allow permissioned previews, approval and recovery for eligible items.';
$string['repairfailed'] = 'Moodle did not persist the approved state. The transaction was rolled back.';
$string['reportviews'] = 'Diagnosis views';
$string['retention'] = 'Audit retention in days';
$string['retention_help'] = 'The daily task removes expired history and simulations.';
$string['rule'] = 'Rule';
$string['rule_aggregation'] = 'Unrecognized aggregation method';
$string['rule_categorycycle'] = 'Category cycle or blocked ancestor';
$string['rule_dependency'] = 'Calculation cycle or dependency blocked by a cycle';
$string['rule_droprules'] = 'Both drop lowest and keep highest are set';
$string['rule_duplicate'] = 'Duplicate activity grade item identity';
$string['rule_empty'] = 'Empty grade category';
$string['rule_formula'] = 'Calculation failed native static validation';
$string['rule_formulalimit'] = 'Formula exceeds the diagnosis length limit';
$string['rule_hidden'] = 'Hidden grade item';
$string['rule_hiddenmismatch'] = 'Hidden activity with a visible grade item';
$string['rule_hiddenreview'] = 'Visibility mismatch requires manual review';
$string['rule_itemcategory'] = 'Grade item without a valid category';
$string['rule_missingactivitygrade'] = 'Grading-capable activity has no grade item';
$string['rule_parent'] = 'Category parent absent in this course';
$string['rule_pending'] = 'Moodle marks this total or calculation for regrading';
$string['rule_range'] = 'Numeric grade maximum is not above its minimum';
$string['rule_reference'] = 'Calculation has an unresolved reference';
$string['rule_root'] = 'Unexpected root category count';
$string['rule_rootactivity'] = 'Activity uses the course root category';
$string['rule_scale'] = 'Missing or out-of-course grade scale';
$string['rule_total'] = 'Missing, duplicate or incorrect category total';
$string['rule_unlinked'] = 'Grade item with no active activity link';
$string['rule_weight'] = 'Weight outside the expected multiplier or proportion';
$string['rule_weightszero'] = 'Weighted mean has no positive net weight';
$string['saved_apply'] = 'Grade item visibility updated and audited.';
$string['saved_diagnose'] = 'Diagnosis saved.';
$string['saved_preview'] = 'Simulation saved. Review the proposed change and recovery.';
$string['saved_revert'] = 'Original grade item visibility restored and audited.';
$string['scanlimit'] = 'Maximum structural records per diagnosis';
$string['scanlimit_help'] = 'The limit counts grade items, categories and grading-capable or linked activities combined.';
$string['severity'] = 'Severity';
$string['severity_error'] = 'Error';
$string['severity_info'] = 'Information';
$string['severity_warning'] = 'Warning';
$string['skillupsgradebookguardian:approve'] = 'Approve and recover grade item repairs';
$string['skillupsgradebookguardian:diagnose'] = 'Run diagnoses and simulate repairs';
$string['skillupsgradebookguardian:export'] = 'Export course gradebook diagnostics';
$string['skillupsgradebookguardian:view'] = 'View gradebook diagnostics';
$string['stale'] = 'The simulation expired, was already processed, or the gradebook changed. Review the current configuration.';
$string['target'] = 'Target';
$string['target_activity'] = 'Activity #{$a}';
$string['target_category'] = 'Category #{$a}';
$string['target_course'] = 'Course gradebook';
$string['target_item'] = 'Grade item #{$a}';
$string['toolarge'] = 'This gradebook exceeds the configured diagnosis limit of {$a} records.';
$string['treatment'] = 'Treatment';
$string['treatment_repair'] = 'Assisted repair available for eligible items';
$string['treatment_review'] = 'Review in Moodle';
$string['treatment_technical'] = 'Technical intervention';
$string['visibility_hidden'] = 'Hidden at item level';
$string['visibility_until'] = 'Hidden until {$a}';
$string['visibility_visible'] = 'Visible at item level';
