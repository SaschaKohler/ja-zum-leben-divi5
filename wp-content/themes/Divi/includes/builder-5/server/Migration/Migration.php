<?php
/**
 * Migration Manager Class
 *
 * This class manages the execution of multiple migrations in sequence
 * through a fluent interface.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Migration\FlexboxMigration;
use ET\Builder\Migration\GlobalColorMigration;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Migration class for handling sequential migration execution.
 *
 * @since ??
 */
class Migration {

	/**
	 * Stores the migration classes to be executed.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_migrations = [];

	/**
	 * Apply a specific migration and return $this for method chaining.
	 *
	 * @since ??
	 *
	 * @param string $migration_class The migration class name to run.
	 * @return self
	 */
	public function apply( string $migration_class ): self {
		$this->_migrations[] = $migration_class;
		return $this;
	}

	/**
	 * Sort migrations by release version.
	 *
	 * @since ??
	 *
	 * @param array $migrations Array of migration class names.
	 *
	 * @return array Sorted array of migration class names.
	 */
	public function sort_migrations_by_version( array $migrations ): array {
		$sorted_migrations = $migrations;
		usort(
			$sorted_migrations,
			fn( $a, $b ) => StringUtility::version_compare( $a::get_release_version(), $b::get_release_version() )
		);

		return $sorted_migrations;
	}

	/**
	 * Execute all registered migrations in sequence, sorted by version.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function execute(): void {
		// Sort migrations by release version before executing.
		$sorted_migrations = $this->sort_migrations_by_version( $this->_migrations );

		foreach ( $sorted_migrations as $migration_class ) {
			$migration_class::load();
		}
	}
}

$migration = new Migration();

// Register migrations here.
$migration->apply( FlexboxMigration::class );
$migration->apply( GlobalColorMigration::class );

$migration->execute();
