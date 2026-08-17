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
 * Language strings for local_coderunner_cqp_linter.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'CodeRunner CQP Linter';
$string['privacy:metadata'] = 'The CodeRunner CQP Linter plugin stores a record each time a student clicks the "Check Code Quality" button, for research purposes.';
$string['privacy:metadata:event'] = 'Records of student CQP lint button interactions.';
$string['privacy:metadata:event:userid'] = 'The ID of the student who clicked the button.';
$string['privacy:metadata:event:questionid'] = 'The question the student was answering.';
$string['privacy:metadata:event:attemptid'] = 'The quiz attempt during which the button was clicked (0 for previews).';
$string['privacy:metadata:event:slot'] = 'The slot number of the question in the quiz.';
$string['privacy:metadata:event:issuecount'] = 'The total number of code quality issues found.';
$string['privacy:metadata:event:resultsjson'] = 'Which Code Quality Principles were violated and how many times.';
$string['privacy:metadata:event:timecreated'] = 'When the button was clicked.';

// Admin settings.
$string['settings_heading'] = 'CodeRunner CQP Linter Settings';
$string['settings_heading_desc'] = 'Configure linting for CodeRunner questions. Currently uses pylint to analyse student Python code for errors, warnings, and style issues.';
$string['jobe_host'] = 'Jobe server host (optional override)';
$string['jobe_host_desc'] = 'Leave empty to inherit the Jobe server URL from CodeRunner\'s settings. Set an explicit hostname (e.g. jobe.example.com) to use a different Jobe server for linting.';
$string['timeout'] = 'Pylint timeout (seconds)';
$string['timeout_desc'] = 'Maximum time in seconds for pylint to analyse a single submission. Increase for large code submissions.';
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
$string['coderunner_cqp_linter:viewlintresults'] = 'View lint results for CodeRunner questions';
$string['coderunner_cqp_linter:configure'] = 'Configure lint settings per question';
$string['coderunner_cqp_linter:manageglobalsettings'] = 'Manage global lint settings';

// Cache.
$string['cachedef_lint_results'] = 'Cached lint analysis results';

// Research data export report.
$string['report_title'] = 'CQP Linter — Export research data';
$string['report_rowcount'] = '{$a} interaction event(s) recorded.';
$string['report_download_csv'] = 'Download as CSV';
$string['report_nodata'] = 'No events have been recorded yet. Data appears here once students start clicking the "Check Code Quality" button.';
$string['report_truncated'] = 'Showing 200 of {$a} rows. Download the CSV for the full dataset.';
$string['report_col_user'] = 'Student';
$string['report_col_question'] = 'Question';
$string['report_col_attempt'] = 'Attempt ID';
$string['report_col_issues'] = 'Issues found';
$string['report_col_principles'] = 'Principles violated';
$string['report_col_violations'] = 'Checks (code@line)';
$string['report_col_eventtype'] = 'Event type';
$string['report_col_code'] = 'Code (preview)';
$string['report_col_airesponse'] = 'AI response (preview)';
$string['report_col_time'] = 'Time';

// Per-question management form.
$string['manage_title'] = 'Configure linting';
$string['manage_heading'] = 'Linting configuration for this question';
$string['manage_question'] = 'Question';
$string['manage_enabled'] = 'Enabled';
$string['manage_enabled_label'] = 'Run the linter on this question';
$string['manage_checks_header'] = 'Enabled checks';
$string['manage_checks_desc'] = 'Untick any check to disable it for this question only. All checks are enabled by default.';
$string['manage_custom_codes'] = 'Additional checks (advanced)';
$string['manage_custom_codes_help'] = 'Add extra pylint or pycodestyle codes to run on this question, beyond the checklist above. Enter a comma-separated list of codes, for example: <code>C0114, W0201, E501</code>. A pylint code is a letter followed by four digits (e.g. C0114); a pycodestyle code is a letter followed by three digits (e.g. E501). Any violations of these codes are shown to students in a separate "Additional checks" section using the tool\'s own message, and are not attributed to a Code Quality Principle.';
$string['manage_custom_codes_invalid'] = 'These are not valid pylint/pycodestyle codes: {$a}. Use a letter followed by 3 digits (pycodestyle, e.g. E501) or 4 digits (pylint, e.g. C0114).';
$string['manage_use_global'] = 'Use site default';
$string['manage_saved'] = 'Linting configuration saved.';
$string['configure_linting'] = 'Configure linting';
$string['enable_linting'] = 'Enable linting';
$string['manage_state_enabled'] = 'Linting is currently enabled for this question. Students will see a "Check Code Quality" button when answering it.';
$string['manage_state_disabled'] = 'Linting is not yet enabled for this question. Students will not see any linting UI until you enable it.';

