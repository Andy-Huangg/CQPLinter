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
 * Admin settings for local_coderunner_pylint.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coderunner_pylint', get_string('pluginname', 'local_coderunner_pylint'));

    // Header.
    $settings->add(new admin_setting_heading(
        'local_coderunner_pylint/heading',
        get_string('settings_heading', 'local_coderunner_pylint'),
        get_string('settings_heading_desc', 'local_coderunner_pylint')
    ));

    // Jobe server host (optional override — inherited from CodeRunner if empty).
    $settings->add(new admin_setting_configtext(
        'local_coderunner_pylint/jobe_host',
        get_string('jobe_host', 'local_coderunner_pylint'),
        get_string('jobe_host_desc', 'local_coderunner_pylint'),
        '',
        PARAM_TEXT
    ));

    // Timeout.
    $settings->add(new admin_setting_configtext(
        'local_coderunner_pylint/timeout',
        get_string('timeout', 'local_coderunner_pylint'),
        get_string('timeout_desc', 'local_coderunner_pylint'),
        '10',
        PARAM_INT
    ));

    // Max code size.
    $settings->add(new admin_setting_configtext(
        'local_coderunner_pylint/max_code_size',
        get_string('max_code_size', 'local_coderunner_pylint'),
        get_string('max_code_size_desc', 'local_coderunner_pylint'),
        '50000',
        PARAM_INT
    ));

    // Default disabled checks.
    $settings->add(new admin_setting_configtextarea(
        'local_coderunner_pylint/default_disable',
        get_string('default_disable', 'local_coderunner_pylint'),
        get_string('default_disable_desc', 'local_coderunner_pylint'),
        'import-error'
    ));

    // Minimum severity.
    $severityoptions = [
        'error' => get_string('severity_option_error', 'local_coderunner_pylint'),
        'warning' => get_string('severity_option_warning', 'local_coderunner_pylint'),
        'refactor' => get_string('severity_option_refactor', 'local_coderunner_pylint'),
        'convention' => get_string('severity_option_convention', 'local_coderunner_pylint'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_coderunner_pylint/min_severity',
        get_string('min_severity', 'local_coderunner_pylint'),
        get_string('min_severity_desc', 'local_coderunner_pylint'),
        'convention',
        $severityoptions
    ));

    // Cache TTL.
    $settings->add(new admin_setting_configtext(
        'local_coderunner_pylint/cache_ttl',
        get_string('cache_ttl', 'local_coderunner_pylint'),
        get_string('cache_ttl_desc', 'local_coderunner_pylint'),
        '3600',
        PARAM_INT
    ));


    $ADMIN->add('localplugins', $settings);
}
