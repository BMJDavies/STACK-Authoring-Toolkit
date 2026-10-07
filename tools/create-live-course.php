<?php
/**
 * Create a course you will actually publish questions into, give it a question
 * bank, and print the COURSES line to add to project/project.conf.
 *
 * Idempotent: reuses an existing course with the same shortname.
 *
 * Usage:
 *   docker cp tools/create-live-course.php <container>:/tmp/clc.php
 *   docker exec <container> php /tmp/clc.php \
 *       --key=alg --shortname="Algebra 1" --fullname="Algebra 1 (interactive)"
 *
 *   --key       short handle you will pass to push-live.sh as --course=<key>
 *   --shortname Moodle course shortname (must be unique on the site)
 *   --fullname  Moodle course full name (defaults to shortname)
 *   --visible   0 or 1 (default 0, i.e. hidden while you build it)
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

global $DB, $CFG;
$admin = get_admin();
\core\session\manager::set_user($admin);

$opts = [];
for ($i = 1; $i < $argc; $i++) {
    if (preg_match('/^--([a-z]+)=(.*)$/i', $argv[$i], $m)) $opts[$m[1]] = $m[2];
}

if (empty($opts['key']) || empty($opts['shortname'])) {
    echo "Usage: php create-live-course.php --key=<handle> --shortname=\"...\" [--fullname=\"...\"] [--visible=0|1]\n";
    exit(1);
}

$key       = $opts['key'];
$shortname = $opts['shortname'];
$fullname  = $opts['fullname'] ?? $shortname;
$visible   = isset($opts['visible']) ? (int)$opts['visible'] : 0;

$course = $DB->get_record('course', ['shortname' => $shortname]);
if ($course) {
    echo "course EXISTS   id={$course->id}  {$shortname}\n";
} else {
    $course = create_course((object)[
        'category'      => 1,
        'shortname'     => $shortname,
        'fullname'      => $fullname,
        'summary'       => '',
        'summaryformat' => FORMAT_HTML,
        'format'        => 'topics',
        'visible'       => $visible,
        'startdate'     => time(),
    ]);
    echo "course CREATED  id={$course->id}  {$shortname}\n";
}

// Question bank activity + top-level category inside it.
$qbankname = "{$shortname} question bank";
$catname   = "{$shortname} questions";
$module = $DB->get_record('modules', ['name' => 'qbank'], '*', MUST_EXIST);
$existing = $DB->get_record_sql(
    'SELECT cm.id AS cmid FROM {course_modules} cm
       JOIN {qbank} q ON q.id = cm.instance
      WHERE cm.course = :cid AND cm.module = :mid AND q.name = :name',
    ['cid' => $course->id, 'mid' => $module->id, 'name' => $qbankname]
);

if ($existing) {
    $cmid = $existing->cmid;
    echo "qbank EXISTS    cmid={$cmid}\n";
} else {
    $created = create_module((object)[
        'modulename'  => 'qbank',
        'course'      => $course->id,
        'section'     => 0,
        'visible'     => 1,
        'name'        => $qbankname,
        'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'type'        => 'standard',
    ]);
    $cmid = $created->coursemodule;
    echo "qbank CREATED   cmid={$cmid}\n";
}

$contextid = context_module::instance($cmid)->id;

$cat = $DB->get_record('question_categories', ['contextid' => $contextid, 'name' => $catname]);
if ($cat) {
    $categoryid = $cat->id;
    echo "category EXISTS categoryid={$categoryid}\n";
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
    echo "category CREATED categoryid={$categoryid}\n";
}

$label = str_replace(' ', '_', $shortname);

echo "\n=========================================================\n";
echo "Add this to COURSES in project/project.conf (space-separated list):\n\n";
echo "  {$key}:{$course->id}:{$categoryid}:{$contextid}:{$label}\n\n";
echo "Then push with:  tools/push-live.sh <xml> <quiz-id> --course={$key}\n";
echo "Course page: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
