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
 * Inject CQP linter into quiz and question-edit pages before the footer.
 *
 * On attempt/preview pages: loads the client-side "Check Code Quality" button
 * for questions whose teacher has enabled linting.
 * On review pages: also renders server-side lint panels.
 * On the question edit page: renders a "Configure linting" card for Python
 * CodeRunner questions, linking to manage.php.
 */
function local_coderunner_pylint_before_footer() {
    global $PAGE;

    if (!\core_component::get_component_directory('qtype_coderunner')) {
        return;
    }

    local_coderunner_pylint_inject_edit_page_link();

    $pagetype = $PAGE->pagetype;
    $isattempt = strpos($pagetype, 'mod-quiz-attempt') === 0;
    $ispreview = strpos($pagetype, 'mod-quiz-preview') === 0
              || strpos($pagetype, 'question-preview') === 0;
    $isreview  = strpos($pagetype, 'mod-quiz-review') === 0;
    if (!$isattempt && !$ispreview && !$isreview) {
        return;
    }

    $quba = local_coderunner_pylint_get_quba();
    if ($quba === null) {
        return;
    }

    $slotsinfo = [];
    foreach ($quba->get_slots() as $slot) {
        $qa = $quba->get_question_attempt($slot);
        if (!\local_coderunner_pylint\question_helper::is_python_coderunner($qa)) {
            continue;
        }
        $question = $qa->get_question();
        if (!\local_coderunner_pylint\question_helper::is_lint_enabled($question->id)) {
            continue;
        }
        $slotsinfo[] = [
            'slot'       => (int)$slot,
            'questionid' => (int)$question->id,
        ];
    }

    if (!empty($slotsinfo)) {
        $PAGE->requires->js_call_amd('local_coderunner_pylint/cqp_linter', 'init', [
            ['slots' => $slotsinfo]
        ]);
    }

    if (!$isreview) {
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
 * On the /question/question.php edit page, render a small card with the
 * current linting state for the question and a button that opens the
 * per-question configuration form.
 *
 * No-op on every other page, on the new-question flow (no id yet), and
 * when the question isn't a Python CodeRunner question or the user lacks
 * the configure capability on its context.
 */
function local_coderunner_pylint_inject_edit_page_link(): void {
    global $DB, $PAGE;

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Matches both the legacy /question/question.php entry point and the
    // qbank_editquestion variant /question/bank/editquestion/question.php
    // used in parts of Moodle 4.x.
    if (!preg_match('#/question(?:/bank/[a-zA-Z0-9_]+)?/question\.php$#', $script)) {
        return;
    }

    $questionid = optional_param('id', 0, PARAM_INT);
    if ($questionid <= 0) {
        // Creating a new question; no id exists yet.
        return;
    }

    // Use direct DB reads instead of question_bank::load_question — that call
    // relies on full qtype loading, and can fail or short-circuit in subtle
    // ways during the edit flow. A plain query on {question} is enough here.
    $question = $DB->get_record('question', ['id' => $questionid], 'id, qtype, name');
    if (!$question || $question->qtype !== 'coderunner') {
        return;
    }

    // Best-effort Python check: only hide the card if we can prove the
    // question is non-Python. If CodeRunner's options table isn't present
    // or doesn't store a recognisable language, fall through and show it.
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('question_coderunner_options')) {
        $options = $DB->get_record('question_coderunner_options',
            ['questionid' => $questionid], 'coderunnertype');
        if ($options && !empty($options->coderunnertype)) {
            $type = strtolower((string)$options->coderunnertype);
            if (strpos($type, 'python') === false) {
                return;
            }
        }
    }

    try {
        $ctx = \local_coderunner_pylint\question_helper::get_question_context($questionid);
    } catch (\Throwable $e) {
        return;
    }
    if (!has_capability('local/coderunner_pylint:configure', $ctx)) {
        return;
    }

    $enabled = \local_coderunner_pylint\question_helper::is_lint_enabled($questionid);
    $returnurl = $PAGE->url instanceof \moodle_url ? $PAGE->url->out_as_local_url(false) : '';
    $manageurl = (new \moodle_url('/local/coderunner_pylint/manage.php', [
        'questionid' => $questionid,
        'returnurl'  => $returnurl,
    ]))->out(false);

    $statestr = $enabled
        ? get_string('manage_state_enabled', 'local_coderunner_pylint')
        : get_string('manage_state_disabled', 'local_coderunner_pylint');
    $btnstr = $enabled
        ? get_string('configure_linting', 'local_coderunner_pylint')
        : get_string('enable_linting', 'local_coderunner_pylint');
    $title = get_string('pluginname', 'local_coderunner_pylint');

    $html = '<div id="local_coderunner_pylint-edit-card" class="card" style="margin:1rem 0;">'
          . '<div class="card-body">'
          . '<h5 class="card-title">' . s($title) . '</h5>'
          . '<p class="card-text">' . s($statestr) . '</p>'
          . '<a class="btn btn-secondary" href="' . $manageurl . '">' . s($btnstr) . '</a>'
          . '</div></div>';

    echo $html;

    // Relocate the card to sit directly below the main question edit form,
    // instead of being stranded above the footer.
    $PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    function move() {
        var card = document.getElementById('local_coderunner_pylint-edit-card');
        if (!card) { return; }
        var form = document.querySelector('form.mform') ||
                   document.querySelector('#mform1') ||
                   document.querySelector('form[action*="question.php"]');
        if (form && form.parentNode) {
            form.parentNode.insertBefore(card, form.nextSibling);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', move);
    } else {
        move();
    }
});
JS);
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
