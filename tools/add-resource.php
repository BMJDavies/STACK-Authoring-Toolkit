<?php
/**
 * Add a file resource (e.g. PDF) to a Moodle course section.
 *
 * Usage:
 *   docker cp tools/add-resource.php <container>:/tmp/add-resource.php
 *   docker cp project/sources/chapter2.pdf <container>:/tmp/upload.pdf
 *   docker exec <container> php /tmp/add-resource.php \
 *       --courseid=<n> --section=2 --name="Source: Chapter 2 (pp. 22-31)" \
 *       --filepath=/tmp/upload.pdf --filename="2_Surds_p022-031.pdf"
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->libdir . '/filelib.php');

global $DB, $USER;

// Parse CLI args.
$opts = [];
for ($i = 1; $i < count($argv); $i++) {
    if (preg_match('/^--([a-z0-9_]+)=(.*)$/i', $argv[$i], $m)) {
        $opts[$m[1]] = $m[2];
    }
}

$required = ['courseid', 'section', 'name', 'filepath', 'filename'];
foreach ($required as $r) {
    if (empty($opts[$r])) {
        echo "Missing --$r\n";
        exit(1);
    }
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$courseid = (int)$opts['courseid'];
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Step 1: Create a draft file in the user's draft area.
$fs = get_file_storage();
$usercontext = context_user::instance($admin->id);
$draftitemid = file_get_unused_draft_itemid();

$filerecord = [
    'contextid' => $usercontext->id,
    'component' => 'user',
    'filearea'  => 'draft',
    'itemid'    => $draftitemid,
    'filepath'  => '/',
    'filename'  => $opts['filename'],
];

$localpath = $opts['filepath'];
if (!file_exists($localpath)) {
    echo "File not found: $localpath\n";
    exit(1);
}

$fs->create_file_from_pathname($filerecord, $localpath);
echo "Uploaded to draft area (itemid=$draftitemid)\n";

// Step 2: Create the resource module.
$data = (object)[
    'course'              => $courseid,
    'modulename'          => 'resource',
    'module'              => $DB->get_field('modules', 'id', ['name' => 'resource']),
    'section'             => (int)$opts['section'],
    'visible'             => 1,
    'visibleoncoursepage' => 1,
    'name'                => $opts['name'],
    'intro'               => '',
    'introformat'         => FORMAT_HTML,
    'cmidnumber'          => '',
    'showdescription'     => 0,
    'display'             => 0,  // automatic
    'showsize'            => 1,
    'showtype'            => 1,
    'files'               => $draftitemid,
];

$module = add_moduleinfo($data, $course);
echo "Created resource cmid={$module->coursemodule}: {$opts['name']}\n";
echo "URL: {$CFG->wwwroot}/pluginfile.php/{$module->coursemodule}/mod_resource/content/0/{$opts['filename']}\n";
