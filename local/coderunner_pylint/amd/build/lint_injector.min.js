/**
 * Minimal DOM positioning for pre-rendered pylint panels.
 *
 * This module makes ZERO network requests. It only moves server-rendered
 * HTML panels from a hidden container to the correct location in each
 * CodeRunner question's feedback area.
 *
 * @module     local_coderunner_pylint/lint_injector
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        /**
         * Initialise the lint panel injector.
         *
         * @param {Object} slotPanelMap - Maps question slot numbers to panel element IDs.
         *                                Example: {"1": "pylint-panel-1", "3": "pylint-panel-3"}
         */
        init: function(slotPanelMap) {
            // Wait for DOM to be ready.
            var doInject = function() {
                Object.keys(slotPanelMap).forEach(function(slot) {
                    var panelId = slotPanelMap[slot];
                    var panel = document.getElementById(panelId);

                    if (!panel) {
                        return;
                    }

                    // Find the question container for this slot.
                    // Moodle uses id="question-{attemptid}-{slot}" or similar patterns.
                    // Try several selectors to find the right question div.
                    var questionDiv = null;
                    var selectors = [
                        '#question-' + slot,
                        '[id*="question-"][id$="-' + slot + '"]',
                        '.que:nth-of-type(' + slot + ')'
                    ];

                    for (var i = 0; i < selectors.length; i++) {
                        questionDiv = document.querySelector(selectors[i]);
                        if (questionDiv) {
                            break;
                        }
                    }

                    if (!questionDiv) {
                        // Fallback: try to find by data attribute.
                        questionDiv = document.querySelector('[data-slot="' + slot + '"]');
                    }

                    if (!questionDiv) {
                        return;
                    }

                    // Find the feedback/outcome area within this question.
                    var feedback = questionDiv.querySelector('.outcome') ||
                                   questionDiv.querySelector('.coderunner-test-results') ||
                                   questionDiv.querySelector('.specificfeedback') ||
                                   questionDiv.querySelector('.feedback');

                    if (feedback) {
                        // Insert the panel after the feedback area.
                        feedback.parentNode.insertBefore(panel, feedback.nextSibling);
                    } else {
                        // Fallback: append to the question container.
                        questionDiv.appendChild(panel);
                    }

                    // Make the panel visible.
                    panel.style.display = '';
                });
            };

            // Run injection. If DOM isn't ready yet, wait for it.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', doInject);
            } else {
                doInject();
            }
        }
    };
});
