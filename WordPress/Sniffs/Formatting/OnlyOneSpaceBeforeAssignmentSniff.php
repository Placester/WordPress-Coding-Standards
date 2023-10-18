<?php
/**
 * Checks alignment of assignments.
 *
 * If there are multiple adjacent assignments, it will check that the equals signs of
 * each assignment are aligned. It will display a warning to advise that the signs should be aligned.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace WordPress\Sniffs\Formatting;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class OnlyOneSpaceBeforeAssignmentSniff implements Sniff
{

    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supportedTokenizers = [
        'PHP',
        'JS',
    ];

    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var boolean
     */
    public $error = false;

    /**
     * The maximum amount of padding before the alignment is ignored.
     *
     * If the amount of padding required to align this assignment with the
     * surrounding assignments exceeds this number, the assignment will be
     * ignored and no errors or warnings will be thrown.
     *
     * @var integer
     */
    public $maxPadding = 1000;


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $tokens = Tokens::$assignmentTokens;
        unset($tokens[T_DOUBLE_ARROW]);
        return $tokens;

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Ignore assignments used in a condition, like an IF or FOR.
        if (isset($tokens[$stackPtr]['nested_parenthesis']) === true) {
            foreach ($tokens[$stackPtr]['nested_parenthesis'] as $start => $end) {
                if (isset($tokens[$start]['parenthesis_owner']) === true) {
                    return;
                }
            }
        }

        $lastAssign = $this->checkAlignment($phpcsFile, $stackPtr);
        return ($lastAssign + 1);

    }//end process()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param int                         $end       The token where checking should end.
     *                                               If NULL, the entire file will be checked.
     *
     * @return int
     */
    public function checkAlignment($phpcsFile, $stackPtr, $end=null)
    {
        $tokens = $phpcsFile->getTokens();

        $prev_token = $tokens[ $stackPtr - 1 ];

        if ( $prev_token['type'] !== 'T_WHITESPACE' ) {
            return;
        }

        $length = $prev_token['length'];
        if ( 1 >= $length  ) {
            return;
        }

        $fix = $phpcsFile->addFixableWarning(
            'Expected 1 space between %s and equal sign; %s found.',
            $stackPtr - 1,
            'TooManySpaces',
            array(
                $tokens[ $stackPtr - 2 ]['content'],
                $length
            ),
        );

        if ( true === $fix ) {
            $phpcsFile->fixer->beginChangeset();
            $phpcsFile->fixer->replaceToken( $stackPtr - 1, ' ' );
            $phpcsFile->fixer->endChangeset();
        }
    }//end checkAlignment()


}//end class
