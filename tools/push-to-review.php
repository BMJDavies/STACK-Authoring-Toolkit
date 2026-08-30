<?php
/**
 * Push a draft XML file into the review question bank and append any new
 * questions to the review preview quiz.
 *
 * Pipeline: import XML → review category → add to preview quiz.
 * No Maxima/PRT validation: that is test-question.sh's job. This script exists
 * so a reviewer (human or agent) can look at drafts the way a student would.
 *
 * Idempotent(ish): re-importing the same XML creates new question versions in
 * the bank (that is Moodle's behaviour), but quiz-slot dedupe by name means you
 * do not get duplicate quiz pages. Dedupe the category with
 * cleanup-duplicates.php if the bank gets noisy.
 *
 * Normally invoked via tools/push-review.sh, which reads tools/project.conf.
 *
 * Usage:
 *   php push-to-review.php <xml> <quizid> --categoryid=<n> --contextid=<n> [--url=...]
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

// --- Config: review qbank (supplied by tools/push-review.sh) ----------------
$categoryid = 0;
$contextid  = 0;
$siteurl    = 'http://localhost:8000';

$positional = [];
for ($i = 1; $i < $argc; $i++) {
    if (preg_match('/^--categoryid=(\d+)$/', $argv[$i], $m))     { $categoryid = (int)$m[1]; }
    else if (preg_match('/^--contextid=(\d+)$/', $argv[$i], $m)) { $contextid  = (int)$m[1]; }
    else if (preg_match('/^--url=(.*)$/', $argv[$i], $m))        { $siteurl    = rtrim($m[1], '/'); }
    else { $positional[] = $argv[$i]; }
}

// --- Setup ------------------------------------------------------------------
$xmlfile = $positional[0] ?? null;
$quizid  = isset($positional[1]) ? (int)$positional[1] : 0;
if (!$xmlfile || !file_exists($xmlfile) || !$quizid || !$categoryid || !$contextid) {
    echo "Usage: php push-to-review.php <xml> <quizid> --categoryid=<n> --contextid=<n> [--url=...]\n";
    echo "Normally invoked via tools/push-review.sh, which fills these in from tools/project.conf.\n";
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);
global $DB;

$context = context::instance_by_id($contextid);

// ============================================================
// 1. Import XML into the review category
// ============================================================
echo "=== Import into review bank (cat $categoryid) ===\n";

$format = new qformat_xml();
$format->setCategory((object)['id' => $categoryid, 'contextid' => $contextid]);
$format->setContexts([$context]);
$format->setCatfromfile(false);
$format->setMatchgrades('error');
$format->setStoponerror(false);
$format->setFilename($xmlfile);

ob_start();
$import_ok = $format->importprocess();
$raw_output = ob_get_clean();

$clean = html_entity_decode(strip_tags($raw_output), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$clean = preg_replace('/\n{3,}/', "\n\n", trim($clean));
echo $clean . "\n";

if (!$import_ok) {
    echo "=== RESULT: FAILED (import error) ===\n";
    exit(1);
}

$imported_ids = $format->questionids ?? [];
echo "Imported " . count($imported_ids) . " question(s).\n\n";

if (empty($imported_ids)) {
    echo "Nothing imported, aborting.\n";
    exit(1);
}

// ============================================================
// 2. Fetch the imported questions (name + qtype for reporting)
// ============================================================
list($in_sql, $params) = $DB->get_in_or_equal($imported_ids, SQL_PARAMS_NAMED, 'qid');
$sql = "SELECT q.id, q.name, q.qtype
          FROM {question} q
         WHERE q.id $in_sql
      ORDER BY q.id ASC";
$imported = array_values($DB->get_records_sql($sql, $params));

// ============================================================
// 3. Build a map of question NAMES already in the quiz → their
//    quiz_slots.id and current questionbankentryid.
// ============================================================
$slot_sql = 'SELECT qs.id AS slotid, qr.id AS refid, qr.questionbankentryid AS old_qbe,
                    q.name AS name
             FROM {quiz_slots} qs
             JOIN {question_references} qr ON qr.itemid = qs.id
                 AND qr.component = \'mod_quiz\'
                 AND qr.questionarea = \'slot\'
             JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
             JOIN {question} q ON q.id = qv.questionid
             WHERE qs.quizid = :quizid';
$existing_in_quiz = []; // name → {refid, old_qbe}
foreach ($DB->get_recordset_sql($slot_sql, ['quizid' => $quizid]) as $r) {
    $existing_in_quiz[$r->name] = $r;
}

// Build a map from imported question id → its new questionbankentryid.
$new_qbe_map = []; // question.id → qbe.id
if (!empty($imported_ids)) {
    list($qid_in, $qid_params) = $DB->get_in_or_equal($imported_ids, SQL_PARAMS_NAMED, 'nq');
    $qbe_rows = $DB->get_records_sql(
        "SELECT qv.questionid, qv.questionbankentryid
           FROM {question_versions} qv
          WHERE qv.questionid $qid_in", $qid_params);
    foreach ($qbe_rows as $row) {
        $new_qbe_map[$row->questionid] = $row->questionbankentryid;
    }
}

// ============================================================
// 4. For each imported question: UPDATE existing quiz ref or ADD new slot.
// ============================================================
$max_slot = (int)$DB->get_field('quiz_slots', 'MAX(slot)', ['quizid' => $quizid]);
$next_page = $max_slot + 1;

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

$added = 0;
$updated = 0;
$orphaned_qbes = []; // old qbe ids to clean up
foreach ($imported as $q) {
    $new_qbe = $new_qbe_map[$q->id] ?? null;
    if (isset($existing_in_quiz[$q->name])) {
        $ref = $existing_in_quiz[$q->name];
        if ($new_qbe && $new_qbe != $ref->old_qbe) {
            // Update the quiz reference to point to the newly imported bank entry.
            $DB->set_field('question_references', 'questionbankentryid', $new_qbe,
                           ['id' => $ref->refid]);
            $orphaned_qbes[] = $ref->old_qbe;
            echo "  Updated [{$q->qtype}] id={$q->id} '{$q->name}' (qbe {$ref->old_qbe} → {$new_qbe})\n";
            $updated++;
        } else {
            echo "  Unchanged [{$q->qtype}] id={$q->id} '{$q->name}'\n";
        }
        continue;
    }
    $maxmark = ($q->qtype === 'description') ? 0 : null;
    quiz_add_quiz_question($q->id, $quiz, $next_page, $maxmark);
    echo "  Added [{$q->qtype}] id={$q->id} '{$q->name}' on page $next_page\n";
    $next_page++;
    $added++;
}

// ============================================================
// 5. Clean up orphaned question bank entries (old versions replaced above).
// ============================================================
if (!empty($orphaned_qbes)) {
    foreach ($orphaned_qbes as $old_qbe) {
        // Only delete if no other quiz still references this bank entry.
        $ref_count = $DB->count_records('question_references', ['questionbankentryid' => $old_qbe]);
        if ($ref_count == 0) {
            // Delete version, then bank entry. Question record stays (Moodle gc handles it).
            $DB->delete_records('question_versions', ['questionbankentryid' => $old_qbe]);
            $DB->delete_records('question_bank_entries', ['id' => $old_qbe]);
            echo "  Cleaned up orphaned qbe $old_qbe\n";
        }
    }
}

if ($added > 0 || $updated > 0) {
    \mod_quiz\grade_calculator::create(\mod_quiz\quiz_settings::create($quiz->id))
        ->recompute_quiz_sumgrades();
    echo "\nAdded $added, updated $updated question(s). Sumgrades recalculated.\n";
} else {
    echo "\nNo changes: quiz already up to date.\n";
}

$cmid = (int)$DB->get_field_sql(
    'SELECT cm.id FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module
      WHERE m.name = :mn AND cm.instance = :qid',
    ['mn' => 'quiz', 'qid' => $quizid]
);
echo "Preview: {$siteurl}/mod/quiz/view.php?id={$cmid}\n";
