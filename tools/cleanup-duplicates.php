<?php
/**
 * Delete duplicate questions from a question category, keeping the highest id
 * (the most recent import) of each name.
 *
 * Every validation run re-imports the same questions, so the sandbox category
 * accumulates copies. This tidies it up. Run it against the SANDBOX category,
 * not a live one.
 *
 * Usage:
 *   docker cp tools/cleanup-duplicates.php <container>:/tmp/cleanup.php
 *   docker exec <container> php /tmp/cleanup.php --categoryid=<DEV_CATEGORYID>
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/editlib.php');

global $DB;

$categoryid = 0;
for ($i = 1; $i < $argc; $i++) {
    if (preg_match('/^--categoryid=(\d+)$/', $argv[$i], $m)) $categoryid = (int)$m[1];
}
if (!$categoryid) {
    echo "Usage: php cleanup-duplicates.php --categoryid=<n>\n";
    echo "Use DEV_CATEGORYID from project/project.conf.\n";
    exit(1);
}

$sql = 'SELECT q.id, q.name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qbe.questioncategoryid = :catid
        ORDER BY q.name ASC, q.id ASC';

$questions = $DB->get_records_sql($sql, ['catid' => $categoryid]);

// Group by name, collect all IDs
$groups = [];
foreach ($questions as $q) {
    $groups[$q->name][] = $q->id;
}

$deleted = 0;
foreach ($groups as $name => $ids) {
    if (count($ids) <= 1) {
        echo "  OK (1 copy): $name\n";
        continue;
    }
    $keep = max($ids);
    $remove = array_filter($ids, fn($id) => $id !== $keep);
    echo "  Keeping id=$keep, deleting ids=" . implode(',', $remove) . " : $name\n";
    foreach ($remove as $id) {
        question_delete_question($id);
        $deleted++;
    }
}

echo "\nDone. Deleted $deleted duplicate question(s).\n";
