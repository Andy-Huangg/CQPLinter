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
 * Per-question pylint configuration page.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$questionid = required_param('questionid', PARAM_INT);
$returnurl  = optional_param('returnurl', '', PARAM_LOCALURL);

require_login();

$question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
$context  = \local_coderunner_pylint\question_helper::get_question_context($questionid);
require_capability('local/coderunner_pylint:configure', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coderunner_pylint/manage.php', [
    'questionid' => $questionid,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage_title', 'local_coderunner_pylint'));
$PAGE->set_heading(get_string('manage_heading', 'local_coderunner_pylint'));

$form = new \local_coderunner_pylint\form\manage_form(null, [
    'questionid'   => $questionid,
    'questionname' => $question->name,
    'returnurl'    => $returnurl,
]);

$existing = $DB->get_record('local_crpylint_qconfig', ['questionid' => $questionid]);
if ($existing) {
    $form->set_data([
        'enabled'         => (int)$existing->enabled,
        'disabled_checks' => $existing->disabled_checks,
        'min_severity'    => $existing->min_severity ?: '',
        'questionid'      => $questionid,
        'returnurl'       => $returnurl,
    ]);
}

$redirecturl = $returnurl !== '' ? new moodle_url($returnurl) : $PAGE->url;

if ($form->is_cancelled()) {
    redirect($redirecturl);
} else if ($data = $form->get_data()) {
    $now = time();
    $record = (object)[
        'questionid'      => $questionid,
        'enabled'         => !empty($data->enabled) ? 1 : 0,
        'disabled_checks' => trim((string)($data->disabled_checks ?? '')) !== ''
                                ? trim((string)$data->disabled_checks)
                                : null,
        'min_severity'    => !empty($data->min_severity) ? $data->min_severity : null,
        'timemodified'    => $now,
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_crpylint_qconfig', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_crpylint_qconfig', $record);
    }
    redirect($redirecturl, get_string('manage_saved', 'local_coderunner_pylint'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($question->name));
$form->display();
echo $OUTPUT->footer();
