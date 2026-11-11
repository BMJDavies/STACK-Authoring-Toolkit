<?php
/**
 * Publish an XML file into a live course question bank and quiz.
 *
 * A question already in the quiz (matched by <name>) gets a new version of its
 * existing bank entry, through the qbank_importasversion plugin that STACK
 * requires. Its quiz slot, history and students' attempts are untouched: an
 * attempt in progress finishes on the version it started with. A question
 * whose content has not changed is left alone, so republishing a section only
 * versions what was edited. A question new to the quiz is imported and added.
 *
 * Usage: php push-to-live.php <xml-path> <quiz-id> \
 *            --categoryid=<n> --contextid=<n> [--label="Course name"] [--url=http://...]
 *
 * You will normally not call this directly. Use the host-side wrapper
 * tools/push-live.sh, which reads project/project.conf, resolves --course=<key>
 * into the right category/context ids, and copies everything into the
 * container under a unique filename.
 */
define('CLI_SCRIPT', true);
require((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->libdir . '/questionlib.php');
// tools/lib/publish.php, copied beside this script by push-live.sh.
require(preg_replace('/\.php$/', '_lib.php', __FILE__));

// --- Target question bank (supplied by the wrapper from project/project.conf) ---
$categoryid  = 0;
$contextid   = 0;
$courselabel = 'target course';
$siteurl     = 'http://localhost:8000';

$positional = [];
for ($i = 1; $i < $argc; $i++) {
    if (preg_match('/^--categoryid=(\d+)$/', $argv[$i], $m))   { $categoryid  = (int)$m[1]; }
    else if (preg_match('/^--contextid=(\d+)$/', $argv[$i], $m)) { $contextid  = (int)$m[1]; }
    else if (preg_match('/^--label=(.*)$/', $argv[$i], $m))      { $courselabel = $m[1]; }
    else if (preg_match('/^--url=(.*)$/', $argv[$i], $m))        { $siteurl     = rtrim($m[1], '/'); }
    else { $positional[] = $argv[$i]; }
}

if (count($positional) < 2 || !$categoryid || !$contextid) {
    echo "Usage: php push-to-live.php <xml-path> <quiz-id> --categoryid=<n> --contextid=<n> [--label=...] [--url=...]\n";
    echo "Normally invoked via tools/push-live.sh, which fills these in from project/project.conf.\n";
    exit(1);
}

$xmlpath = $positional[0];
$quizid = (int)$positional[1];

if (!file_exists($xmlpath)) {
    die("File not found: $xmlpath\n");
}

kit_require_importasversion();

$admin = get_admin();
\core\session\manager::set_user($admin);

// Get quiz info
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);

// 1. Split the XML into one file per question.
$workdir = make_request_directory();
$incoming = kit_split_questions($xmlpath, $workdir);
if ($incoming === null) {
    echo "=== FAILED: $xmlpath is not well-formed XML ===\n";
    exit(1);
}
if (empty($incoming)) {
    echo "No questions in $xmlpath.\n";
    exit(0);
}

// 2. The latest version of each question already in the quiz, by name.
$slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
$existing = kit_quiz_questions($quizid);

// Refuse, before changing anything, if the quiz holds questions this file does
// not. The file sets the slot order, so they would be moved to the end: pushing
// Section B alone into a quiz that also holds Section A would put B first. A
// quiz is one section (style-guide.md), and a renamed question leaves its old
// name behind, so either way the fix is the user's, not this script's.
$others = array_diff(array_keys($existing), array_column($incoming, 'name'));
if ($others) {
    echo "=== REFUSED: quiz $quizid holds " . count($others) . " question(s) that are not in $xmlpath ===\n";
    foreach ($others as $name) {
        echo "  $name\n";
    }
    echo "Nothing was changed. Publishing would move them to the end of the quiz.\n";
    echo "- Another section's questions: publish each section into its own quiz.\n";
    echo "- A question renamed in the file: remove the old name from the quiz first\n";
    echo "  (tools/remove-from-quiz.php), then publish again.\n";
    echo "- A one-question file: publish the whole section file instead. Unchanged\n";
    echo "  questions are left alone.\n";
    exit(1);
}

