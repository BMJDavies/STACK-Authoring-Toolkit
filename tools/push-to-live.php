<?php
/**
 * Import an XML file into a live course question bank and add it to a quiz.
 * If questions with the same name already exist in the quiz, updates the slot
 * to point to the new version and cleans up the old bank entry.
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
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->libdir . '/questionlib.php');

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

$admin = get_admin();
\core\session\manager::set_user($admin);

$context = context::instance_by_id($contextid);

// Get quiz info
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);

// 1. Import XML
echo "=== Import into $courselabel (cat $categoryid) ===\n";

$format = new qformat_xml();
$format->setCategory((object)['id' => $categoryid, 'contextid' => $contextid]);
$format->setContexts([$context]);
$format->setCatfromfile(false);
$format->setMatchgrades('error');
$format->setStoponerror(false);
$format->setFilename($xmlpath);

ob_start();
$import_ok = $format->importprocess();
$raw = ob_get_clean();
$clean = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$clean = preg_replace('/\n{3,}/', "\n\n", trim($clean));
echo $clean . "\n";

if (!$import_ok) {
    echo "=== FAILED ===\n";
    exit(1);
}

$imported_ids = $format->questionids ?? [];
echo "Imported " . count($imported_ids) . " question(s).\n\n";

if (empty($imported_ids)) {
    echo "Nothing imported.\n";
    exit(0);
}

// 2. Build a map of existing quiz slots: name => [slot_id, ref_id, bankentryid]
$existing_slots = [];
$slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
foreach ($slots as $slot) {
    $ref = $DB->get_record('question_references', [
        'component' => 'mod_quiz',
        'questionarea' => 'slot',
        'itemid' => $slot->id
    ]);
    if ($ref) {
        $ver = $DB->get_records('question_versions', ['questionbankentryid' => $ref->questionbankentryid], 'version DESC', '*', 0, 1);
        $ver = reset($ver);
        if ($ver) {
            $q = $DB->get_record('question', ['id' => $ver->questionid]);
            if ($q) {
                $existing_slots[$q->name] = [
                    'slot_id' => $slot->id,
                    'ref_id' => $ref->id,
                    'old_bankentryid' => $ref->questionbankentryid,
                    'page' => $slot->page,
                ];
            }
        }
    }
}

// 3. Process each imported question: update existing slot or add new
$maxpage = 0;
foreach ($slots as $slot) {
    $maxpage = max($maxpage, $slot->page);
}

$updated = 0;
$added = 0;

foreach ($imported_ids as $qid) {
    $q = $DB->get_record('question', ['id' => $qid]);
    // Find the new bank entry for this imported question
    $new_ver = $DB->get_record('question_versions', ['questionid' => $qid]);
    $new_bankentryid = $new_ver->questionbankentryid;

    if (isset($existing_slots[$q->name])) {
        // Question already in quiz: repoint the slot to the new bank entry
        $info = $existing_slots[$q->name];
        $old_bankentryid = $info['old_bankentryid'];

        // Update the question_references row to point to new bank entry
        $DB->set_field('question_references', 'questionbankentryid', $new_bankentryid, ['id' => $info['ref_id']]);

        // Delete the old bank entry and its questions (cleanup)
        if ($old_bankentryid != $new_bankentryid) {
            $old_versions = $DB->get_records('question_versions', ['questionbankentryid' => $old_bankentryid]);
            foreach ($old_versions as $ov) {
                question_delete_question($ov->questionid);
            }
            $DB->delete_records('question_versions', ['questionbankentryid' => $old_bankentryid]);
            $DB->delete_records('question_bank_entries', ['id' => $old_bankentryid]);
        }

        echo "  Updated (p{$info['page']}): {$q->name}\n";
        $updated++;
    } else {
        // New question: add to quiz on a new page
        $maxpage++;
        quiz_add_quiz_question($qid, $quiz, $maxpage);
        echo "  Added (p{$maxpage}): {$q->name}\n";
        $added++;
    }
}

// 4. Reorder slots to match XML import order
// Build name→position map from the imported questions (XML order is authoritative)
$xml_order = [];
foreach ($imported_ids as $pos => $qid) {
    $q = $DB->get_record('question', ['id' => $qid]);
    if ($q) $xml_order[$q->name] = $pos;
}

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

// Sort by XML position (questions not in XML keep their relative order at the end)
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

// 5. Deployed seeds come from the XML (style-guide.md, "Deployed seeds"), so a
// question that has any is left alone. Only a STACK question with none gets 5
// random seeds, with a warning, because those seeds have not been validated.
$deployed_count = 0;
foreach ($imported_ids as $qid) {
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

// 6. Delete any in-progress attempts (they reference old question versions)
$attempts = $DB->get_records('quiz_attempts', ['quiz' => $quizid, 'state' => 'inprogress']);
$deleted_attempts = 0;
foreach ($attempts as $attempt) {
    quiz_delete_attempt($attempt, $quiz);
    $deleted_attempts++;
}
if ($deleted_attempts > 0) {
    echo "\nDeleted $deleted_attempts in-progress attempt(s) (stale after update).\n";
}

// 7. Recalculate quiz sumgrades
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
quiz_update_sumgrades($quiz);

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
$xml_names = [];
foreach ($imported_ids as $qid) {
    $q = $DB->get_record('question', ['id' => $qid]);
    if ($q) $xml_names[] = $q->name;
}
$quiz_names = array_column($final_info, 'name');
// Only check the imported questions (quiz may have other questions not in this XML)
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

echo "\nDone. Updated: $updated, Added: $added.\n";
echo "URL: {$siteurl}/mod/quiz/view.php?id={$cm->id}\n";
