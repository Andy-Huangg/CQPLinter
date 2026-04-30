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
 * Unit tests for the jobe_runner class.
 *
 * These tests require a Jobe server with pylint installed to be reachable.
 * They will be skipped if Jobe is not configured or pylint is not available.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coderunner_pylint\jobe_runner
 */
class pylint_runner_test extends \advanced_testcase {

    /** @var jobe_runner */
    private jobe_runner $runner;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->runner = new jobe_runner();
        $status = $this->runner->check_availability();

        if (!$status['available']) {
            $this->markTestSkipped('Pylint via Jobe is not available: ' . $status['error']);
        }
    }

    /**
     * Test linting clean code.
     */
    public function test_lint_clean_code(): void {
        $code = "\"\"\"A simple module.\"\"\"\n\n\ndef greet(name):\n    \"\"\"Greet someone.\"\"\"\n    print(f\"Hello, {name}!\")\n";
        $result = $this->runner->lint($code);

        $this->assertInstanceOf(pylint_result::class, $result);
        $this->assertTrue($result->is_valid());
        $this->assertGreaterThan(0, $result->executiontime);
    }

    /**
     * Test linting code with errors.
     */
    public function test_lint_code_with_errors(): void {
        $code = "x = undefined_var\n";
        $result = $this->runner->lint($code);

        $this->assertTrue($result->is_valid());
        $this->assertNotEmpty($result->messages);

        $types = array_column(
            array_map(function($m) { return $m->to_array(); }, $result->messages),
            'type'
        );
        $this->assertNotEmpty($types);
    }

    /**
     * Test linting code with convention issues.
     */
    public function test_lint_code_with_conventions(): void {
        $code = "x = 1\nprint(x)\n";
        $result = $this->runner->lint($code);

        $this->assertTrue($result->is_valid());
        $conventions = $result->get_by_type('convention');
        $this->assertNotEmpty($conventions);
    }

    /**
     * Test linting empty code.
     */
    public function test_lint_empty_code(): void {
        $result = $this->runner->lint('');
        $this->assertInstanceOf(pylint_result::class, $result);
    }

    /**
     * Test availability check.
     */
    public function test_check_availability(): void {
        $status = $this->runner->check_availability();

        $this->assertTrue($status['available']);
        $this->assertNotEmpty($status['version']);
        $this->assertEmpty($status['error']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', $status['version']);
    }

    /**
     * Test that custom disable options work.
     */
    public function test_lint_with_disable_option(): void {
        $code = "x = 1\nprint(x)\n";

        $result = $this->runner->lint($code, ['disable' => 'C']);

        $this->assertTrue($result->is_valid());
        $conventions = $result->get_by_type('convention');
        $this->assertEmpty($conventions);
    }

    /**
     * Test result serialisation roundtrip.
     */
    public function test_result_serialisation(): void {
        $code = "x = 1\nprint(x)\n";
        $result = $this->runner->lint($code);

        $json = $result->to_json();
        $restored = pylint_result::from_json($json);

        $this->assertEquals(count($result->messages), count($restored->messages));
        $this->assertEquals($result->score, $restored->score);
        $this->assertEquals($result->returncode, $restored->returncode);

        for ($i = 0; $i < count($result->messages); $i++) {
            $this->assertEquals($result->messages[$i]->type, $restored->messages[$i]->type);
            $this->assertEquals($result->messages[$i]->symbol, $restored->messages[$i]->symbol);
            $this->assertEquals($result->messages[$i]->line, $restored->messages[$i]->line);
        }
    }
}
