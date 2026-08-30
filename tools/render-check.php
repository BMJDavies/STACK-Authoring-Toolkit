<?php
/**
 * render-check.php: server-side render audit for STACK + description questions.
 *
 * Loads each question, renders its formulation HTML via Moodle's question
 * renderer (the same path used in a quiz attempt), then greps for known
 * render-time bug signatures that source-XML inspection can't see.
 *
 * Designed to be run INSIDE the Moodle Docker container. The wrapper script
 * `tools/render-check.sh` copies the target XML into the container and invokes
 * this with the in-container path.
 *
 * Usage (inside container):
 *   php render-check.php /tmp/target.xml          # check questions named in this XML
 *   php render-check.php --all                    # check every question in every approved file
 *
 * Bad signatures detected (in rendered HTML):
 *   - raw_jsxgraph_placeholder   : literal `[[jsxgraph]]` / `[[/jsxgraph]]` text
 *   - raw_input_placeholder      : literal `[[input:...]]` text
 *   - raw_validation_placeholder : literal `[[validation:...]]` text
 *   - raw_feedback_placeholder   : literal `[[feedback:...]]` text
 *   - validation_div_inside_p    : `<p>` wraps both `<input>` and `<div class="stackinputfeedback...">`
 *   - pmatrix_with_text_input    : `\begin{pmatrix} \text{...<input...} ... \end{pmatrix}` (STACK substitution
 *                                  eats the `\\` row break and the second `\text{...}` when this pattern is in
 *                                  source XML, leaving an unrenderable matrix. Source-fixable; detected here as
 *                                  a safety net.
 *
 * Output: text report listing every flagged question, then a tally.
 * Exit 0 = clean. Exit 1 = at least one flag.
 */

define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/question/engine/bank.php');
require_once('/var/www/html/question/engine/lib.php');

global $DB, $PAGE;

// ---------------------------------------------------------------------------
// Args
// ---------------------------------------------------------------------------
$args = array_slice($argv, 1);
if (empty($args)) {
    fwrite(STDERR, "Usage: php render-check.php <xml-path> | --all\n");
    exit(2);
}

$xml_paths = [];
if ($args[0] === '--all') {
    // --all expects every chapter file to have been copied to /tmp/rc-all/
    $xml_paths = glob('/tmp/rc-all/*.xml');
    if (!$xml_paths) {
        fwrite(STDERR, "No files found at /tmp/rc-all/*.xml. Did the wrapper copy them?\n");
        exit(2);
    }
} else {
    foreach ($args as $a) {
        if (!is_file($a)) {
            fwrite(STDERR, "Not a file: $a\n");
            exit(2);
        }
        $xml_paths[] = $a;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function get_question_names_from_xml($path) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        fwrite(STDERR, "Failed to parse XML: $path\n");
        return [];
    }
    $names = [];
    foreach ($xml->question as $q) {
        $type = (string)$q['type'];
        if ($type === 'category') continue;
        $names[] = (string)$q->name->text;
    }
    return $names;
}

function lookup_question_ids(array $names) {
    global $DB;
    if (empty($names)) return [];
    list($insql, $params) = $DB->get_in_or_equal($names);
    $rows = $DB->get_records_sql(
        "SELECT id, name, qtype FROM {question} WHERE name $insql ORDER BY id DESC",
        $params
    );
    $by_name = [];
    foreach ($rows as $r) {
        // Keep highest id per name (most recently imported)
        if (!isset($by_name[$r->name])) {
            $by_name[$r->name] = ['id' => $r->id, 'qtype' => $r->qtype];
        }
    }
    $out = [];
    foreach ($names as $n) {
        if (isset($by_name[$n])) {
            $out[] = ['id' => $by_name[$n]['id'], 'name' => $n, 'qtype' => $by_name[$n]['qtype']];
        }
    }
    return $out;
}

function render_formulation($qid) {
    global $PAGE;
    try {
        $q = question_bank::load_question($qid);
        $quba = question_engine::make_questions_usage_by_activity('core_question_preview', context_system::instance());
        $quba->set_preferred_behaviour('immediatefeedback');
        $slot = $quba->add_question($q);
        $quba->start_all_questions();
        $qa = $quba->get_question_attempt($slot);

        $opts = new question_display_options();
        $opts->context = context_system::instance();

        $renderer = $q->get_renderer($PAGE);
        return $renderer->formulation_and_controls($qa, $opts);
    } catch (Throwable $e) {
        return "<<RENDER ERROR: " . $e->getMessage() . ">>";
    }
}

function check_html($html) {
    $issues = [];

    if (strpos($html, '<<RENDER ERROR') !== false) {
        $issues[] = 'render_error';
        return $issues;
    }

    if (strpos($html, '[[jsxgraph]]') !== false || strpos($html, '[[/jsxgraph]]') !== false) {
        $issues[] = 'raw_jsxgraph_placeholder';
    }
    if (strpos($html, '[[input:') !== false) {
        $issues[] = 'raw_input_placeholder';
    }
    if (strpos($html, '[[validation:') !== false) {
        $issues[] = 'raw_validation_placeholder';
    }
    if (strpos($html, '[[feedback:') !== false) {
        $issues[] = 'raw_feedback_placeholder';
    }

    // <p> containing both <input> and <div class="stackinputfeedback ...">
    // (the source-level <p>-with-input bug that fragments inline layout)
    if (preg_match('/<p\b[^>]*>(?:(?!<\/p>).)*?<input\b[^>]*>(?:(?!<\/p>).)*?<div\b[^>]*class="[^"]*stackinputfeedback/is', $html)) {
        $issues[] = 'validation_div_inside_p';
    }

    // pmatrix containing \text{...<input...}. STACK silently eats the \\ row break
    // and the second \text{...} block during substitution, leaving an unrenderable
    // matrix. This is the only LaTeX-with-HTML pattern that's actually broken;
    // bare <input> inside \(...\) for inline algebraic inputs renders fine.
    if (preg_match('/\\\\begin\{[a-z]*matrix\}[\s\S]*?\\\\text\{[\s\S]*?<input\b/i', $html)) {
        $issues[] = 'pmatrix_with_text_input';
    }

    return $issues;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$total_checked = 0;
$total_flagged = 0;
$any_flags = false;

foreach ($xml_paths as $path) {
    $names = get_question_names_from_xml($path);
    if (empty($names)) continue;
    $questions = lookup_question_ids($names);
    if (empty($questions)) {
        echo "FILE: $path (no matching questions in DB)\n\n";
        continue;
    }

    $file_flags = [];
    foreach ($questions as $q) {
        $total_checked++;
        $html = render_formulation($q['id']);
        $issues = check_html($html);
        if (!empty($issues)) {
            $total_flagged++;
            $any_flags = true;
            $file_flags[] = ['id' => $q['id'], 'name' => $q['name'], 'qtype' => $q['qtype'], 'issues' => $issues];
        }
    }

    if (!empty($file_flags)) {
        echo "FILE: $path\n";
        foreach ($file_flags as $f) {
            echo "  Q{$f['id']} ({$f['qtype']}): {$f['name']}\n";
            echo "    issues: " . implode(', ', $f['issues']) . "\n";
        }
        echo "\n";
    }
}

echo "=== render-check summary ===\n";
echo "Files inspected: " . count($xml_paths) . "\n";
echo "Questions checked: $total_checked\n";
echo "Questions flagged: $total_flagged\n";

exit($any_flags ? 1 : 0);
