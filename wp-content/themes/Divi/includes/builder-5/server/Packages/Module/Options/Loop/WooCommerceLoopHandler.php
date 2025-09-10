<?php
/**
 * WooCommerce Loop Handler.
 *
 * @package Builder\Packages\Module\Options\Loop
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop;

use ET\Builder\Packages\ModuleUtils\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * WooCommerce Loop Handler class.
 *
 * @since ??
 */
class WooCommerceLoopHandler {

	/**
	 * Field configuration mapping.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_field_config = [
		'loop_product_price_regular'       => [
			'method' => 'get_regular_price',
			'escape' => 'esc_html',
		],
		'loop_product_price_sale'          => [
			'method' => 'get_sale_price',
			'escape' => 'esc_html',
		],
		'loop_product_price_current'       => [
			'method' => 'get_price',
			'escape' => 'esc_html',
		],
		'loop_product_description'         => [
			'method' => 'get_description',
			'escape' => 'wp_kses_post',
		],
		'loop_product_stock_quantity'      => [
			'custom' => 'get_stock_quantity_safe',
			'escape' => 'esc_html',
		],
		'loop_product_stock_status'        => [
			'method' => 'get_stock_status',
			'escape' => 'esc_html',
		],
		'loop_product_reviews_count'       => [
			'method' => 'get_review_count',
			'escape' => 'absint',
		],
		'loop_product_sku'                 => [
			'method' => 'get_sku',
			'escape' => 'esc_html',
		],
		'loop_product_id'                  => [
			'method' => 'get_id',
			'escape' => 'absint',
		],
		'loop_product_title'               => [
			'method' => 'get_name',
			'escape' => 'esc_html',
		],
		'loop_product_post_date'           => [
			'custom' => 'get_formatted_date',
			'escape' => 'esc_html',
		],
		'loop_product_post_featured_image' => [
			'custom' => 'get_featured_image_url',
			'escape' => 'esc_url',
		],
		'loop_product_post_link_url'       => [
			'custom' => 'get_product_permalink',
			'escape' => 'esc_url',
		],
		'loop_product_post_comment_count'  => [
			'custom' => 'get_comment_count',
			'escape' => 'absint',
		],
	];

	/**
	 * Get loop content for WooCommerce product.
	 *
	 * SECURITY NOTE: All user inputs in $settings are expected to be sanitized
	 * at the entry point in LoopUtils::get_query_args_from_attrs() before reaching
	 * this method. This method focuses on output escaping only.
	 *
	 * Enhanced to support flexible date formatting similar to HooksRegistration.
	 *
	 * @since ??
	 *
	 * @param string $name     Loop variable name.
	 * @param mixed  $post     WP_Post object.
	 * @param array  $settings Optional. Field settings for customization. Default [].
	 *
	 * @return string The field value or empty string.
	 */
	public static function get_loop_content( string $name, $post, $settings = [] ): string {
		$product = self::_validate_and_get_product( $post );
		if ( ! $product ) {
			return '';
		}

		return self::_get_field_value( $name, $product, $post, $settings );
	}

	/**
	 * Check if a field is supported.
	 *
	 * @since ??
	 *
	 * @param string $name Field name.
	 *
	 * @return bool True if supported.
	 */
	public static function is_supported_field( string $name ): bool {
		return isset( self::$_field_config[ $name ] );
	}

	/**
	 * Validate post and get WooCommerce product.
	 *
	 * @since ??
	 *
	 * @param mixed $post WP_Post object.
	 *
	 * @return \WC_Product|false Product object or false.
	 */
	private static function _validate_and_get_product( $post ) {
		if ( ! isset( $post->ID ) || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $post->ID );

		return $product ? $product : false;
	}

	/**
	 * Get field value using configuration.
	 *
	 * @since ??
	 *
	 * @param string      $name     Field name.
	 * @param \WC_Product $product  Product object.
	 * @param mixed       $post     WP_Post object.
	 * @param array       $settings Optional. Field settings for customization.
	 *
	 * @return string Field value or empty string.
	 */
	private static function _get_field_value( $name, $product, $post, $settings = [] ): string {
		if ( ! isset( self::$_field_config[ $name ] ) ) {
			return '';
		}

		$config = self::$_field_config[ $name ];

		if ( isset( $config['custom'] ) ) {
			$value = self::_get_custom_value( $config['custom'], $product, $post, $settings );
		} else {
			$method = $config['method'];
			$value  = $product->$method();
		}

		return self::_escape_value( $value, $config['escape'] );
	}

	/**
	 * Get custom field value.
	 *
	 * @since ??
	 *
	 * @param string      $method   Custom method name.
	 * @param \WC_Product $product  Product object.
	 * @param mixed       $post     WP_Post object.
	 * @param array       $settings Optional. Field settings for customization. Default [].
	 *
	 * @return mixed Field value.
	 */
	private static function _get_custom_value( $method, $product, $post, $settings = [] ) {
		switch ( $method ) {
			case 'get_formatted_date':
				$date = $product->get_date_created();
				return ModuleUtils::format_date( $date, $settings );

			case 'get_featured_image_url':
				$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'full' );

				// Fallback to WooCommerce product image.
				if ( ! $thumbnail_url ) {
					$image_id = $product->get_image_id();
					if ( $image_id ) {
						$thumbnail_url = wp_get_attachment_url( $image_id );
					}
				}

				return $thumbnail_url ? $thumbnail_url : '';

			case 'get_product_permalink':
				$permalink = get_permalink( $post->ID );

				return $permalink ? $permalink : '';

			case 'get_comment_count':
				return isset( $post->comment_count ) ? absint( $post->comment_count ) : '0';

			case 'get_stock_quantity_safe':
				$quantity = $product->get_stock_quantity();

				return null === $quantity ? '' : absint( $quantity );

			default:
				return '';
		}
	}

	/**
	 * Apply escaping to value.
	 *
	 * @since ??
	 *
	 * @param mixed  $value       Value to escape.
	 * @param string $escape_type Escape function name.
	 *
	 * @return string Escaped value.
	 */
	private static function _escape_value( $value, string $escape_type ): string {
		switch ( $escape_type ) {
			case 'esc_html':
				return esc_html( $value );

			case 'esc_url':
				return esc_url( $value );

			case 'wp_kses_post':
				return wp_kses_post( $value );

			case 'absint':
				return (string) absint( $value );

			default:
				// Log unexpected escape type for debugging.
				// error_log( "Unexpected escape type in WooCommerceLoopHandler: {$escape_type}" );.
				return esc_html( $value ); // Safe default instead of no escaping.
		}
	}

}
