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
 * Security utilities for safe pylint execution.
 *
 * Handles temp file creation, command building, and cleanup.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security {

    /** @var int Maximum code size in bytes (default 50KB). */
    const DEFAULT_MAX_CODE_SIZE = 50000;

    /** @var int Maximum output read size in bytes (1MB). */
    const MAX_OUTPUT_SIZE = 1048576;

    /**
     * Create a secure temporary directory for pylint execution.
     *
     * Uses Moodle's make_request_directory() for automatic cleanup.
     *
     * @return string Path to the created directory.
     * @throws \moodle_exception If directory creation fails.
     */
    public static function create_sandbox(): string {
        $dir = make_request_directory();
        return $dir;
    }

    /**
     * Write student code to a temporary file safely.
     *
     * Validates code size, checks for null bytes, and ensures valid UTF-8.
     *
     * @param string $sandboxdir Path to the sandbox directory.
     * @param string $code The Python source code.
     * @param int $maxsize Maximum allowed code size in bytes.
     * @return string Path to the created temp file.
     * @throws \moodle_exception If validation fails or write fails.
     */
    public static function write_temp_file(string $sandboxdir, string $code, int $maxsize = 0): string {
        if ($maxsize <= 0) {
            $maxsize = get_config('local_coderunner_pylint', 'max_code_size') ?: self::DEFAULT_MAX_CODE_SIZE;
        }

        // Validate code size.
        if (strlen($code) > $maxsize) {
            throw new \moodle_exception('codetoolargeforanalysis', 'local_coderunner_pylint');
        }

        // Check for null bytes (binary content).
        if (strpos($code, "\0") !== false) {
            throw new \moodle_exception('invalidcodesubmission', 'local_coderunner_pylint');
        }

        // Ensure valid UTF-8.
        if (!mb_check_encoding($code, 'UTF-8')) {
            throw new \moodle_exception('invalidcodesubmission', 'local_coderunner_pylint');
        }

        $filepath = $sandboxdir . '/student_code.py';
        $written = file_put_contents($filepath, $code);

        if ($written === false) {
            throw new \moodle_exception('tempfilewritefailed', 'local_coderunner_pylint');
        }

        return $filepath;
    }

    /**
     * Build the environment variables for safe pylint execution.
     *
     * @param string $vendorpath Path to the vendor/python directory (for bundled pylint).
     * @return array Environment variables array.
     */
    public static function build_environment(string $vendorpath = ''): array {
        $env = [];

        // Prevent .pyc file creation in the sandbox.
        $env['PYTHONDONTWRITEBYTECODE'] = '1';

        // Set PYTHONPATH to vendor directory if using bundled pylint.
        if (!empty($vendorpath) && is_dir($vendorpath)) {
            $env['PYTHONPATH'] = $vendorpath;
        } else {
            // Clear PYTHONPATH to prevent loading from unexpected locations.
            $env['PYTHONPATH'] = '';
        }

        // Disable Python user site-packages.
        $env['PYTHONNOUSERSITE'] = '1';

        // Ensure consistent encoding.
        $env['PYTHONIOENCODING'] = 'utf-8';

        // Prevent HOME-based config files from being loaded.
        $env['PYLINTHOME'] = sys_get_temp_dir();

        return $env;
    }

    /**
     * Build the pylint command arguments.
     *
     * @param string $pythonpath Path to the Python interpreter.
     * @param string $filepath Path to the file to lint.
     * @param string $pylintpath Optional path to a specific pylint binary (overrides bundled).
     * @param string $vendorpath Path to the vendor/python directory.
     * @param array $extraargs Additional pylint arguments.
     * @return array Command as an array suitable for proc_open.
     */
    public static function build_command(
        string $pythonpath,
        string $filepath,
        string $pylintpath = '',
        string $vendorpath = '',
        array $extraargs = []
    ): array {
        $cmd = [];

        if (!empty($pylintpath)) {
            // Use a specific pylint binary.
            $cmd[] = $pylintpath;
        } else {
            // Use python -m pylint with bundled vendor.
            $cmd[] = $pythonpath;
            $cmd[] = '-m';
            $cmd[] = 'pylint';
        }

        // Always output JSON for structured parsing.
        $cmd[] = '--output-format=json2';

        // Disable all pylint plugins to prevent code execution via plugins.
        $cmd[] = '--load-plugins=';

        // Disable import-error by default (student code won't have installed packages).
        $cmd[] = '--disable=import-error';

        // Add any extra arguments (e.g. --rcfile, --disable).
        foreach ($extraargs as $arg) {
            $cmd[] = $arg;
        }

        // The file to lint — always last.
        $cmd[] = $filepath;

        return $cmd;
    }

    /**
     * Validate that a pylint binary path is safe to use.
     *
     * @param string $path Path to validate.
     * @return bool True if the path is valid and executable.
     */
    public static function validate_pylint_path(string $path): bool {
        if (empty($path)) {
            return false;
        }

        // Must be an absolute path.
        if ($path[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return false;
        }

        // Must exist and be executable.
        if (!file_exists($path) || !is_executable($path)) {
            return false;
        }

        return true;
    }

    /**
     * Validate a Python interpreter path.
     *
     * @param string $path Path to the Python interpreter.
     * @return bool True if valid and executable.
     */
    public static function validate_python_path(string $path): bool {
        if (empty($path)) {
            return false;
        }

        // Allow bare command name (e.g. 'python3') — resolved via PATH.
        if (strpos($path, '/') === false && strpos($path, '\\') === false) {
            return true;
        }

        // If it's a full path, check it exists and is executable.
        return file_exists($path) && is_executable($path);
    }

    /**
     * Clean up temporary files.
     *
     * Moodle's make_request_directory() handles this automatically,
     * but we also explicitly clean up as a safety measure.
     *
     * @param string $filepath Path to the temp file to remove.
     */
    public static function cleanup(string $filepath): void {
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }
}
