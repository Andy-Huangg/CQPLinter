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
 * Registers the "Configure linting" action column with the question bank.
 *
 * @package    qbank_coderunnerpylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_feature extends \core_question\local\bank\plugin_features_base {

    public function get_question_columns(\core_question\local\bank\view $qbank): array {
        // Defensive: if a future/other Moodle build renames or removes the
        // menu_action_condition_base class, silently contribute no columns
        // rather than breaking the whole question bank page.
        if (!class_exists('\\core_question\\local\\bank\\menu_action_condition_base')) {
            return [];
        }
        return [
            new configure_action_column($qbank),
        ];
    }
}
