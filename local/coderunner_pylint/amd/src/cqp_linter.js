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
     * Annotation type for an Ace gutter marker, derived from the lint severity.
     */
    function annotationType(msgType) {
        if (msgType === 'error' || msgType === 'fatal') {
            return 'error';
        }
        if (msgType === 'warning') {
            return 'warning';
        }
        return 'info';
    }

    /**
     * Clear all CQP annotations and markers from an Ace editor, and detach
     * any document anchors / change listener created by applyAnnotations.
     *
     * @param {Object} editor Ace editor instance.
     * @param {Object|null} state State returned by applyAnnotations, or null.
     */
    function clearAnnotations(editor, state) {
        var session = editor.getSession();
        session.clearAnnotations();
        if (!state) {
            return;
        }
        if (state.onChange) {
            session.off('change', state.onChange);
        }
        state.tracked.forEach(function(t) {
            session.removeMarker(t.markerId);
            t.startAnchor.detach();
            t.endAnchor.detach();
        });
    }

    /**
     * Apply CQP violation annotations and line highlights to the Ace editor.
     *
     * Uses document anchors so markers and gutter annotations track the
     * flagged lines as the student edits the code (inserts/removes lines
     * above a violation shift the highlight with the line, instead of
     * stranding it on a fixed row).
     *
     * @param {Object} editor Ace editor instance.
     * @param {Array} messages Array of CQP-enriched lint messages.
     * @return {Object} State object for clearAnnotations.
     */
    function applyAnnotations(editor, messages) {
        var session = editor.getSession();
        var doc = session.getDocument();
        var Range = ace.require('ace/range').Range;
        var tracked = [];

        messages.forEach(function(msg) {
            var row = msg.line - 1;
            var startAnchor = doc.createAnchor(row, 0);
            var endAnchor = doc.createAnchor(row, Number.MAX_VALUE);
            // Keep the end anchor pinned to the end of the (potentially
            // growing) line rather than sliding left when text is inserted.
            endAnchor.$insertRight = true;

            var range = new Range(row, 0, row, Number.MAX_VALUE);
            range.start = startAnchor;
            range.end = endAnchor;

            var cssClass = HIGHLIGHT_CLASSES[msg.type] || 'cqp-highlight-info';
            var markerId = session.addMarker(range, cssClass, 'fullLine', false);

            tracked.push({
                msg: msg,
                startAnchor: startAnchor,
                endAnchor: endAnchor,
                markerId: markerId
            });
        });

        var refreshAnnotations = function() {
            var seen = {};
            var annotations = [];
            tracked.forEach(function(t) {
                var row = t.startAnchor.row;
                // Collapse duplicates on the same row so the gutter tooltip
                // shows one merged entry when two issues end up on one line
                // after edits.
                var key = row + '|' + annotationType(t.msg.type);
                var text = 'CQP ' + t.msg.cqp_number + ': ' + t.msg.cqp_name + '\n' +
                           t.msg.message + '\n\n' +
                           t.msg.cqp_guideline;
                if (seen[key] !== undefined) {
                    annotations[seen[key]].text += '\n\n---\n\n' + text;
                    return;
                }
                seen[key] = annotations.length;
                annotations.push({
                    row: row,
                    column: 0,
                    text: text,
                    type: annotationType(t.msg.type)
                });
            });
            session.setAnnotations(annotations);
        };

        refreshAnnotations();

        var onChange = function() {
            refreshAnnotations();
            // Force the marker layer to redraw with the updated anchor rows.
            session._signal('changeBackMarker');
        };
        session.on('change', onChange);

        return {tracked: tracked, onChange: onChange};
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

    /**
     * Escape HTML special characters.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    /**
     * Escape for HTML attribute values.
     */
    function escapeAttr(text) {
        return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

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
     * Find the element we should insert our controls after — the answer
     * container wrapping the Ace editor. We insert OUTSIDE it to avoid
     * triggering Ace's auto-resize loop.
     */
    function findAnswerContainer(questionDiv) {
        var answerContainer = questionDiv.querySelector('.answer') ||
                              questionDiv.querySelector('.qtype_coderunner_answer');
        if (!answerContainer) {
            var aceEl = questionDiv.querySelector('.ace_editor');
            if (!aceEl) {
                return null;
            }
            answerContainer = aceEl.parentNode;
        }
        if (!answerContainer || !answerContainer.parentNode) {
            return null;
        }
        return answerContainer;
    }

    /**
     * Get (or create) the per-question wrapper that holds the lint controls.
     * Ensures we don't double-attach if init() fires twice.
     */
    function getOrCreateWrapper(questionDiv) {
        var existing = questionDiv.querySelector('.cqp-lint-wrapper');
        if (existing) {
            return existing;
        }
        var answerContainer = findAnswerContainer(questionDiv);
        if (!answerContainer) {
            return null;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'cqp-lint-wrapper';
        answerContainer.parentNode.insertBefore(wrapper, answerContainer.nextSibling);
        return wrapper;
    }

    /**
     * Attach the "Check Code Quality" button to a question container.
     */
    function attachCheckButton(questionDiv, wrapper) {
        if (wrapper.querySelector('.cqp-lint-btn')) {
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

        var currentState = null;

        btn.addEventListener('click', function() {
            var code = getCode(questionDiv);
            if (!code || !code.trim()) {
                resultsDiv.innerHTML = '<div class="alert alert-info" style="margin-top:0.5rem;">' +
                    'Please write some code before checking quality.</div>';
                resultsDiv.style.display = '';
                return;
            }

            var editor = findAceEditor(questionDiv);
            if (editor) {
                clearAnnotations(editor, currentState);
                currentState = null;
            }

            var data = Analyser.analyse(code);

            if (editor && data.messages.length > 0) {
                currentState = applyAnnotations(editor, data.messages);
            }

            resultsDiv.innerHTML = buildResultsPanel(data);
            resultsDiv.style.display = '';
        });

        wrapper.appendChild(btn);
        wrapper.appendChild(resultsDiv);
    }

    return {
        /**
         * Initialise the CQP linter for the specific CodeRunner questions
         * passed in by the PHP side. Only questions whose teacher has
         * explicitly enabled linting should appear in the slots list.
         *
         * @param {Object} config {slots: [{slot, questionid}]}
         */
        init: function(config) {
            var slots = (config && config.slots) || [];
            if (slots.length === 0) {
                return;
            }

            var doInit = function() {
                slots.forEach(function(slotInfo) {
                    var questionDiv = findQuestionDiv(slotInfo.slot);
                    if (!questionDiv) {
                        return;
                    }
                    var wrapper = getOrCreateWrapper(questionDiv);
                    if (!wrapper) {
                        return;
                    }
                    attachCheckButton(questionDiv, wrapper);
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
});
