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
 * CLI script to vendor (install) pylint and its dependencies into the plugin.
 *
 * Usage: php update_vendor.php [--python=/path/to/python3] [--clean]
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// We may be running outside of Moodle (e.g. during plugin packaging).
// Try to load Moodle config, but proceed without it if unavailable.
$moodleroot = realpath(__DIR__ . '/../../../../config.php');
if ($moodleroot && file_exists($moodleroot)) {
    require($moodleroot);
    require_once($CFG->libdir . '/clilib.php');
    $usemoodlecli = true;
} else {
    $usemoodlecli = false;
}

// Parse arguments.
$options = getopt('', ['python:', 'clean', 'help']);

if (isset($options['help'])) {
    echo "Vendor pylint and dependencies into the plugin.\n\n";
    echo "Usage: php update_vendor.php [--python=/path/to/python3] [--clean]\n\n";
    echo "Options:\n";
    echo "  --python=PATH    Path to Python 3 interpreter (default: python3)\n";
    echo "  --clean          Remove existing vendor/python before installing\n";
    echo "  --help           Show this help\n";
    exit(0);
}

$pythonpath = $options['python'] ?? 'python3';
$clean = isset($options['clean']);

$plugindir = realpath(__DIR__ . '/..');
$vendordir = $plugindir . '/vendor/python';
$requirementsfile = $plugindir . '/requirements.txt';

echo "CodeRunner Pylint - Vendor Update\n";
echo "==================================\n\n";

// Check Python is available.
echo "Checking Python interpreter: {$pythonpath}\n";
$output = [];
$returncode = 0;
exec(escapeshellarg($pythonpath) . ' --version 2>&1', $output, $returncode);

if ($returncode !== 0) {
    echo "ERROR: Python interpreter not found at '{$pythonpath}'.\n";
    echo "Please install Python 3 or specify the path with --python=/path/to/python3\n";
    exit(1);
}
echo "  Found: " . implode(' ', $output) . "\n\n";

// Check pip is available.
echo "Checking pip availability...\n";
$output = [];
exec(escapeshellarg($pythonpath) . ' -m pip --version 2>&1', $output, $returncode);

if ($returncode !== 0) {
    echo "ERROR: pip is not available. Please install pip for Python 3.\n";
    exit(1);
}
echo "  Found: " . implode(' ', $output) . "\n\n";

// Check requirements file exists.
if (!file_exists($requirementsfile)) {
    echo "ERROR: requirements.txt not found at {$requirementsfile}\n";
    exit(1);
}

echo "Requirements file: {$requirementsfile}\n";
echo "  Contents: " . trim(file_get_contents($requirementsfile)) . "\n\n";

// Clean existing vendor if requested.
if ($clean && is_dir($vendordir)) {
    echo "Cleaning existing vendor directory...\n";
    // Use a recursive delete.
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vendordir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($vendordir);
    echo "  Done.\n\n";
}

// Create vendor directory.
if (!is_dir($vendordir)) {
    mkdir($vendordir, 0755, true);
}

// Install pylint and dependencies.
echo "Installing pylint into vendor/python...\n";
$cmd = escapeshellarg($pythonpath)
    . ' -m pip install'
    . ' --target=' . escapeshellarg($vendordir)
    . ' -r ' . escapeshellarg($requirementsfile)
    . ' --no-compile'
    . ' --no-user'
    . ' 2>&1';

$output = [];
exec($cmd, $output, $returncode);

echo implode("\n", $output) . "\n\n";

if ($returncode !== 0) {
    echo "ERROR: pip install failed with exit code {$returncode}.\n";
    exit(1);
}

// Clean up unnecessary files to reduce size.
echo "Cleaning up unnecessary files...\n";

$patternstoremove = [
    '__pycache__',
    '*.pyc',
    '*.pyo',
];

$removedcount = 0;

// Remove __pycache__ directories.
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($vendordir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $file) {
    $basename = $file->getBasename();
    $pathname = $file->getRealPath();

    // Remove __pycache__ dirs.
    if ($file->isDir() && $basename === '__pycache__') {
        $subiterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pathname, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($subiterator as $subfile) {
            if ($subfile->isDir()) {
                @rmdir($subfile->getRealPath());
            } else {
                @unlink($subfile->getRealPath());
            }
        }
        @rmdir($pathname);
        $removedcount++;
        continue;
    }

    // Remove .pyc/.pyo files.
    if ($file->isFile() && preg_match('/\.(pyc|pyo)$/', $basename)) {
        @unlink($pathname);
        $removedcount++;
    }
}

echo "  Removed {$removedcount} unnecessary items.\n\n";

// Verify installation.
echo "Verifying installation...\n";
$output = [];
$verifycmd = 'PYTHONPATH=' . escapeshellarg($vendordir) . ' '
    . escapeshellarg($pythonpath) . ' -m pylint --version 2>&1';
exec($verifycmd, $output, $returncode);

if ($returncode !== 0) {
    echo "ERROR: Verification failed. pylint may not be properly installed.\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "  " . implode("\n  ", $output) . "\n\n";

// Report size.
$totalsize = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($vendordir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $totalsize += $file->getSize();
    }
}

$sizemb = round($totalsize / 1048576, 1);
echo "Vendor directory size: {$sizemb} MB\n";
echo "\nDone! Pylint has been vendored into: {$vendordir}\n";
