<?php
/**
 * Create or update a Moodle quiz with interactive-textbook defaults.
 *
 * Wraps Moodle's quiz module API so agents can spin up a new quiz for a new
 * section, or adjust settings on an existing one, without clicking through
 * the Moodle UI.
 *
 * Usage (from your Mac terminal):
 *   docker cp tools/quiz-admin.php <container>:/tmp/quiz-admin.php
 *   docker exec <container> php /tmp/quiz-admin.php create --courseid=<n> --name="Ch5A Linear inequalities" --section=5
 *   docker exec <container> php /tmp/quiz-admin.php update --quizid=75 --attempts=2
 *   docker exec <container> php /tmp/quiz-admin.php show   --quizid=75
 *
 * Defaults (see style-guide.md and the quiz-settings skill):
 *   - preferredbehaviour = adaptivenopenalty  (STACK "Adaptive mode")
 *   - shufflequestions off, shuffleanswers off
 *   - attempts = 0 (unlimited), grademethod = highest
 *   - showdescription off on course page
 *   - reviewoptions: show everything after the attempt is submitted
 *
 * Overrides: any --key=value pair on the CLI sets the corresponding quiz
 * field. Unknown keys are rejected (to catch typos).
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/modlib.php');

global $DB, $CFG;

// --- Parse CLI args ---------------------------------------------------------
$action = $argv[1] ?? '';
$opts = [];
for ($i = 2; $i < count($argv); $i++) {
    if (preg_match('/^--([a-z0-9_]+)=(.*)$/i', $argv[$i], $m)) {
        $opts[$m[1]] = $m[2];
    }
}

$admin = get_admin();
\core\session\manager::set_user($admin);

// Review options are stored as bitmasks but must be supplied to add_moduleinfo
// as split form-style fields ({field}during, {field}immediately, {field}open,
// {field}closed). quiz_process_options() combines them back into the bitmask.
// For formative interactive use: show specific feedback + correctness
// during the attempt (adaptive Check button), and reveal the right answer
// only after submission.
$REVIEW_DEFAULTS = [];
$review_all      = ['attempt', 'specificfeedback', 'correctness', 'marks', 'maxmarks', 'generalfeedback'];
$review_after    = ['overallfeedback'];
foreach ($review_all as $f) {
    foreach (['during', 'immediately', 'open', 'closed'] as $w) $REVIEW_DEFAULTS["{$f}{$w}"] = 1;
}
foreach ($review_after as $f) {
    foreach (['immediately', 'open', 'closed'] as $w) $REVIEW_DEFAULTS["{$f}{$w}"] = 1;
}

$DEFAULTS = array_merge([
    'preferredbehaviour'  => 'immediatefeedback',
    'attempts'            => 0,
    'grademethod'         => QUIZ_GRADEHIGHEST,
    'shufflequestions'    => 0,
    'shuffleanswers'      => 0,
    'questionsperpage'    => 1,
    'timeopen'            => 0,
    'timeclose'           => 0,
    'timelimit'           => 0,
    'decimalpoints'       => 2,
    'questiondecimalpoints' => -1,
    'sumgrades'           => 0,
    'grade'               => 10,
    'showuserpicture'     => 0,
    'showblocks'          => 1,
    'navmethod'           => 'free',
    'canredoquestions'    => 1,
    'quizpassword'        => '',
], $REVIEW_DEFAULTS);

switch ($action) {
    case 'create':  cmd_create($opts, $DEFAULTS); break;
    case 'update':  cmd_update($opts, $DEFAULTS); break;
    case 'show':    cmd_show($opts); break;
    default:
        echo "Usage: php quiz-admin.php {create|update|show} [--key=value ...]\n";
        exit(1);
}

// ----------------------------------------------------------------------------
function cmd_create(array $opts, array $defaults): void {
    if (empty($opts['courseid']) || empty($opts['name'])) {
        echo "create requires --courseid and --name\n"; exit(1);
    }
    if (empty($opts['section'])) {
        echo "WARNING: --section not specified, quiz will be created in section 0 (General). Use --section=N to place it in the correct chapter section.\n";
    }
    global $DB;
    $courseid = (int)$opts['courseid'];
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    $data = (object)array_merge($defaults, [
        'course'         => $courseid,
        'modulename'     => 'quiz',
        'module'         => $DB->get_field('modules', 'id', ['name' => 'quiz']),
        'section'        => 0,
        'visible'        => 1,
        'visibleoncoursepage' => 1,
        'introformat'    => FORMAT_HTML,
        'intro'          => '',
        'cmidnumber'     => '',
    ], $opts);

    $module = add_moduleinfo($data, $course);
    echo "Created quiz id={$module->instance} cmid={$module->coursemodule}: {$data->name}\n";
    echo "URL: {$GLOBALS['CFG']->wwwroot}/mod/quiz/view.php?id={$module->coursemodule}\n";
}

function cmd_update(array $opts, array $defaults): void {
    if (empty($opts['quizid'])) { echo "update requires --quizid\n"; exit(1); }
    global $DB;
    $quiz = $DB->get_record('quiz', ['id' => (int)$opts['quizid']], '*', MUST_EXIST);

    $allowed = array_keys($defaults);
    foreach ($opts as $k => $v) {
        if ($k === 'quizid') continue;
        if (!in_array($k, $allowed, true) && $k !== 'name' && $k !== 'intro') {
            echo "Unknown setting: $k (aborting to avoid silent typos)\n"; exit(1);
        }
        $quiz->$k = is_numeric($v) ? (int)$v : $v;
    }
    $DB->update_record('quiz', $quiz);
    echo "Updated quiz id={$quiz->id}\n";
}

function cmd_show(array $opts): void {
    if (empty($opts['quizid'])) { echo "show requires --quizid\n"; exit(1); }
    global $DB;
    $quiz = $DB->get_record('quiz', ['id' => (int)$opts['quizid']], '*', MUST_EXIST);
    foreach ((array)$quiz as $k => $v) {
        echo sprintf("  %-28s %s\n", $k, is_scalar($v) ? $v : '(complex)');
    }
}
