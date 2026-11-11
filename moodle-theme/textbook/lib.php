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
 * Interactive Textbook theme lib.
 *
 * @package    theme_textbook
 * @copyright  2026 Ben Davies and the University of Southampton
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content for the theme.
 *
 * @param theme_config $theme The theme config object.
 * @return string SCSS content.
 */
function theme_textbook_get_main_scss_content($theme) {
    // Boost's main SCSS with our custom styles appended.
    $boost = theme_boost_get_main_scss_content($theme);
    $custom = file_get_contents(__DIR__ . '/scss/textbook.scss');
    return $boost . "\n" . $custom;
}

/**
 * Returns pre-SCSS (loaded before Boost's main SCSS).
 *
 * @param theme_config $theme The theme config object.
 * @return string Pre-SCSS content.
 */
function theme_textbook_get_pre_scss($theme) {
    return '';
}

/**
 * Returns extra SCSS (loaded after Boost's main SCSS: our customisations).
 *
 * @param theme_config $theme The theme config object.
 * @return string Extra SCSS content.
 */
function theme_textbook_get_extra_scss($theme) {
    // Empty: our styles are in the main scss callback.
    return '';
}

