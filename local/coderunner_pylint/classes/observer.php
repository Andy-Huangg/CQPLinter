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
 * Event observer for pre-caching pylint results.
 *
 * Listens for quiz attempt submission events and runs pylint on any
 * Python CodeRunner questions to pre-populate the cache. This means
 * the review page loads with lint results already available.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle quiz attempt submission.
     *
     * Iterates through all question attempts in the quiz and runs pylint
     * on Python CodeRunner questions, caching the results.
     *
     * @param \mod_quiz\event\attempt_submitted $event The event.
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        try {
            self::process_attempt($event);
        } catch (\Throwable $e) {
            // Never let lint errors break quiz submission.
            debugging('CodeRunner Pylint observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Process a quiz attempt to pre-cache lint results.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    private static function process_attempt(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        // Check if the plugin is globally enabled.
        if (!get_config('local_coderunner_pylint', 'enable_by_default')) {
            // Even if globally disabled, per-question overrides could exist.
            // But for efficiency in the observer, skip if globally off.
            return;
        }

        $attemptid = $event->objectid;

        // Load the quiz attempt.
        $attemptobj = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attemptobj) {
            return;
        }

        // Load the question usage.
        $quba = \question_engine::load_questions_usage_by_activity($attemptobj->uniqueid);

        // Iterate through all question slots.
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);

            // This method handles all checks (is python, is enabled, has code)
            // and uses the cache internally.
            question_helper::lint_question_attempt($qa);
        }
    }
}
