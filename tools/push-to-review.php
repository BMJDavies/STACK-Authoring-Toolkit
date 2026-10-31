<?php
/**
 * Push a draft XML file into the review question bank and append any new
 * questions to the review preview quiz.
 *
 * Pipeline: import XML → review category → add to preview quiz.
 * No Maxima/PRT validation: that is test-question.sh's job. This script exists
 * so a reviewer (human or agent) can look at drafts the way a student would.
 *
 * Re-pushing is safe: a question already in the quiz (matched by <name>) gets a
 * new version only if its content has changed, through the
 * qbank_importasversion plugin that STACK requires, so the quiz never gets
 * duplicate pages and the bank keeps one entry per question.
 *
 * Normally invoked via tools/push-review.sh, which reads project/project.conf.
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
// tools/lib/publish.php, copied beside this script by push-review.sh.
require(preg_replace('/\.php$/', '_lib.php', __FILE__));

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
    echo "Normally invoked via tools/push-review.sh, which fills these in from project/project.conf.\n";
    exit(1);
}

kit_require_importasversion();

$admin = get_admin();
\core\session\manager::set_user($admin);
global $DB;

// ============================================================
// 1. Split the XML into one file per question
// ============================================================
echo "=== Push into review bank (cat $categoryid) ===\n";

$workdir = make_request_directory();
$incoming = kit_split_questions($xmlfile, $workdir);
if ($incoming === null) {
    echo "=== RESULT: FAILED ($xmlfile is not well-formed XML) ===\n";
    exit(1);
}
if (empty($incoming)) {
    echo "Nothing to import, aborting.\n";
    exit(1);
}

// ============================================================
// 2. The latest version of each question already in the quiz, by name.
// ============================================================
$existing = kit_quiz_questions($quizid);

// ============================================================
// 3. For each question: a NEW VERSION if it is in the quiz and has
//    changed, UNCHANGED if not, ADD a slot if it is new.
// ============================================================
$max_slot = (int)$DB->get_field('quiz_slots', 'MAX(slot)', ['quizid' => $quizid]);
$next_page = $max_slot + 1;

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

// New questions whose names carry the same page code (_P04_, see the style
// guide) share a page, as push-live.sh will place them; everything else gets a
// page of its own. Questions already in the quiz keep their page.
$prev_code = null;

$added = 0;
$updated = 0;
$failed = 0;
foreach ($incoming as $item) {
    $name = $item['name'];
    $info = $existing[$name] ?? null;
    if ($info && $info->qtype === $item['qtype']) {
        $changed = kit_changed_fields($info->questionid, $item['file'], $workdir);
        if (!$changed) {
            echo "  Unchanged [{$info->qtype}] v{$info->version} '$name'\n";
            continue;
        }
        [$newid, $error] = kit_add_version($info->questionid, $item['file']);
        if (!$newid) {
            echo "  FAILED to add a version: '$name': $error\n";
            $failed++;
            continue;
        }
        $v = $info->version + 1;
        echo "  New version [{$info->qtype}] v{$v} '$name' (changed: " . implode(', ', $changed) . ")\n";
        $updated++;
        continue;
    }
    $newid = kit_import_new($item['file'], $categoryid, $contextid);
    if (!$newid) {
        echo "  FAILED to import: '$name'\n";
        $failed++;
        continue;
    }
    if ($info) {
        // Moodle cannot version a question into a different type: point the
        // slot at a new bank entry instead.
        $new_qbe = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $newid]);
        $DB->set_field('question_references', 'questionbankentryid', $new_qbe, ['id' => $info->ref_id]);
        echo "  [WARN] Replaced [{$info->qtype} -> {$item['qtype']}] '$name' (a type change cannot be a new version)\n";
        $updated++;
        continue;
    }
    $code = preg_match('/_P(\d+)[a-z]?_/', $name, $m) ? $m[1] : null;
    if ($added > 0 && ($code === null || $code !== $prev_code)) {
        $next_page++;
    }
    $prev_code = $code;
    $maxmark = ($item['qtype'] === 'description') ? 0 : null;
    quiz_add_quiz_question($newid, $quiz, $next_page, $maxmark);
    echo "  Added [{$item['qtype']}] id={$newid} '$name' on page $next_page\n";
    $added++;
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

if ($failed > 0) {
    echo "=== RESULT: $failed question(s) FAILED ===\n";
    exit(1);
}
