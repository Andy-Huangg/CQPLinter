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
 * Executes pylint on student code via the Jobe sandbox server.
 *
 * Submits a Python runner script to Jobe over HTTP. Jobe handles sandboxing,
 * timeouts, and process isolation. Requires pylint to be installed on Jobe.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobe_runner {

    /** @var int Jobe outcome code for a successful run. */
    const JOBE_OUTCOME_OK = 15;

    /** @var string Jobe REST API path for submitting runs. */
    const JOBE_RUNS_PATH = '/jobe/index.php/restapi/runs';

    /** @var string Jobe REST API path for checking a language. */
    const JOBE_LANGUAGES_PATH = '/jobe/index.php/restapi/languages';

    /** @var string Jobe base URL (with http://). */
    private string $jobeurl;

    /** @var string Optional Jobe API key. */
    private string $apikey;

    /** @var int Timeout in seconds (sent to Jobe as cputime). */
    private int $timeout;

    /** @var string Comma-separated pylint checks to disable. */
    private string $disablechecks;

    /**
     * Constructor. Reads settings from Moodle config.
     *
     * Jobe host is inherited from CodeRunner's settings unless overridden
     * in this plugin's own settings.
     */
    public function __construct() {
        $host = get_config('local_coderunner_pylint', 'jobe_host') ?: '';
        if (empty($host)) {
            $host = get_config('qtype_coderunner', 'jobe_host') ?: '';
        }

        // Normalise: ensure http:// prefix, strip trailing slash.
        if (!empty($host) && !preg_match('#^https?://#', $host)) {
            $host = 'http://' . $host;
        }
        $this->jobeurl = rtrim($host, '/');

        $this->apikey = get_config('qtype_coderunner', 'jobe_apikey') ?: '';
        $this->timeout = (int)(get_config('local_coderunner_pylint', 'timeout') ?: 10);
        $this->disablechecks = get_config('local_coderunner_pylint', 'default_disable') ?: 'import-error';
    }

    /**
     * Run pylint on a code string via Jobe.
     *
     * @param string $code The Python source code to lint.
     * @param array $options Override options: ['disable' => string].
     * @return pylint_result Structured lint result.
     */
    public function lint(string $code, array $options = []): pylint_result {
        $starttime = microtime(true);

        if (empty($this->jobeurl)) {
            return new pylint_result(
                [],
                0.0,
                -1,
                microtime(true) - $starttime,
                'Jobe server not configured. Set jobe_host in CodeRunner or plugin settings.'
            );
        }

        $disable = $options['disable'] ?? $this->disablechecks;
        $runnerscript = $this->build_runner_script($disable);

        $joberesult = $this->submit_to_jobe($runnerscript, $code);
        $executiontime = microtime(true) - $starttime;

        if ($joberesult['error'] !== '') {
            return new pylint_result([], 0.0, -1, $executiontime, $joberesult['error']);
        }

        return result_parser::parse(
            $joberesult['stdout'],
            $joberesult['stderr'],
            $joberesult['returncode'],
            $executiontime
        );
    }

    /**
     * Build the Python runner script that Jobe will execute.
     *
     * Reads student code from stdin, writes it to a temp file, runs pylint
     * via the pylint Python API, and prints JSON output to stdout.
     *
     * @param string $disable Comma-separated pylint checks to disable.
     * @return string Python source code for the runner script.
     */
    private function build_runner_script(string $disable): string {
        // Only pylint check names go here — safe to embed directly.
        $safedisable = addslashes($disable);

        return <<<PYTHON
import sys, os, io, tempfile

DISABLE = '$safedisable'

code = sys.stdin.read()
with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False) as f:
    f.write(code)
    path = f.name

try:
    from pylint.lint import Run
    from pylint.reporters.json_reporter import JSON2Reporter
    buf = io.StringIO()
    reporter = JSON2Reporter(buf)
    args = ['--output-format=json2', '--load-plugins=']
    if DISABLE:
        args.append('--disable=' + DISABLE)
    args.append(path)
    Run(args, reporter=reporter, exit=False)
    print(buf.getvalue())
except Exception as e:
    print(str(e), file=sys.stderr)
    sys.exit(1)
finally:
    try:
        os.unlink(path)
    except OSError:
        pass
PYTHON;
    }

    /**
     * Submit a job to Jobe and return the raw output.
     *
     * @param string $runnerscript Python source code to execute on Jobe.
     * @param string $studentcode Student code passed as stdin to the runner.
     * @return array{stdout: string, stderr: string, returncode: int, error: string}
     */
    private function submit_to_jobe(string $runnerscript, string $studentcode): array {
        $url = $this->jobeurl . self::JOBE_RUNS_PATH;

        $runspec = [
            'run_spec' => [
                'language_id'    => 'python3',
                'sourcecode'     => $runnerscript,
                'input'          => $studentcode,
                'parameters'     => [
                    'cputime'  => $this->timeout,
                    'numprocs' => 20,
                ],
            ],
        ];

        $payload = json_encode($runspec);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
        ];
        if (!empty($this->apikey)) {
            $headers[] = 'X-API-KEY: ' . $this->apikey;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $payload,
                'timeout'       => $this->timeout + 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [
                'stdout'     => '',
                'stderr'     => '',
                'returncode' => -1,
                'error'      => 'Failed to contact Jobe server at ' . $url,
            ];
        }

        $data = json_decode($response, true);

        if ($data === null) {
            return [
                'stdout'     => '',
                'stderr'     => '',
                'returncode' => -1,
                'error'      => 'Invalid JSON response from Jobe: ' . substr($response, 0, 200),
            ];
        }

        $outcome = $data['outcome'] ?? -1;

        if ($outcome !== self::JOBE_OUTCOME_OK) {
            $detail = $data['cmpinfo'] ?? $data['stderr'] ?? '';
            return [
                'stdout'     => '',
                'stderr'     => $detail,
                'returncode' => -1,
                'error'      => 'Jobe returned outcome ' . $outcome . ($detail ? ': ' . $detail : ''),
            ];
        }

        return [
            'stdout'     => $data['stdout'] ?? '',
            'stderr'     => $data['stderr'] ?? '',
            'returncode' => 0,
            'error'      => '',
        ];
    }

    /**
     * Check if Jobe is reachable and pylint is available on it.
     *
     * @return array{available: bool, version: string, error: string}
     */
    public function check_availability(): array {
        if (empty($this->jobeurl)) {
            return [
                'available' => false,
                'version'   => '',
                'error'     => 'Jobe server not configured.',
            ];
        }

        $versionscript = <<<'PYTHON'
try:
    import pylint
    print(pylint.__version__)
except ImportError:
    print('pylint not installed', end='')
    import sys
    sys.exit(1)
PYTHON;

        $result = $this->submit_to_jobe($versionscript, '');

        if ($result['error'] !== '') {
            return ['available' => false, 'version' => '', 'error' => $result['error']];
        }

        $version = trim($result['stdout']);
        if (empty($version)) {
            return ['available' => false, 'version' => '', 'error' => 'pylint not installed on Jobe server.'];
        }

        return ['available' => true, 'version' => $version, 'error' => ''];
    }
}
