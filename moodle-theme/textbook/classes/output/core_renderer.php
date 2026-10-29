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
 * Interactive Textbook theme core renderer.
 *
 * Extends Boost's renderer to load this theme's JavaScript modules.
 *
 * @package    theme_textbook
 * @copyright  2026 Ben Davies and the University of Southampton
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_textbook\output;

/**
 * Core renderer for the Interactive Textbook theme.
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Inject theme JS modules at the end of the page body.
     *
     * @return string HTML fragment.
     */
    public function standard_end_of_body_html(): string {
        $this->page->requires->js_call_amd('theme_textbook/dropdown_placeholder', 'init');
        return parent::standard_end_of_body_html();
    }
}
