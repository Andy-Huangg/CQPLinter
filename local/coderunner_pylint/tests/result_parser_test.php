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
 * Unit tests for the result_parser class.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coderunner_pylint\result_parser
 */
class result_parser_test extends \advanced_testcase {

    /**
     * Test parsing empty output.
     */
    public function test_parse_empty_output(): void {
        $result = result_parser::parse('', '', 0, 0.5);

        $this->assertInstanceOf(pylint_result::class, $result);
        $this->assertEmpty($result->messages);
        $this->assertEquals(10.0, $result->score);
        $this->assertEquals(0, $result->returncode);
    }

    /**
     * Test parsing json2 format output.
     */
    public function test_parse_json2_format(): void {
        $json = json_encode([
            'messages' => [
                [
                    'type' => 'convention',
                    'symbol' => 'missing-module-docstring',
                    'message-id' => 'C0114',
                    'message' => 'Missing module docstring',
                    'line' => 1,
                    'column' => 0,
                ],
                [
                    'type' => 'error',
                    'symbol' => 'undefined-variable',
                    'message-id' => 'E0602',
                    'message' => "Undefined variable 'x'",
                    'line' => 5,
                    'column' => 4,
                    'endLine' => 5,
                    'endColumn' => 5,
                ],
            ],
            'statistics' => [
                'score' => 6.5,
            ],
        ]);

        $result = result_parser::parse($json, '', 20, 1.2);

        $this->assertCount(2, $result->messages);
        $this->assertEquals(6.5, $result->score);
        $this->assertEquals(20, $result->returncode);
        $this->assertEquals(1.2, $result->executiontime);

        // Check first message.
        $msg = $result->messages[0];
        $this->assertEquals('convention', $msg->type);
        $this->assertEquals('missing-module-docstring', $msg->symbol);
        $this->assertEquals('C0114', $msg->messageid);
        $this->assertEquals(1, $msg->line);

        // Check second message.
        $msg = $result->messages[1];
        $this->assertEquals('error', $msg->type);
        $this->assertEquals('E0602', $msg->messageid);
        $this->assertEquals(5, $msg->line);
        $this->assertEquals(5, $msg->endline);
    }

    /**
     * Test parsing legacy json format (array of messages).
     */
    public function test_parse_legacy_json_format(): void {
        $json = json_encode([
            [
                'type' => 'warning',
                'symbol' => 'unused-variable',
                'message-id' => 'W0612',
                'message' => "Unused variable 'temp'",
                'line' => 3,
                'column' => 4,
            ],
        ]);

        $result = result_parser::parse($json);

        $this->assertCount(1, $result->messages);
        $this->assertEquals('warning', $result->messages[0]->type);
        $this->assertEquals('W0612', $result->messages[0]->messageid);
    }

    /**
     * Test parsing invalid JSON.
     */
    public function test_parse_invalid_json(): void {
        $result = result_parser::parse('this is not json', 'some error', 1, 0.5);

        $this->assertEmpty($result->messages);
        $this->assertEquals(0.0, $result->score);
        $this->assertStringContainsString('Failed to parse', $result->stderr);
    }

    /**
     * Test message type mapping from single letter codes.
     */
    public function test_single_letter_type_mapping(): void {
        $json = json_encode([
            'messages' => [
                ['type' => 'C', 'symbol' => 'test', 'message-id' => 'C0001', 'message' => 'Test', 'line' => 1, 'column' => 0],
                ['type' => 'W', 'symbol' => 'test', 'message-id' => 'W0001', 'message' => 'Test', 'line' => 2, 'column' => 0],
                ['type' => 'E', 'symbol' => 'test', 'message-id' => 'E0001', 'message' => 'Test', 'line' => 3, 'column' => 0],
                ['type' => 'F', 'symbol' => 'test', 'message-id' => 'F0001', 'message' => 'Test', 'line' => 4, 'column' => 0],
                ['type' => 'R', 'symbol' => 'test', 'message-id' => 'R0001', 'message' => 'Test', 'line' => 5, 'column' => 0],
            ],
            'statistics' => ['score' => 5.0],
        ]);

        $result = result_parser::parse($json);

        $this->assertEquals('convention', $result->messages[0]->type);
        $this->assertEquals('warning', $result->messages[1]->type);
        $this->assertEquals('error', $result->messages[2]->type);
        $this->assertEquals('fatal', $result->messages[3]->type);
        $this->assertEquals('refactor', $result->messages[4]->type);
    }

    /**
     * Test filtering by severity.
     */
    public function test_filter_by_severity(): void {
        $messages = [
            new lint_message('error', 'test', 'E0001', 'Error', 1),
            new lint_message('warning', 'test', 'W0001', 'Warning', 2),
            new lint_message('convention', 'test', 'C0001', 'Convention', 3),
            new lint_message('refactor', 'test', 'R0001', 'Refactor', 4),
        ];

        // Filter to warnings and above.
        $filtered = result_parser::filter($messages, 'warning');
        $this->assertCount(2, $filtered);
        $this->assertEquals('error', $filtered[0]->type);
        $this->assertEquals('warning', $filtered[1]->type);

        // Filter to errors only.
        $filtered = result_parser::filter($messages, 'error');
        $this->assertCount(1, $filtered);
        $this->assertEquals('error', $filtered[0]->type);

        // All messages.
        $filtered = result_parser::filter($messages, 'convention');
        $this->assertCount(4, $filtered);
    }

    /**
     * Test filtering by disabled checks.
     */
    public function test_filter_by_disabled_checks(): void {
        $messages = [
            new lint_message('error', 'undefined-variable', 'E0602', 'Error', 1),
            new lint_message('convention', 'missing-docstring', 'C0114', 'Missing docstring', 2),
        ];

        $filtered = result_parser::filter($messages, 'convention', ['C0114']);
        $this->assertCount(1, $filtered);
        $this->assertEquals('undefined-variable', $filtered[0]->symbol);

        // Filter by symbol name.
        $filtered = result_parser::filter($messages, 'convention', ['undefined-variable']);
        $this->assertCount(1, $filtered);
        $this->assertEquals('missing-docstring', $filtered[0]->symbol);
    }
}
