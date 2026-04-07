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
 * Value object representing a single pylint message.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lint_message {

    /** @var string Message type: 'fatal', 'error', 'warning', 'refactor', 'convention', 'info' */
    public string $type;

    /** @var string Symbolic name, e.g. 'missing-module-docstring' */
    public string $symbol;

    /** @var string Message ID, e.g. 'C0114' */
    public string $messageid;

    /** @var string Human-readable message text */
    public string $message;

    /** @var int Line number (1-based) */
    public int $line;

    /** @var int Column number (0-based) */
    public int $column;

    /** @var int|null End line number */
    public ?int $endline;

    /** @var int|null End column number */
    public ?int $endcolumn;

    /**
     * Constructor.
     *
     * @param string $type Message severity type.
     * @param string $symbol Symbolic check name.
     * @param string $messageid Pylint message ID code.
     * @param string $message Human-readable text.
     * @param int $line Line number.
     * @param int $column Column number.
     * @param int|null $endline End line number.
     * @param int|null $endcolumn End column number.
     */
    public function __construct(
        string $type,
        string $symbol,
        string $messageid,
        string $message,
        int $line,
        int $column = 0,
        ?int $endline = null,
        ?int $endcolumn = null
    ) {
        $this->type = $type;
        $this->symbol = $symbol;
        $this->messageid = $messageid;
        $this->message = $message;
        $this->line = $line;
        $this->column = $column;
        $this->endline = $endline;
        $this->endcolumn = $endcolumn;
    }

    /**
     * Get the severity level as a numeric value for sorting.
     * Lower number = higher severity.
     *
     * @return int Severity level (0=fatal, 1=error, 2=warning, 3=refactor, 4=convention, 5=info).
     */
    public function get_severity_level(): int {
        $levels = [
            'fatal' => 0,
            'error' => 1,
            'warning' => 2,
            'refactor' => 3,
            'convention' => 4,
            'info' => 5,
        ];
        return $levels[$this->type] ?? 5;
    }

    /**
     * Get a CSS class name for this severity.
     *
     * @return string CSS class.
     */
    public function get_severity_class(): string {
        $classes = [
            'fatal' => 'pylint-error',
            'error' => 'pylint-error',
            'warning' => 'pylint-warning',
            'refactor' => 'pylint-refactor',
            'convention' => 'pylint-convention',
            'info' => 'pylint-info',
        ];
        return $classes[$this->type] ?? 'pylint-info';
    }

    /**
     * Get a human-readable severity label.
     *
     * @return string Label text.
     */
    public function get_severity_label(): string {
        $labels = [
            'fatal' => 'Fatal',
            'error' => 'Error',
            'warning' => 'Warning',
            'refactor' => 'Refactor',
            'convention' => 'Convention',
            'info' => 'Info',
        ];
        return $labels[$this->type] ?? 'Info';
    }

    /**
     * Convert to an array for template rendering.
     *
     * @return array Template data.
     */
    public function to_template_data(): array {
        return [
            'type' => $this->type,
            'symbol' => $this->symbol,
            'messageid' => $this->messageid,
            'message' => $this->message,
            'line' => $this->line,
            'column' => $this->column,
            'endline' => $this->endline,
            'endcolumn' => $this->endcolumn,
            'severityclass' => $this->get_severity_class(),
            'severitylabel' => $this->get_severity_label(),
            'severitylevel' => $this->get_severity_level(),
        ];
    }

    /**
     * Convert to a JSON-serialisable array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'type' => $this->type,
            'symbol' => $this->symbol,
            'messageid' => $this->messageid,
            'message' => $this->message,
            'line' => $this->line,
            'column' => $this->column,
            'endline' => $this->endline,
            'endcolumn' => $this->endcolumn,
        ];
    }

    /**
     * Create from an array (e.g. from cache).
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        return new self(
            $data['type'],
            $data['symbol'],
            $data['messageid'],
            $data['message'],
            $data['line'],
            $data['column'] ?? 0,
            $data['endline'] ?? null,
            $data['endcolumn'] ?? null
        );
    }
}
