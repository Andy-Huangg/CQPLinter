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

namespace local_coderunner_pylint\output;

use local_coderunner_pylint\pylint_result;

/**
 * Renders pylint results as HTML using Mustache templates.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lint_renderer {

    /**
     * Render a pylint_result into an HTML panel.
     *
     * @param pylint_result $result The lint result to render.
     * @param string $minseverity Minimum severity to display.
     * @param string $panelid Unique ID for the panel element.
     * @return string Rendered HTML.
     */
    public static function render(pylint_result $result, string $minseverity = 'convention', string $panelid = ''): string {
        global $OUTPUT;

        if (!$result->is_valid()) {
            return $OUTPUT->render_from_template('local_coderunner_pylint/lint_panel', [
                'panelid' => $panelid,
                'has_error' => true,
                'error_message' => get_string('linterror', 'local_coderunner_pylint'),
                'has_messages' => false,
                'severity_groups' => [],
                'score' => '?',
                'scoreclass' => 'badge-secondary',
                'total_issues' => 0,
            ]);
        }

        $grouped = $result->get_grouped($minseverity);
        $severitygroups = [];
        $totalissues = 0;

        foreach ($grouped as $type => $messages) {
            $groupmessages = [];
            foreach ($messages as $msg) {
                $groupmessages[] = $msg->to_template_data();
            }
            $count = count($groupmessages);
            $totalissues += $count;

            $severitygroups[] = [
                'type' => $type,
                'label' => self::get_severity_label($type),
                'cssclass' => self::get_severity_css($type),
                'count' => $count,
                'messages' => $groupmessages,
            ];
        }

        // Determine score badge class.
        $score = round($result->score, 1);
        if ($score >= 8.0) {
            $scoreclass = 'badge-success';
        } else if ($score >= 5.0) {
            $scoreclass = 'badge-warning';
        } else {
            $scoreclass = 'badge-danger';
        }

        $data = [
            'panelid' => $panelid,
            'has_error' => false,
            'has_messages' => $totalissues > 0,
            'severity_groups' => $severitygroups,
            'score' => number_format($score, 1),
            'scoreclass' => $scoreclass,
            'total_issues' => $totalissues,
            'no_issues_message' => get_string('noissues', 'local_coderunner_pylint'),
        ];

        return $OUTPUT->render_from_template('local_coderunner_pylint/lint_panel', $data);
    }

    /**
     * Get a human-readable label for a severity type.
     *
     * @param string $type Severity type.
     * @return string Localised label.
     */
    private static function get_severity_label(string $type): string {
        $key = 'severity_' . $type;
        if (get_string_manager()->string_exists($key, 'local_coderunner_pylint')) {
            return get_string($key, 'local_coderunner_pylint');
        }
        return ucfirst($type);
    }

    /**
     * Get the CSS class for a severity type.
     *
     * @param string $type Severity type.
     * @return string CSS class name.
     */
    private static function get_severity_css(string $type): string {
        $map = [
            'fatal' => 'pylint-error',
            'error' => 'pylint-error',
            'warning' => 'pylint-warning',
            'refactor' => 'pylint-refactor',
            'convention' => 'pylint-convention',
            'info' => 'pylint-info',
        ];
        return $map[$type] ?? 'pylint-info';
    }
}
