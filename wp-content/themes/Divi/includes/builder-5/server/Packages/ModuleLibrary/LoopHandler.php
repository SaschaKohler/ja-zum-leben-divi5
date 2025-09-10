<?php
/**
 * ModuleLibrary: Loop Handler class
 *
 * Centralizes loop logic for Divi modules, mirroring the Visual Builder's approach.
 * Supports multiple query types: post_types, post_taxonomies, user_roles.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Loop\LoopUtils;

/**
 * LoopHandler class.
 *
 * This class provides utilities for wrapping render callbacks with loop handling logic.
 *
 * @since ??
 */
class LoopHandler {

	/**
	 * Wraps a render callback with loop handling logic.
	 *
	 * @since ??
	 *
	 * @param callable $original_callback The original render callback.
	 *
	 * @return callable The wrapped render callback.
	 */
	public static function wrap_render_callback( callable $original_callback ): callable {
		return function( $attrs, $content, $block, $elements, $default_printed_style_attrs ) use ( $original_callback ) {
			// Check for loop settings in the current module's attributes.
			$is_loop_enabled = LoopUtils::is_loop_enabled( $attrs );

			// Only process loop logic if loop is explicitly enabled for THIS module.
			// This prevents child modules from inheriting parent loop behavior.
			if ( ! $is_loop_enabled ) {
				// No loop enabled for this specific module - render normally.
				return call_user_func(
					$original_callback,
					$attrs,
					$content,
					$block,
					$elements,
					$default_printed_style_attrs
				);
			}

			// Loop is explicitly enabled for this module - handle the iteration.
			return LoopUtils::handle_loop_rendering( $original_callback, $attrs, $content, $block, $elements, $default_printed_style_attrs );
		};
	}
}
