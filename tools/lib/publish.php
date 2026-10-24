<?php
/**
 * Shared by push-to-live.php and push-to-review.php: publish a section file
 * question by question, adding a new version to a question already in the quiz
 * instead of replacing it.
 *
 * The wrappers (push-live.sh, push-review.sh) copy this file into the container
 * beside the script, as <script>_lib.php. Requires Moodle's config.php to have
 * been loaded already.
 */
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/questionlib.php');

// STACK 4.13 requires qbank_importasversion, but a site admin can disable it.
function kit_require_importasversion() {
    global $CFG;
    $file = $CFG->dirroot . '/question/bank/importasversion/classes/importer.php';
    if (!file_exists($file) || !\core\plugininfo\qbank::is_plugin_enabled('qbank_importasversion')) {
        echo "The qbank_importasversion plugin is missing or disabled on this site.\n";
        echo "Enable it under Site administration > Plugins > Question bank plugins, then re-run.\n";
        exit(1);
    }
    require_once($file);
}

// Split an XML file into one file per question, in file order: a list of
// ['name', 'qtype', 'file']. The plugin imports a file that holds exactly one
// question. DOM keeps CDATA sections as they are. Null if not well-formed.
function kit_split_questions($xmlpath, $workdir) {
    $doc = new DOMDocument();
    if (!@$doc->load($xmlpath)) {
        return null;
    }
    $incoming = [];
    foreach ($doc->documentElement->childNodes as $node) {
        if (!($node instanceof DOMElement) || $node->nodeName !== 'question') continue;
        $qtype = $node->getAttribute('type');
        if ($qtype === 'category') continue;
        $name = '';
        foreach ($node->getElementsByTagName('name') as $n) {
            $name = trim($n->textContent);
            break;
        }
        $one = new DOMDocument('1.0', 'UTF-8');
        $one->appendChild($one->createElement('quiz'))->appendChild($one->importNode($node, true));
        $file = $workdir . '/q' . count($incoming) . '.xml';
        $one->save($file);
        $incoming[] = ['name' => $name, 'qtype' => $qtype, 'file' => $file];
    }
    return $incoming;
}

// The latest version of each question in a quiz, keyed by name: slot,
// reference, bank entry, question id, type and version number.
function kit_quiz_questions($quizid) {
    global $DB;
    $rows = $DB->get_records_sql(
        'SELECT qs.id AS slot_id, qs.page, qr.id AS ref_id,
                qr.questionbankentryid AS bankentryid,
                q.id AS questionid, q.name, q.qtype, qv.version
           FROM {quiz_slots} qs
           JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = \'mod_quiz\' AND qr.questionarea = \'slot\'
           JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
           JOIN {question} q ON q.id = qv.questionid
          WHERE qs.quizid = :quizid
                AND qv.version = (SELECT MAX(v2.version) FROM {question_versions} v2
                                   WHERE v2.questionbankentryid = qr.questionbankentryid)',
        ['quizid' => $quizid]);
    $byname = [];
    foreach ($rows as $r) {
        $byname[$r->name] = $r;
    }
    return $byname;
}

// Read a one-question XML file the way the importer will, as an array that can
// be compared field by field. Empty values are dropped, so a field Moodle
// writes out empty and the author left out compare equal.
function kit_read_question_file($file) {
    $f = new qformat_xml();
    $qs = $f->readquestions(file($file));
    $strip = function($v) use (&$strip) {
        if (!is_array($v)) return $v;
        $out = [];
        foreach ($v as $k => $x) {
            $x = $strip($x);
            if ($x === null || $x === '' || $x === []) continue;
            $out[$k] = $x;
        }
        return $out;
    };
    return $strip(json_decode(json_encode($qs[0] ?? null), true));
}

// The top-level fields that differ between a question in Moodle and a
// one-question file: empty means unchanged.
function kit_changed_fields($questionid, $file, $workdir) {
    $f = new qformat_xml();
    $current = $workdir . "/current_{$questionid}.xml";
    file_put_contents($current, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n"
        . $f->writequestion(question_bank::load_question_data($questionid)) . "</quiz>\n");
    $a = kit_read_question_file($current);
    $b = kit_read_question_file($file);
    $changed = [];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
        if (($a[$k] ?? null) != ($b[$k] ?? null)) $changed[] = $k;
    }
    return $changed;
}

// Import a one-question file as a new bank entry. Returns its question id, or
// null after printing the importer's messages.
function kit_import_new($file, $categoryid, $contextid) {
    $format = new qformat_xml();
    $format->setCategory((object)['id' => $categoryid, 'contextid' => $contextid]);
    $format->setContexts([context::instance_by_id($contextid)]);
    $format->setCatfromfile(false);
    $format->setMatchgrades('error');
    $format->setStoponerror(false);
    $format->setFilename($file);
    ob_start();
    $ok = $format->importprocess();
    $raw = ob_get_clean();
    if (!$ok || empty($format->questionids)) {
        $clean = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        echo preg_replace('/\n{3,}/', "\n\n", trim($clean)) . "\n";
        return null;
    }
    return (int)end($format->questionids);
}

// Add a one-question file as a new version of an existing question. Returns
// [new question id, null] or [null, error message].
function kit_add_version($questionid, $file) {
    $qformat = new qformat_xml();
    $qformat->displayprogress = false;
    $result = \qbank_importasversion\importer::import_file(
        $qformat, question_bank::load_question($questionid), $file);
    if (is_object($result) && !empty($result->error)) {
        return [null, $result->error];
    }
    return [(int)end($qformat->questionids), null];
}
