<?php
/**
 * Module: LinkScriptData class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Link;

use ET\Builder\Packages\Module\Options\Link\LinkUtils;
use ET\Builder\Packages\Module\Options\Loop\LoopContext;
use ET\Builder\FrontEnd\Module\ScriptData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LinkScriptData class.
 *
 * This class provides functionality for setting properties of a link script data object.
 *
 * @since ??
 */
class LinkScriptData {

	/**
	 * Set script data for link options.
	 *
	 * This function generates script data for link options based on the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id            Optional. The module ID. Example: `divi/cta-0`. Default empty string.
	 *     @type string $selector      Optional. The module selector. Example: `.et_pb_cta_0`. Default empty string.
	 *     @type array  $attr          Optional. The module link group attributes. Default `[]`.
	 *     @type array  $module_attrs  Optional. Complete module attributes for context. Default `[]`.
	 *     @type int    $storeInstance Optional. The ID of the instance where this block is stored in the BlockParserStore. Default `null`.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 * ET_Core_Cache::set( [
	 *     'id'            => 'divi/cta-0',
	 *     'selector'      => '.et_pb_cta_0',
	 *     'attr'          => [],
	 *     'module_attrs'  => [],
	 *     'storeInstance' => null
	 * ] );
	 * ```
	 */
	public static function set( array $args ): void {
		$data = LinkUtils::generate_data(
			[
				'id'            => $args['id'] ?? '',
				'selector'      => $args['selector'] ?? '',
				'attr'          => $args['attr'] ?? [],
				'module_attrs'  => $args['module_attrs'] ?? [],
				'storeInstance' => $args['storeInstance'] ?? null,
			]
		);

		if ( ! $data ) {
			return;
		}

		// Check if we're in a loop context.
		$loop_context   = LoopContext::get();
		$loop_iteration = $loop_context ? $loop_context->get_current_iteration() : null;

		if ( null !== $loop_iteration && is_int( $loop_iteration ) && $loop_iteration >= 0 ) {
			// Loop module: use et_clickable_loop_X format.
			$safe_iteration = max( 0, min( 9999, $loop_iteration ) );
			$data_item_id   = 'et_clickable_loop_' . $safe_iteration;
			$data['class']  = $data_item_id;
		} else {
			// Regular module: use the actual module class name.
			$data_item_id = $data['class'] ?? '';
			if ( empty( $data_item_id ) ) {
				$data_item_id = ltrim( $args['selector'] ?? '', '.' );
			}
			if ( empty( $data_item_id ) ) {
				$data_item_id = $args['id'] ?? 'et_clickable';
			}
			$data['class'] = $data_item_id;
		}

		// Register script data item.
		ScriptData::add_data_item(
			[
				'data_name'    => 'link',
				'data_item_id' => $data_item_id,
				'data_item'    => $data,
			]
		);
	}

}
