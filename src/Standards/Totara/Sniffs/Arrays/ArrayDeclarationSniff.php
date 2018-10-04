<?php
/*
 * This file is part of Totara Learn
 *
 * Copyright (C) 2018 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Fabian Derschatta <fabian.derschatta@totaralearning.com>
 */

namespace TotaraCodeSniffer\Standards\Totara\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ArrayDeclarationSniff implements Sniff
{

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [
            T_ARRAY,
            T_OPEN_SHORT_ARRAY,
        ];
    }

    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int $stackPtr The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_ARRAY) {
            $phpcsFile->recordMetric($stackPtr, 'Short array syntax used', 'no');

            $arrayStart = $tokens[$stackPtr]['parenthesis_opener'];
            if (isset($tokens[$arrayStart]['parenthesis_closer']) === false) {
                return;
            }

            $arrayEnd = $tokens[$arrayStart]['parenthesis_closer'];

            if ($arrayStart !== ($stackPtr + 1)) {
                $error = 'There must be no space between the "array" keyword and the opening parenthesis';

                $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), $arrayStart, true);
                if (isset(Tokens::$commentTokens[$tokens[$next]['code']]) === true) {
                    // We don't have anywhere to put the comment, so don't attempt to fix it.
                    $phpcsFile->addError($error, $stackPtr, 'SpaceAfterKeyword');
                } else {
                    $fix = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterKeyword');
                    if ($fix === true) {
                        $phpcsFile->fixer->beginChangeset();
                        for ($i = ($stackPtr + 1); $i < $arrayStart; $i++) {
                            $phpcsFile->fixer->replaceToken($i, '');
                        }

                        $phpcsFile->fixer->endChangeset();
                    }
                }
            }
        } else {
            $phpcsFile->recordMetric($stackPtr, 'Short array syntax used', 'yes');
            $arrayStart = $stackPtr;
            $arrayEnd = $tokens[$stackPtr]['bracket_closer'];
        }

        if ($tokens[$arrayStart]['line'] === $tokens[$arrayEnd]['line']) {
            $this->processSingleLineArray($phpcsFile, $stackPtr, $arrayStart, $arrayEnd);
        } else {
            $this->processMultiLineArray($phpcsFile, $stackPtr, $arrayStart, $arrayEnd);
        }
    }

    /**
     * Processes a single-line array definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int $stackPtr The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int $arrayStart The token that starts the array definition.
     * @param int $arrayEnd The token that ends the array definition.
     *
     * @return void
     */
    public function processSingleLineArray($phpcsFile, $stackPtr, $arrayStart, $arrayEnd)
    {
        $tokens = $phpcsFile->getTokens();

        // Check if there are multiple values. If so, then it has to be multiple lines
        // unless it is contained inside a function call or condition.
        $valueCount = 0;
        $commas = [];
        for ($i = ($arrayStart + 1); $i < $arrayEnd; $i++) {
            // Skip bracketed statements, like function calls.
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $i = $tokens[$i]['parenthesis_closer'];
                continue;
            }

            if ($tokens[$i]['code'] === T_COMMA) {
                // Before counting this comma, make sure we are not
                // at the end of the array.
                $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), $arrayEnd, true);
                if ($next !== false) {
                    $valueCount++;
                    $commas[] = $i;
                }
            }
        }

        // Now check each of the double arrows (if any).
        $nextArrow = $arrayStart;
        while (($nextArrow = $phpcsFile->findNext(T_DOUBLE_ARROW, ($nextArrow + 1), $arrayEnd)) !== false) {
            if ($tokens[($nextArrow - 1)]['code'] !== T_WHITESPACE) {
                $content = $tokens[($nextArrow - 1)]['content'];
                $error = 'Expected 1 space between "%s" and double arrow; 0 found';
                $data = [$content];
                $fix = $phpcsFile->addFixableError($error, $nextArrow, 'NoSpaceBeforeDoubleArrow', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->addContentBefore($nextArrow, ' ');
                }
            } else {
                $spaceLength = $tokens[($nextArrow - 1)]['length'];
                if ($spaceLength !== 1) {
                    $content = $tokens[($nextArrow - 2)]['content'];
                    $error = 'Expected 1 space between "%s" and double arrow; %s found';
                    $data = [
                        $content,
                        $spaceLength,
                    ];

                    $fix = $phpcsFile->addFixableError($error, $nextArrow, 'SpaceBeforeDoubleArrow', $data);
                    if ($fix === true) {
                        $phpcsFile->fixer->replaceToken(($nextArrow - 1), ' ');
                    }
                }
            }

            if ($tokens[($nextArrow + 1)]['code'] !== T_WHITESPACE) {
                $content = $tokens[($nextArrow + 1)]['content'];
                $error = 'Expected 1 space between double arrow and "%s"; 0 found';
                $data = [$content];
                $fix = $phpcsFile->addFixableError($error, $nextArrow, 'NoSpaceAfterDoubleArrow', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->addContent($nextArrow, ' ');
                }
            } else {
                $spaceLength = $tokens[($nextArrow + 1)]['length'];
                if ($spaceLength !== 1) {
                    $content = $tokens[($nextArrow + 2)]['content'];
                    $error = 'Expected 1 space between double arrow and "%s"; %s found';
                    $data = [
                        $content,
                        $spaceLength,
                    ];

                    $fix = $phpcsFile->addFixableError($error, $nextArrow, 'SpaceAfterDoubleArrow', $data);
                    if ($fix === true) {
                        $phpcsFile->fixer->replaceToken(($nextArrow + 1), ' ');
                    }
                }
            }
        }

        if ($valueCount > 0) {
            // We have a multiple value array that is inside a condition or
            // function. Check its spacing is correct.
            foreach ($commas as $comma) {
                if ($tokens[($comma + 1)]['code'] !== T_WHITESPACE) {
                    $content = $tokens[($comma + 1)]['content'];
                    $error = 'Expected 1 space between comma and "%s"; 0 found';
                    $data = [$content];
                    $fix = $phpcsFile->addFixableError($error, $comma, 'NoSpaceAfterComma', $data);
                    if ($fix === true) {
                        $phpcsFile->fixer->addContent($comma, ' ');
                    }
                } else {
                    $spaceLength = $tokens[($comma + 1)]['length'];
                    if ($spaceLength !== 1) {
                        $content = $tokens[($comma + 2)]['content'];
                        $error = 'Expected 1 space between comma and "%s"; %s found';
                        $data = [
                            $content,
                            $spaceLength,
                        ];

                        $fix = $phpcsFile->addFixableError($error, $comma, 'SpaceAfterComma', $data);
                        if ($fix === true) {
                            $phpcsFile->fixer->replaceToken(($comma + 1), ' ');
                        }
                    }
                }

                if ($tokens[($comma - 1)]['code'] === T_WHITESPACE) {
                    $content = $tokens[($comma - 2)]['content'];
                    $spaceLength = $tokens[($comma - 1)]['length'];
                    $error = 'Expected 0 spaces between "%s" and comma; %s found';
                    $data = [
                        $content,
                        $spaceLength,
                    ];

                    $fix = $phpcsFile->addFixableError($error, $comma, 'SpaceBeforeComma', $data);
                    if ($fix === true) {
                        $phpcsFile->fixer->replaceToken(($comma - 1), '');
                    }
                }
            }
        }
    }

    /**
     * Processes a multi-line array definition.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int $stackPtr The position of the current token
     *                                                in the stack passed in $tokens.
     * @param int $arrayStart The token that starts the array definition.
     * @param int $arrayEnd The token that ends the array definition.
     *
     * @return void
     */
    public function processMultiLineArray($phpcsFile, $stackPtr, $arrayStart, $arrayEnd)
    {
        return;
    }

}