// 3. Each question: a new version if it is in the quiz and has changed, left
//    alone if unchanged, added to the quiz if new.
echo "=== Publish into $courselabel (cat $categoryid) ===\n";
$maxpage = 0;
foreach ($slots as $slot) {
    $maxpage = max($maxpage, $slot->page);
}

$versioned = 0;
$unchanged = 0;
$added = 0;
$replaced = 0;
$failed = 0;
$changed_ids = []; // question ids created by this run

foreach ($incoming as $item) {
    $name = $item['name'];
    $info = $existing[$name] ?? null;

    if ($info && $info->qtype === $item['qtype']) {
        $changed = kit_changed_fields($info->questionid, $item['file'], $workdir);
        if (!$changed) {
            echo "  Unchanged (v{$info->version}): $name\n";
            $unchanged++;
            continue;
        }
        [$newid, $error] = kit_add_version($info->questionid, $item['file']);
        if (!$newid) {
            echo "  FAILED to add a version: $name: $error\n";
            $failed++;
            continue;
        }
        $changed_ids[] = $newid;
        $v = $info->version + 1;
        echo "  New version (v{$v}, changed: " . implode(', ', $changed) . "): $name\n";
        $versioned++;
    } else if ($info) {
        // Moodle cannot version a question into a different type. Import a new
        // bank entry and point the slot at it; the old entry stays in the bank.
        $newid = kit_import_new($item['file'], $categoryid, $contextid);
        if (!$newid) {
            echo "  FAILED to import: $name\n";
            $failed++;
            continue;
        }
        $new_ver = $DB->get_record('question_versions', ['questionid' => $newid]);
        $DB->set_field('question_references', 'questionbankentryid', $new_ver->questionbankentryid, ['id' => $info->ref_id]);
        $changed_ids[] = $newid;
        echo "  [WARN] Replaced (type {$info->qtype} -> {$item['qtype']}, so not a new version): $name\n";
        echo "         The old {$info->qtype} question is still in the bank; delete it there if it is not needed.\n";
        $replaced++;
    } else {
        $newid = kit_import_new($item['file'], $categoryid, $contextid);
        if (!$newid) {
            echo "  FAILED to import: $name\n";
            $failed++;
            continue;
        }
        $maxpage++;
        quiz_add_quiz_question($newid, $quiz, $maxpage);
        $changed_ids[] = $newid;
        echo "  Added (p{$maxpage}): $name\n";
        $added++;
    }
}

if ($failed > 0) {
    echo "\n=== $failed question(s) FAILED; the rest were published ===\n";
}

// 4. Reorder slots to match XML order (XML order is authoritative)
$xml_names = array_column($incoming, 'name');
$xml_order = array_flip($xml_names);

// Re-fetch slots and resolve each to its question name
$slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
$slot_info = [];
foreach ($slots as $s) {
    $ref = $DB->get_record('question_references', [
        'component' => 'mod_quiz', 'questionarea' => 'slot', 'itemid' => $s->id
    ]);
    if (!$ref) continue;
    $ver = $DB->get_records('question_versions', ['questionbankentryid' => $ref->questionbankentryid], 'version DESC', '*', 0, 1);
    $v = reset($ver);
    if (!$v) continue;
    $q = $DB->get_record('question', ['id' => $v->questionid]);
    if (!$q) continue;
    $slot_info[] = ['slot_rec' => $s, 'name' => $q->name, 'qtype' => $q->qtype,
                    'xml_pos' => isset($xml_order[$q->name]) ? $xml_order[$q->name] : -1];
}

// Sort by XML position. Every named question is in the XML (checked above), so
// only a slot whose question could not be resolved lands at the end.
usort($slot_info, function($a, $b) {
    if ($a['xml_pos'] === -1 && $b['xml_pos'] === -1) return 0;
    if ($a['xml_pos'] === -1) return 1;
    if ($b['xml_pos'] === -1) return -1;
    return $a['xml_pos'] - $b['xml_pos'];
});

