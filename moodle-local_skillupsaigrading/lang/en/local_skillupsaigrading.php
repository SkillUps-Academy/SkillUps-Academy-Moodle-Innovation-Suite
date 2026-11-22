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
 * Canonical English language strings.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'You do not have permission to access this review or submission.';
$string['aigenerated'] = 'AI-generated recommendation. Verify every criterion, quotation and inference before using it.';
$string['allowexternal'] = 'Allow non-loopback provider destinations';
$string['allowexternal_desc'] = 'Applies to every endpoint outside the exact loopback Ollama configuration. Enable only under your institution’s data processing policy.';
$string['approve'] = 'Approve review';
$string['assignmentreviews'] = 'Assignment reviews';
$string['approved'] = 'Approved';
$string['backassignment'] = 'Back to assignment reviews';
$string['busy'] = 'This assignment is processing a request. Try again shortly.';
$string['compare'] = 'Record grade comparison';
$string['comparison'] = 'Recorded Moodle grade: {$a->human}% on {$a->date}. Difference: {$a->delta} percentage points.';
$string['consent'] = 'I reviewed the excerpt and rubric for personal data and explicitly authorize sending them to the selected AI provider.';
$string['create'] = 'Create review';
$string['dailylimit'] = 'Reviews per assignment in 24 hours';
$string['dailylimit_desc'] = 'Allowed range 1–500. Includes local and AI reviews.';
$string['decisions'] = 'Decision history (latest 100)';
$string['edit'] = 'Save feedback draft';
$string['emptydocument'] = 'The source contains no extractable text.';
$string['enableai'] = 'Enable AI providers';
$string['enableai_desc'] = 'Enable asynchronous AI requests. The local worksheet is always available.';
$string['eraseduser'] = 'Former reviewer';
$string['evaluatedat'] = 'Evaluated {$a}';
$string['eventaggregate'] = 'SkillUps grading aggregate report exported';
$string['eventreviewupdated'] = 'SkillUps grading review updated';
$string['excerpt'] = 'Selected excerpt';
$string['excerpthelp'] = 'Keep only relevant excerpts. Remove identifiers or sensitive passages before sending. Use line breaks to separate excerpts copied from the source. Basic redaction is applied to common identifiers and still requires teacher review.';
$string['exportsummary'] = 'Download aggregate CSV';
$string['external'] = 'external transfer';
$string['failed'] = 'Failed';
$string['failedhelp'] = 'The request could not be completed. Check the provider availability and settings, then create a new review.';
$string['feedback'] = 'Teacher-editable feedback';
$string['gap'] = 'Possible gap';
$string['history'] = 'Recent reviews';
$string['historyentry'] = '{$a->date} · {$a->state} · {$a->review}';
$string['humanonly'] = 'These reviews are visible to authorized markers. Saving, approving or rejecting a review does not publish feedback or update grades. Use Moodle grading to record the final decision.';
$string['invaliddocument'] = 'Choose a supported file from this submission (TXT, DOCX, PPTX or PDF, up to 8 MB).';
$string['invalidexcerpt'] = 'Select up to 12,000 characters from the displayed source. Each line must occur verbatim in that source.';
$string['invalidreview'] = 'Invalid review operation or feedback length.';
$string['jobstate'] = 'Attempt {$a->attempt} · {$a->state} · {$a->review} · Requested {$a->date} · Updated {$a->updated}';
$string['licensecommunity'] = 'Community';
$string['licenseenterprise'] = 'Enterprise';
$string['licenseexpired'] = 'Expired';
$string['licenseexpires'] = 'Renew before {$a}';
$string['licensegrace'] = 'Offline grace period';
$string['licenseinstallation'] = 'Installation identifier: {$a}';
$string['licenseprofessional'] = 'Professional';
$string['licensestatus'] = 'Subscription status';
$string['localworksheet'] = 'Local evidence worksheet. Keyword matches require teacher verification.';
$string['loopback'] = 'local server';
$string['maintenance'] = 'Clean up interrupted and expired grading reviews';
$string['metricai'] = 'AI requests';
$string['metricapproved'] = 'Approved';
$string['metriccompared'] = 'Grade comparisons';
$string['metriccomparedvalue'] = 'Grade comparisons: {$a}';
$string['metricfailed'] = 'Failed';
$string['metricmae'] = 'Mean absolute difference (percentage points)';
$string['metricrejected'] = 'Rejected';
$string['metrictotal'] = 'Authorized reviews';
$string['nativegrader'] = 'Open Moodle grader';
$string['noassignments'] = 'No compatible assignments are available to you in this course.';
$string['nohistory'] = 'No entries yet.';
$string['noparticipants'] = 'There are no eligible participants to review in the current scope.';
$string['norubric'] = 'Select Rubric as the advanced grading method and publish a rubric with up to 20 criteria and 15 levels per criterion.';
$string['nosources'] = 'No supported evidence sources were found in the submitted attempt.';
$string['nosubmission'] = 'This student has no current submitted attempt.';
$string['onlinetext'] = 'Online text';
$string['overview'] = 'Review evidence and rubric-based suggestions for assignment submissions in this course.';
$string['participants'] = 'Eligible participants';
$string['pdfunavailable'] = 'PDF text extraction needs a deployment-configured Poppler pdftotext binary.';
$string['pending'] = 'Pending review';
$string['pluginname'] = 'SkillUps AI Grading Assistant';
$string['points'] = 'Suggested points: {$a}';
$string['prepare'] = 'Prepare evidence';
$string['privacy:metadata:action'] = 'Teacher review action.';
$string['privacy:metadata:actor'] = 'Teacher acting on the review.';
$string['privacy:metadata:connector'] = 'An enabled provider receives authorized selected supporting text and rubric criteria.';
$string['privacy:metadata:decision'] = 'Teacher feedback, decisions and timestamps.';
$string['privacy:metadata:excerpt'] = 'Teacher-approved selection of submission text.';
$string['privacy:metadata:feedback'] = 'Teacher feedback or comparison snapshot.';
$string['privacy:metadata:human'] = 'Recorded native grade percentage for comparison.';
$string['privacy:metadata:job'] = 'Review snapshots, selected evidence pending transfer, model metadata, suggestions and grade comparisons.';
$string['privacy:metadata:provider'] = 'Provider and model provenance metadata.';
$string['privacy:metadata:result'] = 'Evidence-backed recommendations and draft feedback.';
$string['privacy:metadata:rubric'] = 'Rubric used for the review.';
$string['privacy:metadata:subject'] = 'Student whose submission is reviewed.';
$string['privacy:metadata:timecreated'] = 'Time of the operation.';
$string['privacy:path'] = 'SkillUps grading reviews';
$string['provenance'] = 'Provider {$a->provider} · Requested model {$a->model} · Configured version {$a->version} · Reported model {$a->reported} · Rubric SHA-256 {$a->hash}';
$string['provider'] = 'Processing method';
$string['provider_enabled'] = 'Enable provider slot';
$string['provider_model'] = 'Model ID';
$string['provider_modelversion'] = 'Deployed model version or revision';
$string['provider_output'] = 'Maximum output tokens (64–4096)';
$string['provider_temperature'] = 'Temperature (0–2)';
$string['providercompatible'] = 'Compatible endpoint';
$string['providerconf'] = 'Connections and tokens are configured in config.php and environment variables.';
$string['providerlocal'] = 'Local worksheet without AI';
$string['provideroff'] = 'This provider is unavailable or the transfer was not authorized.';
$string['providerollama'] = 'Ollama';
$string['provideropenai'] = 'OpenAI';
$string['queued'] = 'Queued';
$string['ratelimit'] = 'This assignment has reached its review limit for the last 24 hours.';
$string['rationale'] = 'Justification to verify';
$string['ready'] = 'Ready';
$string['refresh'] = 'Refresh status';
$string['reject'] = 'Reject review';
$string['rejected'] = 'Rejected';
$string['retention'] = 'Review retention in days';
$string['retention_desc'] = 'Allowed range 1–365. Cron removes expired reviews and decision records.';
$string['reviewjob'] = 'Review for {$a}';
$string['rubric'] = 'Native assignment rubric';
$string['saved'] = 'Review decision saved.';
$string['skillupsaigrading:review'] = 'Create and review grading suggestions';
$string['skillupsaigrading:useai'] = 'Authorize AI processing of selected evidence';
$string['skillupsaigrading:view'] = 'View the grading assistant';
$string['source'] = 'Submission evidence source';
$string['stale'] = 'Out of date';
$string['stalesnapshot'] = 'This review snapshot or decision is out of date. Reload the page and prepare a new review if the submission or rubric changed.';
$string['statistics'] = 'Review statistics';
$string['statisticsscope'] = 'Statistics include the latest 500 reviews in the authorized participant scope.';
$string['studenthistory'] = 'Review history';
$string['suggested'] = 'Suggested rubric score: {$a}%';
$string['unavailablegrade'] = 'A comparable numeric grade for this same attempt is unavailable.';
$string['uncertain'] = 'Insufficient or uncertain evidence';
$string['unsupportedassignment'] = 'This release supports individual assignments with visible student identities.';
$string['working'] = 'Processing';
