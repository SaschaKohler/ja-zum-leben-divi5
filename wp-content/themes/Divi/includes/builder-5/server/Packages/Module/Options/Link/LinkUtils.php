<?php
/**
 * Module: LinkUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Link;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\Packages\Module\Options\Loop\LoopContext;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * LinkUtils class.
 *
 * @since ??
 */
class LinkUtils {

	/**
	 * Get link options classnames based on given link group attributes.
	 *
	 * @since ??
	 *
	 * @param array $attr         The link group attributes.
	 * @param array $module_attrs Optional. Complete module attributes for context. Default [].
	 *
	 * @return string The return value will be empty string when the link group
	 *                attributes is empty i.e `false === LinkUtils::is_enabled( $attr )`.
	 */
	public static function classnames( array $attr, array $module_attrs = [] ): string {
		if ( ! self::is_enabled( $attr ) ) {
			return '';
		}

		$loop_iteration = self::_get_loop_iteration( $module_attrs );

		if ( null !== $loop_iteration ) {
			$safe_iteration = self::_sanitize_loop_iteration( $loop_iteration );
			return 'et_clickable et_clickable_loop_' . $safe_iteration;
		}

		return 'et_clickable';
	}

	/**
	 * Generate link script data.
	 *
	 * This include the link classnames, URL and target.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $selector      Module selector. Example: `.et_pb_cta_0`.
	 *     @type array  $attr          Module link group attributes.
	 *     @type array  $module_attrs  Optional. Complete module attributes for context. Default [].
	 * }
	 *
	 * @return array The generated link data.
	 *               If the link is not enabled, an empty array is returned.
	 *
	 * @example
	 * ```php
	 * $args = [
	 *     'selector' => '.et_pb_cta_0',
	 *     'attr'     => [
	 *         'desktop' => [
	 *             'value' => [
	 *                 'url'    => 'https://example.com',
	 *                 'target' => 'on',
	 *             ],
	 *         ],
	 *     ],
	 * ];
	 * $animationData = LinkUtils::generate_data( $args );
	 * ```
	 */
	public static function generate_data( array $args ): array {
		$attr         = $args['attr'] ?? [];
		$module_attrs = $args['module_attrs'] ?? [];

		if ( ! self::is_enabled( $attr ) ) {
			return [];
		}

		$resolved_url = self::_resolve_url_if_needed( $attr['desktop']['value']['url'] ?? '' );
		$css_selector = self::_generate_javascript_selector( $args['selector'] ?? '', $module_attrs );

		// Extract the module class name from the selector for the class field.
		// Strip out the CPT prefix '.et-db #et-boc .et-l ' if present.
		$selector     = $args['selector'] ?? '';
		$module_class = '';

		if ( ! empty( $selector ) ) {
			// Remove CPT prefix if present.
			$cleaned_selector = str_replace( '.et-db #et-boc .et-l ', '', $selector );
			$module_class     = ltrim( $cleaned_selector, '.' );
		}

		return [
			'class'  => sanitize_html_class( $module_class ),
			'url'    => esc_url( $resolved_url ),
			'target' => 'on' === ( $attr['desktop']['value']['target'] ?? 'off' ) ? '_blank' : '_self',
		];
	}

	/**
	 * Checks if the link is enabled based on given link group attributes.
	 *
	 * @since ??
	 *
	 * @param array $attr The link group attributes.
	 *
	 * @return bool
	 */
	public static function is_enabled( array $attr ): bool {
		$raw_url = $attr['desktop']['value']['url'] ?? '';

		if ( empty( $raw_url ) ) {
			return false;
		}

		$needs_resolution = self::_needs_dynamic_resolution( $raw_url );

		if ( $needs_resolution ) {
			$resolved_url = self::_resolve_url_if_needed( $raw_url );

			return ! ! esc_url( $resolved_url );
		}

		// For static URLs, validate directly.
		return ! ! esc_url( $raw_url );
	}

	/**
	 * Check if a URL contains dynamic variables that need resolution.
	 *
	 * @since ??
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL contains dynamic variables, false otherwise.
	 */
	private static function _needs_dynamic_resolution( string $url ): bool {
		return false !== strpos( $url, '$variable(' ) && false !== strpos( $url, ')$' );
	}

