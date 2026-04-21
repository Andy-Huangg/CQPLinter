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
 * CLI script to verify pylint installation and configuration.
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

$runner = new \local_coderunner_pylint\pylint_runner();
$status = $runner->check_availability();

if ($status['available']) {
    echo "[OK] Pylint is available.\n";
    echo "  Version: {$status['version']}\n\n";
} else {
    echo "[FAIL] Pylint is NOT available.\n";
    echo "  Error: {$status['error']}\n\n";
}

// Check configuration.
echo "Configuration:\n";
echo "  python_path:     " . (get_config('local_coderunner_pylint', 'python_path') ?: 'python3 (default)') . "\n";
echo "  pylint_path:     " . (get_config('local_coderunner_pylint', 'pylint_path') ?: '(bundled)') . "\n";
echo "  timeout:         " . (get_config('local_coderunner_pylint', 'timeout') ?: '10') . "s\n";
echo "  max_code_size:   " . (get_config('local_coderunner_pylint', 'max_code_size') ?: '50000') . " bytes\n";
echo "  default_disable: " . (get_config('local_coderunner_pylint', 'default_disable') ?: 'import-error') . "\n";
echo "  min_severity:    " . (get_config('local_coderunner_pylint', 'min_severity') ?: 'convention') . "\n\n";

// Check vendor directory.
$vendordir = __DIR__ . '/../vendor/python';
if (is_dir($vendordir)) {
    // Count files in vendor.
    $count = 0;
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vendordir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $count++;
            $size += $file->getSize();
        }
    }
    $sizemb = round($size / 1048576, 1);
    echo "Vendor directory: {$vendordir}\n";
    echo "  Files: {$count}\n";
    echo "  Size: {$sizemb} MB\n\n";
} else {
    echo "Vendor directory: NOT FOUND at {$vendordir}\n";
    echo "  Run 'php cli/update_vendor.php' to install bundled pylint.\n\n";
}

// Smoke test: run pylint on a trivial piece of code.
if ($status['available']) {
    echo "Smoke test: linting trivial code...\n";
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
