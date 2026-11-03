<?php
/**
 * STACK Question Validator
 *
 * Imports a Moodle XML question file into the Claude sandbox, then:
 *   1. Validates XML structure and imports to DB
 *   2. Runs question variables through Maxima (CAS errors)
 *   3. Tests against the question's own <deployedseed> values (the variants
 *      students will see); a question with none gets the 5 default seeds
 *   4. Runs all embedded question tests against all seeds (PRT logic)
 *
 * Before the import, a lint stage reads the XML itself (no CAS needed):
 *   - FAIL a question or feedback variable whose name has an "s" entry in
 *     STACK's security-map.json (style-guide.md, "Variable names STACK
 *     already uses");
 *   - FAIL a call to a function defined in one of STACK's contrib libraries
 *     (stack/maxima/contrib/*.mac) in a question that does not include that
 *     library. A missing include leaves the call unevaluated, so a test that
 *     uses it comes back blank with no message.
 * Both checks read the installed STACK, so they follow its version.
 *
 * --lint-only runs the lint stage and stops: no import, no CAS, seconds per
 * file. Use it to sweep many files.
 *
 * Usage (from your Mac terminal):
 *   docker cp <xmlfile> moodle-docker-webserver-1:/tmp/validate.xml && \
 *   docker exec moodle-docker-webserver-1 php /opt/validate-questions.php /tmp/validate.xml
 *
 * Or use the wrapper: ./tools/test-question.sh path/to/questions.xml
 *
 * Exit code 0 = all checks passed, 1 = errors found.
 */

