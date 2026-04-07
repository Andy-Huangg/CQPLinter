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

namespace local_coderunner_pylint;

/**
 * Helper for extracting student code and configuration from question attempts.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_helper {

    /**
     * Check if a question attempt is for a Python CodeRunner question.
     *
     * @param \question_attempt $qa The question attempt.
     * @return bool True if this is a Python CodeRunner question.
     */
    public static function is_python_coderunner(\question_attempt $qa): bool {
        $question = $qa->get_question();

        // Check it's a CodeRunner question.
        if ($question->get_type_name() !== 'coderunner') {
            return false;
        }

        // Check the language is Python.
        // CodeRunner stores language in the question object.
        if (isset($question->language)) {
            $lang = strtolower($question->language);
            return strpos($lang, 'python') !== false;
        }

        // Fallback: check the coderunnertype field.
        if (isset($question->coderunnertype)) {
            $type = strtolower($question->coderunnertype);
            return strpos($type, 'python') !== false;
        }

        return false;
    }

    /**
     * Get the student's submitted code from a question attempt.
     *
     * @param \question_attempt $qa The question attempt.
     * @return string|null The student's code, or null if no answer submitted.
     */
    public static function get_student_code(\question_attempt $qa): ?string {
        $code = $qa->get_last_qt_var('answer');
        if ($code === null || trim($code) === '') {
            return null;
        }
        return $code;
    }

    /**
     * Check if a question attempt has been graded (has feedback to show).
     *
     * @param \question_attempt $qa The question attempt.
     * @return bool True if the question has been graded at least once.
     */
    public static function has_been_graded(\question_attempt $qa): bool {
        // CodeRunner stores test outcome in _testoutcome after Check,
        // or _precheckoutcome after Precheck.
        return $qa->get_last_qt_var('_testoutcome') !== null ||
               $qa->get_last_qt_var('_precheckoutcome') !== null;
    }

    /**
     * Check if linting is enabled for a specific question.
     *
     * Checks per-question config first, then falls back to the global default.
     *
     * @param int $questionid The question ID.
     * @return bool True if linting is enabled.
     */
    public static function is_lint_enabled(int $questionid): bool {
        global $DB;

        // Check per-question config.
        $config = $DB->get_record('local_crpylint_qconfig', ['questionid' => $questionid]);
        if ($config) {
            return (bool)$config->enabled;
        }

        // Fall back to global default.
        return (bool)get_config('local_coderunner_pylint', 'enable_by_default');
    }

    /**
     * Get the effective lint configuration for a question.
     *
     * Merges admin defaults with per-question overrides.
     *
     * @param int $questionid The question ID.
     * @return array Configuration array with keys: disable, min_severity, rcfile.
     */
    public static function get_lint_config(int $questionid): array {
        global $DB;

        // Start with admin defaults.
        $config = [
            'disable' => get_config('local_coderunner_pylint', 'default_disable') ?: 'import-error',
            'min_severity' => get_config('local_coderunner_pylint', 'min_severity') ?: 'convention',
            'rcfile' => get_config('local_coderunner_pylint', 'pylintrc_path') ?: '',
        ];

        // Merge per-question overrides.
        $qconfig = $DB->get_record('local_crpylint_qconfig', ['questionid' => $questionid]);
        if ($qconfig) {
            if (!empty($qconfig->disabled_checks)) {
                // Append question-specific disabled checks to the global ones.
                $config['disable'] .= ',' . $qconfig->disabled_checks;
            }
            if (!empty($qconfig->min_severity)) {
                $config['min_severity'] = $qconfig->min_severity;
            }
        }

        return $config;
    }

    /**
     * Run pylint on a question attempt's code, using cache.
     *
     * @param \question_attempt $qa The question attempt.
     * @return pylint_result|null The lint result, or null if linting is not applicable.
     */
    public static function lint_question_attempt(\question_attempt $qa): ?pylint_result {
        if (!self::is_python_coderunner($qa)) {
            return null;
        }

        $question = $qa->get_question();
        if (!self::is_lint_enabled($question->id)) {
            return null;
        }

        $code = self::get_student_code($qa);
        if ($code === null) {
            return null;
        }

        $config = self::get_lint_config($question->id);

        // Check cache first.
        $cached = result_cache::get($code, $config);
        if ($cached !== null) {
            return $cached;
        }

        // Run pylint.
        $runner = new pylint_runner();
        $result = $runner->lint($code, $config);

        // Cache the result.
        result_cache::set($code, $config, $result);

        return $result;
    }
}
