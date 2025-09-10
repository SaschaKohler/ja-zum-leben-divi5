<?php
/**
 * Admin: WooCommerce class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Admin\WooCommerce;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\DependencyTree;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;


/**
 * Admin's WooCommerce Class.
 *
 * This class is responsible for loading all the woocommerce related functionality on the admin area. It accepts
 * a DependencyTree on construction, specifying the dependencies and their priorities for loading.
 *
 * @since ??
 *
 * @param DependencyTree $dependencyTree The dependency tree instance specifying the dependencies and priorities.
 */
class WooCommerce implements DependencyInterface {

	/**
	 * Load WooCommerce class.
	 *
	 * @since ??
	 */
	public function load() {
		if ( ! Conditions::is_woocommerce_enabled() ) {
			return;
		}

		add_action( 'save_post_product', [ WooCommerceHooks::class, 'invalidate_product_description_caches' ] );
	}
}
