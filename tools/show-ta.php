<?php
// Print the teacher's answer for one input of each named question, seed by seed.
// Run inside the Moodle container by show-ta.sh, which explains the arguments.
// The names file has one line per question: its name, a tab, and a
// comma-separated list of seeds.
// Reads the database (the highest id of each name), so import the file first.

define('CLI_SCRIPT', true);
require_once((getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
\core\session\manager::set_user(get_admin());

[$namesfile, $input] = array_slice($argv, 1, 2);
$lines = array_filter(array_map('trim', file($namesfile)));
$status = 0;

foreach ($lines as $line) {
    [$name, $seedlist] = explode("\t", $line);
    $seeds = array_map('intval', explode(',', $seedlist));
    $id = $DB->get_field_sql('SELECT MAX(id) FROM {question} WHERE name = ?', [$name]);
    if (!$id) {
        echo "=== $name: not in the database. Import the file first (tools/test-question.sh).\n";
        $status = 1;
        continue;
    }
    foreach ($seeds as $seed) {
        $q = question_bank::load_question($id);
        if (!isset($q->inputs[$input])) {
            echo "=== $name: has no input $input\n";
            break;
        }
        $q->deployedseeds = [$seed];
        $quba = question_engine::make_questions_usage_by_activity('core_question_preview',
            context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question($q);
        $quba->start_question($slot, 1);
        $q = $quba->get_question_attempt($slot)->get_question();
        echo "=== $name, seed $seed\n" . $q->get_ta_for_input($input) . "\n\n";
    }
}
exit($status);