// Renumber slots sequentially (two-pass to avoid unique constraint conflicts)
$reordered = 0;
$offset = 10000;
foreach ($slot_info as $i => $info) {
    $DB->set_field('quiz_slots', 'slot', $offset + $i + 1, ['id' => $info['slot_rec']->id]);
}
foreach ($slot_info as $i => $info) {
    $new_slot = $i + 1;
    if ($info['slot_rec']->slot != $new_slot) {
        $reordered++;
    }
    $DB->set_field('quiz_slots', 'slot', $new_slot, ['id' => $info['slot_rec']->id]);
}
if ($reordered > 0) {
    echo "Reordered $reordered slot(s) to match XML sequence.\n";
}

// 5. Regroup pages: keep almost every quiz on a single page. The break-on-overflow
//    branch only fires for unusually long quizzes (>30 items); descriptions never
//    force a new page below that threshold. We arrived at 30 after starting at 7:
//    a low threshold produces lots of "lonely pages" (one item stranded on its own
//    page), which reads as an afterthought to students. Lower it if you prefer
//    shorter pages.
$page = 0;
$items_on_page = 0;
$max_per_page = 30;
$near_max = $max_per_page - 1;

// Helper: extract the question group prefix so multi-part questions (a, b, c)
// are never split across pages. Assumes names of the form
// "<anything><number><part-letter>_<title>", e.g. "Ch1_Q06a_Factorising"
// groups with "Ch1_Q06b_Solving". Adjust the regex if your naming differs.
function question_group($name) {
    if (preg_match('/^(.*\d+)[a-z]_/', $name, $m)) {
        return $m[1];
    }
    return $name; // single-part questions are their own group
}

// Helper: explicit page code in the name, e.g. "3A_P04b_Products" -> "04".
// When EVERY slot carries one, pages follow the codes (renumbered 1, 2, 3...
// in slot order) and the count-based logic below is skipped entirely.
function page_code($name) {
    if (preg_match('/_P(\d+)[a-z]?_/', $name, $m)) {
        return $m[1];
    }
    return null;
}

$count = count($slot_info);
$codes = array_map(function($s) { return page_code($s['name']); }, $slot_info);
$use_page_codes = $count > 0 && !in_array(null, $codes, true);

for ($i = 0; $use_page_codes && $i < $count; $i++) {
    if ($i === 0 || $codes[$i] !== $codes[$i-1]) {
        $page++;
    }
    if ($slot_info[$i]['slot_rec']->page != $page) {
        $DB->set_field('quiz_slots', 'page', $page, ['id' => $slot_info[$i]['slot_rec']->id]);
    }
}

for ($i = 0; !$use_page_codes && $i < $count; $i++) {
    $info = $slot_info[$i];
    $is_desc = ($info['qtype'] === 'description');
    $prev_was_desc = ($i > 0) ? ($slot_info[$i-1]['qtype'] === 'description') : false;

    if ($page === 0) {
        // First slot of the quiz: starts page 1, no break logic needed
        $page = 1;
        $items_on_page = 0;
    } elseif ($is_desc) {
        // Description: only force a new page when transitioning from a
        // question-filled page that's already near max. Otherwise stay put
        // so the desc heads the upcoming question chunk on one page.
        if (!$prev_was_desc && $items_on_page >= $near_max) {
            $page++;
            $items_on_page = 0;
        }
    } elseif ($items_on_page >= $max_per_page) {
        // Question: break at question-group boundaries when at/over max
        $prev_group = question_group($slot_info[$i-1]['name']);
        $this_group = question_group($info['name']);
        if ($this_group !== $prev_group) {
            $page++;
            $items_on_page = 0;
        }
        // Otherwise keep going to avoid splitting a group (e.g. D06a and D06b)
    }

    $items_on_page++;

    if ($info['slot_rec']->page != $page) {
        $DB->set_field('quiz_slots', 'page', $page, ['id' => $info['slot_rec']->id]);
    }
}
echo "\nRegrouped quiz into $page page(s).\n";

