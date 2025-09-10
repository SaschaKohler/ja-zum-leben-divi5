<?php
/**
 * FormattingUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * FormattingUtility class.
 *
 * This class contains methods for formatting text.
 *
 * @since ??
 */
class FormattingUtility {

	/**
	 * Conditionally applies wpautop to a string.
	 *
	 * This method intelligently determines whether to apply wpautop based on content type:
	 * - For HTML content: Only applies wpautop for consecutive newlines or consecutive `<br>` tags
	 * - For plain text: Applies wpautop for any newlines to preserve formatting
	 *
	 * @since ??
	 *
	 * @param string $string The input string to process.
	 * @return string The processed string, with wpautop applied if conditions are met.
	 */
	public static function maybe_wpautop( string $string ): string {
		// If string contains HTML tags (excluding br tags), be conservative.
		// Only process consecutive newlines on HTML content.
		// Regex101: https://regex101.com/r/k0YIVz/1.
		$new_lines = preg_match( '/<(?!br\s*\/?>)[^>]+>/', $string ) ? "\n\n" : "\n";

		if ( false !== strpos( $string, $new_lines ) || preg_match( '|<br\s*/?>\s*<br\s*/?>|', $string ) ) {
			return wpautop( $string );
		}

		return $string;
	}
}
