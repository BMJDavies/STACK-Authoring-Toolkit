<?php
/**
 * Sync questions from a Moodle quiz back to local project/content/live/.
 *
 * Exports every question currently in the given quiz to a single Moodle XML
 * file under project/content/live/. Use this when questions have been hand-edited in
 * Moodle and you want the repo to catch up.
 *
 * Direction: Moodle -> disk. Never the reverse (use tools/push-live.sh for that).
 *
 * Usage (from your Mac terminal):
 *   docker cp tools/sync-from-moodle.php <container>:/tmp/sync.php
 *   docker exec <container> php /tmp/sync.php <quizid> <outfile>
 *
 * Example:
 *   docker exec <container> php /tmp/sync.php 75 /tmp/export.xml
 *   docker cp <container>:/tmp/export.xml project/content/approved/Ch5A.xml
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/editlib.php'); // get_questions_category(), used by the exporter

global $DB;

$quizid  = (int)($argv[1] ?? 0);
$outfile = $argv[2] ?? null;

if (!$quizid || !$outfile) {
    echo "Usage: php sync-from-moodle.php <quizid> <outfile>\n";
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

// Find all question IDs currently in the quiz (latest version of each slot).
$sql = 'SELECT q.id, q.name
        FROM {quiz_slots} qs
        JOIN {question_references} qr
             ON qr.itemid = qs.id
             AND qr.component = \'mod_quiz\'
             AND qr.questionarea = \'slot\'
        JOIN {question_versions} qv
             ON qv.questionbankentryid = qr.questionbankentryid
        JOIN {question} q ON q.id = qv.questionid
        WHERE qs.quizid = :quizid
        ORDER BY qs.slot ASC, qv.version DESC';

$rows = $DB->get_records_sql($sql, ['quizid' => $quizid]);
if (!$rows) {
    echo "No questions found in quiz $quizid.\n";
    exit(1);
}

// De-duplicate (multiple versions per bank entry): keep the first we see per entry.
$question_ids = [];
$seen = [];
foreach ($rows as $r) {
    if (isset($seen[$r->name])) continue;
    $seen[$r->name] = true;
    $question_ids[] = (int)$r->id;
}

echo "Exporting " . count($question_ids) . " question(s) from quiz $quizid...\n";

// Use Moodle's qformat_xml exporter. It needs a context; we pick the context
// of the first question's category. Do not give it the category itself: with
// one set, the exporter ignores setQuestions() and exports every question in
// the category, which for a course bank is every section.
$first_cat_sql = 'SELECT qbe.questioncategoryid, qc.contextid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  WHERE q.id = :qid
                  LIMIT 1';
$catrow = $DB->get_record_sql($first_cat_sql, ['qid' => $question_ids[0]]);
$context = context::instance_by_id($catrow->contextid);

$format = new qformat_xml();
$format->setContexts([$context]);
$format->setCattofile(false);
$format->setQuestions(array_map(function($qid) { global $DB; return $DB->get_record('question', ['id' => $qid]); }, $question_ids));

if (!$format->exportpreprocess()) {
    echo "Export preprocess failed.\n";
    exit(1);
}

$xml = $format->exportprocess();
file_put_contents($outfile, $xml);

echo "Wrote " . strlen($xml) . " bytes to $outfile\n";
echo "Done.\n";
