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
 * CLI script to verify Jobe connectivity and pylint availability.
 *
 * Usage: php check_pylint.php
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

echo "CodeRunner Pylint - Installation Check\n";
echo "=======================================\n\n";

$runner = new \local_coderunner_pylint\jobe_runner();
$status = $runner->check_availability();

if ($status['available']) {
    echo "[OK] Jobe is reachable and pylint is available.\n";
    echo "  Pylint version: {$status['version']}\n\n";
} else {
    echo "[FAIL] Pylint is NOT available via Jobe.\n";
    echo "  Error: {$status['error']}\n\n";
}

// Show configuration.
$jobeoverride = get_config('local_coderunner_pylint', 'jobe_host') ?: '';
$joberunner   = get_config('qtype_coderunner', 'jobe_host') ?: '(not configured)';
echo "Configuration:\n";
echo "  Jobe host (plugin override): " . ($jobeoverride ?: '(none — inheriting from CodeRunner)') . "\n";
echo "  Jobe host (CodeRunner):      " . $joberunner . "\n";
echo "  timeout:                     " . (get_config('local_coderunner_pylint', 'timeout') ?: '10') . "s\n";
echo "  max_code_size:               " . (get_config('local_coderunner_pylint', 'max_code_size') ?: '50000') . " bytes\n";
echo "  default_disable:             " . (get_config('local_coderunner_pylint', 'default_disable') ?: 'import-error') . "\n";
echo "  min_severity:                " . (get_config('local_coderunner_pylint', 'min_severity') ?: 'convention') . "\n\n";

// Smoke test.
if ($status['available']) {
    echo "Smoke test: linting trivial code via Jobe...\n";
    $testcode = "x = 1\nprint(x)\n";
    $result = $runner->lint($testcode);

    if ($result->is_valid()) {
        echo "  [OK] Pylint executed successfully.\n";
        echo "  Score: {$result->score}/10\n";
        echo "  Messages: " . count($result->messages) . "\n";
        echo "  Time: " . round($result->executiontime, 2) . "s\n";
    } else {
        echo "  [FAIL] Pylint execution failed.\n";
        echo "  Return code: {$result->returncode}\n";
        echo "  Stderr: {$result->stderr}\n";
    }
}

echo "\nCheck complete.\n";
