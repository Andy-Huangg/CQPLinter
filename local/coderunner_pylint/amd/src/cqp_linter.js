/**
 * CQP Linter — adds a "Check Code Quality" button to CodeRunner questions
 * and highlights violations inline in the Ace editor with CQP principle annotations.
 *
 * All analysis happens client-side via python_analyser.js. No code is sent to any server.
 *
 * @module     local_coderunner_pylint/cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_coderunner_pylint/python_analyser'], function(Analyser) {
    'use strict';

    /** CSS class prefix for CQP severity highlighting in the Ace editor. */
    var HIGHLIGHT_CLASSES = {
        fatal:      'cqp-highlight-error',
        error:      'cqp-highlight-error',
        warning:    'cqp-highlight-warning',
        refactor:   'cqp-highlight-refactor',
        convention: 'cqp-highlight-convention',
        info:       'cqp-highlight-info'
    };

    /**
     * Find the Ace editor instance for a given question container.
     *
     * @param {HTMLElement} questionDiv The question container element.
     * @return {Object|null} The Ace editor instance, or null.
     */
    function findAceEditor(questionDiv) {
        var aceEl = questionDiv.querySelector('.ace_editor');
        if (!aceEl || !aceEl.id) {
            return null;
        }
        if (typeof ace !== 'undefined' && ace.edit) {
            try {
                return ace.edit(aceEl.id);
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the student's code from the question.
     *
     * @param {HTMLElement} questionDiv The question container.
     * @return {string|null} The code, or null if not found.
     */
    function getCode(questionDiv) {
        var editor = findAceEditor(questionDiv);
        if (editor) {
            return editor.getValue();
        }
        var textarea = questionDiv.querySelector('[name$="_answer"]') ||
                       questionDiv.querySelector('textarea.coderunner-answer') ||
                       questionDiv.querySelector('textarea.edit_code');
        if (textarea) {
            return textarea.value;
        }
        return null;
    }

    /**
     * Clear all CQP annotations and markers from an Ace editor.
     *
     * @param {Object} editor Ace editor instance.
     * @param {Array} markerIds Array of marker IDs to remove.
     */
    function clearAnnotations(editor, markerIds) {
        editor.getSession().clearAnnotations();
        var session = editor.getSession();
        markerIds.forEach(function(id) {
            session.removeMarker(id);
        });
    }

    /**
     * Apply CQP violation annotations and line highlights to the Ace editor.
     *
     * @param {Object} editor Ace editor instance.
     * @param {Array} messages Array of CQP-enriched lint messages.
     * @return {Array} Array of marker IDs (for later removal).
     */
    function applyAnnotations(editor, messages) {
        var session = editor.getSession();
        var annotations = [];
        var markerIds = [];
        var Range = ace.require('ace/range').Range;

        messages.forEach(function(msg) {
            var row = msg.line - 1;

            var annoType = 'info';
            if (msg.type === 'error' || msg.type === 'fatal') {
                annoType = 'error';
            } else if (msg.type === 'warning') {
                annoType = 'warning';
            }

            annotations.push({
                row: row,
                column: 0,
                text: 'CQP ' + msg.cqp_number + ': ' + msg.cqp_name + '\n' +
                      msg.message + '\n\n' +
                      msg.cqp_guideline,
                type: annoType
            });

            var cssClass = HIGHLIGHT_CLASSES[msg.type] || 'cqp-highlight-info';
            var range = new Range(row, 0, row, Infinity);
            var markerId = session.addMarker(range, cssClass, 'fullLine', false);
            markerIds.push(markerId);
        });

        session.setAnnotations(annotations);
        return markerIds;
    }

    /**
     * Build the results panel HTML to show below the editor.
     *
     * @param {Object} data The analysis result from python_analyser.
     * @return {string} HTML string.
     */
    function buildResultsPanel(data) {
        var html = '<div class="cqp-results-panel">';

        html += '<details open>';

        // Header.
        html += '<summary class="cqp-results-header">';
        html += '<span class="cqp-results-title">Code Quality Report</span>';
        html += '<span class="cqp-issue-count">' + data.total_issues + ' issue' +
                (data.total_issues !== 1 ? 's' : '') + ' found</span>';
        html += '</summary>';

        if (data.total_issues === 0) {
            html += '<div class="cqp-clean">No issues found. Well done!</div>';
            html += '</details></div>';
            return html;
        }

        // Principle summary badges.
        html += '<div class="cqp-principle-summary">';
        data.principles.forEach(function(p) {
            html += '<span class="cqp-principle-badge cqp-principle-' + p.number + '" ' +
                    'title="' + escapeAttr(p.short) + '">' +
                    'CQP ' + p.number + ': ' + escapeHtml(p.name) +
                    ' <span class="cqp-badge-count">(' + p.count + ')</span></span>';
        });
        html += '</div>';

        // Message groups by principle.
        data.principles.forEach(function(group) {
            html += '<div class="cqp-group">';
            html += '<div class="cqp-group-header cqp-principle-' + group.number + '-header">';
            html += '<strong>CQP ' + group.number + ': ' + escapeHtml(group.name) + '</strong>';
            html += '</div>';
            html += '<div class="cqp-group-guideline">' + escapeHtml(group.guideline) + '</div>';
            html += '<table class="table table-sm cqp-messages-table"><tbody>';
            group.messages.forEach(function(msg) {
                var rowClass = HIGHLIGHT_CLASSES[msg.type]
                    ? HIGHLIGHT_CLASSES[msg.type].replace('cqp-highlight-', 'cqp-row-')
                    : '';
                html += '<tr class="' + rowClass + '">';
                html += '<td class="cqp-line-col"><code>L' + msg.line + '</code></td>';
                html += '<td class="cqp-msg-col">' + escapeHtml(msg.message) +
                        ' <span class="cqp-symbol text-muted">(' + escapeHtml(msg.symbol) + ')</span></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
        });

        html += '</details></div>';
        return html;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    return {
        /**
         * Initialise the CQP linter for CodeRunner questions on the page.
         *
         * Called from lib.php. If slots are provided, buttons are added to those
         * specific questions. If discover mode is set, all CodeRunner questions
         * are discovered via DOM inspection.
         *
         * @param {Object} config Configuration: {slots: [{slot, questionid}], discover: bool}
         */
        init: function(config) {
            var doInit = function() {
                var targets = [];

                if (config.slots && config.slots.length > 0) {
                    config.slots.forEach(function(slotInfo) {
                        var questionDiv = findQuestionDiv(slotInfo.slot);
                        if (questionDiv) {
                            targets.push(questionDiv);
                        }
                    });
                }

                if (config.discover || targets.length === 0) {
                    // Discover all CodeRunner questions by looking for Ace editors.
                    var queContainers = document.querySelectorAll('.que.coderunner, .que');
                    queContainers.forEach(function(q) {
                        if (q.querySelector('.ace_editor') && targets.indexOf(q) === -1) {
                            targets.push(q);
                        }
                    });
                }

                targets.forEach(function(questionDiv) {
                    attachButton(questionDiv);
                });
            };

            // Delay to let Ace editors initialise.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(doInit, 500);
                });
            } else {
                setTimeout(doInit, 500);
            }
        }
    };

    /**
     * Find a question container by slot number.
     */
    function findQuestionDiv(slot) {
        var selectors = [
            '#question-' + slot,
            '[id*="question-"][id$="-' + slot + '"]',
            '[data-slot="' + slot + '"]'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
                return el;
            }
        }
        return null;
    }

    /**
     * Attach the "Check Code Quality" button to a question container.
     */
    function attachButton(questionDiv) {
        if (questionDiv.querySelector('.cqp-lint-btn')) {
            return;
        }

        var answerArea = questionDiv.querySelector('.answer') ||
                         questionDiv.querySelector('.qtype_coderunner_answer') ||
                         questionDiv.querySelector('.ace_editor');
        if (!answerArea) {
            return;
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-primary btn-sm cqp-lint-btn';
        btn.textContent = 'Check Code Quality';
        btn.title = 'Analyse your code against the Code Quality Principles (runs locally, no code is sent anywhere)';

        var resultsDiv = document.createElement('div');
        resultsDiv.className = 'cqp-results-container';
        resultsDiv.style.display = 'none';

        var currentMarkers = [];

        btn.addEventListener('click', function() {
            var code = getCode(questionDiv);
            if (!code || !code.trim()) {
                resultsDiv.innerHTML = '<div class="alert alert-info" style="margin-top:0.5rem;">' +
                    'Please write some code before checking quality.</div>';
                resultsDiv.style.display = '';
                return;
            }

            // Clear previous results.
            var editor = findAceEditor(questionDiv);
            if (editor) {
                clearAnnotations(editor, currentMarkers);
                currentMarkers = [];
            }

            // Run the client-side analysis.
            var data = Analyser.analyse(code);

            // Apply Ace editor annotations.
            if (editor && data.messages.length > 0) {
                currentMarkers = applyAnnotations(editor, data.messages);
            }

            // Show results panel.
            resultsDiv.innerHTML = buildResultsPanel(data);
            resultsDiv.style.display = '';
        });

        var wrapper = document.createElement('div');
        wrapper.className = 'cqp-lint-wrapper';
        wrapper.appendChild(btn);
        wrapper.appendChild(resultsDiv);

        if (answerArea.parentNode) {
            answerArea.parentNode.insertBefore(wrapper, answerArea.nextSibling);
        }
    }
});