// 6. Deployed seeds come from the XML (style-guide.md, "Deployed seeds"), so a
// question that has any is left alone. Only a STACK question with none gets 5
// random seeds, with a warning, because those seeds have not been validated.
$deployed_count = 0;
foreach ($changed_ids as $qid) {
    $q = question_bank::load_question($qid);
    if (!($q instanceof qtype_stack_question)) continue;
    if (count($q->deployedseeds) > 0) continue;

    echo "  [WARN] {$q->name} has no <deployedseed> in the XML; deploying 5 unvalidated random seeds.\n";
    $needed = 5;
    $attempts = 0;
    while ($needed > 0 && $attempts < 20) {
        $seed = mt_rand(1, 999999);
        $attempts++;
        try {
            $q->deploy_variant($seed);
            $needed--;
            $deployed_count++;
        } catch (Exception $e) {
            continue;
        }
    }
}
if ($deployed_count > 0) {
    echo "Deployed variants for STACK questions ($deployed_count seeds).\n";
}

// 7. Recalculate quiz sumgrades
\mod_quiz\grade_calculator::create(\mod_quiz\quiz_settings::create($quizid))
    ->recompute_quiz_sumgrades();

// 8. Verify: slot order matches XML, no lonely pages, descriptions grouped with questions
echo "\n=== Verification ===\n";
$verify_ok = true;

// Re-fetch final slot state
$final_slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
$final_info = [];
foreach ($final_slots as $s) {
    $ref = $DB->get_record('question_references', [
        'component' => 'mod_quiz', 'questionarea' => 'slot', 'itemid' => $s->id
    ]);
    if (!$ref) continue;
    $ver = $DB->get_records('question_versions', ['questionbankentryid' => $ref->questionbankentryid], 'version DESC', '*', 0, 1);
    $v = reset($ver);
    if (!$v) continue;
    $q = $DB->get_record('question', ['id' => $v->questionid]);
    if (!$q) continue;
    $final_info[] = ['slot' => $s->slot, 'page' => $s->page, 'name' => $q->name, 'qtype' => $q->qtype];
}

// Check 1: slot order matches XML order
$quiz_names = array_column($final_info, 'name');
$quiz_imported = array_values(array_intersect($quiz_names, $xml_names));
if ($quiz_imported !== $xml_names) {
    echo "WARNING: Slot order does not match XML order.\n";
    echo "  XML:  " . implode(', ', array_map(function($n) { return substr($n, -15); }, $xml_names)) . "\n";
    echo "  Quiz: " . implode(', ', array_map(function($n) { return substr($n, -15); }, $quiz_imported)) . "\n";
    $verify_ok = false;
} else {
    echo "Slot order: OK (" . count($xml_names) . " questions match XML sequence)\n";
}

// Check 2: no lonely pages (single item, unless quiz has only 1 question)
$pages = [];
foreach ($final_info as $fi) {
    $pages[$fi['page']][] = $fi;
}
if (count($final_info) > 1) {
    foreach ($pages as $pnum => $items) {
        if (count($items) === 1) {
            echo "WARNING: Lonely item on page $pnum: {$items[0]['name']}\n";
            $verify_ok = false;
        }
    }
}

// Check 3: descriptions are not the last item on their page (should have questions after them)
foreach ($pages as $pnum => $items) {
    $last = end($items);
    if ($last['qtype'] === 'description' && count($items) === 1) {
        echo "WARNING: Page $pnum has only a description with no questions: {$last['name']}\n";
        $verify_ok = false;
    }
}

if ($verify_ok) {
    echo "All checks passed.\n";
}

echo "\nDone. New versions: $versioned, Unchanged: $unchanged, Added: $added, Replaced: $replaced, Failed: $failed.\n";
echo "URL: {$siteurl}/mod/quiz/view.php?id={$cm->id}\n";

if ($failed > 0) {
    exit(1);
}
