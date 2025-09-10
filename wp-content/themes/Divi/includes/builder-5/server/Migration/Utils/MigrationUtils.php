<?php
/**
 * Migration Utilities
 *
 * Shared utility functions for migration classes.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;

/**
 * Migration Utilities Class.
 *
 * @since ??
 */
class MigrationUtils {

	/**
	 * Get current post content.
	 *
	 * @since ??
	 *
	 * @return string|null Post content or null if not in post context.
	 */
	public static function get_current_content(): ?string {
		global $post;
		return $post instanceof \WP_Post ? get_the_content( null, false, $post ) : null;
	}

	/**
	 * Ensure content is wrapped with wp:divi/placeholder block.
	 *
	 * This is needed because later `MigrationUtils::serialize_blocks` is used and it uses
	 * `get_comment_delimited_block_content()` that will skip the direct child of `divi/root`, causing $content
	 * without placeholder block to be broken: only the first row gets rendered, the rest is gone.
	 *
	 * @since ??
	 *
	 * @param string $content The content to wrap.
	 *
	 * @return string The wrapped content.
	 */
	public static function ensure_placeholder_wrapper( string $content ): string {
		$is_wrapped = '' !== $content &&
			strpos( $content, '<!-- wp:divi/placeholder -->' ) === 0 &&
			strpos( $content, '<!-- /wp:divi/placeholder -->' ) !== false;

		if ( ! $is_wrapped ) {
			$content = "<!-- wp:divi/placeholder -->\n" . $content . "\n<!-- /wp:divi/placeholder -->";
		}

		return $content;
	}

	/**
	 * Convert flat module objects back to block array structure.
	 *
	 * @since ??
	 *
	 * @param array $flat_objects The flat module objects.
	 * @return array The block array structure.
	 */
	public static function flat_objects_to_blocks( array $flat_objects ): array {
		// Find the root object.
		$root = null;
		foreach ( $flat_objects as $object ) {
			if ( isset( $object['parent'] ) && ( null === $object['parent'] || 'root' === $object['parent'] ) ) {
				$root = $object;
				break;
			}
		}
		if ( ! $root ) {
			return [];
		}
		return array_map(
			function( $child_id ) use ( $flat_objects ) {
				return self::build_block_from_flat( $child_id, $flat_objects );
			},
			$root['children']
		);
	}

	/**
	 * Recursively build a block from a flat object.
	 *
	 * @since ??
	 *
	 * @param string $id The object ID.
	 * @param array  $flat_objects The flat module objects.
	 * @return array The block array.
	 */
	public static function build_block_from_flat( string $id, array $flat_objects ): array {
		$object = $flat_objects[ $id ];
		$block  = [
			'blockName'    => $object['name'],
			'attrs'        => $object['props']['attrs'] ?? [],
			'innerBlocks'  => [],
			'innerContent' => [],
		];
		if ( ! empty( $object['children'] ) ) {
			foreach ( $object['children'] as $child_id ) {
				$block['innerBlocks'][]  = self::build_block_from_flat( $child_id, $flat_objects );
				$block['innerContent'][] = null; // Placeholder, will be filled by serializer.
			}
		}
		if ( isset( $object['props']['innerHTML'] ) ) {
			$block['innerContent'][] = $object['props']['innerHTML'];
		}
		return $block;
	}

	/**
	 * Get parent column type for a module.
	 *
	 * Module hierarchy is typically: Column → Parent Module → Child Module
	 * So we traverse to the grandparent (column) to get the column type.
	 *
	 * @since ??
	 *
	 * @param array $module_data The child module data.
	 * @param array $flat_objects All flat module objects.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	public static function get_parent_column_type( array $module_data, array $flat_objects ): ?string {
		// Get parent (parent module).
		$parent_id = $module_data['parent'] ?? null;
		if ( ! $parent_id || ! isset( $flat_objects[ $parent_id ] ) ) {
			return null;
		}

		// Get grandparent (column).
		$parent_module  = $flat_objects[ $parent_id ];
		$grandparent_id = $parent_module['parent'] ?? null;
		if ( ! $grandparent_id || ! isset( $flat_objects[ $grandparent_id ] ) ) {
			return null;
		}

		$grandparent_module = $flat_objects[ $grandparent_id ];
		if ( in_array( $grandparent_module['name'], [ 'divi/column', 'divi/column-inner' ], true ) ) {
			// First check for old type attribute.
			$column_type = $grandparent_module['props']['attrs']['module']['advanced']['type']['desktop']['value'] ?? null;

			// If not found, check for flexType and map it back to old column type.
			if ( ! $column_type ) {
				$flex_type   = $grandparent_module['props']['attrs']['module']['advanced']['flexType']['desktop']['value'] ?? null;
				$column_type = self::_map_flex_type_to_column_type( $flex_type );
			}

			return $column_type;
		}

		return null;
	}

	/**
	 * Map flexType values back to old column type system.
	 *
	 * @since ??
	 *
	 * @param string|null $flex_type The flexType value.
	 *
	 * @return string|null The corresponding old column type.
	 */
	private static function _map_flex_type_to_column_type( ?string $flex_type ): ?string {
		if ( ! $flex_type ) {
			return null;
		}

		// Map flexType values to old column types.
		$flex_to_column_map = [
			'24_24' => '4_4',   // 100% width
			'18_24' => '3_4',   // 75% width
			'16_24' => '2_3',   // 66.67% width
			'14_24' => '7_12',  // 58.33% width
			'12_24' => '1_2',   // 50% width
			'10_24' => '5_12',  // 41.67% width
			'9_24'  => '3_8',   // 37.5% width
			'8_24'  => '1_3',   // 33.33% width
			'6_24'  => '1_4',   // 25% width
			'4_24'  => '1_6',   // 16.67% width
			'3_24'  => '1_8',   // 12.5% width
			// Add more mappings as needed
		];

		return $flex_to_column_map[ $flex_type ] ?? null;
	}

