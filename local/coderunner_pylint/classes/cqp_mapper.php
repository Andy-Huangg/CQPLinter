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
 * Maps pylint messages to Code Quality Principles (CQP).
 *
 * Each of the 8 CQP principles captures a language-neutral aspect of code quality.
 * This class maps pylint symbol names to the most relevant CQP principle, providing
 * the principle name and a short guideline explanation for student feedback.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cqp_mapper {

    /** @var array CQP principle definitions keyed by number. */
    const PRINCIPLES = [
        1 => [
            'name' => 'Clear Presentation',
            'short' => 'Different elements are easy to recognise and distinguish and the relationships between them are apparent.',
            'guideline' => 'Formatting (spacing, indentation, brackets, line length) should make it easy to distinguish separate elements.',
        ],
        2 => [
            'name' => 'Explanatory Language',
            'short' => 'The rationale, intent and meaning of code is explicit.',
            'guideline' => 'Names should be descriptive, comments should clarify intent, and symbolic constants should convey meaning of literals.',
        ],
        3 => [
            'name' => 'Consistent Code',
            'short' => 'Elements that are similar in nature are presented and used in a similar way.',
            'guideline' => 'Be consistent in documentation, notation, and implementation choices throughout your code.',
        ],
        4 => [
            'name' => 'Used Content',
            'short' => 'All elements that are introduced are meaningfully used.',
            'guideline' => 'All code constructs should be needed. Remove code that is executed but does not affect functionality.',
        ],
        5 => [
            'name' => 'Simple Constructs',
            'short' => 'Coding constructs are selected to minimise complexity for the intended reader.',
            'guideline' => 'Minimise nesting, keep control flow easy to follow, and keep expressions appropriately simple.',
        ],
        6 => [
            'name' => 'Minimal Duplication',
            'short' => 'Code repetition is avoided.',
            'guideline' => 'Consolidate repeated groups of statements; replace adjacent repeated statements with a loop.',
        ],
        7 => [
            'name' => 'Modular Structure',
            'short' => 'Related code is grouped together and dependencies between groups minimised.',
            'guideline' => 'Functions should implement a single task. Minimise scope of variables. Organise related elements together.',
        ],
        8 => [
            'name' => 'Problem Alignment',
            'short' => 'Implementation choices are consistent with the problem to be solved.',
            'guideline' => 'Data structures should align with the data represented. Algorithms should reflect the process they represent.',
        ],
    ];

    /**
     * @var array Maps pylint symbols to CQP principle numbers.
     *
     * Symbols not in this map are assigned a principle based on their pylint category
     * (convention, refactor, etc.) via get_default_principle().
     */
    const SYMBOL_MAP = [
        // CQP 1: Clear Presentation — formatting, layout, whitespace.
        'line-too-long' => 1,
        'trailing-whitespace' => 1,
        'missing-final-newline' => 1,
        'mixed-indentation' => 1,
        'bad-indentation' => 1,
        'bad-whitespace' => 1,
        'multiple-statements' => 1,
        'superfluous-parens' => 1,
        'trailing-newlines' => 1,
        'unexpected-line-ending-format' => 1,
        'mixed-line-endings' => 1,

        // CQP 2: Explanatory Language — naming, documentation, magic numbers.
        'missing-module-docstring' => 2,
        'missing-function-docstring' => 2,
        'missing-class-docstring' => 2,
        'invalid-name' => 2,
        'disallowed-name' => 2,
        'empty-docstring' => 2,
        'wrong-spelling-in-comment' => 2,
        'wrong-spelling-in-docstring' => 2,
        'non-ascii-name' => 2,
        'single-char-type-var' => 2,
        'typevar-name-incorrect-variance' => 2,
        'magic-value-comparison' => 2,

        // CQP 3: Consistent Code — consistency in style and approach.
        'inconsistent-return-statements' => 3,
        'inconsistent-quotes' => 3,
        'consider-using-f-string' => 3,
        'use-implicit-booleaness-not-comparison-to-zero' => 3,
        'use-implicit-booleaness-not-comparison-to-string' => 3,
        'use-implicit-booleaness-not-comparison' => 3,

        // CQP 4: Used Content — unused code, unreachable code.
        'unused-import' => 4,
        'unused-variable' => 4,
        'unused-argument' => 4,
        'unused-wildcard-import' => 4,
        'unreachable' => 4,
        'pointless-statement' => 4,
        'pointless-string-statement' => 4,
        'unnecessary-pass' => 4,
        'unnecessary-semicolon' => 4,
        'unnecessary-lambda' => 4,
        'unnecessary-lambda-assignment' => 4,
        'unnecessary-comprehension' => 4,
        'self-assigning-variable' => 4,
        'unnecessary-ellipsis' => 4,
        'using-constant-test' => 4,
        'comparison-with-itself' => 4,
        'confusing-with-statement' => 4,
        'reimported' => 4,
        'import-self' => 4,

        // CQP 5: Simple Constructs — complexity, nesting, control flow.
        'too-many-branches' => 5,
        'too-many-nested-blocks' => 5,
        'too-many-return-statements' => 5,
        'too-complex' => 5,
        'too-many-boolean-expressions' => 5,
        'simplifiable-if-statement' => 5,
        'simplifiable-if-expression' => 5,
        'no-else-return' => 5,
        'no-else-raise' => 5,
        'no-else-continue' => 5,
        'no-else-break' => 5,
        'consider-using-in' => 5,
        'consider-using-min-builtin' => 5,
        'consider-using-max-builtin' => 5,
        'consider-using-with' => 5,
        'consider-using-join' => 5,
        'consider-using-ternary' => 5,
        'chained-comparison' => 5,
        'consider-merging-isinstance' => 5,
        'too-many-statements' => 5,
        'consider-using-generator' => 5,
        'use-a-generator' => 5,
        'consider-using-any-or-all' => 5,
        'unnecessary-negation' => 5,
        'consider-iterating-dictionary' => 5,

        // CQP 6: Minimal Duplication — duplicated code.
        'duplicate-code' => 6,

        // CQP 7: Modular Structure — function size, arguments, scope.
        'too-many-arguments' => 7,
        'too-many-locals' => 7,
        'too-many-lines' => 7,
        'too-many-instance-attributes' => 7,
        'too-many-public-methods' => 7,
        'too-few-public-methods' => 7,
        'import-outside-toplevel' => 7,
        'too-many-ancestors' => 7,
        'global-statement' => 7,
        'global-variable-not-assigned' => 7,
        'global-variable-undefined' => 7,
        'global-at-module-level' => 7,
        'cyclic-import' => 7,
        'wildcard-import' => 7,

        // CQP 8: Problem Alignment — idiomatic constructs, data structure choice.
        'consider-using-enumerate' => 8,
        'consider-using-dict-items' => 8,
        'consider-using-dict-comprehension' => 8,
        'consider-using-set-comprehension' => 8,
        'consider-using-namedtuple-or-dataclass' => 8,
        'use-list-literal' => 8,
        'use-dict-literal' => 8,
        'use-sequence-for-iteration' => 8,
        'consider-using-from-import' => 8,
        'unidiomatic-typecheck' => 8,
        'consider-using-assignment-expr' => 8,
    ];

    /**
     * Get the CQP principle for a given pylint message.
     *
     * @param lint_message $msg The pylint lint message.
     * @return array{number: int, name: string, short: string, guideline: string}
     */
    public static function get_principle(lint_message $msg): array {
        $number = self::SYMBOL_MAP[$msg->symbol] ?? self::get_default_principle($msg->type);
        $principle = self::PRINCIPLES[$number];
        return array_merge(['number' => $number], $principle);
    }

    /**
     * Get a default CQP principle based on pylint message type when no specific mapping exists.
     *
     * @param string $type The pylint message type (error, warning, convention, refactor, etc.)
     * @return int CQP principle number.
     */
    private static function get_default_principle(string $type): int {
        switch ($type) {
            case 'fatal':
            case 'error':
                return 5; // Errors often relate to construct complexity or misuse.
            case 'warning':
                return 4; // Warnings often relate to unused or problematic content.
            case 'refactor':
                return 7; // Refactor suggestions relate to modular structure.
            case 'convention':
                return 1; // Convention messages relate to presentation.
            default:
                return 1;
        }
    }

    /**
     * Enrich a lint_message template data array with CQP principle information.
     *
     * @param array $templatedata The output of lint_message::to_template_data().
     * @param lint_message $msg The original message.
     * @return array Enriched template data with cqp_number, cqp_name, cqp_short, cqp_guideline.
     */
    public static function enrich_template_data(array $templatedata, lint_message $msg): array {
        $principle = self::get_principle($msg);
        $templatedata['cqp_number'] = $principle['number'];
        $templatedata['cqp_name'] = $principle['name'];
        $templatedata['cqp_short'] = $principle['short'];
        $templatedata['cqp_guideline'] = $principle['guideline'];
        return $templatedata;
    }

    /**
     * Get all CQP principles.
     *
     * @return array Keyed by principle number.
     */
    public static function get_all_principles(): array {
        return self::PRINCIPLES;
    }
}