// Style check marks mode.
$string['manage_marks_enabled'] = 'Enable style check marks';
$string['manage_marks_enabled_label'] = 'Inject a style check test case that awards marks if code has no violations';
$string['manage_marks_enabled_help'] = 'When enabled, a hidden test case is added to this question that runs the CQP style checker on the student\'s submitted code. If the code has no style violations, the student earns the allocated marks. The mark weight for this test case can be adjusted afterwards on the question\'s own edit form, like any other test case. <strong>Warning:</strong> enabling this sets the question to partial-credit mode (all-or-nothing grading is disabled), which affects all test cases in the question.';

// AI analysis — admin settings.
$string['ai_heading'] = 'AI analysis (experimental)';
$string['ai_heading_desc'] = 'Optionally use an AI model to assess the Code Quality Principles a static linter cannot check well (descriptive naming, comment quality, duplication, modular structure, problem alignment). <strong>AI feedback can be unreliable</strong> — it is clearly labelled as experimental for students. AI stays off until you set an API key below, and is then enabled per question. Enabling this sends student code to the configured AI provider.';
$string['ai_provider'] = 'AI provider';
$string['ai_provider_desc'] = 'Which AI service to send requests to. The API key, model and base URL settings below all apply to the selected provider. Choose "OpenAI or compatible" for api.openai.com or any other service exposing an OpenAI-compatible endpoint — just set its base URL below. The model does not have to be an OpenAI model.';
$string['ai_provider_openai'] = 'OpenAI or compatible';
$string['ai_provider_azure'] = 'Azure OpenAI / AI Foundry (deployment endpoint)';
$string['ai_provider_gemini'] = 'Google Gemini';
$string['ai_api_key'] = 'API key';
$string['ai_api_key_desc'] = 'The API key for the selected provider. Stored in Moodle config. This is the global on/off for AI: set a key to make AI available (then enable it per question), or leave it blank to keep AI disabled everywhere.';
$string['ai_model'] = 'Model';
$string['ai_model_desc'] = 'The model to use, in the form the selected endpoint expects. Azure: the name of your <em>deployment</em>, not the underlying model. Gemini: e.g. gemini-2.0-flash. OpenAI or compatible: the endpoint\'s model id, e.g. gpt-4o-mini.';
$string['ai_base_url'] = 'API base URL';
$string['ai_base_url_desc'] = '<strong>Required for Azure</strong>: your resource endpoint, e.g. https://myresource.openai.azure.com. For Gemini and OpenAI, leave blank to use the provider\'s default endpoint, or set it to use any other OpenAI-compatible endpoint.';
$string['ai_azure_api_version'] = 'Azure API version';
$string['ai_azure_api_version_desc'] = 'Only used by the Azure provider: the api-version query parameter for Azure OpenAI requests. Default: 2024-10-21.';
$string['ai_timeout'] = 'AI request timeout (seconds)';
$string['ai_timeout_desc'] = 'Maximum time to wait for the API response before giving up.';
$string['ai_max_code_size'] = 'Maximum code size for AI (bytes)';
$string['ai_max_code_size_desc'] = 'Submissions larger than this are not sent to the AI (to bound token cost).';
$string['ai_temperature'] = 'Temperature';
$string['ai_temperature_desc'] = 'Sampling temperature 0.0-1.0. Lower is more consistent/conservative. Default 0.2. ' .
    'Reasoning-class Azure deployments (e.g. GPT-5-class, o-series models) only accept the default value and will ' .
    'reject requests at any other temperature. Set this to 1 if you are using one of those models.';

// AI analysis — per-question and UI.
$string['manage_ai_enabled'] = '(experimental) Enable AI analysis';
$string['manage_ai_enabled_label'] = 'Use AI to assess this question';
$string['manage_ai_enabled_help'] = 'When enabled, the configured AI model assesses this question\'s submissions against the selected Code Quality Principles. AI feedback is shown to students in a separate, clearly labelled "experimental" section. AI can be unreliable, so review its suggestions before relying on them.';
$string['manage_ai_principles'] = 'Principles assessed by AI';
$string['manage_ai_principles_help'] = 'Choose which Code Quality Principles the AI should evaluate for this question. The static linter continues to handle principles 1, 3, and 5. Unticking all of them means the AI assesses nothing even if AI analysis is enabled above.';
$string['ai_panel_title'] = 'AI Feedback (experimental)';
$string['ai_panel_loading'] = 'Analysing code with AI…';
$string['ai_panel_disclaimer'] = 'This feedback is generated by an AI model and may be incomplete or incorrect. Treat it as suggestions, not a definitive grade.';
$string['ai_panel_error'] = 'AI analysis is unavailable right now.';
$string['ai_panel_clean'] = 'The AI found no issues for the assessed principles.';
$string['ai_source_label'] = 'AI';