	/**
	 * Resolve dynamic variables in URL if needed and loop context is available.
	 *
	 * @since ??
	 *
	 * @param string $url The URL that may contain dynamic variables.
	 *
	 * @return string The resolved URL or original URL if no resolution needed/possible.
	 */
	private static function _resolve_url_if_needed( string $url ): string {
		if ( ! self::_needs_dynamic_resolution( $url ) ) {
			return $url;
		}

		$loop_context = LoopContext::get();

		if ( $loop_context ) {
			$query_type  = $loop_context->get_query_type();
			$loop_object = $loop_context->get_result_for_position( 0 );

			$current_post_id = self::_get_current_post_id( $query_type, $loop_object );
			$resolved_url    = DynamicData::get_processed_dynamic_data(
				$url,
				null,
				false,
				$current_post_id,
				$query_type,
				$loop_object
			);

			// If resolution was successful and we got a different result, use it.
			if ( $resolved_url !== $url && ! empty( $resolved_url ) ) {
				return $resolved_url;
			}
		}

		// Fallback: try standard dynamic data processing.
		$resolved_url = DynamicData::get_processed_dynamic_data( $url );

		// If resolution was successful, use the resolved URL, otherwise return original.
		return ( $resolved_url !== $url && ! empty( $resolved_url ) ) ? $resolved_url : $url;
	}

	/**
	 * Get the current post ID based on query type and object.
	 *
	 * @since ??
	 *
	 * @param string $query_type  The query type.
	 * @param mixed  $query_item  The query item (post, term, user, repeater data, etc.).
	 *
	 * @return int|null The current post ID or null if not applicable.
	 */
	private static function _get_current_post_id( string $query_type, $query_item ): ?int {
		switch ( $query_type ) {
			case 'post_types':
			case 'current_page':
				return is_object( $query_item ) && isset( $query_item->ID ) ? (int) $query_item->ID : null;

			case 'terms':
				return is_object( $query_item ) && isset( $query_item->term_id ) ? (int) $query_item->term_id : null;

			case 'user_roles':
				return is_object( $query_item ) && isset( $query_item->ID ) ? (int) $query_item->ID : null;

			default:
				// ACF repeater queries.
				if ( StringUtility::starts_with( $query_type, 'repeater_' ) ) {
					return is_array( $query_item ) && isset( $query_item['_post_id'] ) ? (int) $query_item['_post_id'] : null;
				}

				return null;
		}
	}

	/**
	 * Get the current loop iteration from module attributes or LoopContext.
	 *
	 * This is a centralized helper to eliminate code duplication between
	 * classnames() and _generate_javascript_selector() methods.
	 *
	 * @since ??
	 *
	 * @param array $module_attrs The complete module attributes.
	 *
	 * @return int|null The loop iteration or null if not in a loop context.
	 */
	private static function _get_loop_iteration( array $module_attrs ): ?int {
		$loop_iteration = $module_attrs['__loop_iteration'] ?? null;

		// Fallback: Get directly from LoopContext if not available in module attrs.
		if ( null === $loop_iteration ) {
			$loop_context = LoopContext::get();

			if ( $loop_context ) {
				$loop_iteration = $loop_context->get_current_iteration();
			}
		}

		// Validate that loop iteration is a non-negative integer.
		if ( null !== $loop_iteration && ( ! is_int( $loop_iteration ) || $loop_iteration < 0 ) ) {
			return null;
		}

		return $loop_iteration;
	}

	/**
	 * Sanitize loop iteration for safe use in CSS class names.
	 *
	 * @since ??
	 *
	 * @param int $loop_iteration The loop iteration to sanitize.
	 *
	 * @return string The sanitized loop iteration as a string.
	 */
	private static function _sanitize_loop_iteration( int $loop_iteration ): string {
		// Ensure it's a non-negative integer and within reasonable bounds.
		$safe_iteration = max( 0, min( 9999, (int) $loop_iteration ) );

		return (string) $safe_iteration;
	}

	/**
	 * Generates a CSS selector for JavaScript targeting that combines the base module selector with the loop iteration.
	 *
	 * This is useful when you need to target specific elements within a loop
	 * that have the same base class, but need to be uniquely identifiable.
	 *
	 * @since ??
	 *
	 * @param string $base_selector The base selector for the module.
	 * @param array  $module_attrs  The complete module attributes.
	 *
	 * @return string The generated CSS selector.
	 */
	private static function _generate_javascript_selector( string $base_selector, array $module_attrs ): string {
		$loop_iteration = self::_get_loop_iteration( $module_attrs );

		if ( null !== $loop_iteration ) {
			// Sanitize loop iteration to prevent CSS injection.
			$safe_iteration = self::_sanitize_loop_iteration( $loop_iteration );
			return 'et_clickable_loop_' . $safe_iteration;
		}

		return 'et_clickable';
	}

}
