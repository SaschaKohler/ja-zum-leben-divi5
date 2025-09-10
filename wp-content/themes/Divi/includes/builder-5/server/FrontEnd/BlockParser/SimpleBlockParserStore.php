<?php
/**
 * SimpleBlockParserStore class file.
 *
 * This file contains the SimpleBlockParserStore class which provides a storage
 * mechanism for managing collections of parsed SimpleBlock objects during the
 * block parsing process.
 *
 * @package ET\Builder\FrontEnd\BlockParser
 * @since   5.0.0
 */

namespace ET\Builder\FrontEnd\BlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\BlockParser\SimpleBlock;

/**
 * Simple Block Parser Store class.
 *
 * A storage container for managing collections of parsed SimpleBlock objects.
 * This class provides a simple interface for adding blocks to a collection
 * and retrieving the complete collection when needed.
 *
 * The store is designed to be lightweight and efficient for use during the
 * block parsing process, allowing for incremental addition of blocks as they
 * are processed.
 *
 * @since 5.0.0
 *
 * @see SimpleBlock For the block objects stored in this collection.
 */
class SimpleBlockParserStore {

	/**
	 * The collection of parsed blocks.
	 *
	 * An array containing SimpleBlock objects that have been added to this store.
	 * The array maintains insertion order and can contain any number of blocks.
	 *
	 * @since 5.0.0
	 *
	 * @var SimpleBlock[] Array of SimpleBlock objects.
	 */
	private $_results;

	/**
	 * Constructor.
	 *
	 * Initializes the store with an optional collection of SimpleBlock objects.
	 * If no blocks are provided, the store will be initialized as empty and
	 * blocks can be added later using the add() method.
	 *
	 * @since 5.0.0
	 *
	 * @param SimpleBlock[] $results Initial collection of parsed blocks. Can be
	 *                               an empty array if starting with no blocks.
	 *
	 * @see add() For adding blocks after initialization.
	 */
	public function __construct( array $results ) {
		$this->_results = $results;
	}

	/**
	 * Add a new block to the collection.
	 *
	 * Appends a SimpleBlock object to the end of the current collection.
	 * The block will be added in the order it was received, maintaining
	 * insertion order within the store.
	 *
	 * @since 5.0.0
	 *
	 * @param SimpleBlock $block The block object to add to the collection.
	 *                           Must be a valid SimpleBlock instance.
	 *
	 * @return void
	 *
	 * @see results() For retrieving the complete collection including added blocks.
	 */
	public function add( SimpleBlock $block ) {
		$this->_results[] = $block;
	}

	/**
	 * Get the complete collection of stored blocks.
	 *
	 * Returns all SimpleBlock objects currently stored in this collection,
	 * including both blocks provided during initialization and any blocks
	 * added subsequently via the add() method.
	 *
	 * The returned array maintains the original insertion order and contains
	 * references to the actual SimpleBlock objects (not copies).
	 *
	 * @since 5.0.0
	 *
	 * @return SimpleBlock[] The complete collection of parsed blocks. Returns
	 *                       an empty array if no blocks have been stored.
	 *
	 * @see add() For adding blocks to the collection.
	 */
	public function results(): array {
		return $this->_results;
	}
}