define('CLI_SCRIPT', true);
// MOODLE_ROOT: the folder that holds Moodle's config.php inside the container.
// The wrappers pass it from project.conf; most Docker images use /var/www/html.
require((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');

require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/type/stack/stack/cas/keyval.class.php');
require_once($CFG->dirroot . '/question/type/stack/stack/cas/cassession2.class.php');
require_once($CFG->dirroot . '/question/type/stack/stack/options.class.php');
require_once($CFG->dirroot . '/question/type/stack/stack/questiontest.php');
require_once($CFG->dirroot . '/question/type/stack/stack/questiontestresult.php');
require_once($CFG->dirroot . '/question/type/stack/questiontype.php');

// --- Config ---
// Import target: the sandbox question category and its context. Supplied by
// tools/test-question.sh from DEV_CATEGORYID / DEV_CONTEXTID in project.conf.
// Imports accumulate here, so point it at a throwaway course, never a live one.
$categoryid = (int)getenv('VALIDATE_CATEGORYID');
$contextid  = (int)getenv('VALIDATE_CONTEXTID');
// Used only for a question whose XML has no <deployedseed>. Otherwise the
// question's own seeds are tested, since those are the variants that go live.
$default_seeds = [1, 42, 137, 256, 999];

// --- Setup ---
$args = array_slice($argv, 1);
$lintonly = in_array('--lint-only', $args, true);
$args = array_values(array_diff($args, ['--lint-only']));
$xmlfile = $args[0] ?? null;
if (!$xmlfile || !file_exists($xmlfile)) {
    echo "Usage: php validate-questions.php <path-to-xml-file> [--lint-only]\n";
    exit(1);
}

if (!$lintonly && (!$categoryid || !$contextid)) {
    echo "VALIDATE_CATEGORYID and VALIDATE_CONTEXTID must be set.\n";
    echo "Fill in DEV_CATEGORYID / DEV_CONTEXTID in project/project.conf and use tools/test-question.sh.\n";
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);
global $DB, $CFG;

// ============================================================
// STAGE 0: lint the XML against the installed STACK
// ============================================================

/** Maxima source with comments removed and string literals emptied. */
function kit_strip_maxima(string $src): string {
    $src = preg_replace('~/\*.*?\*/~s', ' ', $src);
    return preg_replace('~"(?:[^"\\\\]|\\\\.)*"~s', '""', $src);
}

/** Function names defined with name(args) := in Maxima source. */
function kit_defined_functions(string $src): array {
    preg_match_all('~(?:^|[;$\s(,])([A-Za-z_][A-Za-z0-9_]*)\s*\([^()]*\)\s*:=~m',
        kit_strip_maxima($src), $m);
    return array_unique($m[1]);
}

/** Names of functions called in Maxima source. */
function kit_called_functions(string $src): array {
    preg_match_all('~(?<![A-Za-z0-9_%?])([A-Za-z_][A-Za-z0-9_]*)\s*\(~', kit_strip_maxima($src), $m);
    return array_unique($m[1]);
}

/** Names a keyval block assigns (a: ..., f(x) := ...). */
function kit_written_names(string $src): array {
    $names = [];
    if (trim($src) !== '') {
        $kv = new stack_cas_keyval($src, new stack_options(), 1);
        if ($kv->get_valid()) {
            $names = array_keys($kv->get_variable_usage()['write']);
        }
    }
    return array_unique(array_merge($names, kit_defined_functions($src)));
}

/** Contrib libraries a question includes, by file name. */
function kit_included_libraries(string $qvars): array {
    preg_match_all('~stack_include\(\s*"contribl?://([^"]+)"\s*\)|stack_include_contrib\(\s*"([^"]+)"\s*\)~',
        $qvars, $m, PREG_SET_ORDER);
    $libs = [];
    foreach ($m as $hit) {
        $libs[] = basename(($hit[2] ?? '') !== '' ? $hit[2] : $hit[1]);
    }
    return $libs;
}

/** Stage 0. Returns the number of problems found. */
function kit_lint(string $xmlfile): int {
    global $CFG;
    $stackdir = $CFG->dirroot . '/question/type/stack/stack';
    $secmap = json_decode(file_get_contents("$stackdir/cas/security-map.json"), true) ?: [];

    // Functions STACK always has: those in its core Maxima files, and every name
    // the security map knows. A contrib function outside these needs its library.
    $core = [];
    foreach (glob("$stackdir/maxima/*.mac") as $f) {
        if (!preg_match('~(_test\.mac|^rtest_.*)$~', basename($f))) {
            $core = array_merge($core, kit_defined_functions(file_get_contents($f)));
        }
    }
    $core = array_flip($core);
    $contrib = [];  // function name => [library file, ...]
    foreach (glob("$stackdir/maxima/contrib/*.mac") as $f) {
        if (preg_match('~_test\.mac$~', $f)) {
            continue;
        }
        foreach (kit_defined_functions(file_get_contents($f)) as $fn) {
            if (!isset($core[$fn]) && !isset($secmap[$fn])) {
                $contrib[$fn][] = basename($f);
            }
        }
    }

    $xml = simplexml_load_file($xmlfile);
    if ($xml === false) {
        echo "  [FAIL] not well-formed XML\n";
        return 1;
    }
    $fails = 0;
    foreach ($xml->question as $q) {
        if ((string)$q['type'] !== 'stack') {
            continue;
        }
        $name = (string)$q->name->text;
        $qvars = (string)$q->questionvariables->text;
        $blocks = ['question variables' => $qvars];
        foreach ($q->prt as $prt) {
            $blocks['feedback variables of ' . (string)$prt->name] = (string)$prt->feedbackvariables->text;
        }
        $msgs = [];

        // A name with an "s" entry is accepted for students before STACK looks
        // at the question's own variables, so the security map's meaning wins.
        $own = [];
        foreach ($blocks as $where => $src) {
            foreach (kit_written_names($src) as $v) {
                $own[$v] = true;
                $kinds = [];
                foreach ((array)($secmap[$v] ?? []) as $kind => $level) {
                    if ($level === 's') {
                        $kinds[] = $kind;
                    }
                }
                if ($kinds) {
                    $msgs[] = "$where assign `$v`, which STACK's security map already allows students as a "
                        . implode('/', $kinds) . "; rename it";
                }
            }
        }

        // A contrib function called with its library not included stays
        // unevaluated, so a test that uses it comes back blank.
        $tests = '';
        foreach ($q->qtest as $t) {
            foreach ($t->testinput as $ti) {
                $tests .= (string)$ti->value . ";\n";
            }
        }
        $libs = kit_included_libraries($qvars);
        foreach (kit_called_functions(implode(";\n", $blocks) . ";\n" . $tests) as $fn) {
            if (isset($own[$fn]) || !isset($contrib[$fn]) || array_intersect($contrib[$fn], $libs)) {
                continue;
            }
            $msgs[] = "calls `$fn` from " . implode(' or ', $contrib[$fn])
                . ', which the question does not include: add stack_include("contribl://'
                . $contrib[$fn][0] . '")';
        }

        foreach (array_unique($msgs) as $msg) {
            echo "  [FAIL] $name: $msg\n";
            $fails++;
        }
    }
    echo "  Lint: " . ($fails ? "$fails problem(s)" : "OK") . "\n";
    return $fails;
}

echo "=== STAGE 0: Lint against the installed STACK ===\n";
$lintfails = kit_lint($xmlfile);
echo "\n";
if ($lintonly) {
    echo $lintfails ? "=== RESULT: FAILED ===\n" : "=== RESULT: SUCCESS ===\n";
    exit($lintfails ? 1 : 0);
}

$context = context::instance_by_id($contextid);
$any_failure = $lintfails > 0;

// ============================================================
// STAGE 1: XML import
// ============================================================
echo "=== STAGE 1: XML Import ===\n";

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
echo $clean . "\n\n";

if (!$import_ok) {
    echo "=== RESULT: FAILED (import error) ===\n";
    exit(1);
}

$question_ids = $format->questionids;

// ============================================================
// STAGE 2: Maxima / CAS validation
// ============================================================
echo "=== STAGE 2: Maxima CAS Validation ===\n";

$cas_options = new stack_options();

foreach ($question_ids as $qid) {
    $stack_opts = $DB->get_record('qtype_stack_options', ['questionid' => $qid]);
    if (!$stack_opts) {
        echo "  [id=$qid] No STACK options found (skipping)\n";
        continue;
    }

    $qname = $DB->get_field('question', 'name', ['id' => $qid]);
    echo "  Checking: $qname (id=$qid)\n";

    $qvars = $stack_opts->questionvariables ?? '';
    if (empty(trim($qvars))) {
        echo "    (no question variables, skipping)\n";
        continue;
    }

    $keyval = new stack_cas_keyval($qvars, $cas_options, 1);
    if (!$keyval->get_valid()) {
        echo "    [FAIL] Question variables invalid (parse error)\n";
        $any_failure = true;
        continue;
    }

    $keyval->instantiate();
    $session = $keyval->get_session();
    $session_errors = $session->get_errors(true);

    if ($session_errors) {
        echo "    [FAIL] Maxima errors: $session_errors\n";
        $any_failure = true;
    } else {
        echo "    [OK] Question variables valid\n";
    }
}

echo "\n";

// ============================================================
// STAGE 3: Deploy seeds + run question tests
// ============================================================
echo "=== STAGE 3: Deploy Seeds & Run Question Tests ===\n";

$qtype = question_bank::get_qtype('stack');

foreach ($question_ids as $qid) {
    $qname = $DB->get_field('question', 'name', ['id' => $qid]);
    echo "Question: $qname (id=$qid)\n";

    // Test the seeds the XML deploys (the import keeps them); fall back to
    // the defaults only when it deploys none.
    $deploy_seeds = $DB->get_fieldset_select('qtype_stack_deployed_seeds', 'seed',
            'questionid = ?', [$qid], 'id');
    if ($deploy_seeds) {
        echo "  Deployed seeds (from XML): " . implode(', ', $deploy_seeds) . "\n";
    } else {
        $deploy_seeds = $default_seeds;
        foreach ($deploy_seeds as $seed) {
            $DB->insert_record('qtype_stack_deployed_seeds', ['questionid' => $qid, 'seed' => $seed]);
        }
        echo "  Deployed seeds (none in XML, using defaults): " . implode(', ', $deploy_seeds) . "\n";
    }

    // Load test cases
    $testcases = $qtype->load_question_tests($qid);
    if (empty($testcases)) {
        echo "  [WARN] No question tests found, skipping PRT validation\n";
        continue;
    }
    echo "  Running " . count($testcases) . " test case(s) across " . count($deploy_seeds) . " seeds:\n";

    foreach ($deploy_seeds as $seed) {
        foreach ($testcases as $num => $tc) {
            $result = $tc->test_question($qid, $seed, $context);
            $passed = $result->passed();
            $any_failure = $any_failure || !$passed;
            $status = $passed ? '[PASS]' : '[FAIL]';
            echo "  Seed $seed / Test $num ({$tc->description}): $status\n";
            if (!$passed) {
                foreach ($result->get_prt_states() as $prtname => $state) {
                    if (!$state->testoutcome) {
                        echo "    $prtname: score={$state->score} (exp {$state->expectedscore}), note={$state->answernote} (exp {$state->expectedanswernote})\n";
                    }
                }
                // passed() also fails on these two, which the loop above never shows.
                if (!empty($result->emptytestcase)) {
                    echo "    empty test case (no inputs and no expectations)\n";
                }
                // Why an input was not accepted. STACK evaluates a test input
                // with simp:false and submits the result as the student's
                // answer, so an expression that does not reduce to a value
                // (an unsimplified matrix sum, a call left unevaluated) is
                // refused. Without this the PRT line above shows only an
                // empty score.
                foreach ($result->get_input_states() as $iname => $istate) {
                    if ($istate->status !== stack_input::SCORE && trim((string)$istate->rawinput) !== '') {
                        $why = trim(preg_replace('/\s+/', ' ', strip_tags((string)$istate->errors)));
                        $sent = trim((string)$istate->input);
                        echo "    input $iname not accepted (status " . ($istate->status === '' ? 'blank' : $istate->status)
                            . "): test value " . trim((string)$istate->rawinput) . "\n"
                            . "      submitted as: " . ($sent !== '' ? $sent : '(nothing: it did not evaluate)') . "\n"
                            . ($why !== '' ? "      STACK says: $why\n" : '');
                    }
                }
                // STACK keys runtime errors by message (message => true).
                foreach ((array) $result->runtimeerrors as $key => $err) {
                    $msg = is_string($err) ? $err : $key;
                    echo "    runtime error: " . trim(strip_tags($msg)) . "\n";
                }
            }
        }
    }
}

// ============================================================
// Final result
// ============================================================
echo "\n";
if ($any_failure) {
    echo "=== RESULT: FAILED ===\n";
    exit(1);
} else {
    echo "=== RESULT: SUCCESS ===\n";
    exit(0);
}
