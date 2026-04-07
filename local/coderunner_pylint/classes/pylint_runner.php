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
 * Executes pylint on student code via proc_open.
 *
 * Uses the bundled vendor/python pylint by default, or a system-installed pylint
 * if configured by the admin. All execution is sandboxed with timeouts.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pylint_runner {

    /** @var int Default timeout in seconds. */
    const DEFAULT_TIMEOUT = 10;

    /** @var string Path to Python interpreter. */
    private string $pythonpath;

    /** @var string Path to pylint binary (empty = use bundled via python -m pylint). */
    private string $pylintpath;

    /** @var string Path to vendor/python directory. */
    private string $vendorpath;

    /** @var int Timeout in seconds. */
    private int $timeout;

    /** @var string Path to pylintrc file (empty = no custom rc). */
    private string $rcfile;

    /** @var string Comma-separated list of checks to disable. */
    private string $disablechecks;

    /**
     * Constructor. Reads settings from Moodle config if not provided.
     *
     * @param string|null $pythonpath Python interpreter path.
     * @param string|null $pylintpath Pylint binary path (empty = bundled).
     * @param int|null $timeout Timeout in seconds.
     * @param string|null $rcfile Path to pylintrc file.
     * @param string|null $disablechecks Comma-separated checks to disable.
     */
    public function __construct(
        ?string $pythonpath = null,
        ?string $pylintpath = null,
        ?int $timeout = null,
        ?string $rcfile = null,
        ?string $disablechecks = null
    ) {
        $this->pythonpath = $pythonpath ?? get_config('local_coderunner_pylint', 'python_path') ?: 'python3';
        $this->pylintpath = $pylintpath ?? get_config('local_coderunner_pylint', 'pylint_path') ?: '';
        $this->timeout = $timeout ?? (int)(get_config('local_coderunner_pylint', 'timeout') ?: self::DEFAULT_TIMEOUT);
        $this->rcfile = $rcfile ?? get_config('local_coderunner_pylint', 'pylintrc_path') ?: '';
        $this->disablechecks = $disablechecks ?? get_config('local_coderunner_pylint', 'default_disable') ?: 'import-error';

        // Resolve vendor path relative to plugin directory.
        $this->vendorpath = __DIR__ . '/../vendor/python';
    }

    /**
     * Run pylint on a code string.
     *
     * @param string $code The Python source code to lint.
     * @param array $options Override options: ['disable' => string, 'rcfile' => string].
     * @return pylint_result Structured lint result.
     */
    public function lint(string $code, array $options = []): pylint_result {
        $starttime = microtime(true);

        // Create sandbox and write code to temp file.
        try {
            $sandboxdir = security::create_sandbox();
            $filepath = security::write_temp_file($sandboxdir, $code);
        } catch (\moodle_exception $e) {
            return new pylint_result(
                [],
                0.0,
                -1,
                microtime(true) - $starttime,
                $e->getMessage()
            );
        }

        try {
            // Build extra arguments.
            $extraargs = [];

            // Custom rcfile.
            $rcfile = $options['rcfile'] ?? $this->rcfile;
            if (!empty($rcfile) && file_exists($rcfile)) {
                $extraargs[] = '--rcfile=' . $rcfile;
            }

            // Disabled checks — merge defaults with per-question overrides.
            $disable = $options['disable'] ?? $this->disablechecks;
            if (!empty($disable)) {
                $extraargs[] = '--disable=' . $disable;
            }

            // Build the command.
            $cmd = security::build_command(
                $this->pythonpath,
                $filepath,
                $this->pylintpath,
                $this->vendorpath,
                $extraargs
            );

            // Build the environment.
            $env = security::build_environment($this->vendorpath);

            // Execute pylint.
            $result = $this->execute($cmd, $env);

            $executiontime = microtime(true) - $starttime;

            // Parse the output.
            return result_parser::parse(
                $result['stdout'],
                $result['stderr'],
                $result['returncode'],
                $executiontime
            );
        } finally {
            // Always clean up the temp file.
            security::cleanup($filepath);
        }
    }

    /**
     * Execute a command via proc_open with timeout enforcement.
     *
     * @param array $cmd Command as an array (no shell interpolation).
     * @param array $env Environment variables.
     * @return array{stdout: string, stderr: string, returncode: int}
     */
    private function execute(array $cmd, array $env): array {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            return [
                'stdout' => '',
                'stderr' => 'Failed to start pylint process',
                'returncode' => -1,
            ];
        }

        // Close stdin — pylint doesn't need input.
        fclose($pipes[0]);

        // Read stdout and stderr with timeout.
        $stdout = '';
        $stderr = '';
        $deadline = time() + $this->timeout;

        // Set streams to non-blocking.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $remaining = $deadline - time();
            if ($remaining <= 0) {
                // Timeout — kill the process.
                proc_terminate($process, 9); // SIGKILL.
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [
                    'stdout' => $stdout,
                    'stderr' => 'Pylint execution timed out after ' . $this->timeout . ' seconds',
                    'returncode' => -1,
                ];
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, min($remaining, 1));

            if ($ready === false) {
                break;
            }

            if ($ready > 0) {
                foreach ($read as $pipe) {
                    $data = fread($pipe, 8192);
                    if ($data !== false && $data !== '') {
                        if ($pipe === $pipes[1]) {
                            $stdout .= $data;
                            // Enforce max output size.
                            if (strlen($stdout) > security::MAX_OUTPUT_SIZE) {
                                proc_terminate($process, 9);
                                fclose($pipes[1]);
                                fclose($pipes[2]);
                                proc_close($process);

                                return [
                                    'stdout' => substr($stdout, 0, security::MAX_OUTPUT_SIZE),
                                    'stderr' => 'Pylint output exceeded maximum size',
                                    'returncode' => -1,
                                ];
                            }
                        } else {
                            $stderr .= $data;
                        }
                    }
                }
            }

            // Check if both pipes are at EOF.
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $returncode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'returncode' => $returncode,
        ];
    }

    /**
     * Check if pylint is available and working.
     *
     * @return array{available: bool, version: string, error: string}
     */
    public function check_availability(): array {
        $cmd = [];

        if (!empty($this->pylintpath)) {
            if (!security::validate_pylint_path($this->pylintpath)) {
                return [
                    'available' => false,
                    'version' => '',
                    'error' => 'Configured pylint path is not valid or not executable: ' . $this->pylintpath,
                ];
            }
            $cmd = [$this->pylintpath, '--version'];
        } else {
            $cmd = [$this->pythonpath, '-m', 'pylint', '--version'];
        }

        $env = security::build_environment($this->vendorpath);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            return [
                'available' => false,
                'version' => '',
                'error' => 'Failed to start process. Check python_path setting.',
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returncode = proc_close($process);

        if ($returncode !== 0) {
            return [
                'available' => false,
                'version' => '',
                'error' => trim($stderr ?: $stdout ?: 'Unknown error (exit code ' . $returncode . ')'),
            ];
        }

        // Extract version from output like "pylint 3.3.6\n...".
        $version = '';
        if (preg_match('/pylint\s+([\d.]+)/', $stdout, $matches)) {
            $version = $matches[1];
        }

        return [
            'available' => true,
            'version' => $version,
            'error' => '',
        ];
    }
}
