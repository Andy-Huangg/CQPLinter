/**
 * Client-side Python code analyser for the 8 Code Quality Principles (CQP).
 *
 * Performs static analysis on Python source code entirely in the browser.
 * No code is sent to any server.
 *
 * @module     local_coderunner_pylint/python_analyser
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    // ──────────────────────────────────────────────
    // CQP Principle definitions
    // ──────────────────────────────────────────────
    var PRINCIPLES = {
        1: {
            name: 'Clear Presentation',
            short: 'Different elements are easy to recognise and distinguish and the relationships between them are apparent.',
            guideline: 'Formatting (spacing, indentation, brackets, line length) should make it easy to distinguish separate elements.'
        },
        2: {
            name: 'Explanatory Language',
            short: 'The rationale, intent and meaning of code is explicit.',
            guideline: 'Names should be descriptive, comments should clarify intent, and symbolic constants should convey meaning of literals.'
        },
        3: {
            name: 'Consistent Code',
            short: 'Elements that are similar in nature are presented and used in a similar way.',
            guideline: 'Be consistent in documentation, notation, and implementation choices throughout your code.'
        },
        4: {
            name: 'Used Content',
            short: 'All elements that are introduced are meaningfully used.',
            guideline: 'All code constructs should be needed. Remove code that does not affect functionality.'
        },
        5: {
            name: 'Simple Constructs',
            short: 'Coding constructs are selected to minimise complexity for the intended reader.',
            guideline: 'Minimise nesting, keep control flow easy to follow, and keep expressions appropriately simple.'
        },
        6: {
            name: 'Minimal Duplication',
            short: 'Code repetition is avoided.',
            guideline: 'Consolidate repeated groups of statements; replace adjacent repeated statements with a loop.'
        },
        7: {
            name: 'Modular Structure',
            short: 'Related code is grouped together and dependencies between groups minimised.',
            guideline: 'Functions should implement a single task. Minimise scope of variables. Organise related elements together.'
        },
        8: {
            name: 'Problem Alignment',
            short: 'Implementation choices are consistent with the problem to be solved.',
            guideline: 'Data structures should align with the data represented. Algorithms should reflect the process they represent.'
        }
    };

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Check if a line is inside a multi-line string.
     * Returns an array of booleans, one per line.
     */
    function buildStringMask(lines) {
        var mask = [];
        var inTriple = false;
        var tripleChar = '';
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            if (inTriple) {
                mask.push(true);
                // Check if triple quote ends on this line.
                var endPattern = tripleChar + tripleChar + tripleChar;
                var pos = line.indexOf(endPattern);
                if (pos !== -1) {
                    inTriple = false;
                }
            } else {
                mask.push(false);
                // Check for triple-quote start (not already closed on same line).
                var dq = line.indexOf('"""');
                var sq = line.indexOf("'''");
                var first = -1;
                var ch = '';
                if (dq !== -1 && (sq === -1 || dq < sq)) {
                    first = dq;
                    ch = '"';
                } else if (sq !== -1) {
                    first = sq;
                    ch = "'";
                }
                if (first !== -1) {
                    var end3 = ch + ch + ch;
                    var closePos = line.indexOf(end3, first + 3);
                    if (closePos === -1) {
                        inTriple = true;
                        tripleChar = ch;
                    }
                }
            }
        }
        return mask;
    }

    /**
     * Strip inline comments from a line (naive — doesn't handle # inside strings perfectly).
     */
    function stripComment(line) {
        var inStr = false;
        var strChar = '';
        for (var i = 0; i < line.length; i++) {
            var c = line[i];
            if (inStr) {
                if (c === '\\') {
                    i++;
                    continue;
                }
                if (c === strChar) {
                    inStr = false;
                }
            } else {
                if (c === '#') {
                    return line.substring(0, i);
                }
                if (c === '"' || c === "'") {
                    inStr = true;
                    strChar = c;
                }
            }
        }
        return line;
    }

    /**
     * Get the indentation level (number of leading spaces) of a line.
     */
    function indentLevel(line) {
        var m = line.match(/^( *)/);
        return m ? m[1].length : 0;
    }

    /**
     * Check if name is snake_case.
     */
    function isSnakeCase(name) {
        return /^[a-z_][a-z0-9_]*$/.test(name);
    }

    /**
     * Check if name is UPPER_CASE.
     */
    function isUpperCase(name) {
        return /^[A-Z_][A-Z0-9_]*$/.test(name);
    }

    /**
     * Check if name is CamelCase.
     */
    function isCamelCase(name) {
        return /^[A-Z][a-zA-Z0-9]*$/.test(name);
    }

    // Common acceptable single-char names in limited contexts.
    var ACCEPTABLE_SHORT = ['i', 'j', 'k', 'n', 'x', 'y', 'z', 'e', 'f', '_'];

    // ──────────────────────────────────────────────
    // Individual CQP checks
    // ──────────────────────────────────────────────

    /**
     * CQP 1: Clear Presentation
     */
    function checkPresentation(lines, stringMask) {
        var issues = [];
        var prevBlank = false;
        var MAX_LINE = 120;

        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var line = lines[i];
            var stripped = stripComment(line);

            // Line too long.
            if (line.length > MAX_LINE) {
                issues.push({
                    line: i + 1, type: 'convention',
                    symbol: 'line-too-long',
                    message: 'Line is ' + line.length + ' characters (limit ' + MAX_LINE + ').'
                });
            }

            // Trailing whitespace.
            if (line.length > 0 && /\s+$/.test(line) && line.trim().length > 0) {
                issues.push({
                    line: i + 1, type: 'convention',
                    symbol: 'trailing-whitespace',
                    message: 'Trailing whitespace.'
                });
            }

            // Mixed tabs and spaces in indentation.
            if (/^\s+/.test(line) && /\t/.test(line.match(/^(\s+)/)[1]) && / /.test(line.match(/^(\s+)/)[1])) {
                issues.push({
                    line: i + 1, type: 'convention',
                    symbol: 'mixed-indentation',
                    message: 'Indentation contains both tabs and spaces.'
                });
            }

            // Multiple statements on one line (a ; b outside strings).
            if (/;/.test(stripped)) {
                var codePart = stripped.trim();
                if (codePart.length > 0 && !/^#/.test(codePart) && !/;\s*$/.test(codePart)) {
                    // Check there's actual code on both sides of the semicolon.
                    var parts = stripped.split(';');
                    if (parts.length > 1 && parts[0].trim().length > 0 && parts[1].trim().length > 0) {
                        issues.push({
                            line: i + 1, type: 'convention',
                            symbol: 'multiple-statements',
                            message: 'Multiple statements on one line. Place each statement on its own line.'
                        });
                    }
                }
            }

            // Missing blank line before function/class definition.
            if (/^(def |class )/.test(line.trim()) && i > 0) {
                // Top-level defs should have 2 blank lines before them.
                if (indentLevel(line) === 0 && i >= 2) {
                    if (lines[i - 1].trim() !== '' || lines[i - 2].trim() !== '') {
                        // Check it's not the first def in the file.
                        var hasPriorCode = false;
                        for (var p = 0; p < i; p++) {
                            if (lines[p].trim().length > 0 && !stringMask[p] && !/^(import |from |#)/.test(lines[p].trim())) {
                                hasPriorCode = true;
                                break;
                            }
                        }
                        if (hasPriorCode) {
                            issues.push({
                                line: i + 1, type: 'convention',
                                symbol: 'missing-blank-lines',
                                message: 'Expected 2 blank lines before a top-level function or class definition.'
                            });
                        }
                    }
                }
            }

            // Multiple consecutive blank lines (more than 2).
            if (line.trim() === '') {
                if (prevBlank && i >= 2 && lines[i - 1].trim() === '' && lines[i - 2].trim() === '') {
                    issues.push({
                        line: i + 1, type: 'convention',
                        symbol: 'too-many-blank-lines',
                        message: 'Too many consecutive blank lines.'
                    });
                }
                prevBlank = true;
            } else {
                prevBlank = false;
            }
        }

        return issues;
    }

    /**
     * CQP 2: Explanatory Language
     */
    function checkExplanatoryLanguage(lines, stringMask) {
        var issues = [];

        // Track function/class definitions and whether they have docstrings.
        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var trimmed = lines[i].trim();

            // Check function definitions.
            var funcMatch = trimmed.match(/^def\s+([a-zA-Z_]\w*)\s*\(/);
            if (funcMatch) {
                var fname = funcMatch[1];

                // Check for docstring on next non-blank line.
                var nextCode = i + 1;
                while (nextCode < lines.length && lines[nextCode].trim() === '') {
                    nextCode++;
                }
                if (nextCode < lines.length) {
                    var nextTrimmed = lines[nextCode].trim();
                    var hasDocstring = /^("""|''')/.test(nextTrimmed);
                    if (!hasDocstring && fname !== '__init__' && fname.charAt(0) !== '_') {
                        issues.push({
                            line: i + 1, type: 'convention',
                            symbol: 'missing-function-docstring',
                            message: 'Function \'' + fname + '\' is missing a docstring explaining its purpose.'
                        });
                    }
                }

                // Short/non-descriptive function names (less than 3 chars, not dunder).
                if (fname.length < 3 && fname.indexOf('__') !== 0 && ACCEPTABLE_SHORT.indexOf(fname) === -1) {
                    issues.push({
                        line: i + 1, type: 'convention',
                        symbol: 'short-function-name',
                        message: 'Function name \'' + fname + '\' is very short. Use a descriptive name that conveys purpose.'
                    });
                }
            }

            // Check class definitions.
            var classMatch = trimmed.match(/^class\s+([a-zA-Z_]\w*)/);
            if (classMatch) {
                var cname = classMatch[1];
                var nextCode2 = i + 1;
                while (nextCode2 < lines.length && lines[nextCode2].trim() === '') {
                    nextCode2++;
                }
                if (nextCode2 < lines.length) {
                    var hasDocstring2 = /^("""|''')/.test(lines[nextCode2].trim());
                    if (!hasDocstring2) {
                        issues.push({
                            line: i + 1, type: 'convention',
                            symbol: 'missing-class-docstring',
                            message: 'Class \'' + cname + '\' is missing a docstring explaining its purpose.'
                        });
                    }
                }
            }

            // Check for variable assignments with very short names (outside loops).
            var assignMatch = trimmed.match(/^([a-zA-Z_]\w*)\s*=[^=]/);
            if (assignMatch && !funcMatch && !classMatch) {
                var vname = assignMatch[1];
                if (vname.length === 1 && ACCEPTABLE_SHORT.indexOf(vname) === -1) {
                    // Check if it's in a for-loop context (for x in ...).
                    if (i > 0 && !/^\s*for\s/.test(lines[i].trim())) {
                        issues.push({
                            line: i + 1, type: 'convention',
                            symbol: 'single-char-variable',
                            message: 'Variable \'' + vname + '\' is a single character. Use a descriptive name that conveys meaning.'
                        });
                    }
                }
            }

            // Check for loop variables.
            var forMatch = trimmed.match(/^for\s+([a-zA-Z_]\w*)\s+in\s/);
            if (forMatch) {
                var loopVar = forMatch[1];
                if (loopVar.length === 1 && ACCEPTABLE_SHORT.indexOf(loopVar) === -1) {
                    issues.push({
                        line: i + 1, type: 'convention',
                        symbol: 'single-char-variable',
                        message: 'Loop variable \'' + loopVar + '\' is a single character. Consider a descriptive name.'
                    });
                }
            }

            // Magic numbers — numeric literals in expressions (not 0, 1, -1, or in assignments to UPPER_CASE).
            if (!stringMask[i] && !/^\s*#/.test(lines[i])) {
                var codePart = stripComment(lines[i]);
                // Look for numeric literals in comparisons or arithmetic.
                var magicPattern = /(?:==|!=|<=?|>=?|[+\-*\/%])\s*(\d+\.?\d*)/g;
                var magicMatch2;
                while ((magicMatch2 = magicPattern.exec(codePart)) !== null) {
                    var num = parseFloat(magicMatch2[1]);
                    if (num !== 0 && num !== 1 && num !== -1 && num !== 2) {
                        // Check it's not part of a constant assignment.
                        var assignTarget = codePart.match(/^(\s*)([A-Z_][A-Z0-9_]*)\s*=/);
                        if (!assignTarget) {
                            issues.push({
                                line: i + 1, type: 'convention',
                                symbol: 'magic-number',
                                message: 'Magic number ' + magicMatch2[1] + ' used. Consider using a named constant to convey its meaning.'
                            });
                            break; // One per line is enough.
                        }
                    }
                }
            }
        }

        return issues;
    }

    /**
     * CQP 3: Consistent Code
     */
    function checkConsistency(lines, stringMask) {
        var issues = [];

        // Check for mixed quote styles in simple strings.
        var singleQuoteCount = 0;
        var doubleQuoteCount = 0;
        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var line = stripComment(lines[i]);
            // Count simple string literals (not triple-quoted).
            var singles = line.match(/(^|[^a-zA-Z0-9_])'[^']*'/g);
            var doubles = line.match(/(^|[^a-zA-Z0-9_])"[^"]*"/g);
            if (singles) {
                singleQuoteCount += singles.length;
            }
            if (doubles) {
                doubleQuoteCount += doubles.length;
            }
        }
        // If both styles are used substantially, flag the minority style.
        if (singleQuoteCount > 0 && doubleQuoteCount > 0) {
            var minorityIsDouble = singleQuoteCount > doubleQuoteCount;
            var total = singleQuoteCount + doubleQuoteCount;
            var minority = Math.min(singleQuoteCount, doubleQuoteCount);
            // Only flag if there's a meaningful mix (not just one occurrence).
            if (minority >= 2 && minority / total > 0.15) {
                issues.push({
                    line: 1, type: 'convention',
                    symbol: 'inconsistent-quotes',
                    message: 'Mixed quote styles: ' + singleQuoteCount + ' single-quoted and ' +
                             doubleQuoteCount + ' double-quoted strings. Pick one style and use it throughout.'
                });
            }
        }

        // Check for mixed naming conventions (snake_case vs camelCase) in functions.
        var funcNames = [];
        for (var j = 0; j < lines.length; j++) {
            if (stringMask[j]) {
                continue;
            }
            var fmatch = lines[j].trim().match(/^def\s+([a-zA-Z_]\w*)/);
            if (fmatch && fmatch[1].indexOf('__') !== 0) {
                funcNames.push({name: fmatch[1], line: j + 1});
            }
        }
        if (funcNames.length >= 2) {
            var snakeCount = 0;
            var camelCount = 0;
            funcNames.forEach(function(f) {
                if (/^[a-z][a-zA-Z0-9]*$/.test(f.name) && /[A-Z]/.test(f.name)) {
                    camelCount++;
                } else if (isSnakeCase(f.name)) {
                    snakeCount++;
                }
            });
            if (snakeCount > 0 && camelCount > 0) {
                issues.push({
                    line: 1, type: 'convention',
                    symbol: 'inconsistent-naming',
                    message: 'Mixed naming conventions: ' + snakeCount + ' snake_case and ' +
                             camelCount + ' camelCase function names. Python convention is snake_case.'
                });
            }
        }

        // Inconsistent return — functions that sometimes return a value and sometimes return None/nothing.
        for (var k = 0; k < lines.length; k++) {
            if (stringMask[k]) {
                continue;
            }
            var defMatch = lines[k].trim().match(/^def\s+([a-zA-Z_]\w*)/);
            if (!defMatch) {
                continue;
            }
            var defIndent = indentLevel(lines[k]);
            var bodyStart = k + 1;
            var hasValueReturn = false;
            var hasBareReturn = false;
            for (var m = bodyStart; m < lines.length; m++) {
                if (stringMask[m]) {
                    continue;
                }
                var ml = lines[m];
                if (ml.trim().length > 0 && indentLevel(ml) <= defIndent && m > bodyStart) {
                    break; // Left the function body.
                }
                var retMatch = ml.trim().match(/^return\b(.*)/);
                if (retMatch) {
                    if (retMatch[1].trim().length > 0 && retMatch[1].trim() !== 'None') {
                        hasValueReturn = true;
                    } else {
                        hasBareReturn = true;
                    }
                }
            }
            if (hasValueReturn && hasBareReturn) {
                issues.push({
                    line: k + 1, type: 'refactor',
                    symbol: 'inconsistent-return',
                    message: 'Function \'' + defMatch[1] +
                             '\' has both value-returning and bare return statements. Be consistent.'
                });
            }
        }

        return issues;
    }

    /**
     * CQP 4: Used Content
     */
    function checkUsedContent(lines, stringMask) {
        var issues = [];

        // Detect imports and check if they're used.
        var imports = [];
        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var trimmed = lines[i].trim();

            // import X / import X as Y.
            var importMatch = trimmed.match(/^import\s+(\w+)(?:\s+as\s+(\w+))?/);
            if (importMatch) {
                imports.push({name: importMatch[2] || importMatch[1], line: i + 1});
                continue;
            }
            // from X import Y, Z / from X import Y as Z.
            var fromMatch = trimmed.match(/^from\s+\w+(?:\.\w+)*\s+import\s+(.+)/);
            if (fromMatch) {
                var items = fromMatch[1].split(',');
                items.forEach(function(item) {
                    item = item.trim();
                    if (item === '*') {
                        return;
                    }
                    var asMatch = item.match(/(\w+)\s+as\s+(\w+)/);
                    if (asMatch) {
                        imports.push({name: asMatch[2], line: i + 1});
                    } else {
                        var plain = item.match(/^(\w+)/);
                        if (plain) {
                            imports.push({name: plain[1], line: i + 1});
                        }
                    }
                });
            }
        }

        // Check if each import is referenced elsewhere.
        var codeWithoutImports = '';
        for (var j = 0; j < lines.length; j++) {
            if (stringMask[j]) {
                continue;
            }
            var t = lines[j].trim();
            if (/^import\s/.test(t) || /^from\s/.test(t)) {
                continue;
            }
            codeWithoutImports += ' ' + lines[j];
        }
        imports.forEach(function(imp) {
            var regex = new RegExp('\\b' + imp.name + '\\b');
            if (!regex.test(codeWithoutImports)) {
                issues.push({
                    line: imp.line, type: 'warning',
                    symbol: 'unused-import',
                    message: 'Import \'' + imp.name + '\' is not used.'
                });
            }
        });

        // Detect variables assigned but never read.
        // Simple heuristic: track assignments and usages in non-import, non-def lines.
        var assignments = {};
        var usages = {};
        for (var k = 0; k < lines.length; k++) {
            if (stringMask[k]) {
                continue;
            }
            var tl = lines[k].trim();
            if (/^(import|from|class|def|#|@)/.test(tl) || tl === '') {
                continue;
            }
            var code = stripComment(lines[k]);

            // Simple assignment: name = ...
            var aMatch = code.match(/^\s*([a-zA-Z_]\w*)\s*=[^=]/);
            if (aMatch) {
                var varName = aMatch[1];
                if (varName !== 'self' && !isUpperCase(varName)) {
                    if (!assignments[varName]) {
                        assignments[varName] = [];
                    }
                    assignments[varName].push(k + 1);
                }
            }

            // Augmented assignment: name += / -= etc.
            var augMatch = code.match(/^\s*([a-zA-Z_]\w*)\s*[+\-*\/%]=\s/);
            if (augMatch) {
                var augName = augMatch[1];
                if (!usages[augName]) {
                    usages[augName] = true;
                }
            }

            // Track usages: any identifier reference.
            var words = code.match(/\b[a-zA-Z_]\w*\b/g);
            if (words) {
                // Exclude the assignment target.
                var isAssign = code.match(/^\s*([a-zA-Z_]\w*)\s*=[^=]/);
                words.forEach(function(w, idx) {
                    if (isAssign && idx === 0 && w === isAssign[1]) {
                        return; // Skip the assignment target itself.
                    }
                    usages[w] = true;
                });
            }
        }
        Object.keys(assignments).forEach(function(varName) {
            if (!usages[varName]) {
                var lastLine = assignments[varName][assignments[varName].length - 1];
                issues.push({
                    line: lastLine, type: 'warning',
                    symbol: 'unused-variable',
                    message: 'Variable \'' + varName + '\' is assigned but never used.'
                });
            }
        });

        // Unreachable code after return/break/continue.
        for (var r = 0; r < lines.length - 1; r++) {
            if (stringMask[r]) {
                continue;
            }
            var rt = lines[r].trim();
            if (/^(return|break|continue)\b/.test(rt)) {
                var currentIndent = indentLevel(lines[r]);
                var nextLine = r + 1;
                while (nextLine < lines.length && lines[nextLine].trim() === '') {
                    nextLine++;
                }
                if (nextLine < lines.length && !stringMask[nextLine]) {
                    var nextIndent = indentLevel(lines[nextLine]);
                    var nextTrimmed = lines[nextLine].trim();
                    // If next line is at same or deeper indent and is code (not elif/else/except/finally).
                    if (nextIndent >= currentIndent && nextTrimmed.length > 0 &&
                        !/^(elif|else|except|finally|def|class)/.test(nextTrimmed)) {
                        issues.push({
                            line: nextLine + 1, type: 'warning',
                            symbol: 'unreachable-code',
                            message: 'This code is unreachable (it follows a return/break/continue statement).'
                        });
                    }
                }
            }
        }

        // Commented-out code (lines that look like commented-out Python statements).
        for (var c = 0; c < lines.length; c++) {
            if (stringMask[c]) {
                continue;
            }
            var ct = lines[c].trim();
            if (/^#\s*(def |class |if |for |while |import |from |return |print\(|[a-z_]\w*\s*=)/.test(ct)) {
                issues.push({
                    line: c + 1, type: 'convention',
                    symbol: 'commented-out-code',
                    message: 'This looks like commented-out code. Remove it if it is no longer needed.'
                });
            }
        }

        return issues;
    }

    /**
     * CQP 5: Simple Constructs
     */
    function checkSimpleConstructs(lines, stringMask) {
        var issues = [];
        var MAX_NESTING = 3;
        var MAX_FUNC_LENGTH = 30;

        // Track nesting depth.
        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var trimmed = lines[i].trim();
            if (trimmed.length === 0 || /^#/.test(trimmed)) {
                continue;
            }

            // Count indentation-based nesting (within functions).
            var indent = indentLevel(lines[i]);
            var nestingLevel = Math.floor(indent / 4); // Assuming 4-space indent.
            if (nestingLevel > MAX_NESTING && /^(if|for|while|elif|else|try|except|with)\b/.test(trimmed)) {
                issues.push({
                    line: i + 1, type: 'refactor',
                    symbol: 'too-many-nested-blocks',
                    message: 'Too deeply nested (' + nestingLevel + ' levels). Consider extracting logic into a separate function.'
                });
            }

            // Unnecessary else after return.
            if (/^else\s*:/.test(trimmed)) {
                // Look back for a return in the preceding if-block.
                var elseIndent = indent;
                for (var b = i - 1; b >= 0; b--) {
                    if (stringMask[b] || lines[b].trim() === '') {
                        continue;
                    }
                    if (indentLevel(lines[b]) < elseIndent) {
                        break;
                    }
                    if (indentLevel(lines[b]) === elseIndent + 4 && /^\s*return\b/.test(lines[b])) {
                        issues.push({
                            line: i + 1, type: 'refactor',
                            symbol: 'no-else-return',
                            message: 'Unnecessary \'else\' after \'return\'. Remove the else and unindent the code.'
                        });
                        break;
                    }
                    break;
                }
            }

            // Simplifiable if: "if cond: return True else: return False".
            if (/^if\s+/.test(trimmed) && i + 3 < lines.length) {
                var ifIndent = indent;
                var line1 = (i + 1 < lines.length) ? lines[i + 1].trim() : '';
                var line2 = (i + 2 < lines.length) ? lines[i + 2].trim() : '';
                var line3 = (i + 3 < lines.length) ? lines[i + 3].trim() : '';
                if (/^return\s+True$/.test(line1) && /^else\s*:$/.test(line2) && /^return\s+False$/.test(line3)) {
                    issues.push({
                        line: i + 1, type: 'refactor',
                        symbol: 'simplifiable-if',
                        message: 'This if/else can be simplified to a single return statement of the condition itself.'
                    });
                }
                if (/^return\s+False$/.test(line1) && /^else\s*:$/.test(line2) && /^return\s+True$/.test(line3)) {
                    issues.push({
                        line: i + 1, type: 'refactor',
                        symbol: 'simplifiable-if',
                        message: 'This if/else can be simplified to: return not <condition>.'
                    });
                }
            }

            // Multiple == comparisons that could use "in".
            var orChainMatch = stripComment(lines[i]).match(/(\w+)\s*==\s*\S+\s+or\s+\1\s*==\s*\S+/);
            if (orChainMatch) {
                issues.push({
                    line: i + 1, type: 'refactor',
                    symbol: 'consider-using-in',
                    message: 'Multiple equality comparisons can be replaced with \'' + orChainMatch[1] + ' in (...)\'.'
                });
            }
        }

        // Check function length.
        for (var f = 0; f < lines.length; f++) {
            if (stringMask[f]) {
                continue;
            }
            var defMatch = lines[f].trim().match(/^def\s+([a-zA-Z_]\w*)/);
            if (!defMatch) {
                continue;
            }
            var defIndent = indentLevel(lines[f]);
            var bodyLines = 0;
            for (var g = f + 1; g < lines.length; g++) {
                if (lines[g].trim() === '') {
                    continue;
                }
                if (!stringMask[g] && indentLevel(lines[g]) <= defIndent) {
                    break;
                }
                bodyLines++;
            }
            if (bodyLines > MAX_FUNC_LENGTH) {
                issues.push({
                    line: f + 1, type: 'refactor',
                    symbol: 'too-many-statements',
                    message: 'Function \'' + defMatch[1] + '\' is ' + bodyLines +
                             ' lines long. Consider breaking it into smaller functions.'
                });
            }
        }

        return issues;
    }

    /**
     * CQP 6: Minimal Duplication
     */
    function checkDuplication(lines, stringMask) {
        var issues = [];
        var MIN_DUP_LINES = 3;
        var reported = {};

        // Normalise lines (strip whitespace) for comparison.
        var normalised = lines.map(function(l, idx) {
            if (stringMask[idx] || l.trim() === '' || /^\s*#/.test(l)) {
                return null; // Skip.
            }
            return l.trim();
        });

        // Look for blocks of MIN_DUP_LINES+ identical consecutive normalised lines appearing elsewhere.
        for (var i = 0; i < normalised.length - MIN_DUP_LINES; i++) {
            if (normalised[i] === null) {
                continue;
            }
            var block = [];
            for (var k = 0; k < MIN_DUP_LINES; k++) {
                if (normalised[i + k] === null) {
                    break;
                }
                block.push(normalised[i + k]);
            }
            if (block.length < MIN_DUP_LINES) {
                continue;
            }
            var blockKey = block.join('\n');
            if (reported[blockKey]) {
                continue;
            }

            // Search for the same block elsewhere.
            for (var j = i + MIN_DUP_LINES; j < normalised.length - MIN_DUP_LINES + 1; j++) {
                var match = true;
                for (var m = 0; m < MIN_DUP_LINES; m++) {
                    if (normalised[j + m] !== block[m]) {
                        match = false;
                        break;
                    }
                }
                if (match) {
                    reported[blockKey] = true;
                    issues.push({
                        line: i + 1, type: 'refactor',
                        symbol: 'duplicate-code',
                        message: 'Lines ' + (i + 1) + '-' + (i + MIN_DUP_LINES) +
                                 ' are duplicated at lines ' + (j + 1) + '-' + (j + MIN_DUP_LINES) +
                                 '. Consider consolidating into a function or loop.'
                    });
                    break;
                }
            }
        }

        // Adjacent similar lines that could be a loop.
        for (var a = 0; a < normalised.length - 1; a++) {
            if (normalised[a] === null || normalised[a + 1] === null) {
                continue;
            }
            // Check if adjacent lines are structurally similar (same function call, different args).
            var patt = normalised[a].replace(/\(.*\)/, '(...)');
            var patt2 = normalised[a + 1].replace(/\(.*\)/, '(...)');
            if (patt === patt2 && patt.indexOf('(...)') !== -1 && normalised[a] !== normalised[a + 1]) {
                // Check for a run of 3+.
                var runLen = 2;
                while (a + runLen < normalised.length && normalised[a + runLen] !== null) {
                    var pattN = normalised[a + runLen].replace(/\(.*\)/, '(...)');
                    if (pattN !== patt) {
                        break;
                    }
                    runLen++;
                }
                if (runLen >= 3) {
                    var key = 'adj-' + a;
                    if (!reported[key]) {
                        reported[key] = true;
                        issues.push({
                            line: a + 1, type: 'refactor',
                            symbol: 'repeated-adjacent-calls',
                            message: runLen + ' similar consecutive statements (lines ' + (a + 1) + '-' +
                                     (a + runLen) + '). Consider using a loop.'
                        });
                    }
                    a += runLen - 1; // Skip ahead.
                }
            }
        }

        return issues;
    }

    /**
     * CQP 7: Modular Structure
     */
    function checkModularStructure(lines, stringMask) {
        var issues = [];
        var MAX_PARAMS = 5;
        var MAX_LOCALS = 10;

        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var trimmed = lines[i].trim();

            // Check function parameter count.
            var defMatch = trimmed.match(/^def\s+([a-zA-Z_]\w*)\s*\(([^)]*)\)/);
            if (defMatch) {
                var fname = defMatch[1];
                var params = defMatch[2].split(',').filter(function(p) {
                    p = p.trim();
                    return p.length > 0 && p !== 'self' && p !== 'cls';
                });
                if (params.length > MAX_PARAMS) {
                    issues.push({
                        line: i + 1, type: 'refactor',
                        symbol: 'too-many-arguments',
                        message: 'Function \'' + fname + '\' has ' + params.length +
                                 ' parameters (limit ' + MAX_PARAMS + '). Consider grouping related parameters.'
                    });
                }

                // Count local variables in function body.
                var defIndent = indentLevel(lines[i]);
                var locals = {};
                for (var b = i + 1; b < lines.length; b++) {
                    if (lines[b].trim() === '') {
                        continue;
                    }
                    if (!stringMask[b] && indentLevel(lines[b]) <= defIndent) {
                        break;
                    }
                    var localMatch = lines[b].trim().match(/^([a-z_]\w*)\s*=[^=]/);
                    if (localMatch && localMatch[1] !== 'self') {
                        locals[localMatch[1]] = true;
                    }
                }
                var localCount = Object.keys(locals).length;
                if (localCount > MAX_LOCALS) {
                    issues.push({
                        line: i + 1, type: 'refactor',
                        symbol: 'too-many-locals',
                        message: 'Function \'' + fname + '\' has ' + localCount +
                                 ' local variables (limit ' + MAX_LOCALS + '). Consider splitting into smaller functions.'
                    });
                }
            }

            // Use of global statement.
            if (/^\s*global\s+/.test(lines[i])) {
                issues.push({
                    line: i + 1, type: 'refactor',
                    symbol: 'global-statement',
                    message: 'Use of \'global\' increases coupling. Consider passing values as parameters and returning results.'
                });
            }
        }

        // Check if all code is at module level (no functions at all for non-trivial code).
        var funcCount = 0;
        var moduleLevelStatements = 0;
        for (var j = 0; j < lines.length; j++) {
            if (stringMask[j]) {
                continue;
            }
            var t = lines[j].trim();
            if (t === '' || /^#/.test(t) || /^(import|from)\s/.test(t)) {
                continue;
            }
            if (/^(def|class)\s/.test(t)) {
                funcCount++;
            } else if (indentLevel(lines[j]) === 0) {
                moduleLevelStatements++;
            }
        }
        if (funcCount === 0 && moduleLevelStatements > 15) {
            issues.push({
                line: 1, type: 'refactor',
                symbol: 'no-functions',
                message: 'All code is at module level (' + moduleLevelStatements +
                         ' statements, no functions). Consider organising into functions.'
            });
        }

        return issues;
    }

    /**
     * CQP 8: Problem Alignment
     */
    function checkProblemAlignment(lines, stringMask) {
        var issues = [];

        for (var i = 0; i < lines.length; i++) {
            if (stringMask[i]) {
                continue;
            }
            var code = stripComment(lines[i]);
            var trimmed = code.trim();

            // range(len(x)) pattern — suggest enumerate.
            if (/range\s*\(\s*len\s*\(/.test(trimmed)) {
                issues.push({
                    line: i + 1, type: 'refactor',
                    symbol: 'consider-enumerate',
                    message: 'Using range(len(...)) to iterate. Consider using enumerate() for clearer code.'
                });
            }

            // Manual index tracking: idx = 0 ... idx += 1 inside a for loop.
            if (/for\s+\w+\s+in\s/.test(trimmed)) {
                // Look ahead for idx += 1 pattern.
                var loopIndent = indentLevel(lines[i]);
                for (var k = i + 1; k < lines.length; k++) {
                    if (lines[k].trim() === '') {
                        continue;
                    }
                    if (!stringMask[k] && indentLevel(lines[k]) <= loopIndent) {
                        break;
                    }
                    if (/\w+\s*\+=\s*1/.test(lines[k].trim()) || /\w+\s*=\s*\w+\s*\+\s*1/.test(lines[k].trim())) {
                        issues.push({
                            line: i + 1, type: 'refactor',
                            symbol: 'manual-index-tracking',
                            message: 'Manual index counter inside a for loop. Consider using enumerate() instead.'
                        });
                        break;
                    }
                }
            }

            // .keys() in dict iteration — usually unnecessary.
            if (/for\s+\w+\s+in\s+\w+\.keys\(\)/.test(trimmed)) {
                issues.push({
                    line: i + 1, type: 'refactor',
                    symbol: 'unnecessary-dict-keys',
                    message: 'Calling .keys() is unnecessary — iterating a dict yields keys by default.'
                });
            }

            // Building a list with append in a loop when comprehension would work.
            if (/\.append\(/.test(trimmed)) {
                // Look back for a loop.
                for (var p = i - 1; p >= 0; p--) {
                    if (stringMask[p] || lines[p].trim() === '') {
                        continue;
                    }
                    if (indentLevel(lines[p]) < indentLevel(lines[i])) {
                        if (/^\s*(for|while)\s/.test(lines[p])) {
                            // Check if append is the only operation in the loop body.
                            var bodyIndent = indentLevel(lines[i]);
                            var onlyAppend = true;
                            for (var q = p + 1; q < lines.length; q++) {
                                if (lines[q].trim() === '') {
                                    continue;
                                }
                                if (indentLevel(lines[q]) < bodyIndent) {
                                    break;
                                }
                                if (indentLevel(lines[q]) === bodyIndent && !/\.append\(/.test(lines[q])) {
                                    onlyAppend = false;
                                    break;
                                }
                            }
                            if (onlyAppend && /^\s*for\s/.test(lines[p])) {
                                issues.push({
                                    line: p + 1, type: 'refactor',
                                    symbol: 'consider-comprehension',
                                    message: 'Building a list with a loop and append. Consider using a list comprehension.'
                                });
                            }
                        }
                        break;
                    }
                }
            }

            // type(x) == type — suggest isinstance.
            if (/type\s*\(\s*\w+\s*\)\s*==/.test(trimmed)) {
                issues.push({
                    line: i + 1, type: 'refactor',
                    symbol: 'unidiomatic-typecheck',
                    message: 'Using type() for type checking. Consider using isinstance() instead.'
                });
            }
        }

        return issues;
    }

    // ──────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────

    return {
        PRINCIPLES: PRINCIPLES,

        /**
         * Analyse Python code against all 8 CQP principles.
         *
         * @param {string} code The Python source code to analyse.
         * @return {Object} Analysis result with messages grouped by principle.
         */
        analyse: function(code) {
            var lines = code.split('\n');
            var stringMask = buildStringMask(lines);

            // Run all checks.
            var allIssues = [];
            var checkers = [
                {fn: checkPresentation,       cqp: 1},
                {fn: checkExplanatoryLanguage, cqp: 2},
                {fn: checkConsistency,         cqp: 3},
                {fn: checkUsedContent,         cqp: 4},
                {fn: checkSimpleConstructs,    cqp: 5},
                {fn: checkDuplication,         cqp: 6},
                {fn: checkModularStructure,    cqp: 7},
                {fn: checkProblemAlignment,    cqp: 8}
            ];

            checkers.forEach(function(checker) {
                var issues = checker.fn(lines, stringMask);
                issues.forEach(function(issue) {
                    issue.cqp_number = checker.cqp;
                    issue.cqp_name = PRINCIPLES[checker.cqp].name;
                    issue.cqp_guideline = PRINCIPLES[checker.cqp].guideline;
                    allIssues.push(issue);
                });
            });

            // Sort by line number.
            allIssues.sort(function(a, b) { return a.line - b.line; });

            // Group by principle.
            var principleGroups = {};
            allIssues.forEach(function(issue) {
                var pn = issue.cqp_number;
                if (!principleGroups[pn]) {
                    principleGroups[pn] = {
                        number: pn,
                        name: PRINCIPLES[pn].name,
                        short: PRINCIPLES[pn].short,
                        guideline: PRINCIPLES[pn].guideline,
                        count: 0,
                        messages: []
                    };
                }
                principleGroups[pn].messages.push(issue);
                principleGroups[pn].count++;
            });

            var keys = Object.keys(principleGroups).sort(function(a, b) { return a - b; });
            var principles = keys.map(function(k) { return principleGroups[k]; });

            return {
                success: true,
                total_issues: allIssues.length,
                messages: allIssues,
                principles: principles
            };
        }
    };
});
