<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Post-install hook for the Interactive Textbook theme.
 * Sets up language customisations for quiz labels.
 *
 * @package    theme_textbook
 * @copyright  2026 Ben Davies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_theme_textbook_install() {
    // Write language overrides for quiz strings.
    // These make quizzes feel like textbook topics rather than tests.
    $overrides = [
        'attemptquiz' => 'Start topic',
        'attemptquiznow' => 'Start topic',
        'previewquiznow' => 'Start topic',
        'reattempt' => 'Continue topic',
        'continueattemptquiz' => 'Continue topic',
        'continuepreview' => 'Continue topic',
    ];

    global $CFG;
    $langdir = $CFG->langlocalroot . '/en_local';
    if (!is_dir($langdir)) {
        mkdir($langdir, 0755, true);
    }

    $filepath = $langdir . '/quiz.php';

    // Read existing overrides if file exists.
    $existing = [];
    if (file_exists($filepath)) {
        include($filepath);
        $existing = $string ?? [];
    }

    // Merge our overrides (don't clobber user's other customisations).
    $merged = array_merge($existing, $overrides);

    $content = "<?php\n// Language overrides installed by theme_textbook.\n";
    foreach ($merged as $key => $val) {
        $content .= '$string[\'' . $key . '\'] = \'' . addslashes($val) . "';\n";
    }

    file_put_contents($filepath, $content);
    get_string_manager()->reset_caches();

    // Add Google Fonts to the HTML head (only if not already present).
    $fontslink = '<link rel="preconnect" href="https://fonts.googleapis.com">'
               . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
               . '<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">';
    $existing_head = get_config('core', 'additionalhtmlhead') ?: '';
    if (strpos($existing_head, 'Source+Serif') === false) {
        set_config('additionalhtmlhead', $existing_head . "\n" . $fontslink);
    }
}
