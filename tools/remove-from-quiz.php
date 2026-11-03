<?php
/**
 * Remove questions from a quiz by name pattern.
 *
 * Usage:
 *   docker cp tools/remove-from-quiz.php <container>:/tmp/rfq.php
 *   docker exec <container> php /tmp/rfq.php <quizid> <name-pattern>
 *
 * name-pattern uses SQL LIKE syntax (% = wildcard). E.g.:
 *   php /tmp/rfq.php 97 "PROTOTYPE:%"
 *
 * With --dry-run as 3rd arg, lists matches without removing.
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

global $DB;

$quizid  = (int)($argv[1] ?? 0);
$pattern = $argv[2] ?? '';
$dryrun  = ($argv[3] ?? '') === '--dry-run';

if (!$quizid || !$pattern) {
    die("Usage: php remove-from-quiz.php <quizid> <name-pattern> [--dry-run]\n");
}

$admin = get_admin();
\core\session\manager::set_user($admin);

// Find all slots in the quiz and resolve names
$slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
$to_remove = [];

foreach ($slots as $slot) {
    $ref = $DB->get_record('question_references', [
        'component' => 'mod_quiz',
        'questionarea' => 'slot',
        'itemid' => $slot->id
    ]);
    if (!$ref) continue;

    $ver = $DB->get_records('question_versions', [
        'questionbankentryid' => $ref->questionbankentryid
    ], 'version DESC', '*', 0, 1);
    $ver = reset($ver);
    if (!$ver) continue;

    $q = $DB->get_record('question', ['id' => $ver->questionid]);
    if (!$q) continue;

    if ($DB->sql_like_escape($q->name) === $q->name) {
        // No special chars in name: do exact LIKE match
        if (!$DB->sql_like($q->name, $pattern)) {
            // Use PHP fnmatch-style check instead
            if (fnmatch(str_replace('%', '*', $pattern), $q->name)) {
                $to_remove[] = ['slot' => $slot, 'ref' => $ref, 'name' => $q->name];
            }
            continue;
        }
    }
    // Check with simple PHP pattern matching
    if (fnmatch(str_replace('%', '*', $pattern), $q->name)) {
        $to_remove[] = ['slot' => $slot, 'ref' => $ref, 'name' => $q->name];
    }
}

if (empty($to_remove)) {
    echo "No questions matching '$pattern' found in quiz $quizid.\n";

    // List all questions for reference
    echo "\nAll questions in quiz $quizid:\n";
    foreach ($slots as $slot) {
        $ref = $DB->get_record('question_references', [
            'component' => 'mod_quiz', 'questionarea' => 'slot', 'itemid' => $slot->id
        ]);
        if (!$ref) continue;
        $ver = $DB->get_records('question_versions', [
            'questionbankentryid' => $ref->questionbankentryid
        ], 'version DESC', '*', 0, 1);
        $ver = reset($ver);
        if (!$ver) continue;
        $q = $DB->get_record('question', ['id' => $ver->questionid]);
        if ($q) echo "  Slot {$slot->slot}, Page {$slot->page}: {$q->name}\n";
    }
    exit(0);
}

echo ($dryrun ? "DRY RUN: " : "") . "Found " . count($to_remove) . " question(s) to remove:\n";
foreach ($to_remove as $item) {
    echo "  Slot {$item['slot']->slot}, Page {$item['slot']->page}: {$item['name']}\n";
}

if ($dryrun) {
    exit(0);
}

// Remove slots
foreach ($to_remove as $item) {
    $DB->delete_records('quiz_slots', ['id' => $item['slot']->id]);
    $DB->delete_records('question_references', ['id' => $item['ref']->id]);
    echo "  Removed: {$item['name']}\n";
}

// Renumber remaining slots sequentially
$remaining = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
$i = 1;
foreach ($remaining as $s) {
    if ((int)$s->slot !== $i) {
        $DB->set_field('quiz_slots', 'slot', $i, ['id' => $s->id]);
    }
    $i++;
}

// Recalculate sumgrades
$quiz = $DB->get_record('quiz', ['id' => $quizid]);
quiz_update_sumgrades($quiz);

echo "\nDone. Remaining slots renumbered.\n";
