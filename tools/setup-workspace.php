<?php
/**
 * One-off: create the two behind-the-scenes courses the pipeline needs, give
 * each a question bank, and create the review preview quiz.
 *
 *   Authoring sandbox: where validation imports land. Fills up with junk;
 *                       never point this at anything student-facing.
 *   Authoring review:  where drafts go to be looked at in quiz context.
 *
 * Both are created hidden. Idempotent: run it as often as you like, it reuses
 * anything that already exists.
 *
 * At the end it prints the exact lines to paste into project/project.conf.
 *
 * Usage:
 *   docker cp tools/setup-workspace.php <container>:/tmp/setup.php
 *   docker exec <container> php /tmp/setup.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

global $DB, $CFG;
$admin = get_admin();
\core\session\manager::set_user($admin);

/**
 * Find or create a hidden course.
 */
function ensure_course(string $shortname, string $fullname, string $summary): object {
    global $DB;
    $existing = $DB->get_record('course', ['shortname' => $shortname]);
    if ($existing) {
        echo "  course EXISTS   id={$existing->id}  {$shortname}\n";
        return $existing;
    }
    $course = create_course((object)[
        'category'      => 1,          // Miscellaneous
        'shortname'     => $shortname,
        'fullname'      => $fullname,
        'summary'       => $summary,
        'summaryformat' => FORMAT_HTML,
        'format'        => 'topics',
        'visible'       => 0,          // hidden from students
        'startdate'     => time(),
    ]);
    echo "  course CREATED  id={$course->id}  {$shortname}\n";
    return $course;
}

/**
 * Find or create a question bank activity in a course, plus a top-level
 * category inside it. Returns [cmid, contextid, categoryid].
 */
function ensure_qbank(int $courseid, string $qbankname, string $catname): array {
    global $DB;
    $module = $DB->get_record('modules', ['name' => 'qbank'], '*', MUST_EXIST);
    $existing = $DB->get_record_sql(
        'SELECT cm.id AS cmid FROM {course_modules} cm
           JOIN {qbank} q ON q.id = cm.instance
          WHERE cm.course = :cid AND cm.module = :mid AND q.name = :name',
        ['cid' => $courseid, 'mid' => $module->id, 'name' => $qbankname]
    );

    if ($existing) {
        $cmid = $existing->cmid;
        echo "  qbank EXISTS    cmid={$cmid}  {$qbankname}\n";
    } else {
        $created = create_module((object)[
            'modulename'  => 'qbank',
            'course'      => $courseid,
            'section'     => 0,
            'visible'     => 1,
            'name'        => $qbankname,
            'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'type'        => 'standard',
        ]);
        $cmid = $created->coursemodule;
        echo "  qbank CREATED   cmid={$cmid}  {$qbankname}\n";
    }

    $contextid = context_module::instance($cmid)->id;

    $cat = $DB->get_record('question_categories', ['contextid' => $contextid, 'name' => $catname]);
    if ($cat) {
        $categoryid = $cat->id;
        echo "  category EXISTS categoryid={$categoryid}  {$catname}\n";
    } else {
        $parent = $DB->get_record('question_categories', ['contextid' => $contextid, 'parent' => 0]);
        $categoryid = $DB->insert_record('question_categories', (object)[
            'name'       => $catname,
            'contextid'  => $contextid,
            'info'       => '',
            'infoformat' => FORMAT_HTML,
            'parent'     => $parent ? $parent->id : 0,
            'sortorder'  => 999,
            'idnumber'   => null,
            'stamp'      => make_unique_id_code(),
        ]);
        echo "  category CREATED categoryid={$categoryid}  {$catname}\n";
    }

    return [$cmid, $contextid, $categoryid];
}

/**
 * Find or create a quiz shell in a course. Returns [quizid, cmid].
 */
function ensure_quiz(int $courseid, string $name): array {
    global $DB;
    $existing = $DB->get_record('quiz', ['course' => $courseid, 'name' => $name]);
    if ($existing) {
        $cm = get_coursemodule_from_instance('quiz', $existing->id, $courseid, false, MUST_EXIST);
        echo "  quiz EXISTS     id={$existing->id}  cmid={$cm->id}  {$name}\n";
        return [$existing->id, $cm->id];
    }
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $data = (object)[
        'course'              => $courseid,
        'name'                => $name,
        'modulename'          => 'quiz',
        'module'              => $DB->get_field('modules', 'id', ['name' => 'quiz']),
        'section'             => 0,
        'visible'             => 1,
        'visibleoncoursepage' => 1,
        'intro'               => '',
        'introformat'         => FORMAT_HTML,
        'cmidnumber'          => '',
        'preferredbehaviour'  => 'immediatefeedback',
        'attempts'            => 0,
        'grademethod'         => QUIZ_GRADEHIGHEST,
        'shufflequestions'    => 0,
        'shuffleanswers'      => 0,
        'questionsperpage'    => 1,
        'grade'               => 10,
        'sumgrades'           => 0,
        'navmethod'           => 'free',
        'canredoquestions'    => 1,
        'decimalpoints'       => 2,
        'questiondecimalpoints' => -1,
        'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0,
        'showuserpicture' => 0, 'showblocks' => 1, 'quizpassword' => '',
    ];
    foreach (['attempt', 'specificfeedback', 'correctness', 'marks', 'maxmarks', 'generalfeedback'] as $f) {
        foreach (['during', 'immediately', 'open', 'closed'] as $w) $data->{"{$f}{$w}"} = 1;
    }
    $module = add_moduleinfo($data, $course);
    echo "  quiz CREATED    id={$module->instance}  cmid={$module->coursemodule}  {$name}\n";
    return [$module->instance, $module->coursemodule];
}

echo "--- Authoring sandbox (validation target) ---\n";
$dev = ensure_course('authoring-sandbox', 'Authoring sandbox (validation target)',
    'Validation imports land here. Fills with duplicates by design. Not student-facing.');
[, $devctx, $devcat] = ensure_qbank((int)$dev->id, 'Sandbox question bank', 'Sandbox questions');

echo "\n--- Authoring review (draft preview) ---\n";
$rev = ensure_course('authoring-review', 'Authoring review (draft preview)',
    'Drafts are previewed here in quiz context before publishing. Not student-facing.');
[, $revctx, $revcat] = ensure_qbank((int)$rev->id, 'Review question bank', 'Review questions');
[$revquiz, $revcmid] = ensure_quiz((int)$rev->id, 'Draft preview');

echo "\n=========================================================\n";
echo "Paste these into project/project.conf:\n";
echo "=========================================================\n\n";
echo "DEV_CATEGORYID={$devcat}\n";
echo "DEV_CONTEXTID={$devctx}\n";
echo "REVIEW_CATEGORYID={$revcat}\n";
echo "REVIEW_CONTEXTID={$revctx}\n";
echo "REVIEW_QUIZID={$revquiz}\n";
echo "REVIEW_QUIZ_CMID={$revcmid}\n";
echo "\nPreview quiz: {$CFG->wwwroot}/mod/quiz/view.php?id={$revcmid}\n";
