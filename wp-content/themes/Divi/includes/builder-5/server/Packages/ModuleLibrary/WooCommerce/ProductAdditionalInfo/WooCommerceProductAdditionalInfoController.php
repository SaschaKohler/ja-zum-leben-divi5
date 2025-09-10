<?php
/**
 * Module Library: WooCommerce Product Information Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAdditionalInfo;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAdditionalInfo\WooCommerceProductAdditionalInfoModule;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Product Information REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductAdditionalInfoController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Information module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$product_id = $request->get_param( 'productId' ) ?? 'current';

		// This will convert 'current', 'latest', or numeric IDs to an actual product ID.
		$product = WooCommerceUtils::get_product( $product_id );

		// Warn if not a valid product ID.
		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				array( 'status' => 404 ),
				404
			);
		}

		$show_title = $request->get_param( 'showTitle' );

		$args = array( 'product' => $product->get_id() );

		if ( ! empty( $show_title ) ) {
			$args['show_title'] = $show_title;
		}

		// Retrieve the product additional information using the WooCommerceProductAdditionalInfoModule class.
		$additional_info_html = WooCommerceProductAdditionalInfoModule::get_additional_info( $args );

		$response = array(
			'html' => $additional_info_html,
		);

		return self::response_success( $response );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This function returns an array that defines the arguments for the index action,
	 * which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return array(
			'productId' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function( $param ) {
					$param = sanitize_text_field( $param );

					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			),
			'showTitle' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function( $param ) {
					return sanitize_text_field( $param );
				},
				'validate_callback' => function( $param ) {
					return in_array( $param, array( 'on', 'off' ), true );
				},
			),
		);
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the rest endpoint, otherwise `false`.
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}

}
