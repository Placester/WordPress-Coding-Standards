<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\Arrays;

use PHPCSUtils\Tokens\Collections;
use PHPCSUtils\Utils\Arrays;
use PHPCSUtils\Utils\PassedParameters;
use WordPressCS\WordPress\Sniff;

/**
 * Enforces alignment of the double arrow assignment operator to be exactly one space.
 */
final class ArrayDoubleArrowSpacingSniff extends Sniff {
    public function register() {
        return Collections::arrayOpenTokensBC();
    }

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 0.14.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
    public function process_token( $stackPtr ) {
		if ( isset( Collections::shortArrayListOpenTokensBC()[ $this->tokens[ $stackPtr ]['code'] ] )
			&& Arrays::isShortArray( $this->phpcsFile, $stackPtr ) === false
		) {
			// Short list, not short array.
			return;
		}

		/*
		 * Determine the array opener & closer.
		 */
		$array_open_close = Arrays::getOpenClose( $this->phpcsFile, $stackPtr );
		if ( false === $array_open_close ) {
			// Array open/close could not be determined.
			return;
		}

		$opener = $array_open_close['opener'];
		$closer = $array_open_close['closer'];

		$array_items = PassedParameters::getParameters( $this->phpcsFile, $stackPtr );
		if ( empty( $array_items ) ) {
			return;
		}

		// Pass off to either the single line or multi-line array analysis.
		if ( $this->tokens[ $opener ]['line'] === $this->tokens[ $closer ]['line'] ) {
			return $this->process_single_line_array( $stackPtr, $array_items, $opener, $closer );
		} else {
			return $this->process_multi_line_array( $stackPtr, $array_items, $opener, $closer );
		}
    }

	/**
	 * Process a single-line array.
	 *
	 * While the WP standard does not allow single line multi-item associative arrays,
	 * this sniff should function independently of that.
	 *
	 * The `WordPress.WhiteSpace.OperatorSpacing` sniff already covers checking that
	 * there is a space between the array key and the double arrow, but doesn't
	 * enforce it to be exactly one space for single line arrays.
	 * That is what this method covers.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $stackPtr The position of the current token in the stack.
	 * @param array $items    Info array containing information on each array item.
	 * @param int   $opener   The position of the array opener.
	 * @param int   $closer   The position of the array closer.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	protected function process_single_line_array( $stackPtr, $items, $opener, $closer ) {
		/*
		 * For single line arrays, we don't care about what level the arrow is from.
		 * Just find and fix them all.
		 */
		$next_arrow = $this->phpcsFile->findNext(
			\T_DOUBLE_ARROW,
			( $opener + 1 ),
			$closer
		);

		while ( false !== $next_arrow ) {
			if ( \T_WHITESPACE === $this->tokens[ ( $next_arrow - 1 ) ]['code'] ) {
				$space_length = $this->tokens[ ( $next_arrow - 1 ) ]['length'];
				if ( 1 !== $space_length ) {
					$error = 'Expected 1 space between "%s" and double arrow; %s found';
					$data  = array(
						$this->tokens[ ( $next_arrow - 2 ) ]['content'],
						$space_length,
					);

					$fix = $this->phpcsFile->addFixableWarning( $error, $next_arrow, 'SpaceBeforeDoubleArrow', $data );
					if ( true === $fix ) {
						$this->phpcsFile->fixer->replaceToken( ( $next_arrow - 1 ), ' ' );
					}
				}
			}

			// Find the position of the next double arrow.
			$next_arrow = $this->phpcsFile->findNext(
				\T_DOUBLE_ARROW,
				( $next_arrow + 1 ),
				$closer
			);
		}

		// Ignore any child-arrays as the double arrows in these will already have been handled.
		return ( $closer + 1 );
	}

	/**
	 * Process a multi-line array.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $stackPtr The position of the current token in the stack.
	 * @param array $items    Info array containing information on each array item.
	 * @param int   $opener   The position of the array opener.
	 * @param int   $closer   The position of the array closer.
	 *
	 * @return void
	 */
    protected function process_multi_line_array( $stackPtf, $items, $opener, $closer ) {
        foreach ( $items as $key => $item ) {
            $double_arrow = Arrays::getDoubleArrowPtr( $this->phpcsFile, $item['start'], $item['end'] );

			if ( false === $double_arrow ) {
				unset( $items[ $key ] );
				continue;
			}

			// Find the end of the array key.
			$last_index_token = $this->phpcsFile->findPrevious(
				\T_WHITESPACE,
				( $double_arrow - 1 ),
				$item['start'],
				true
			);

			$item['operatorPtr']      = $double_arrow;
			$item['last_index_token'] = $last_index_token;

			$key_end_column = $this->tokens[ $last_index_token ]['column'] + $this->tokens[ $last_index_token ]['length'];
			$double_arrow_start = $this->tokens[ $double_arrow ]['column'];
			$result = $double_arrow_start - $key_end_column;

			if ( $result === 1 ) {
				continue;
			}

			if ( \T_WHITESPACE !== $this->tokens[ ( $item['operatorPtr'] - 1 ) ]['code'] ) {
				$before = 0;
			} elseif ( $this->tokens[ $item['last_index_token'] ]['line'] !== $this->tokens[ $item['operatorPtr'] ]['line'] ) {
				$before = 'newline';
			} else {
				$before = $this->tokens[ ( $item['operatorPtr'] - 1 ) ]['length'];
			}

			$fix = $this->phpcsFile->addFixableWarning(
				'Expected 1 space between "%s" and double arrow; %s found.',
				$item['operatorPtr'],
				'TooManySpaces',
				array(
					$this->tokens[ $item['last_index_token'] ]['content'],
					$before,
				)
			);

			if ( true === $fix ) {
				$this->phpcsFile->fixer->beginChangeset();

				// Remove whitespace tokens between the end of the index and the arrow, if any.
				for ( $i = ( $item['last_index_token'] + 1 ); $i < $item['operatorPtr']; $i++ ) {
					$this->phpcsFile->fixer->replaceToken( $i, '' );
				}

				// Add the correct whitespace.
				$this->phpcsFile->fixer->addContent( $item['last_index_token'], ' ' );

				$this->phpcsFile->fixer->endChangeset();
			}
        }
    }
}
