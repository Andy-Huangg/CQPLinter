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

namespace qbank_coderunnerpylint;

/**
 * Action column that adds a "Configure linting" entry to the question bank
 * row menu for CodeRunner questions. Clicking it opens the per-question
 * linting configuration page provided by local_coderunner_pylint.
 *
 * @package    qbank_coderunnerpylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configure_action_column extends \core_question\local\bank\action_column_base
        implements \core_question\local\bank\menuable_action {

    /**
     * Unique column name used by the qbank renderer.
     */
    public function get_name(): string {
        return 'configurecoderunnerpylintaction';
    }

    /**
     * Fields we need the qbank query to select for us.
     */
    public function get_required_fields(): array {
        return ['q.id', 'q.qtype'];
    }

    /**
     * Decide what URL, icon and label to show for this question row.
     *
     * Returns nulls (the standard "hide this row action" signal) when the
     * question isn't a CodeRunner question or the user lacks the configure
     * capability on its context — so students / non-editors never see it.
     *
     * @param \stdClass $question Row data (contains at least id and qtype).
     * @return array [\moodle_url|null, string|null, string|null]
     */
    protected function get_url_icon_and_label(\stdClass $question): array {
        if (empty($question->qtype) || $question->qtype !== 'coderunner') {
            return [null, null, null];
        }

        try {
            $ctx = \local_coderunner_pylint\question_helper::get_question_context((int)$question->id);
        } catch (\Throwable $e) {
            return [null, null, null];
        }

        if (!has_capability('local/coderunner_pylint:configure', $ctx)) {
            return [null, null, null];
        }

        $url = new \moodle_url('/local/coderunner_pylint/manage.php', [
            'questionid' => (int)$question->id,
        ]);
        $label = get_string('configure_linting', 'qbank_coderunnerpylint');
        return [$url, 't/preferences', $label];
    }
}
