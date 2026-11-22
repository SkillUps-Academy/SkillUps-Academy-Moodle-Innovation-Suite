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
 * SkillUps Question Studio local skillupsquestionstudio.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'You cannot perform this Studio operation.';
$string['aiunavailable'] = 'Moodle AI is unavailable. Check your permission, transfer consent and Moodle AI policy acceptance.';
$string['all'] = 'All types';
$string['answerarea'] = 'Response area';
$string['answerpool'] = 'Answer options';
$string['approve'] = 'Approve this revision';
$string['approved'] = 'Approved';
$string['approvefirst'] = 'Approve at least one current question revision to create an assessment.';
$string['assessment'] = 'Assessment preview';
$string['assessmentintro'] = 'Review this ordered selection of approved questions before exporting or importing it.';
$string['bloom'] = 'Bloom level';
$string['bloom_analyze'] = 'Analyze';
$string['bloom_apply'] = 'Apply';
$string['bloom_create'] = 'Create';
$string['bloom_evaluate'] = 'Evaluate';
$string['bloom_remember'] = 'Remember';
$string['bloom_understand'] = 'Understand';
$string['busy'] = 'This project is busy. Try again shortly.';
$string['category'] = 'Bank category';
$string['choices'] = 'Choices, one per line';
$string['competency'] = 'Course competency';
$string['confirmdelete'] = 'Confirm project deletion';
$string['consent'] = 'Authorize source transfer';
$string['consentdesc'] = 'I authorize sending an excerpt to this site\'s configured Moodle AI provider and confirm that institutional policy permits it.';
$string['correct'] = 'Correct answer';
$string['correctkey'] = 'Correct';
$string['count'] = 'Question count';
$string['createvariant'] = 'Create variant';
$string['dashboard'] = 'Studio projects';
$string['dashboardintro'] = 'Build reviewed assessments and publish approved questions to your Moodle course bank.';
$string['date'] = 'Updated';
$string['deleteconfirm'] = 'Remove Studio sources, drafts, history and variants. Moodle bank entries are managed separately.';
$string['deleteproject'] = 'Delete project';
$string['draft'] = 'Draft';
$string['duplicate'] = 'This project already has a question with the same normalized prompt.';
$string['duplicatesource'] = 'This file is already queued or available in this project.';
$string['editconflict'] = 'This question changed. Reload it before saving or reviewing.';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Expired';
$string['edition_grace'] = 'Offline grace period';
$string['edition_professional'] = 'Professional';
$string['editor'] = 'Question editor';
$string['emptydocument'] = 'No selectable text was found in this document.';
$string['enableai'] = 'Enable Moodle AI generation';
$string['enableaidesc'] = 'Allow consented text excerpts to be processed by the institution\'s configured Moodle AI provider.';
$string['eventstudiochanged'] = 'SkillUps Question Studio operation';
$string['evidence'] = 'View source text';
$string['export'] = 'Export Moodle XML';
$string['exportstats'] = 'Export aggregate CSV';
$string['extractionfailed'] = 'Document extraction failed.';
$string['feedback'] = 'General feedback';
$string['filter'] = 'Filter';
$string['generate'] = 'Generate drafts';
$string['generationfailed'] = 'Question generation did not return usable drafts.';
$string['generationqueued'] = 'Generation queued.';
$string['generator'] = 'Draft generator';
$string['hidekey'] = 'Hide answer key';
$string['historical'] = 'You are viewing a historical revision.';
$string['history'] = 'Revision history';
$string['importbank'] = 'Import approved questions';
$string['importfailed'] = 'Moodle could not import the approved questions.';
$string['invalidanswers'] = 'Check the answer count, uniqueness and correct response. Matching lists must have equal length.';
$string['invalidcategory'] = 'Select an accessible question bank category in this course.';
$string['invalidcompetency'] = 'The selected competency is unavailable in this course.';
$string['invalidcourse'] = 'Select a valid course.';
$string['invaliddocument'] = 'Choose one valid PDF, DOCX, PPTX or UTF-8 TXT file up to 8 MiB.';
$string['invalidquestion'] = 'Check the required question fields and classification.';
$string['invalidvariant'] = 'Select 1 to 30 questions. The project needs at least that many approved questions.';
$string['itemlimit'] = 'This project already contains 400 questions.';
$string['joblimit'] = 'Wait for the current job to finish or try again tomorrow if you reached the daily limit.';
$string['jobs'] = 'Recent generation jobs';
$string['kind'] = 'Question type';
$string['left'] = 'Matching prompts, one per line';
$string['level'] = 'Difficulty';
$string['level_easy'] = 'Easy';
$string['level_hard'] = 'Hard';
$string['level_medium'] = 'Medium';
$string['name'] = 'Question title';
$string['nearduplicate'] = 'Review a possible near duplicate before approving.';
$string['newproject'] = 'Create project';
$string['newquestion'] = 'New question';
$string['nocategories'] = 'Create or open a question bank in this course and verify permission to add questions to a category.';
$string['noprojects'] = 'Create a project to get started.';
$string['noquestions'] = 'No questions match the current filters.';
$string['nosources'] = 'No documents have been added.';
$string['notassigned'] = 'Unassigned';
$string['pdfunavailable'] = 'PDF extraction requires the site administrator to configure Poppler pdftotext.';
$string['pluginname'] = 'SkillUps Question Studio';
$string['preview'] = 'Question preview';
$string['privacy:account'] = 'Author, reviewer or publisher account.';
$string['privacy:ai'] = 'Consented excerpts may be sent to the site\'s configured Moodle AI provider.';
$string['privacy:document'] = 'Uploaded file name or authorized extracted source text.';
$string['privacy:files'] = 'Temporary source uploads use Moodle\'s File API.';
$string['privacy:question'] = 'Revision content, answer key and classification.';
$string['privacy:records'] = 'Question Studio course project data.';
$string['privacy:request'] = 'Configuration for a draft generation request.';
$string['produced'] = 'Drafts created';
$string['project'] = 'Project';
$string['projectdeleted'] = 'Student-facing bank questions remain available. Project removed.';
$string['projectintro'] = 'Manage source evidence, draft questions and review decisions for this assessment.';
$string['projectname'] = 'Project name';
$string['projects'] = 'Projects';
$string['prompt'] = 'Question prompt';
$string['promptchoice'] = 'Which description matches {$a}?';
$string['promptessay'] = 'Explain {$a} in your own words and illustrate it with an example.';
$string['promptmatch'] = 'Match these concepts to their descriptions: {$a}.';
$string['promptrubric'] = 'Check that the definition is accurate, the example is relevant, and the explanation shows how the example illustrates the concept. Reference: {$a}';
$string['promptshort'] = 'Name the concept described here: {$a}';
$string['prompttruth'] = 'True or false: {$a->term} is described as “{$a->statement}”.';
$string['provider'] = 'Generator or origin';
$string['provider_ai'] = 'Moodle AI drafts';
$string['provider_local'] = 'Local drafts';
$string['provider_manual'] = 'Manual questions';
$string['publish'] = 'Publish to course question bank';
$string['publishedcount'] = 'Questions imported into Moodle: {$a}';
$string['questions'] = 'Question manager';
$string['refresh'] = 'Refresh project';
$string['remove'] = 'Delete source text';
$string['removesource'] = 'Delete source';
$string['removesourceconfirm'] = 'Delete this document\'s extracted text and pending upload. Existing drafts stay in the project.';
$string['renewal'] = 'Installation identifier for SkillUps licence renewal';
$string['reopen'] = 'Return to draft';
$string['retention'] = 'Document text retention in days';
$string['retentiondesc'] = 'Purge extracted text after 1 to 365 days. Question revisions remain in their projects.';
$string['review'] = 'Teaching review';
$string['reviewintro'] = 'Check the wording and answer key against the teaching material before approving this revision.';
$string['revision'] = 'Revision';
$string['right'] = 'Matching answers, in prompt order';
$string['rubric'] = 'Grading guide';
$string['saved'] = 'Draft revision saved.';
$string['saverevision'] = 'Save revision';
$string['short'] = 'Accepted answers, one per line';
$string['showkey'] = 'Show answer key';
$string['skillupsquestionstudio:manage'] = 'Manage course question projects';
$string['skillupsquestionstudio:publish'] = 'Export approved questions or publish to Moodle banks';
$string['skillupsquestionstudio:review'] = 'Review and approve question revisions';
$string['skillupsquestionstudio:useai'] = 'Generate drafts with Moodle AI';
$string['source'] = 'Source';
$string['sourcefile'] = 'Source document';
$string['sourcelimit'] = 'This project has reached 30 documents.';
$string['sourcequeued'] = 'Document queued for extraction.';
$string['sourceremoved'] = 'Source text and pending upload removed.';
$string['sources'] = 'Source documents';
$string['sourcesintro'] = 'Import PDF, DOCX, PPTX or UTF-8 text. Each file may be up to 8 MiB.';
$string['sourceunavailable'] = 'This source is not ready or is unavailable.';
$string['stalevariant'] = 'An approval or competency changed. Create a new variant from current approved questions.';
$string['state_failed'] = 'Failed';
$string['state_queued'] = 'Queued';
$string['state_ready'] = 'Ready';
$string['state_working'] = 'Processing';
$string['status'] = 'Status';
$string['subscription'] = 'Subscription';
$string['taskmaintenance'] = 'Recover jobs and expire Question Studio sources';
$string['type_essay'] = 'Essay';
$string['type_match'] = 'Matching';
$string['type_multichoice'] = 'Multiple choice';
$string['type_shortanswer'] = 'Short answer';
$string['type_truefalse'] = 'True/false';
$string['unit'] = 'Unit';
$string['upload'] = 'Upload document';
$string['variantlimit'] = 'This project has reached 100 assessment variants.';
$string['variantname'] = 'Variant name';
$string['variants'] = 'Assessment variants';
$string['waitsource'] = 'Upload a document and wait for extraction. Manual authoring is available now.';
