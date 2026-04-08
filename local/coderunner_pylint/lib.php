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
 * Library functions for local_coderunner_pylint.
 *
 * Injects the CQP "Check Code Quality" button on quiz attempt pages and
 * server-rendered CQP lint panels on review pages.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject CQP linter into quiz pages before the footer.
 *
 * On attempt/preview pages: loads the client-side CQP analyser button.
 * On review pages: renders server-side lint panels with CQP annotations.
 */
function local_coderunner_pylint_before_footer() {
    global $PAGE;

    // Always load the client-side CQP linter. The JS module discovers CodeRunner
    // questions by looking for Ace editors in the DOM — if none are present on the
    // page it does nothing. This avoids fragile page-type matching.
    $PAGE->requires->js_call_amd('local_coderunner_pylint/cqp_linter', 'init', [
        ['slots' => [], 'discover' => true]
    ]);

    // For review pages, also render server-side panels if pylint is available.
    $pagetype = $PAGE->pagetype;
    $isreview = strpos($pagetype, 'mod-quiz-review') === 0;
    if (!$isreview) {
        return;
    }

    // Check CodeRunner is installed (only needed for server-side review panels).
    if (!\core_component::get_component_directory('qtype_coderunner')) {
        return;
    }

    // On review pages, render server-side panels (using pylint if available).
    try {
        $panels = local_coderunner_pylint_build_panels();
    } catch (\Throwable $e) {
        debugging('CodeRunner Pylint before_footer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return;
    }

    if (empty($panels)) {
        return;
    }

    $slotpanelmap = [];
    $html = '';

    foreach ($panels as $slot => $panelhtml) {
        $panelid = 'pylint-panel-' . $slot;
        $slotpanelmap[$slot] = $panelid;
        $html .= $panelhtml;
    }

    echo '<div id="pylint-panels-container" style="display:none;" aria-hidden="true">' . $html . '</div>';
    echo '<script>
(function(slotPanelMap) {
    function doInject() {
        Object.keys(slotPanelMap).forEach(function(slot) {
            var panelId = slotPanelMap[slot];
            var panel = document.getElementById(panelId);
            if (!panel) { return; }

            var questionDiv = null;
            var selectors = [
                "#question-" + slot,
                "[id*=\"question-\"][id$=\"-" + slot + "\"]",
                ".que:nth-of-type(" + slot + ")"
            ];
            for (var i = 0; i < selectors.length; i++) {
                questionDiv = document.querySelector(selectors[i]);
                if (questionDiv) { break; }
            }
            if (!questionDiv) {
                questionDiv = document.querySelector("[data-slot=\"" + slot + "\"]");
            }
            if (!questionDiv) { return; }

            var feedback = questionDiv.querySelector(".outcome") ||
                           questionDiv.querySelector(".coderunner-test-results") ||
                           questionDiv.querySelector(".specificfeedback") ||
                           questionDiv.querySelector(".feedback");

            if (feedback) {
                feedback.parentNode.insertBefore(panel, feedback.nextSibling);
            } else {
                questionDiv.appendChild(panel);
            }
            panel.style.display = "";
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", doInject);
    } else {
        doInject();
    }
})(' . json_encode($slotpanelmap) . ');
</script>';
}

/**
 * Build lint panels for all Python CodeRunner questions on the current page.
 *
 * @return array Keyed by slot number, values are rendered HTML strings.
 */
function local_coderunner_pylint_build_panels(): array {
    $panels = [];

    $quba = local_coderunner_pylint_get_quba();
    if ($quba === null) {
        return [];
    }

    $minseverity = get_config('local_coderunner_pylint', 'min_severity') ?: 'convention';

    foreach ($quba->get_slots() as $slot) {
        $qa = $quba->get_question_attempt($slot);

        if (!\local_coderunner_pylint\question_helper::has_been_graded($qa)) {
            continue;
        }

        $result = \local_coderunner_pylint\question_helper::lint_question_attempt($qa);
        if ($result === null) {
            continue;
        }

        $question = $qa->get_question();
        $config = \local_coderunner_pylint\question_helper::get_lint_config($question->id);
        $effectiveseverity = $config['min_severity'] ?? $minseverity;

        $panelid = 'pylint-panel-' . $slot;
        $panels[$slot] = \local_coderunner_pylint\output\lint_renderer::render(
            $result,
            $effectiveseverity,
            $panelid
        );
    }

    return $panels;
}

/**
 * Get the question usage (quba) for the current quiz page.
 *
 * @return \question_usage_by_activity|null
 */
function local_coderunner_pylint_get_quba(): ?\question_usage_by_activity {
    global $DB;

    $attemptid = optional_param('attempt', 0, PARAM_INT);
    if (!empty($attemptid)) {
        $attemptobj = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attemptobj) {
            return null;
        }
        try {
            return \question_engine::load_questions_usage_by_activity($attemptobj->uniqueid);
        } catch (\Exception $e) {
            debugging('CodeRunner Pylint: Failed to load question usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    $previewid = optional_param('previewid', 0, PARAM_INT);
    if (!empty($previewid)) {
        $preview = $DB->get_record('question_previews', ['id' => $previewid]);
        if (!$preview) {
            return null;
        }
        try {
            return \question_engine::load_questions_usage_by_activity($preview->qubaid);
        } catch (\Exception $e) {
            debugging('CodeRunner Pylint: Failed to load preview question usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    return null;
}
