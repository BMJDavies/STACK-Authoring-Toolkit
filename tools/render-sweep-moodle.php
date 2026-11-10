<?php
/**
 * render-sweep-moodle.php: the Moodle side of tools/render-sweep.sh, for
 * machines where the STACK API cannot run (its images are amd64-only).
 *
 * Imports the XML into the sandbox category, as validate-questions.php does,
 * then renders every deployed seed of every STACK question through Moodle's
 * own question renderer: question text, worked solution (general feedback)
 * and question note. Writes them as JSON, in the shape of the STACK API's
 * /render reply, for tools/stackapi/render_sweep.py --renders to check.
 * Read-only apart from the sandbox import.
 *
 * Usage (inside the container; the wrapper does this):
 *   php render-sweep-moodle.php <xml-file> <json-out>
 * Needs VALIDATE_CATEGORYID and VALIDATE_CONTEXTID, and MOODLE_ROOT if Moodle
 * is not at /var/www/html.
 *
 * Exit 0 = rendered, 2 = could not import or render.
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

[$xmlfile, $outfile] = [$argv[1] ?? null, $argv[2] ?? null];
$categoryid = (int)getenv('VALIDATE_CATEGORYID');
$contextid = (int)getenv('VALIDATE_CONTEXTID');
if (!$xmlfile || !is_file($xmlfile) || !$outfile || !$categoryid || !$contextid) {
    fwrite(STDERR, "Usage: php render-sweep-moodle.php <xml-file> <json-out>, with VALIDATE_CATEGORYID and VALIDATE_CONTEXTID set\n");
    exit(2);
}
// Same fallback as validate-questions.php and render_sweep.py.
$defaultseeds = [1, 42, 137, 256, 999];

global $DB, $PAGE;
\core\session\manager::set_user(get_admin());
$context = context::instance_by_id($contextid);

$format = new qformat_xml();
$format->setCategory((object)['id' => $categoryid, 'contextid' => $contextid]);
$format->setContexts([$context]);
$format->setCatfromfile(false);
$format->setMatchgrades('error');
$format->setStoponerror(false);
$format->setFilename($xmlfile);
ob_start();
$ok = $format->importprocess();
ob_end_clean();
if (!$ok) {
    fwrite(STDERR, "Import failed: run tools/test-question.sh on the file first.\n");
    exit(2);
}

$out = [];
$errors = 0;
foreach ($format->questionids as $qid) {
    $name = $DB->get_field('question', 'name', ['id' => $qid]);
    if ($DB->get_field('question', 'qtype', ['id' => $qid]) !== 'stack') {
        continue;
    }
    if (!$DB->record_exists('qtype_stack_deployed_seeds', ['questionid' => $qid])) {
        foreach ($defaultseeds as $seed) {
            $DB->insert_record('qtype_stack_deployed_seeds', ['questionid' => $qid, 'seed' => $seed]);
        }
    }
    $nv = question_bank::load_question($qid)->get_num_variants();
    for ($v = 1; $v <= $nv; $v++) {
        try {
            $quba = question_engine::make_questions_usage_by_activity('core_question_preview', $context);
            $quba->set_preferred_behaviour('immediatefeedback');
            $slot = $quba->add_question(question_bank::load_question($qid));
            $quba->start_question($slot, $v);
            $qa = $quba->get_question_attempt($slot);
            $question = $qa->get_question();
            $opts = new question_display_options();
            $opts->context = $context;
            $renderer = $question->get_renderer($PAGE);
            $out[$name][(string)$question->seed] = [
                'questionrender' => $renderer->formulation_and_controls($qa, $opts),
                // general_feedback() instantiates the CASText; format_generalfeedback() does not.
                'questionsamplesolutiontext' => $renderer->general_feedback($qa),
                'questionnote' => $question->get_question_summary(),
            ];
        } catch (Throwable $e) {
            $out[$name]['error-variant-' . $v] = ['error' => $e->getMessage()];
            $errors++;
        }
    }
}
file_put_contents($outfile, json_encode($out));
echo "Rendered " . count($out) . " STACK question(s)" . ($errors ? ", $errors variant(s) failed" : '') . ".\n";
exit($errors ? 2 : 0);
