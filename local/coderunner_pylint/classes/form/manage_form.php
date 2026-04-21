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

namespace local_coderunner_pylint\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Per-question pylint configuration form.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('header', 'general', get_string('manage_heading', 'local_coderunner_pylint'));

        $mform->addElement('static', 'questionname_static',
            get_string('manage_question', 'local_coderunner_pylint'),
            format_string($customdata['questionname']));

        $mform->addElement('advcheckbox', 'enabled',
            get_string('manage_enabled', 'local_coderunner_pylint'),
            get_string('manage_enabled_label', 'local_coderunner_pylint'));
        $mform->setDefault('enabled', 0);

        $mform->addElement('text', 'disabled_checks',
            get_string('manage_disabled_checks', 'local_coderunner_pylint'),
            ['size' => 60]);
        $mform->setType('disabled_checks', PARAM_TEXT);
        $mform->addHelpButton('disabled_checks', 'manage_disabled_checks', 'local_coderunner_pylint');

        $severityoptions = [
            ''           => get_string('manage_use_global', 'local_coderunner_pylint'),
            'error'      => get_string('severity_option_error', 'local_coderunner_pylint'),
            'warning'    => get_string('severity_option_warning', 'local_coderunner_pylint'),
            'refactor'   => get_string('severity_option_refactor', 'local_coderunner_pylint'),
            'convention' => get_string('severity_option_convention', 'local_coderunner_pylint'),
        ];
        $mform->addElement('select', 'min_severity',
            get_string('min_severity', 'local_coderunner_pylint'),
            $severityoptions);
        $mform->setDefault('min_severity', '');

        $mform->addElement('hidden', 'questionid', $customdata['questionid']);
        $mform->setType('questionid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl', $customdata['returnurl'] ?? '');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
