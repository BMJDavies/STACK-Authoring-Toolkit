/**
 * Replace blank first option in STACK dropdown inputs with a disabled
 * "Select an answer" placeholder.
 *
 * Loaded by the theme on quiz pages. Uses a MutationObserver to
 * handle re-renders after Check button clicks (AJAX replaces DOM).
 *
 * @module     theme_textbook/dropdown_placeholder
 * @copyright  2026 Ben Davies and the University of Southampton
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var PLACEHOLDER = 'Select an answer';

    /**
     * Find all STACK dropdown selects and replace the blank first option.
     */
    var processDropdowns = function() {
        document.querySelectorAll('.formulation select').forEach(function(sel) {
            var first = sel.options[0];
            if (first && first.value === '' && first.text.trim() !== PLACEHOLDER) {
                first.text = PLACEHOLDER;
                first.disabled = true;
                first.selected = !sel.value;
            }
        });
    };

    return {
        init: function() {
            processDropdowns();

            // Re-process after STACK re-renders (Check button AJAX).
            var observer = new MutationObserver(function() {
                processDropdowns();
            });
            observer.observe(document.body, {childList: true, subtree: true});
        }
    };
});
