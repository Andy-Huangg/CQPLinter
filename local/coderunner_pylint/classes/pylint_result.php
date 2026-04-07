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
 * Value object representing the result of a pylint run.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pylint_result {

    /** @var lint_message[] Array of lint messages. */
    public array $messages;

    /** @var float Pylint score (0.0-10.0). */
    public float $score;

    /** @var int Pylint exit code. */
    public int $returncode;

    /** @var float Execution time in seconds. */
    public float $executiontime;

    /** @var string Any error output from pylint. */
    public string $stderr;

    /**
     * Constructor.
     *
     * @param lint_message[] $messages Lint messages.
     * @param float $score Pylint score.
     * @param int $returncode Exit code.
     * @param float $executiontime Time taken.
     * @param string $stderr Error output.
     */
    public function __construct(
        array $messages = [],
        float $score = 10.0,
        int $returncode = 0,
        float $executiontime = 0.0,
        string $stderr = ''
    ) {
        $this->messages = $messages;
        $this->score = $score;
        $this->returncode = $returncode;
        $this->executiontime = $executiontime;
        $this->stderr = $stderr;
    }

    /**
     * Check if there are any error-level messages.
     *
     * @return bool
     */
    public function has_errors(): bool {
        foreach ($this->messages as $msg) {
            if ($msg->type === 'error' || $msg->type === 'fatal') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if there are any warning-level messages.
     *
     * @return bool
     */
    public function has_warnings(): bool {
        foreach ($this->messages as $msg) {
            if ($msg->type === 'warning') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get messages filtered by severity type.
     *
     * @param string $type One of: fatal, error, warning, refactor, convention, info.
     * @return lint_message[]
     */
    public function get_by_type(string $type): array {
        return array_values(array_filter($this->messages, function($msg) use ($type) {
            return $msg->type === $type;
        }));
    }

    /**
     * Get messages filtered to minimum severity level.
     *
     * @param string $minseverity Minimum severity: convention, refactor, warning, error, fatal.
     * @return lint_message[]
     */
    public function get_filtered(string $minseverity = 'convention'): array {
        $levels = [
            'fatal' => 0,
            'error' => 1,
            'warning' => 2,
            'refactor' => 3,
            'convention' => 4,
            'info' => 5,
        ];
        $threshold = $levels[$minseverity] ?? 4;

        return array_values(array_filter($this->messages, function($msg) use ($threshold) {
            return $msg->get_severity_level() <= $threshold;
        }));
    }

    /**
     * Get messages grouped by severity type, sorted by severity.
     *
     * @param string $minseverity Minimum severity to include.
     * @return array Keyed by type, each containing an array of lint_message objects.
     */
    public function get_grouped(string $minseverity = 'convention'): array {
        $filtered = $this->get_filtered($minseverity);
        $groups = [];

        foreach ($filtered as $msg) {
            $groups[$msg->type][] = $msg;
        }

        // Sort groups by severity.
        $order = ['fatal', 'error', 'warning', 'refactor', 'convention', 'info'];
        $sorted = [];
        foreach ($order as $type) {
            if (isset($groups[$type])) {
                $sorted[$type] = $groups[$type];
            }
        }

        return $sorted;
    }

    /**
     * Get the total count of messages at or above a minimum severity.
     *
     * @param string $minseverity Minimum severity.
     * @return int
     */
    public function count_filtered(string $minseverity = 'convention'): int {
        return count($this->get_filtered($minseverity));
    }

    /**
     * Whether the pylint run completed successfully (even if issues were found).
     *
     * @return bool
     */
    public function is_valid(): bool {
        // Pylint returns bit-encoded exit codes:
        // 0 = no issues, 1 = fatal, 2 = error, 4 = warning, 8 = refactor, 16 = convention, 32 = usage error.
        // Anything with bit 1 (fatal) or 32 (usage error) is a problem.
        return ($this->returncode & 32) === 0;
    }

    /**
     * Convert to a JSON string for caching.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode($this->to_array());
    }

    /**
     * Convert to an array for serialisation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'messages' => array_map(function($msg) {
                return $msg->to_array();
            }, $this->messages),
            'score' => $this->score,
            'returncode' => $this->returncode,
            'executiontime' => $this->executiontime,
            'stderr' => $this->stderr,
        ];
    }

    /**
     * Create from a JSON string (e.g. from cache).
     *
     * @param string $json
     * @return self
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, true);
        if ($data === null) {
            return new self([], 0.0, -1, 0.0, 'Failed to decode cached result');
        }
        return self::from_array($data);
    }

    /**
     * Create from an array.
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        $messages = [];
        foreach (($data['messages'] ?? []) as $msgdata) {
            $messages[] = lint_message::from_array($msgdata);
        }

        return new self(
            $messages,
            (float)($data['score'] ?? 10.0),
            (int)($data['returncode'] ?? 0),
            (float)($data['executiontime'] ?? 0.0),
            $data['stderr'] ?? ''
        );
    }
}
