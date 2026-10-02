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
 * Usage (from your Mac terminal):
 *   docker cp <xmlfile> moodle-docker-webserver-1:/tmp/validate.xml && \
 *   docker exec moodle-docker-webserver-1 php /opt/validate-questions.php /tmp/validate.xml
 *
 * Or use the wrapper: ./tools/test-question.sh path/to/questions.xml
 *
 * Exit code 0 = all checks passed, 1 = errors found.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

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

if (!$categoryid || !$contextid) {
    echo "VALIDATE_CATEGORYID and VALIDATE_CONTEXTID must be set.\n";
    echo "Fill in DEV_CATEGORYID / DEV_CONTEXTID in tools/project.conf and use tools/test-question.sh.\n";
    exit(1);
}

// --- Setup ---
$xmlfile = $argv[1] ?? null;
if (!$xmlfile || !file_exists($xmlfile)) {
    echo "Usage: php validate-questions.php <path-to-xml-file>\n";
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);
global $DB;

$context = context::instance_by_id($contextid);
$any_failure = false;

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
