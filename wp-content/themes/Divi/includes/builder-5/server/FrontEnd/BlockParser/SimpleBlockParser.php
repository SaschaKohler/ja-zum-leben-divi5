<?php
/**
 * Simple Gutenberg Block Parser Implementation
 *
 * This file contains the SimpleBlockParser class which provides lightweight
 * Gutenberg block parsing functionality for the Divi theme. The parser extracts
 * basic block information without maintaining complex hierarchical structures,
 * making it suitable for performance-critical scenarios where full block parsing
 * is unnecessary.
 *
 * The parser focuses on extracting block names and attributes from Gutenberg
 * block comments while providing built-in caching for improved performance.
 * It intentionally omits advanced features like parent-child relationships,
 * block ordering, and nested structures to maintain simplicity and speed.
 *
 * @since ??
 * @package Divi\FrontEnd\BlockParser
 */

namespace ET\Builder\FrontEnd\BlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\BlockParser\SimpleBlock;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParserStore;

/**
 * Simple Gutenberg Block Parser
 *
 * A lightweight parser for extracting Gutenberg block information from content.
 * This parser provides basic block extraction functionality without the complexity
 * of maintaining hierarchical relationships or advanced parsing features.
 *
 * Key Features:
 * - Extracts block name and attributes from Gutenberg block comments
 * - Built-in caching for performance optimization
 * - Simple, flat array output structure
 * - Error handling for malformed JSON attributes
 *
 * Limitations:
 * - Does NOT preserve parent-child block relationships
 * - Does NOT maintain block order indices
 * - Does NOT support nested block structures
 * - Does NOT parse block content (only comments)
 * - Does NOT provide block positioning information
 *
 * Use Cases:
 * - Quick block identification and attribute extraction
 * - Performance-critical scenarios where full parsing is unnecessary
 * - Simple block analysis and filtering operations
 * - Basic block metadata extraction for processing
 *
 * @since ??
 */
class SimpleBlockParser {

	/**
	 * Cache for the parsed blocks.
	 *
	 * @var array<SimpleBlockParserStore>
	 */
	private static $_cache = [];

	/**
	 * Cache for the parsed blocks when the content is empty.
	 *
	 * @var SimpleBlockParserStore
	 */
	private static $_empty_content_cache;

	/**
	 * Parse Gutenberg blocks from content as a flattened array.
	 *
	 * This method extracts Gutenberg block information from content and returns
	 * a simplified, flattened array structure. It does NOT preserve parent-child
	 * relationships, block order index, or other advanced hierarchical information
	 * that would be available in a full block parser. Each block is treated as
	 * an independent entity with only basic name and attributes data.
	 *
	 * The parsed results are cached using MD5 hash of the content for performance.
	 *
	 * @param string $content The content containing Gutenberg block comments to parse.
	 *
	 * @return SimpleBlockParserStore
	 */
	public static function parse( string $content ): SimpleBlockParserStore {
		if ( ! self::has_blocks( $content ) ) {
			if ( ! self::$_empty_content_cache ) {
				self::$_empty_content_cache = new SimpleBlockParserStore( [] );
			}

			return self::$_empty_content_cache;
		}

		$cache_key = md5( $content );

		if ( isset( self::$_cache[ $cache_key ] ) ) {
			return self::$_cache[ $cache_key ];
		}

		// Regex to match Gutenberg block comments, capturing the block name and optional JSON attributes.
		// Supports blocks with or without attributes.
		// Test regex: https://regex101.com/r/okrckj/1.
		$regex = '/<!-- wp:(?P<block_name>[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+)\s+(?:\{(?P<json>.*?)\})?\s*\/?-->/s';

		preg_match_all( $regex, $content, $matches, PREG_SET_ORDER, 0 );

		$parsed = new SimpleBlockParserStore( [] );

		foreach ( $matches as $match ) {
			$raw          = $match[0];
			$block_name   = $match['block_name'];
			$json_content = $match['json'] ?? '';
			$json         = '{' . $json_content . '}';
			$attrs        = json_decode( $json, true );

			$parsed->add(
				new SimpleBlock(
					[
						'raw'   => $raw,
						'name'  => $block_name,
						'attrs' => $attrs ?? [],
						'json'  => $json,
						'error' => null === $attrs,
					]
				)
			);
		}

		self::$_cache[ $cache_key ] = $parsed;

		return self::$_cache[ $cache_key ];
	}

	/**
	 * Check if content contains Gutenberg blocks, optionally filtered by namespace and/or block name.
	 *
	 * This method performs a quick string search to determine if the given content
	 * contains Gutenberg block comments. It supports three filtering combinations:
	 *
	 * - No filters: Checks for any Gutenberg blocks ("<!-- wp:")
	 * - Namespace only: Checks for blocks in specific namespace ("<!-- wp:divi/")
	 * - Namespace + block name: Checks for specific block in namespace ("<!-- wp:divi/button")
	 *
	 * Note: Block name is ignored when no namespace is provided, since Gutenberg blocks
	 * are typically namespaced and a block name without namespace context is incomplete.
	 *
	 * When a namespace is provided, the trailing slash is automatically added if not present.
	 * Input validation ensures that block names don't accidentally include the namespace prefix.
	 *
	 * This is a lightweight check that doesn't validate block structure or parse
	 * the content - it simply checks for the presence of block markers.
	 *
	 * @since ??
	 *
	 * @param string $content    The content to check for block presence.
	 * @param string $namespace  Optional. The namespace. Trailing slash will be
	 *                           automatically added if not present and not empty.
	 *                           When empty, checks across all namespaces and ignores block_name.
	 * @param string $block_name Optional. The block name without namespace.
	 *                           Only used when namespace is provided.
	 *                           When empty, checks for any blocks in the specified namespace.
	 *                           When provided, checks only for this specific block.
	 *                           Must not start with the namespace (e.g., use "button" not "divi/button").
	 *
	 * @return bool True if content contains the specified blocks, false otherwise.
	 * @throws \Exception If block name starts with the namespace prefix.
	 */
	public static function has_blocks( string $content, string $namespace = '', string $block_name = '' ): bool {
		if ( empty( $content ) ) {
			return false;
		}

		$search_pattern = '<!-- wp:';

		if ( ! empty( $namespace ) ) {
			$namespace_with_slash = trailingslashit( $namespace );

			if ( $block_name && 0 === strpos( $block_name, $namespace_with_slash ) ) {
				throw new \Exception( 'Block name cannot contain the namespace.' );
			}

			$search_pattern .= $namespace_with_slash . $block_name;
		}

		return false !== strpos( $content, $search_pattern );
	}

	/**
	 * Check if content contains any Divi-specific Gutenberg blocks.
	 *
	 * This method performs a quick string search to determine if the given content
	 * contains any Divi namespace blocks. It looks for the Divi-specific block
	 * comment pattern "<!-- wp:divi/" which identifies blocks created by the
	 * Divi theme or builder.
	 *
	 * This is a lightweight check that doesn't validate block structure or parse
	 * the content - it simply checks for the presence of Divi block markers.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check for Divi block presence.
	 *
	 * @return bool True if content contains Divi blocks, false otherwise.
	 */
	public static function has_divi_blocks( string $content ): bool {
		return self::has_blocks( $content, 'divi' );
	}
}
