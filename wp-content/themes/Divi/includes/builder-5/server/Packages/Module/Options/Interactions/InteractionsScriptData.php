<?php
/**
 * Module Options: Interactions Script Data Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Interactions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Options\Interactions\InteractionUtils;

/**
 * Module Options: Interactions script data
 *
 * @since ??
 */
class InteractionsScriptData {

	/**
	 * Set the interactions data item.
	 *
	 * This function sets the interactions data item by registering it with the ScriptData class.
	 * The interactions data item contains information such as the module ID, selector, and interaction configurations.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments.
	 *
	 *     @type string $id            Optional. The module ID. Default empty string.
	 *     @type string $selector      Optional. The selector for the module element. Default empty string.
	 *     @type array  $attr          Optional. The interactions attributes. Default `[]`.
	 *     @type int    $storeInstance Optional. The ID of instance where this block stored in BlockParserStore.
	 *                                 Default `null`.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 * InteractionsScriptData::set( [
	 *     'id'            => 'divi/blurb-0',
	 *     'selector'      => '.et_pb_blurb_0',
	 *     'attr'          => [
	 *       'desktop' => [
	 *         'value' => [
	 *           'interactions' => [...]
	 *         ]
	 *       ]
	 *     ],
	 *     'storeInstance' => 123,
	 * ] );
	 * ```
	 */
	public static function set( array $args ): void {
		$args = wp_parse_args(
			$args,
			[
				'id'            => '',
				'selector'      => '',
				'attr'          => [],
				'storeInstance' => null,
			]
		);

		if ( ! InteractionUtils::has_interactions( $args['attr'] ) ) {
			return;
		}

		// Generate interactions data.
		$processed_interactions = self::generate_data( $args );

		// Register script data item.
		ScriptData::add_data_item(
			[
				'data_name'    => 'interactions',
				'data_item_id' => $args['id'],
				'data_item'    => $processed_interactions,
			]
		);

		// Register preset selectors as used so their CSS gets rendered.
		if ( ! empty( $processed_interactions ) ) {
			self::_register_preset_selectors_as_used( $processed_interactions );
		}
	}



	/**
	 * Generate interactions data for the frontend script.
	 *
	 * @since ??
	 *
	 * @param array $args The arguments containing module info and attributes.
	 *
	 * @return array The generated interactions data.
	 */
	public static function generate_data( array $args ): array {
		$id   = $args['id'] ?? '';
		$attr = $args['attr'] ?? [];

		if ( empty( $attr ) ) {
			return [];
		}

		// Get interactions from desktop value (interactions don't support responsive breakpoints).
		$interactions_value = $attr['desktop']['value'] ?? [];
		$interactions       = $interactions_value['interactions'] ?? [];

		if ( empty( $interactions ) ) {
			return [];
		}

		// Process interactions for frontend consumption.
		$processed_interactions = [];
		foreach ( $interactions as $interaction ) {

			// Use stored trigger class or generate one as fallback (for element that gets interacted with).
			$trigger_class = $interaction['triggerClass'] ?? 'et-interaction-trigger-' . substr( md5( $id . '-trigger' ), 0, 8 );

			$processed_interactions[] = [
				'id'                    => $interaction['id'] ?? '',
				'triggerClass'          => $trigger_class, // Element that gets interacted with.
				'trigger'               => $interaction['trigger'] ?? '',
				'effect'                => $interaction['effect'] ?? '',
				'target'                => [ // Element that receives the effect.
					'targetClass' => $interaction['target']['targetClass'] ?? '',
					'label'       => $interaction['target']['label'] ?? '',
				],
				'attributeName'         => $interaction['attributeName'] ?? '',
				'attributeValue'        => $interaction['attributeValue'] ?? '',
				'cookieName'            => $interaction['cookieName'] ?? '',
				'cookieValue'           => $interaction['cookieValue'] ?? '',
				'timeDelay'             => $interaction['timeDelay'] ?? '0ms',
				'presetId'              => $interaction['presetId'] ?? '',
				'replaceExistingPreset' => $interaction['replaceExistingPreset'] ?? false,
				'sensitivity'           => $interaction['sensitivity'] ?? 50, // Sensitivity for mirror mouse movement effect (0-100).
				'mouseMovementType'     => $interaction['mouseMovementType'] ?? 'translate', // Type of mouse movement effect (translate, scale, opacity, tilt, rotate).
				'enabled'               => true,
			];
		}

		if ( empty( $processed_interactions ) ) {
			return [];
		}

		// Return just the interactions array since ScriptData will use module ID as key.
		return $processed_interactions;
	}

	/**
	 * Register preset selectors as used so their CSS gets rendered by the existing preset system.
	 *
	 * @since ??
	 *
	 * @param array $interactions Array of processed interactions.
	 *
	 * @return void
	 */
	private static function _register_preset_selectors_as_used( array $interactions ): void {
		$fake_blocks = [];

		foreach ( $interactions as $interaction ) {
			if ( false === strpos( $interaction['effect'], 'Preset' ) || empty( $interaction['presetId'] ) ) {
				continue;
			}

			$preset_id = $interaction['presetId'];

			// Find the preset data to determine its module type.
			$preset_data = \ET\Builder\Packages\GlobalData\GlobalPreset::find_preset_data_by_id( $preset_id );

			if ( ! $preset_data ) {
				continue;
			}

			// Get module name and ensure it has the divi/ prefix.
			$module_name = $preset_data['moduleName'] ?? 'text';
			$block_name  = str_starts_with( $module_name, 'divi/' ) ? $module_name : 'divi/' . $module_name;

			// Create a fake block with the preset assigned.
			$fake_blocks[] = sprintf(
				'<!-- wp:%s {"modulePreset":"%s"} --><!-- /wp:%s -->',
				$block_name,
				$preset_id,
				$block_name
			);
		}

		if ( ! empty( $fake_blocks ) ) {
			$fake_content = implode( "\n", $fake_blocks );

			// Process the fake content to trigger preset CSS generation.
			// This will call the normal module rendering pipeline which handles preset CSS.
			do_blocks( $fake_content );
		}
	}


}