	/**
	 * Get parent column type from shortcode context.
	 *
	 * For shortcodes, the hierarchy is typically:
	 * Column -> Parent Module -> Child Module
	 * So we need to find the grandparent (column) via the parent module.
	 *
	 * @since ??
	 *
	 * @param array $shortcode The shortcode data.
	 * @param array $all_shortcodes All shortcodes array for context.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	public static function get_parent_column_type_from_shortcode( array $shortcode, array $all_shortcodes ): ?string {
		// Find the parent module in the nested shortcode structure.
		$current_context = $all_shortcodes;

		// Recursively search for this shortcode within the shortcode hierarchy.
		// to determine its parent column.
		$parent_column_type = self::find_parent_column_in_shortcodes( $shortcode, $current_context );

		return $parent_column_type;
	}

	/**
	 * Recursively find parent column type in shortcode hierarchy.
	 *
	 * @since ??
	 *
	 * @param array $target_shortcode The shortcode to find parent for.
	 * @param array $shortcodes The shortcodes to search in.
	 * @param array $parent_stack Stack of parent shortcodes.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	public static function find_parent_column_in_shortcodes( array $target_shortcode, array $shortcodes, array $parent_stack = [] ): ?string {
		foreach ( $shortcodes as $shortcode ) {
			// Check if this is a column.
			if ( in_array( $shortcode['name'], [ 'et_pb_column', 'et_pb_column_inner' ], true ) ) {
				// Check if target shortcode is nested within this column.
				if ( self::is_shortcode_nested_in( $target_shortcode, $shortcode ) ) {
					return $shortcode['attributes']['type'] ?? null;
				}
			}

			// If this shortcode has nested content, search recursively.
			if ( isset( $shortcode['content'] ) && is_array( $shortcode['content'] ) ) {
				$new_parent_stack = array_merge( $parent_stack, [ $shortcode ] );
				$result           = self::find_parent_column_in_shortcodes( $target_shortcode, $shortcode['content'], $new_parent_stack );
				if ( $result ) {
					return $result;
				}
			}
		}

		return null;
	}

	/**
	 * Check if a target shortcode is nested within a parent shortcode.
	 *
	 * @since ??
	 *
	 * @param array $target_shortcode The shortcode to find.
	 * @param array $parent_shortcode The parent shortcode to search in.
	 *
	 * @return bool True if target is nested within parent.
	 */
	public static function is_shortcode_nested_in( array $target_shortcode, array $parent_shortcode ): bool {
		// If parent has no content, target can't be nested.
		if ( ! isset( $parent_shortcode['content'] ) || ! is_array( $parent_shortcode['content'] ) ) {
			return false;
		}

		// Search through all nested content.
		foreach ( $parent_shortcode['content'] as $child_shortcode ) {
			// Direct match.
			if ( $child_shortcode === $target_shortcode ) {
				return true;
			}

			// Check if target is nested deeper.
			if ( isset( $child_shortcode['content'] ) && is_array( $child_shortcode['content'] ) ) {
				if ( self::is_shortcode_nested_in( $target_shortcode, $child_shortcode ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Serialize an array of blocks into a string.
	 *
	 * This function takes an array of blocks and converts them into a concatenated string representation.
	 * Each block in the array is individually serialized using the `serialize_block` method and then
	 * joined together without any separators to form the final output.
	 *
	 * @since ??
	 *
	 * @param array $blocks Array of blocks to be serialized.
	 *
	 * @return string The serialized blocks as a concatenated string.
	 *
	 * @example
	 * ```php
	 * $blocks = [
	 *     [
	 *         'blockName' => 'core/paragraph',
	 *         'attrs' => ['content' => 'Hello World'],
	 *         'innerBlocks' => [],
	 *         'innerContent' => ['Hello World']
	 *     ],
	 *     [
	 *         'blockName' => 'core/heading',
	 *         'attrs' => ['level' => 2, 'content' => 'My Heading'],
	 *         'innerBlocks' => [],
	 *         'innerContent' => ['My Heading']
	 *     ]
	 * ];
	 *
	 * $serialized = MigrationUtils::serialize_blocks($blocks);
	 *
	 * // Output: Concatenated string of all serialized blocks
	 * ```
	 */
	public static function serialize_blocks( array $blocks ): string {
		return implode( '', array_map( array( __CLASS__, 'serialize_block' ), $blocks ) );
	}

	/**
	 * Serialize a single block into a string.
	 *
	 * This function takes a single block array and converts it into its serialized string representation.
	 * The function processes the block's inner content, recursively serializing any nested inner blocks,
	 * and handles block attributes by removing empty array attributes. The final output is generated
	 * using WordPress's `get_comment_delimited_block_content` function.
	 *
	 * @since ??
	 *
	 * @param array $block {
	 *     The block to be serialized.
	 *
	 *     @type string $blockName     The name of the block (e.g., 'core/paragraph', 'divi/text').
	 *     @type array  $attrs         Optional. The block attributes. Default empty array.
	 *     @type array  $innerBlocks   Optional. Array of nested blocks. Default empty array.
	 *     @type array  $innerContent  Optional. Array of content chunks, can contain strings or references to inner blocks.
	 * }
	 *
	 * @return string The serialized block as a comment-delimited string.
	 *
	 * @example
	 * ```php
	 * $block = [
	 *     'blockName' => 'core/paragraph',
	 *     'attrs' => [
	 *         'content' => 'Hello World',
	 *         'className' => 'my-paragraph'
	 *     ],
	 *     'innerBlocks' => [],
	 *     'innerContent' => ['Hello World']
	 * ];
	 *
	 * $serialized = MigrationUtils::serialize_block($block);
	 *
	 * // Output: <!-- wp:core/paragraph {"content":"Hello World","className":"my-paragraph"} -->
	 * //         Hello World
	 * //         <!-- /wp:core/paragraph -->
	 * ```
	 */
	public static function serialize_block( array $block ): string {
		$block_content = '';

		$index = 0;

		foreach ( $block['innerContent'] as $chunk ) {
			$block_content .= is_string( $chunk ) ? $chunk : self::serialize_block( $block['innerBlocks'][ $index++ ] );
		}

		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			$block['attrs'] = [];
		}

		if ( ! empty( $block['attrs'] ) ) {
			$block['attrs'] = ModuleUtils::remove_empty_array_attributes( $block['attrs'] );
		}

		return get_comment_delimited_block_content(
			$block['blockName'],
			$block['attrs'],
			$block_content
		);
	}

	/**
	 * Parse serialized post content into a flat module object structure for migration purposes.
	 *
	 * This method sets up a temporary layout context for the migration process, parses the serialized
	 * post content into a flat associative array of module objects, and then cleans up the context
	 * to prevent conflicts with subsequent rendering operations.
	 *
	 * The method is primarily used by migration classes (such as GlobalColorMigration and FlexboxMigration)
	 * to convert legacy serialized post data into the new flat module object format required by Divi 5.
	 *
	 * @since ??
	 *
	 * @param string $content The serialized post content to parse. This should contain the raw
	 *                        serialized data from the database that represents the post's module structure.
	 * @param string $migration_name The name of the migration being performed. This is used to
	 *                               identify the layout context during parsing and should typically
	 *                               match the migration class name (e.g., 'GlobalColorMigration').
	 *
	 * @return array A flat associative array where keys are module IDs and values are module objects.
	 *               Each module object contains:
	 *               - 'id': The unique identifier for the module
	 *               - 'name': The module type/name (e.g., 'divi/row', 'divi/column')
	 *               - 'props': Module properties including attributes and settings
	 *               - 'children': Array of child module IDs (if any)
	 *               - 'parent': Parent module ID (if not root)
	 *               The array also includes a root module with ID 'root' that serves as the
	 *               top-level container for all other modules.
	 *
	 * @example
	 * ```php
	 * $content = get_post_meta($post_id, '_et_pb_old_content', true);
	 * $flat_objects = MigrationUtils::parseSerializedPostIntoFlatModuleObject($content, 'GlobalColorMigration');
	 *
	 * // Access root module
	 * $root = $flat_objects['root'];
	 *
	 * // Access specific module by ID
	 * $module = $flat_objects['some-module-id'];
	 * ```
	 *
	 * @see ConversionUtils::parseSerializedPostIntoFlatModuleObject() The underlying conversion method
	 * @see BlockParserStore::set_layout() For layout context management
	 * @see BlockParserStore::reset_layout() For layout cleanup
	 * @see BlockParserBlock::reset_order_index() For order index cleanup
	 */
	public static function parse_serialized_post_into_flat_module_object( string $content, string $migration_name ): array {
		BlockParserStore::set_layout(
			[
				'id'   => $migration_name,
				'type' => 'migration',
			]
		);

		$flat_objects = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $content );

		// Reset the block parser store and order index to avoid conflicts with rendering.
		BlockParserBlock::reset_order_index();

		BlockParserStore::reset_layout();

		return $flat_objects;
	}
}
