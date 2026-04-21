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
 * Language strings for local_coderunner_pylint.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'CodeRunner Pylint';
$string['privacy:metadata'] = 'The CodeRunner Pylint plugin does not store any personal data. Lint results are cached temporarily using code hashes.';

// Admin settings.
$string['settings_heading'] = 'CodeRunner Pylint Settings';
$string['settings_heading_desc'] = 'Configure Python linting for CodeRunner questions. Pylint analyses student code for errors, warnings, and style issues.';
$string['python_path'] = 'Python interpreter path';
$string['python_path_desc'] = 'Path to the Python 3 interpreter. Use "python3" to rely on the system PATH, or specify an absolute path.';
$string['pylint_path'] = 'Pylint binary path (optional)';
$string['pylint_path_desc'] = 'Leave empty to use the bundled pylint. Set an absolute path to use a system-installed pylint instead.';
$string['timeout'] = 'Pylint timeout (seconds)';
$string['timeout_desc'] = 'Maximum time in seconds for pylint to analyse a single submission. Increase for large code submissions.';
$string['max_code_size'] = 'Maximum code size (bytes)';
$string['max_code_size_desc'] = 'Maximum size of student code that will be analysed. Submissions larger than this are skipped.';
$string['default_disable'] = 'Disabled checks';
$string['default_disable_desc'] = 'Comma-separated list of pylint checks to disable globally (e.g. "import-error,no-member"). These checks will not appear in student feedback.';
$string['min_severity'] = 'Minimum severity';
$string['min_severity_desc'] = 'Only show messages at this severity level or above.';
$string['cache_ttl'] = 'Cache lifetime (seconds)';
$string['cache_ttl_desc'] = 'How long to cache lint results for identical code submissions.';
$string['pylintrc_path'] = 'Pylintrc file path';
$string['pylintrc_path_desc'] = 'Absolute path to a custom .pylintrc configuration file. Leave empty to use pylint defaults.';

// Severity levels.
$string['severity_fatal'] = 'Fatal';
$string['severity_error'] = 'Error';
$string['severity_warning'] = 'Warning';
$string['severity_refactor'] = 'Refactor';
$string['severity_convention'] = 'Convention';
$string['severity_info'] = 'Info';

// Severity options for settings dropdown.
$string['severity_option_error'] = 'Errors only';
$string['severity_option_warning'] = 'Warnings and above';
$string['severity_option_refactor'] = 'Refactoring suggestions and above';
$string['severity_option_convention'] = 'All (including style conventions)';

// Lint panel UI.
$string['lintresults'] = 'Code Quality Report';
$string['score'] = 'Score';
$string['line'] = 'Line';
$string['severity'] = 'Severity';
$string['code'] = 'Code';
$string['messagecol'] = 'Message';
$string['noissues'] = 'No issues found. Well done!';
$string['linterror'] = 'Code analysis could not be completed.';
$string['linttimeout'] = 'Code analysis timed out.';
$string['checkcqp'] = 'Check Code Quality';
$string['checkcqp_tooltip'] = 'Analyse your code against the Code Quality Principles';
$string['analysing'] = 'Analysing...';

// CQP principle names.
$string['cqp_1'] = 'Clear Presentation';
$string['cqp_2'] = 'Explanatory Language';
$string['cqp_3'] = 'Consistent Code';
$string['cqp_4'] = 'Used Content';
$string['cqp_5'] = 'Simple Constructs';
$string['cqp_6'] = 'Minimal Duplication';
$string['cqp_7'] = 'Modular Structure';
$string['cqp_8'] = 'Problem Alignment';

// Error messages.
$string['codetoolargeforanalysis'] = 'The submitted code is too large to analyse.';
$string['invalidcodesubmission'] = 'The submitted code contains invalid characters.';
$string['tempfilewritefailed'] = 'Failed to create temporary file for analysis.';
$string['pylintnotavailable'] = 'Pylint is not available. Please contact your administrator.';

// Capabilities.
$string['coderunner_pylint:viewlintresults'] = 'View pylint results for CodeRunner questions';
$string['coderunner_pylint:configure'] = 'Configure pylint settings per question';
$string['coderunner_pylint:manageglobalsettings'] = 'Manage global pylint settings';

// Cache.
$string['cachedef_lint_results'] = 'Cached pylint analysis results';

// Per-question management form.
$string['manage_title'] = 'Configure linting';
$string['manage_heading'] = 'Pylint configuration for this question';
$string['manage_question'] = 'Question';
$string['manage_enabled'] = 'Enabled';
$string['manage_enabled_label'] = 'Run pylint on this question';
$string['manage_disabled_checks'] = 'Extra disabled checks';
$string['manage_disabled_checks_help'] = 'Comma-separated list of pylint checks to disable for this question only (e.g. "missing-docstring,too-few-public-methods"). These are added to the site-wide disabled list.';
$string['manage_use_global'] = 'Use site default';
$string['manage_saved'] = 'Linting configuration saved.';
$string['configure_linting'] = 'Configure linting';
$string['enable_linting'] = 'Enable linting';
$string['manage_state_enabled'] = 'Pylint is currently enabled for this question. Students will see a "Check Code Quality" button when answering it.';
$string['manage_state_disabled'] = 'Pylint is not yet enabled for this question. Students will not see any linting UI until you enable it.';
