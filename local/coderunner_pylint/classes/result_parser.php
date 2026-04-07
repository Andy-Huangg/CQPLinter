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
 * Parses pylint JSON output into structured result objects.
 *
 * Handles both json2 format (pylint 2.15+) and legacy json format.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result_parser {

    /**
     * Parse pylint JSON output into a pylint_result object.
     *
     * @param string $stdout Raw stdout from pylint (JSON format).
     * @param string $stderr Raw stderr from pylint.
     * @param int $returncode Pylint exit code.
     * @param float $executiontime Time taken in seconds.
     * @return pylint_result Parsed result.
     */
    public static function parse(
        string $stdout,
        string $stderr = '',
        int $returncode = 0,
        float $executiontime = 0.0
    ): pylint_result {
        $stdout = trim($stdout);

        if (empty($stdout)) {
            return new pylint_result([], 10.0, $returncode, $executiontime, $stderr);
        }

        $data = json_decode($stdout, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            // JSON parse failed — return error result.
            return new pylint_result(
                [],
                0.0,
                $returncode,
                $executiontime,
                'Failed to parse pylint output: ' . json_last_error_msg() . "\n" . $stderr
            );
        }

        // Detect format: json2 has 'messages' and 'statistics' keys at top level.
        if (isset($data['messages'])) {
            return self::parse_json2($data, $returncode, $executiontime, $stderr);
        }

        // Legacy json format: top-level array of message objects.
        if (is_array($data) && (empty($data) || isset($data[0]))) {
            return self::parse_legacy_json($data, $returncode, $executiontime, $stderr);
        }

        // Unknown format.
        return new pylint_result([], 0.0, $returncode, $executiontime, 'Unknown pylint output format');
    }

    /**
     * Parse json2 format output (pylint 2.15+).
     *
     * @param array $data Decoded JSON data.
     * @param int $returncode Exit code.
     * @param float $executiontime Execution time.
     * @param string $stderr Error output.
     * @return pylint_result
     */
    private static function parse_json2(
        array $data,
        int $returncode,
        float $executiontime,
        string $stderr
    ): pylint_result {
        $messages = [];

        foreach (($data['messages'] ?? []) as $item) {
            $messages[] = self::parse_message($item);
        }

        // Extract score from statistics if available.
        $score = 10.0;
        if (isset($data['statistics']['score'])) {
            $score = (float)$data['statistics']['score'];
        }

        return new pylint_result($messages, $score, $returncode, $executiontime, $stderr);
    }

    /**
     * Parse legacy JSON format output (array of message objects).
     *
     * @param array $data Array of message objects.
     * @param int $returncode Exit code.
     * @param float $executiontime Execution time.
     * @param string $stderr Error output.
     * @return pylint_result
     */
    private static function parse_legacy_json(
        array $data,
        int $returncode,
        float $executiontime,
        string $stderr
    ): pylint_result {
        $messages = [];

        foreach ($data as $item) {
            if (is_array($item)) {
                $messages[] = self::parse_message($item);
            }
        }

        // Legacy format doesn't include score; estimate from messages.
        $score = self::estimate_score($messages);

        return new pylint_result($messages, $score, $returncode, $executiontime, $stderr);
    }

    /**
     * Parse a single message item from pylint JSON output.
     *
     * @param array $item Message data from pylint.
     * @return lint_message
     */
    private static function parse_message(array $item): lint_message {
        // Map pylint type codes to type names.
        $typemap = [
            'F' => 'fatal',
            'E' => 'error',
            'W' => 'warning',
            'R' => 'refactor',
            'C' => 'convention',
            'I' => 'info',
        ];

        $type = $item['type'] ?? 'info';

        // If type is a single letter code, map it.
        if (strlen($type) === 1 && isset($typemap[$type])) {
            $type = $typemap[$type];
        }

        // Normalise type to lowercase.
        $type = strtolower($type);

        return new lint_message(
            $type,
            $item['symbol'] ?? $item['message-id'] ?? '',
            $item['message-id'] ?? $item['messageId'] ?? '',
            $item['message'] ?? '',
            (int)($item['line'] ?? 0),
            (int)($item['column'] ?? 0),
            isset($item['endLine']) ? (int)$item['endLine'] : (isset($item['end_line']) ? (int)$item['end_line'] : null),
            isset($item['endColumn']) ? (int)$item['endColumn'] : (isset($item['end_column']) ? (int)$item['end_column'] : null)
        );
    }

    /**
     * Estimate a pylint-like score from messages when not provided.
     *
     * Uses the same formula as pylint: 10 - (5 * errors + warnings + refactor + convention) / statements * 10.
     * Since we don't know statements, we use a simplified heuristic.
     *
     * @param lint_message[] $messages
     * @return float Estimated score (0.0 to 10.0).
     */
    private static function estimate_score(array $messages): float {
        if (empty($messages)) {
            return 10.0;
        }

        $penalty = 0.0;
        foreach ($messages as $msg) {
            switch ($msg->type) {
                case 'fatal':
                case 'error':
                    $penalty += 2.0;
                    break;
                case 'warning':
                    $penalty += 1.0;
                    break;
                case 'refactor':
                case 'convention':
                    $penalty += 0.5;
                    break;
            }
        }

        return max(0.0, min(10.0, 10.0 - $penalty));
    }

    /**
     * Filter messages based on configuration.
     *
     * @param lint_message[] $messages Messages to filter.
     * @param string $minseverity Minimum severity to include.
     * @param string[] $disabledchecks List of symbol names or message IDs to exclude.
     * @return lint_message[]
     */
    public static function filter(array $messages, string $minseverity = 'convention', array $disabledchecks = []): array {
        $levels = [
            'fatal' => 0,
            'error' => 1,
            'warning' => 2,
            'refactor' => 3,
            'convention' => 4,
            'info' => 5,
        ];
        $threshold = $levels[$minseverity] ?? 4;

        return array_values(array_filter($messages, function($msg) use ($threshold, $disabledchecks) {
            // Filter by severity.
            if ($msg->get_severity_level() > $threshold) {
                return false;
            }

            // Filter by disabled checks.
            if (!empty($disabledchecks)) {
                if (in_array($msg->symbol, $disabledchecks) || in_array($msg->messageid, $disabledchecks)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
