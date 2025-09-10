<?php
/**
 * Utility class for WooCommerce-related operations.
 *
 * This class serves as a container for utility methods and functionality
 * specific to interacting with WooCommerce.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\WooCommerce;

use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\VisualBuilder\SettingsData\SettingsDataCallbacks;
use ET_Core_Data_Utils;
use ET_Theme_Builder_Layout;
use ET\Builder\ThemeBuilder\WooCommerce\WooCommerceProductVariablePlaceholder;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\Breadcrumb\WooCommerceBreadcrumbModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CartNotice\WooCommerceCartNoticeModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAdditionalInfo\WooCommerceProductAdditionalInfoModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAddToCart\WooCommerceProductAddToCartModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductDescription\WooCommerceProductDescriptionModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductImages\WooCommerceProductImagesModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductMeta\WooCommerceProductMetaModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductGallery\WooCommerceProductGalleryModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductPrice\WooCommerceProductPriceModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductRating\WooCommerceProductRatingModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductReviews\WooCommerceProductReviewsModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductStock\WooCommerceProductStockModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTabs\WooCommerceProductTabsModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTitle\WooCommerceProductTitleModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductUpsell\WooCommerceProductUpsellModule;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\RelatedProducts\WooCommerceRelatedProductsModule;
use stdClass;
use WC_Product;
use WP_Query;
use WP_Error;
use WP_REST_Request;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Utility class for various WooCommerce-related operations and helper functions.
 *
 * @since ??
 */
class WooCommerceUtils {

	/**
	 * An array of allowed WooCommerce functions that can be safely called from templates.
	 * This list acts as a security allowlist to prevent unauthorized function execution.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_allowed_functions = [
		'the_title',
		'woocommerce_breadcrumb',
		'woocommerce_template_single_price',
		'woocommerce_template_single_add_to_cart',
		'woocommerce_product_additional_information_tab',
		'woocommerce_template_single_meta',
		'woocommerce_template_single_rating',
		'woocommerce_show_product_images',
		'wc_get_stock_html',
		'wc_print_notices',
		'wc_print_notice',
		'woocommerce_output_related_products',
		'woocommerce_upsell_display',
		'woocommerce_checkout_login_form',
		'wc_cart_empty_template',
		'woocommerce_output_all_notices',
	];

	/**
	 * Static cache for processed dynamic attribute defaults.
	 *
	 * @var array
	 */
	private static $_processed_attr_defaults_cache = [];

	/**
	 * The current REST request query params.
	 *
	 * @since ??
	 *
	 * @var array|null
	 */
	private static $_current_rest_request_query_params = [];

	/**
	 * Check if current global $post uses builder / layout block, not `product` CPT, and contains
	 * WooCommerce module inside it. This check is needed because WooCommerce by default only adds
	 * scripts and style to `product` CPT while WooCommerce Modules can be used at any CPT.
	 *
	 * Based on legacy `et_builder_wc_is_non_product_post_type` function.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_non_product_post_type(): bool {
		static $is_non_product_post_type;

		// If the result is already cached, return it immediately.
		if ( null !== $is_non_product_post_type ) {
			return $is_non_product_post_type;
		}

		// Bail early for specific request types (e.g., AJAX requests, REST API requests, or VB top window requests).
		if ( Conditions::is_ajax_request() || Conditions::is_rest_api_request() || Conditions::is_vb_top_window() ) {
			$is_non_product_post_type = false;
			return $is_non_product_post_type;
		}

		global $post;

		// If the global $post is missing or is a WooCommerce 'product', immediately return false.
		if ( empty( $post ) || 'product' === $post->post_type ) {
			$is_non_product_post_type = false;
			return $is_non_product_post_type;
		}

		// Check whether the current post uses the builder or layout block.
		$is_builder_used           = et_pb_is_pagebuilder_used( $post->ID );
		$is_layout_block_used      = has_block( 'divi/layout', $post->post_content );
		$is_builder_or_layout_used = $is_builder_used || $is_layout_block_used;

		// Detect Woo modules in the current post content.
		$has_wc_module_block     = DetectFeature::has_woocommerce_module_block( $post->post_content );
		$has_wc_module_shortcode = DetectFeature::has_woocommerce_module_shortcode( $post->post_content );
		$has_woocommerce_module  = $has_wc_module_block || $has_wc_module_shortcode;

		// Check if the current post uses the Theme Builder Body layout.
		$has_woocommerce_module_tb = false;
		$tb_layouts                = function_exists( 'et_theme_builder_get_template_layouts' ) ? et_theme_builder_get_template_layouts() : [];
		$tb_body_layout            = $tb_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ] ?? null;
		$tb_body_override          = is_array( $tb_body_layout ) && ! empty( $tb_body_layout['override'] );

		// If the Theme Builder Body layout is used, check if it has WooCommerce modules or shortcodes.
		if ( $tb_body_override ) {
			$tb_body_layout_id          = $tb_body_override ? (int) ( $tb_body_layout['id'] ?? 0 ) : 0;
			$tb_body_content            = $tb_body_layout_id ? get_post_field( 'post_content', $tb_body_layout_id ) : '';
			$has_wc_module_block_tb     = DetectFeature::has_woocommerce_module_block( $tb_body_content );
			$has_wc_module_shortcode_tb = DetectFeature::has_woocommerce_module_shortcode( $tb_body_content );
			$has_woocommerce_module_tb  = $has_wc_module_block_tb || $has_wc_module_shortcode_tb;
		}

		// Final decision: consider TB Body override and its content as valid builder/context.
		$is_non_product_post_type = ( ( $is_builder_or_layout_used || $tb_body_override ) && ( $has_woocommerce_module || $has_woocommerce_module_tb ) );

		return $is_non_product_post_type;
	}

	/**
	 * Returns TRUE if the Product attribute value is valid.
	 *
	 * Valid values are Product Ids, `current` and `latest`.
	 *
	 * @since ??
	 *
	 * @param string $maybe_product_id Product ID.
	 *
	 * @return bool
	 */
	public static function is_product_attr_valid( string $maybe_product_id ): bool {
		if ( empty( $maybe_product_id ) ) {
			return false;
		}

		if (
			absint( $maybe_product_id ) === 0
			&& ! in_array( $maybe_product_id, [ 'current', 'latest' ], true )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the default column settings for WooCommerce posts.
	 *
	 * This method applies the 'divi_woocommerce_get_default_columns' filter
	 * to determine and return the default column configuration.
	 *
	 * Based on the legacy `get_columns_posts_default` function.
	 *
	 * @since ??
	 *
	 * @return string The default column configuration for WooCommerce posts, as filtered by the applied hook.
	 */
	public static function get_default_columns_posts(): string {
		// Get the value for columns.
		$columns = self::get_default_columns_posts_value();

		/**
		 * Filters the default column configuration for WooCommerce posts.
		 *
		 * @since ??
		 *
		 * @param string $columns The default column configuration for WooCommerce posts.
		 */
		return apply_filters( 'divi_woocommerce_get_default_columns', $columns );
	}

	/**
	 * Retrieves the default number of columns for displaying posts.
	 *
	 * Determines the appropriate default column value based on the current page's
	 * layout and context. If the page has a sidebar, it returns a value indicative
	 * of a layout with a sidebar; otherwise, it defaults to standard values often
	 * influenced by WooCommerce settings.
	 *
	 * Based on the legacy `get_columns_posts_default_value` function.
	 *
	 * @since ??
	 *
	 * @return string The number of columns as a string. Returns '3' for layouts
	 *                with a sidebar or '4' as the default value.
	 */
	public static function get_default_columns_posts_value(): string {
		static $cache = [];

		$post_id = DynamicAssetsUtils::get_current_post_id();

		// Return cached value if available.
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$page_layout = get_post_meta( $post_id, '_et_pb_page_layout', true );

		// Check if page has a sidebar.
		if ( $page_layout && 'et_full_width_page' !== $page_layout && ! ET_Theme_Builder_Layout::is_theme_builder_layout() ) {
			// Set to 3 if page has sidebar and store in cache.
			$cache[ $post_id ] = '3';
			return $cache[ $post_id ];
		}

		/*
		* Default number is based on the WooCommerce plugin default value.
		*
		* @see woocommerce_output_related_products()
		*/
		$cache[ $post_id ] = '4';
		return $cache[ $post_id ];
	}

	/**
	 * Retrieves the default product configuration.
	 *
	 * Applies a filter to allow the retrieval or modification of the default product configuration.
	 * The returned value is expected to be an associative array containing details about the default product.
	 *
	 * Based on the legacy `get_product_default` function.
	 *
	 * @since ??
	 *
	 * @return string The default product configuration. The structure and content of the array
	 *               depend on the filter `divi_woocommerce_get_default_product`.
	 */
	public static function get_default_product(): string {
		// Get the value for the $default_product.
		$default_product = self::get_default_product_value();

		/**
		 * Filters the default product configuration.
		 *
		 * @since ??
		 *
		 * @param string $default_product The default product configuration.
		 */
		return apply_filters( 'divi_woocommerce_get_default_product', $default_product );
	}

	/**
	 * Retrieves the default product value identifier.
	 *
	 * Determines the default product value based on the current context, including post type
	 * or page resource. This method assesses whether the current post type is a "product"
	 * or a "theme builder layout" and returns an appropriate default value.
	 *
	 * Based on the legacy `get_product_default_value` function.
	 *
	 * @since ??
	 *
	 * @return string The default product value, either 'current' if the context relates to
	 *                a product or theme builder layout, or 'latest' as a fallback.
	 */
	public static function get_default_product_value(): string {
		$post_id   = DynamicAssetsUtils::get_current_post_id();
		$post_type = get_post_type( $post_id );

		if ( 'product' === $post_type || et_theme_builder_is_layout_post_type( $post_type ) ) {
			return 'current';
		}

		return 'latest';
	}

	/**
	 * Retrieves the default WooCommerce tabs.
	 *
	 * This method returns the default WooCommerce tabs, allowing filters
	 * to modify the data before it is returned. It is primarily used
	 * to obtain the currently configured set of WooCommerce product tabs.
	 *
	 * Based on the legacy `get_woo_default_tabs` function.
	 *
	 * @since ??
	 *
	 * @return array The default WooCommerce tabs after applying filters.
	 */
	public static function get_default_product_tabs(): array {
		// Get the value for the $default_tabs.
		$default_tabs = self::get_default_product_tabs_options();

		/**
		 * Filters the default WooCommerce tabs.
		 *
		 * @since ??
		 *
		 * @param array $default_tabs The default WooCommerce tabs.
		 */
		return apply_filters( 'divi_woocommerce_get_default_product_tabs', $default_tabs );
	}

	/**
	 * Retrieves default WooCommerce product tabs options.
	 *
	 * Processes the current product data, applies necessary filters, and returns
	 * a list of available WooCommerce product tabs. Handles resetting global variables
	 * after usage to maintain consistent behavior.
	 *
	 * Based on the legacy `get_woo_default_tabs_options` function.
	 *
	 * @since ??
	 *
	 * @return array Array of default WooCommerce product tabs. Returns an empty array
	 *               if no valid tabs are found, or if the current product cannot be retrieved.
	 */
	public static function get_default_product_tabs_options(): array {
		// Bail if WooCommerce is not enabled.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [];
		}

		$maybe_product_id = self::get_default_product_value();
		$current_product  = self::get_product( $maybe_product_id );

		if ( ! $current_product ) {
			return [];
		}

		global $product, $post;
		$original_product = $product;
		$original_post    = $post;
		$product          = $current_product;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally done.
		$post = get_post( $product->get_id() );

		$tabs = apply_filters( 'woocommerce_product_tabs', [] );

		// Reset global $product.
		$product = $original_product;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally done.
		$post = $original_post;

		if ( ! empty( $tabs ) ) {
			return array_keys( $tabs );
		}

		return [];
	}

	/**
	 * Retrieves the WooCommerce product tabs.
	 *
	 * This method fetches the available WooCommerce product tabs by invoking the
	 * 'woocommerce_product_tabs' filter. It ensures that appropriate product context
	 * is set globally in cases where it is not already defined. If no valid product
	 * context can be established, default product tab options are returned.
	 *
	 * Based on the legacy `et_fb_woocommerce_tabs` function.
	 *
	 * @since ??
	 *
	 * @return array An associative array of product tabs, where each key is the tab name,
	 *               and each value is an array containing 'value' (tab's name) and
	 *               'label' (tab's title).
	 */
	public static function get_product_tabs_options(): array {
		global $product, $post;

		$old_product = $product;
		$old_post    = $post;
		$is_product  = isset( $product ) && is_a( $product, 'WC_Product' );

		if ( ! $is_product && Conditions::is_woocommerce_enabled() ) {
			$product = self::get_product( self::get_default_product() );

			if ( $product ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Overriding global post is safe as the original $ post has been restored at the end.
				$post = get_post( $product->get_id() );
			} else {
				$product = $old_product;
				return self::set_default_product_tabs_options();
			}
		}

		// On non-product post-types, the filter will cause a fatal error unless we have a global $product set.
		$tabs    = apply_filters( 'woocommerce_product_tabs', [] );
		$options = array();

		foreach ( $tabs as $name => $tab ) {
			$options[ $name ] = array(
				'value' => $name,
				'label' => $tab['title'],
			);
		}

		// Reset global $product.
		$product = $old_product;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Restoring original global $post data.
		$post = $old_post;

		return $options;
	}

	/**
	 * Retrieves the default page type configuration.
	 *
	 * Provides an array of default page type settings, which can be filtered
	 * by the 'divi_woocommerce_get_default_page_type' filter.
	 *
	 * Based on the legacy `get_page_type_default` function.
	 *
	 * @since ??
	 *
	 * @return string The default page type configuration.
	 */
	public static function get_default_page_type(): string {
		// Get the value for the $default_page_type.
		$default_page_type = self::get_default_page_type_value();

		/**
		 * Filters the default page type configuration.
		 *
		 * @since ??
		 *
		 * @param string $default_page_type The default page type configuration.
		 */
		return apply_filters( 'divi_woocommerce_get_default_page_type', $default_page_type );
	}

	/**
	 * Retrieves the default page type value based on the current page.
	 *
	 * Determines the page type by checking if the current page is a cart or checkout page.
	 * If neither condition is met, it defaults to the "product" page type.
	 *
	 * Based on the legacy `get_page_type_default_value` function.
	 *
	 * @since ??
	 *
	 * @return string The determined page type, which can be "cart", "checkout", or "product".
	 */
	public static function get_default_page_type_value(): string {
		$is_cart_page     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout_page = function_exists( 'is_checkout' ) && is_checkout();

		if ( $is_cart_page ) {
			return 'cart';
		} elseif ( $is_checkout_page ) {
			return 'checkout';
		} else {
			return 'product';
		}
	}

	/**
	 * Processes dynamic attribute defaults for WooCommerce modules.
	 *
	 * This function replaces 'dynamic' attribute values in the default attributes
	 * with the default values retrieved from the server.
	 *
	 * It's the PHP equivalent of the TypeScript processDynamicAttrDefaults function.
	 *
	 * @since ??
	 *
	 * @param array $default_attrs The module default attributes.
	 * @param array $metadata      The module metadata.
	 *
	 * @return array The processed module default attributes.
	 */
	public static function process_dynamic_attr_defaults( array $default_attrs, array $metadata ): array {
		// Create a cache key based on the input parameters.
		$cache_key = md5( wp_json_encode( $default_attrs ) );

		// Check if we already have a cached result for these inputs.
		if ( isset( self::$_processed_attr_defaults_cache[ $cache_key ] ) ) {
			return self::$_processed_attr_defaults_cache[ $cache_key ];
		}

		$processed_default_attrs = $default_attrs;

		// Get WooCommerce defaults from SettingsDataCallbacks.
		$defaults = SettingsDataCallbacks::woocommerce()['defaults'] ?? [];

		$dynamic_attrs_map = [
			'product'       => [
				'default_key' => 'product',
				'callback'    => [ self::class, 'get_default_product_value' ],
			],
			'homeUrl'       => [
				'default_key' => 'homeUrl',
				// Use the function name as a string to prevent it from being called on every page load.
				// The function will be called only when needed.
				'callback'    => 'get_home_url',
			],
			'columnsNumber' => [
				'default_key' => 'columnsPosts',
				'callback'    => [ self::class, 'get_default_columns_posts_value' ],
			],
			'postsNumber'   => [
				'default_key' => 'columnsPosts',
				'callback'    => [ self::class, 'get_default_columns_posts_value' ],
			],
			'includeTabs'   => [
				'default_key' => 'productTabs',
				'callback'    => [ self::class, 'get_default_product_tabs' ],
			],
			'pageType'      => [
				'default_key' => 'pageType',
				'callback'    => [ self::class, 'get_default_page_type_value' ],
			],
		];

		foreach ( $dynamic_attrs_map as $attr => $config ) {
			$path = "content.advanced.$attr.desktop.value";

			if ( 'dynamic' === ArrayUtility::get_value( $default_attrs, $path ) ) {
				// `call_user_func` is safe here because the callback is not derived from user input.
				$value = $defaults[ $config['default_key'] ] ?? call_user_func( $config['callback'] );

				// Sanitize the URL for 'homeUrl'.
				if ( 'homeUrl' === $attr ) {
					$value = esc_url_raw( $value );
				}

				$processed_default_attrs['content']['advanced'][ $attr ]['desktop']['value'] = $value;
			}
		}

		// Store the result in the cache before returning.
		self::$_processed_attr_defaults_cache[ $cache_key ] = $processed_default_attrs;

		return $processed_default_attrs;
	}

	/**
	 * Retrieves the product ID based on the provided product attribute.
	 *
	 * Determines the correct product ID to return based on the given attribute,
	 * handling cases like "current", "latest", and numeric values. If the attribute
	 * is invalid, fallback mechanisms are employed to retrieve a relevant product ID.
	 *
	 * @since ??
	 *
	 * @param string $valid_product_attr The input attribute used to determine the product ID.
	 *                                   Acceptable values include "current", "latest",
	 *                                   or a numeric product ID.
	 *
	 * @return int The determined product ID. Returns 0 if no valid product ID can be resolved.
	 */
	public static function get_product_id_by_prop( string $valid_product_attr ): int {
		if ( ! self::is_product_attr_valid( $valid_product_attr ) ) {
			return 0;
		}

		if ( 'current' === $valid_product_attr ) {
			$current_post_id = DynamicAssetsUtils::get_current_post_id();

			if ( et_theme_builder_is_layout_post_type( get_post_type( $current_post_id ) ) ) {
				// We want to use the latest product when we are editing a TB layout.
				$valid_product_attr = 'latest';
			}
		}

		if (
			! in_array( $valid_product_attr, [ 'current', 'latest' ], true )
			&& false === get_post_status( $valid_product_attr )
		) {
			$valid_product_attr = 'latest';
		}

		if ( 'current' === $valid_product_attr ) {
			$product_id = DynamicAssetsUtils::get_current_post_id();
		} elseif ( 'latest' === $valid_product_attr ) {
			$args = [
				'limit'       => 1,
				'post_status' => [ 'publish', 'private' ],
				'perm'        => 'readable',
			];

			if ( ! function_exists( 'wc_get_products' ) ) {
				return 0;
			}

			$products = wc_get_products( $args );

			if ( empty( $products ) || ! is_array( $products ) ) {
				return 0;
			}

			if ( isset( $products[0] ) && is_a( $products[0], 'WC_Product' ) ) {
				$product_id = $products[0]->get_id();
			} else {
				return 0;
			}
		} elseif ( is_numeric( $valid_product_attr ) && 'product' !== get_post_type( $valid_product_attr ) ) {
			// There is a condition that $valid_product_attr value passed here is not the product ID.
			// For example when you set product breadcrumb as Blurb Title when building layout in TB.
			// So we get the most recent product ID in date descending order.
			$query = new \WC_Product_Query(
				[
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'ids',
					'status'  => [ 'publish' ],
				]
			);

			$products = $query->get_products();

			if ( $products && ! empty( $products[0] ) ) {
				$product_id = absint( $products[0] );
			} else {
				$product_id = absint( $valid_product_attr );
			}
		} else {
			$product_id = absint( $valid_product_attr );
		}

		return $product_id;
	}

	/**
	 * Retrieves the product object based on the provided product identifier.
	 *
	 * Resolves the WooCommerce product object corresponding to the given product identifier.
	 * Utilizes a helper method to determine the appropriate product ID and fetches the product
	 * if it exists. Returns false if no valid product can be retrieved.
	 *
	 * @since ??
	 *
	 * @param string $maybe_product_id The input value which may represent a product ID,
	 *                                 or another attribute to determine the product.
	 *
	 * @return \WC_Product|false The WooCommerce product object if successfully resolved,
	 *                           or false if no valid product can be found.
	 */
	public static function get_product( string $maybe_product_id ) {
		$product_id = self::get_product_id_by_prop( $maybe_product_id );

		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		if ( empty( $product ) ) {
			return false;
		}

		return $product;
	}

	/**
	 * Gets the Product layout for a given Post ID.
	 *
	 * This function retrieves the product layout associated with a specific post ID.
	 * It checks if the post exists and returns the layout value stored in the post meta.
	 *
	 * @since ??
	 *
	 * @param int $post_id This is the ID of the Post requesting the product layout.
	 *
	 * @return string|false The return value will be one of the values from
	 *                      {@see et_builder_wc_get_page_layouts()} when the
	 *                      Post ID is valid, or false if the post is not found.
	 */
	public static function get_product_layout( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		return get_post_meta( $post_id, ET_BUILDER_WC_PRODUCT_PAGE_LAYOUT_META_KEY, true );
	}

	/**
	 * Retrieves the product ID from a given input.
	 *
	 * Resolves and returns the product ID associated with the input. If the input does not
	 * correspond to a valid product, the function will return 0.
	 *
	 * @since ??
	 *
	 * @param string $maybe_product_id A potential product identifier that will be validated
	 *                                 and used to retrieve the corresponding product ID.
	 *
	 * @return int The ID of the resolved product. Returns 0 if the input does not correspond
	 *             to a valid product.
	 */
	public static function get_product_id( string $maybe_product_id ): int {
		$product = self::get_product( $maybe_product_id );
		if ( ! $product ) {
			return 0;
		}

		return $product->get_id();
	}

	/**
	 * Retrieves the product ID based on the provided attributes.
	 *
	 * Determines the appropriate product ID by evaluating the provided arguments,
	 * handling cases such as "latest", "current", or a specific product ID.
	 * If the provided product ID is not valid or does not exist, a fallback mechanism
	 * is used to retrieve the latest product ID.
	 *
	 * @since ??
	 *
	 * @param array $args The input arguments containing product-related attributes.
	 *                    The "product" key may have a value of "latest", "current",
	 *                    or a specific product ID.
	 *
	 * @return int The resolved product ID. Returns 0 if no valid product ID can be determined.
	 */
	public static function get_product_id_from_attributes( array $args ): int {
		$maybe_product_id        = ArrayUtility::get_value( $args, 'product', self::get_default_product() );
		$is_latest_product       = 'latest' === $maybe_product_id;
		$is_current_product_page = 'current' === $maybe_product_id;

		if ( $is_latest_product ) {
			// Dynamic filter's product_id need to be translated into correct id.
			$product_id = self::get_product_id( $maybe_product_id );
		} elseif ( $is_current_product_page ) {
			/*
			 * $product global doesn't exist in REST request; thus get the fallback post id.
			 */
			$product_id = DynamicAssetsUtils::get_current_post_id();
		} else {
			// Besides two situation above, $product_id is current $args['product'].
			if ( false !== get_post_status( $maybe_product_id ) ) {
				$product_id = $maybe_product_id;
			} else {
				// Fallback to Latest product if saved product ID doesn't exist.
				$product_id = self::get_product_id( 'latest' );
			}
		}

		return $product_id;
	}

	/**
	 * Renders module templates based on specified actions and arguments. This function manages global context and
	 * ensures proper handling of the WooCommerce environment for different module templates.
	 *
	 * Legacy function: et_builder_wc_render_module_template()
	 *
	 * @since ??
	 *
	 * @param string $function_name The action or function name to process. It must be within the allowlist of
	 *                              supported functions.
	 * @param array  $args          Optional. An array of arguments to pass to the action or function. Default
	 *                              empty array.
	 * @param array  $overwrite     Optional. An array specifying which global variables should be temporarily
	 *                              overwritten (e.g., 'product', 'post', 'wp_query'). Default includes 'product'.
	 * @param string $module_context Optional. The calling module context to distinguish between different modules
	 *                              using the same function. Default empty string.
	 *
	 * @return string The generated output for the module template when applicable, or an empty string if the
	 * function cannot process the requested action.
	 */
	public static function render_module_template(
		string $function_name,
		array $args = [],
		array $overwrite = [ 'product' ],
		string $module_context = ''
	): string {
		// Bail early.
		if ( is_admin() && ! Conditions::is_rest_api_request() ) {
			return '';
		}

		// Check if the passed function name is allowlisted or not.
		if ( ! in_array( $function_name, self::$_allowed_functions, true ) ) {
			return '';
		}

		// phpcs:disable WordPress.WP.GlobalVariablesOverride -- Overwrite global variables when rendering templates which are restored before this function exist.
		global $product, $post, $wp_query;

		$defaults = [
			'product' => 'current',
		];

		$args               = wp_parse_args( $args, $defaults );
		$overwrite_global   = self::need_overwrite_global( $args['product'] );
		$overwrite_product  = in_array( 'product', $overwrite, true );
		$overwrite_post     = in_array( 'post', $overwrite, true );
		$overwrite_wp_query = in_array( 'wp_query', $overwrite, true );
		$is_tb              = et_builder_tb_enabled();
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		if ( $is_use_placeholder ) {
			// global object needs to be set before output rendering. This needs to be performed on each
			// module template rendering instead of once for all module template rendering because some
			// module's template rendering uses `wp_reset_postdata()` which resets global query.
			self::set_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			$product_id = self::get_product_id_from_attributes( $args );

			if ( 'product' !== get_post_type( $product_id ) ) {
				// We are in a Theme Builder layout and the current post is not a product - use the latest one instead.
				$products = new WP_Query(
					[
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'no_found_rows'  => true,
					]
				);

				if ( ! $products->have_posts() ) {
					return '';
				}

				$product_id = $products->posts[0]->ID;
			}

			// Overwrite product.
			if ( $overwrite_product ) {
				$original_product = $product;
				$product          = wc_get_product( $product_id );
			}

			// Overwrite post.
			if ( $overwrite_post ) {
				$original_post = $post;
				$post          = get_post( $product_id );
			}

			// Overwrite wp_query.
			if ( $overwrite_wp_query ) {
				$original_wp_query = $wp_query;
				$wp_query          = new WP_Query( [ 'p' => $product_id ] );
			}
		}

		ob_start();

		switch ( $function_name ) {
			case 'woocommerce_breadcrumb':
				if ( is_a( $product, 'WC_Product' ) ) {
					$breadcrumb_separator = $args['breadcrumb_separator'] ?? '';
					$breadcrumb_separator = str_replace( '&#8221;', '', $breadcrumb_separator );

					woocommerce_breadcrumb(
						[
							'delimiter' => ' ' . $breadcrumb_separator . ' ',
							'home'      => $args['breadcrumb_home_text'] ?? '',
						]
					);
				}
				break;
			case 'woocommerce_show_product_images':
				if ( is_a( $product, 'WC_Product' ) ) {
					// Distinguish between Product Images and Product Gallery modules.
					if ( 'product-gallery' === $module_context ) {
						// Product Gallery Module: Handle gallery-specific rendering.
						// Based on WooCommerce Blocks ProductGallery implementation.
						self::_render_product_gallery_template( $product, $args );
					} else {
						// Product Images Module: Handle traditional product images with toggles.
						self::_render_product_images_template( $product, $args, $function_name );
					}
				}
				break;
			case 'woocommerce_template_single_price':
			case 'woocommerce_template_single_meta':
				if ( is_a( $product, 'WC_Product' ) ) {
					$function_name();
				}
				break;
			case 'wc_get_stock_html':
				if ( is_a( $product, 'WC_Product' ) ) {
					echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- `wc_get_stock_html` used to include WooCommerce's `single-product/stock.php` template.
				}
				break;
			case 'wc_print_notice':
				$message = ArrayUtility::get_value( $args, 'wc_cart_message', '' );

				// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Functions that reach here are allowlisted.
				call_user_func( $function_name, $message );
				break;
			case 'wc_print_notices':
				if ( isset( WC()->session ) ) {
					// Save existing notices to restore them as many times as we need.
					$et_wc_cached_notices = WC()->session->get( 'wc_notices', array() );

					// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Functions that reach here are allowlisted.
					call_user_func( $function_name );

					// Restore notices which were removed after wc_print_notices() executed to render multiple modules on page.
					if ( ! empty( $et_wc_cached_notices ) && empty( WC()->session->get( 'wc_notices', array() ) ) ) {
						WC()->session->set( 'wc_notices', $et_wc_cached_notices );
					}
				}
				break;
			case 'woocommerce_checkout_login_form':
				if ( function_exists( 'woocommerce_checkout_login_form' ) ) {
					woocommerce_checkout_login_form();
				}
				if ( function_exists( 'woocommerce_checkout_coupon_form' ) ) {
					woocommerce_checkout_coupon_form();
				}
				break;
			case 'woocommerce_upsell_display':
			    // @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Only allowlisted functions will reach here.
				call_user_func( $function_name, '', '', '', $args['order'] ?? '' );
				break;
			case 'wc_cart_empty_template':
				wc_get_template( 'cart/cart-empty.php' );
				break;
			case 'woocommerce_output_all_notices':
				if ( isset( WC()->session ) ) {
					// Save existing notices to restore them as many times as we need.
					$et_wc_cached_notices = WC()->session->get( 'wc_notices', array() );

					if ( function_exists( $function_name ) ) {
						// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Functions that reach here are allowlisted.
						call_user_func( $function_name );
					}

					// Restore notices which were removed after wc_print_notices() executed to render multiple modules on page.
					if ( ! empty( $et_wc_cached_notices ) && empty( WC()->session->get( 'wc_notices', array() ) ) ) {
						WC()->session->set( 'wc_notices', $et_wc_cached_notices );
					}
				}
				break;
			default:
				// Only allowlisted functions shall be allowed until this point of execution.
				if ( is_a( $product, 'WC_Product' ) ) {
					// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Only allowlisted functions reach here.
					call_user_func( $function_name );
				}
		}

		$output = ob_get_clean();

		// Reset the original product variable to global $product.
		if ( $is_use_placeholder ) {
			self::reset_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			// Reset $product global.
			if ( $overwrite_product ) {
				$product = $original_product;
			}

			// Reset post.
			if ( $overwrite_post ) {
				$post = $original_post;
			}

			// Reset wp_query.
			if ( $overwrite_wp_query ) {
				$wp_query = $original_wp_query;
			}
			// phpcs:enable WordPress.WP.GlobalVariablesOverride -- Enable global variable override check.
		}

		return $output;
	}

	/**
	 * Determines if WooCommerce's `$product` global needs to be overwritten.
	 *
	 * IMPORTANT: Ensure that the `$product` global is reset to its original state after use.
	 * Overwriting the global is necessary in specific scenarios to avoid using incorrect
	 * or stale product information.
	 *
	 * @since ??
	 *
	 * @param string $product_id Product ID to check against. Defaults to 'current', which means
	 *                           the current product page is being referenced.
	 *
	 * @return bool True if the global `$product` needs to be overwritten, false otherwise.
	 */
	public static function need_overwrite_global( string $product_id = 'current' ): bool {
		// Check if the provided product ID corresponds to the current product page.
		$is_current_product_page = 'current' === $product_id;

		/*
		 * The global `$product` variable needs to be overwritten in the following scenarios:
		 *
		 * 1. The current request is a WordPress REST API request.
		 *    This includes:
		 *    - Any REST requests made during AJAX calls (such as VB actions),
		 *      where the global `$product` is often inconsistent or incorrect.
		 *    - Special requests with `?rest_route=/` or prefixed with the REST API base URL.
		 * 2. The specified `$product_id` is not for the current product page.
		 * 3. When requesting 'current' product but global $product is not set (Theme Builder context).
		 */
		$need_overwrite_global = Conditions::is_rest_api_request() || ! $is_current_product_page;

		// Additional check: If requesting 'current' product but global $product is not valid.
		// We need to overwrite it (this happens in Theme Builder context).
		if ( $is_current_product_page ) {
			global $product;
			if ( ! is_a( $product, 'WC_Product' ) ) {
				$need_overwrite_global = true;
			}
		}

		// Return true if a global overwrite is needed, otherwise false.
		return $need_overwrite_global;
	}

	/**
	 * Sets the default product tabs for WooCommerce products.
	 *
	 * Defines the structure and properties of the default WooCommerce product tabs,
	 * including their titles, display priority, and callback functions for rendering
	 * the tab content. If Theme Builder is enabled, additional processing is performed
	 * to apply any customizations to the product tabs.
	 *
	 * Based on the legacy `get_default_product_tabs` function.
	 *
	 * @since ??
	 *
	 * @return array An array of default product tabs with their respective configurations.
	 */
	public static function set_default_product_tabs(): array {
		$tabs = [
			'description'            => [
				'title'    => esc_html__( 'Description', 'et_builder' ),
				'priority' => 10,
				'callback' => 'woocommerce_product_description_tab',
			],
			'additional_information' => [
				'title'    => esc_html__( 'Additional information', 'et_builder' ),
				'priority' => 20,
				'callback' => 'woocommerce_product_additional_information_tab',
			],
			'reviews'                => [
				'title'    => esc_html__( 'Reviews', 'et_builder' ),
				'priority' => 30,
				'callback' => 'comments_template',
			],
		];

		// Add custom tabs on default for theme builder.
		if ( et_builder_tb_enabled() ) {
			self::set_global_objects_for_theme_builder();

			$tabs = apply_filters( 'woocommerce_product_tabs', $tabs );

			self::reset_global_objects_for_theme_builder();
		}

		return $tabs;
	}

	/**
	 * Sets default product tabs options.
	 *
	 * Processes the default product tabs to generate an array of options
	 * containing tab names, values, and labels. Each option corresponds
	 * to a tab with a title attribute. Special handling is applied for the
	 * "reviews" tab to set its label.
	 *
	 * Based on the legacy `get_default_tab_options` function.
	 *
	 * @since ??
	 *
	 * @return array An associative array of default product tab options,
	 *               where each key represents a tab name and its value
	 *               contains value-label pairs. Returns an empty array
	 *               if no valid tabs are available.
	 */
	public static function set_default_product_tabs_options(): array {
		$tabs    = self::set_default_product_tabs();
		$options = [];

		foreach ( $tabs as $name => $tab ) {
			if ( ! isset( $tab['title'] ) ) {
				continue;
			}

			$options[ $name ] = [
				'value' => $name,
				'label' => 'reviews' === $name
					? esc_html__( 'Reviews', 'et_builder' )
					: esc_html( $tab['title'] ),
			];
		}

		return $options;
	}

	/**
	 * Sets global objects for the theme builder.
	 *
	 * Configures global variables and placeholders to ensure compatibility and rendering
	 * functionality within the theme builder. This includes preparing global `$product` and
	 * `$post` objects with correct placeholder or existing values.
	 *
	 * Based on the legacy `et_theme_builder_wc_set_global_objects` function.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Associative array of conditional tags used for internal checks.
	 *                                Example keys:
	 *                                - 'is_tb' (bool): Whether the current request is related to the theme builder.
	 *
	 * @return void
	 */
	public static function set_global_objects_for_theme_builder( array $conditional_tags = [] ) {
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		// Check if current request is theme builder (direct page / AJAX request).
		if ( ! et_builder_tb_enabled() && ! $is_use_placeholder ) {
			return;
		}

		// Global variable that affects WC module rendering.
		global $product, $post, $tb_original_product, $tb_original_post, $tb_wc_post, $tb_wc_product;

		// Making sure the correct comment template is loaded on WC tabs' review tab.
		add_filter( 'comments_template', [ WooCommerceProductTabsModule::class, 'comments_template_loader' ], 20 );

		// Force display related posts; technically sets all products as related.
		add_filter( 'woocommerce_product_related_posts_force_display', '__return_true' );

		// Make sure review's form is opened.
		add_filter( 'comments_open', '__return_true' );

		// Save original $post for reset later.
		$tb_original_post = $post;

		// Save original $product for reset later.
		$tb_original_product = $product;

		// If modified global existed, use it for efficiency.
		if ( ! is_null( $tb_wc_post ) && ! is_null( $tb_wc_product ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Need to override the post with the theme builder post.
			$post    = $tb_wc_post;
			$product = $tb_wc_product;

			return;
		}

		// Get placeholders.
		$placeholders = self::woocommerce_placeholders();

		if ( $is_use_placeholder ) {
			$placeholder_src = wc_placeholder_img_src( 'full' );
			$placeholder_id  = attachment_url_to_postid( $placeholder_src );

			if ( absint( $placeholder_id ) > 0 ) {
				$placeholders['gallery_image_ids'] = [ $placeholder_id ];
			}
		} else {
			$placeholders['gallery_image_ids'] = [];
		}

		// $post might be null if current request is computed callback (ie. WC gallery)
		if ( is_null( $post ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally done.
			$post = new stdClass();
		}

		// Overwrite $post global.
		$post->post_title     = $placeholders['title'];
		$post->post_slug      = $placeholders['slug'];
		$post->post_excerpt   = $placeholders['short_description'];
		$post->post_content   = $placeholders['description'];
		$post->post_status    = $placeholders['status'];
		$post->comment_status = $placeholders['comment_status'];

		// Set a dummy ID for the placeholder post to ensure product gets a valid ID.
		if ( ! isset( $post->ID ) ) {
			$post->ID = 1; // Use a dummy ID for placeholder context.
		}

		// Overwrite global $product.
		$product = new WooCommerceProductVariablePlaceholder();

		// Set current post ID as product's ID. `WooCommerceProductVariablePlaceholder`
		// handles all placeholder related value but product ID need to be manually set to match current
		// post's ID. This is especially needed when add-ons is used and accessing get_id() method.
		if ( isset( $post->ID ) ) {
			$product->set_id( $post->ID );
		}

		// Save modified global for later use.
		$tb_wc_post    = $post;
		$tb_wc_product = $product;
	}

	/**
	 * Resets global objects for use in the theme builder.
	 *
	 * Adjusts global variables and removes specific filters to prepare
	 * the environment for theme builder rendering or processing. This ensures
	 * proper behavior and compatibility when building or previewing themes.
	 *
	 * Based on the legacy `et_theme_builder_wc_reset_global_objects` function.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Optional. An array of conditional tags to indicate
	 *                                the current context. Supports:
	 *                                - 'is_tb' (bool): Whether the current context is the
	 *                                  theme builder.
	 *
	 * @return void
	 */
	public static function reset_global_objects_for_theme_builder( array $conditional_tags = array() ) {
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		// Check if current request is theme builder (direct page / AJAX request).
		if ( ! et_builder_tb_enabled() && ! $is_use_placeholder ) {
			return;
		}

		global $product, $post, $tb_original_product, $tb_original_post;

		// TODO feat(D5, WooCommerce Product Tabs Module): update the callback once we have the module for tabs in place [https://github.com/elegantthemes/Divi/issues/25756.
		remove_filter( 'comments_template', [ WooCommerceProductTabsModule::class, 'comments_template_loader' ], 20 );
		remove_filter( 'woocommerce_product_related_posts_force_display', '__return_true' );
		remove_filter( 'comments_open', '__return_true' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Need to override the post with the theme builder post.
		$post = $tb_original_post;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Need to override the product with the theme builder product.
		$product = $tb_original_product;
	}

	/**
	 * Retrieves available product page layouts.
	 *
	 * This method returns an array of product page layout options with their labels.
	 * It handles different translation contexts and applies a filter to allow customization.
	 *
	 * Legacy function: et_builder_wc_get_page_layouts()
	 *
	 * @since ??
	 *
	 * @param string $translation_context Translation Context to indicate if translation origins
	 *                                    from Divi Theme or from the Builder. Default 'et_builder'.
	 * @return array Array of product page layouts with their labels.
	 */
	public static function get_page_layouts( string $translation_context = 'et_builder' ): array {
		switch ( $translation_context ) {
			case 'Divi':
				$product_page_layouts = [
					'et_build_from_scratch' => esc_html__( 'Build From Scratch', 'Divi' ),
					'et_default_layout'     => esc_html__( 'Default', 'Divi' ),
				];
				break;
			default:
				$product_page_layouts = [
					'et_build_from_scratch' => esc_html__( 'Build From Scratch', 'et_builder' ),
					'et_default_layout'     => et_builder_i18n( 'Default' ),
				];
				break;
		}

		/**
		 * Filters the available product page layouts.
		 *
		 * @since ??
		 *
		 * @param array $product_page_layouts Array of product page layouts.
		 */
		return apply_filters( 'divi_woocommerce_get_page_layouts', $product_page_layouts );
	}

	/**
	 * Modifies the product image HTML to use a placeholder when needed.
	 *
	 * This method is hooked into the 'woocommerce_single_product_image_thumbnail_html'
	 * filter and modifies the HTML for product images to use a placeholder when needed.
	 *
	 * Legacy function: et_builder_wc_placeholder_img() (partial implementation)
	 *
	 * @since ??
	 *
	 * @param string $html Original image HTML.
	 * @return string Modified image HTML.
	 */
	public static function placeholder_img( string $html ): string {
		// Only modify the HTML if we're in the builder or if the image is missing.
		if ( ! et_core_is_fb_enabled() && ! empty( $html ) ) {
			return $html;
		}

		// Get placeholder image.
		$placeholder_src = wc_placeholder_img_src( 'full' );

		// Create placeholder HTML.
		$placeholder_html  = '<div class="woocommerce-product-gallery__image--placeholder">';
		$placeholder_html .= '<img src="' . esc_url( $placeholder_src ) . '" alt="' . esc_attr__( 'Placeholder', 'et_builder' ) . '" class="wp-post-image" />';
		$placeholder_html .= '</div>';

		return $placeholder_html;
	}

	/**
	 * Returns an HTML img tag for the default image placeholder.
	 *
	 * This method returns an HTML img tag for a placeholder image. It supports
	 * both 'portrait' and 'landscape' modes.
	 *
	 * Legacy function: et_builder_wc_placeholder_img() (direct implementation)
	 *
	 * @since ??
	 *
	 * @param string $mode Default 'portrait'. Either 'portrait' or 'landscape' image mode.
	 * @return string HTML img tag for the placeholder image.
	 */
	public static function get_placeholder_img( string $mode = 'portrait' ): string {
		$allowed_list = [
			'portrait'  => ET_BUILDER_PLACEHOLDER_PORTRAIT_VARIATION_IMAGE_DATA,
			'landscape' => ET_BUILDER_PLACEHOLDER_LANDSCAPE_IMAGE_DATA,
		];

		if ( ! in_array( $mode, array_keys( $allowed_list ), true ) ) {
			$mode = 'portrait';
		}

		return sprintf(
			'<img src="%1$s" alt="%2$s" />',
			et_core_esc_attr( 'placeholder', $allowed_list[ $mode ] ),
			esc_attr__( 'Product image', 'et_builder' )
		);
	}

	/**
	 * Gets the Title header tag.
	 *
	 * WooCommerce version influences the returned header.
	 *
	 * Legacy function: get_title_header()
	 *
	 * @since ??
	 *
	 * @return string The appropriate HTML header tag ('h2' or 'h3') for product titles.
	 */
	public static function get_title_header(): string {
		$header = 'h3';

		if ( ! Conditions::is_woocommerce_enabled() ) {
			return $header;
		}

		global $woocommerce;
		if ( version_compare( '3.0.0', $woocommerce->version, '<=' ) ) {
			$header = 'h2';
		}

		return $header;
	}

	/**
	 * Gets the Title selector.
	 *
	 * WooCommerce changed the title tag from h3 to h2 in v3.0.0.
	 * This function returns a CSS selector for product titles.
	 *
	 * Legacy function: get_title_selector()
	 *
	 * @since ??
	 *
	 * @return string CSS selector for product titles.
	 */
	public static function get_title_selector(): string {
		return sprintf( 'li.product %s', self::get_title_header() );
	}

	/**
	 * Sets the display type to render only products.
	 *
	 * This method is used to control how products are displayed in RelatedProducts and Upsells modules.
	 * It temporarily changes the display type and returns the original value for later restoration.
	 *
	 * Legacy function: set_display_type_to_render_only_products()
	 *
	 * @since ??
	 *
	 * @param string $option_name  The WooCommerce option name to modify.
	 *                             Allowed values: 'woocommerce_shop_page_display', 'woocommerce_category_archive_display'.
	 * @param string $display_type The new display type value. Default empty string.
	 *
	 * @return string The original display type value.
	 */
	public static function set_display_type_to_render_only_products( string $option_name, string $display_type = '' ): string {
		// Allowlist of permitted option names.
		$allowed_option_names = [
			'woocommerce_shop_page_display',
			'woocommerce_category_archive_display',
		];

		// Validate the option name.
		if ( ! in_array( $option_name, $allowed_option_names, true ) ) {
			return '';
		}

		$existing_display_type = get_option( $option_name );
		update_option( $option_name, $display_type );

		return $existing_display_type;
	}

	/**
	 * Resets the display type to the original value.
	 *
	 * This method is used to restore the original display type after rendering
	 * RelatedProducts and Upsells modules.
	 *
	 * Legacy function: reset_display_type()
	 *
	 * @since ??
	 *
	 * @param string $option_name  The WooCommerce option name to modify.
	 *                             Allowed values: 'woocommerce_shop_page_display', 'woocommerce_category_archive_display'.
	 * @param string $display_type The original display type value to restore.
	 *
	 * @return void
	 */
	public static function reset_display_type( string $option_name, string $display_type ): void {
		// Allowlist of permitted option names.
		$allowed_option_names = [
			'woocommerce_shop_page_display',
			'woocommerce_category_archive_display',
		];

		// Validate the option name.
		if ( ! in_array( $option_name, $allowed_option_names, true ) ) {
			return;
		}

		update_option( $option_name, $display_type );
	}

	/**
	 * Gets the HTML for the product reviews comment form.
	 *
	 * This method returns the HTML for the product reviews comment form.
	 * It handles cases where comments are closed or the user is not logged in.
	 *
	 * Legacy function: get_reviews_comment_form()
	 *
	 * @since ??
	 *
	 * @param \WC_Product|false $product The product object. Default false.
	 * @return string The HTML for the product reviews comment form.
	 */
	public static function get_reviews_comment_form( $product = false ): string {
		if ( false === $product ) {
			$product = self::get_product( self::get_default_product() );
		}

		if ( false === $product ) {
			return '';
		}

		$product_id = $product->get_id();

		// Save the current global post to restore it later.
		global $post;
		$original_post = $post;

		// Set the global post to the product post.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Need to override the post for comment_form to work correctly.
		$post = get_post( $product_id );

		// Start output buffering to capture the form HTML.
		ob_start();

		// Check if comments are open for this product.
		if ( comments_open( $product_id ) ) {
			// Check purchase verification requirement.
			$verification_required   = 'yes' === get_option( 'woocommerce_review_rating_verification_required' );
			$customer_bought_product = wc_customer_bought_product( '', get_current_user_id(), $product_id );

			if ( ! $verification_required || $customer_bought_product ) {
				echo '<div id="review_form_wrapper">';
				echo '<div id="review_form">';

				// Get existing reviews to determine if this is the first review.
				$existing_reviews = get_comments(
					[
						'post_id' => $product_id,
						'status'  => 'approve',
						'count'   => true,
					]
				);
				$has_reviews      = $existing_reviews > 0;

				// Get current commenter data.
				$commenter = wp_get_current_commenter();

				// Build comment form configuration.
				$comment_form = [
					'title_reply'         => $has_reviews
						? esc_html__( 'Add a review', 'et_builder' )
						: sprintf( esc_html__( 'Be the first to review &ldquo;%s&rdquo;', 'woocommerce' ), get_the_title( $product_id ) ),
					'title_reply_to'      => esc_html__( 'Leave a Reply to %s', 'et_builder' ),
					'title_reply_before'  => '<span id="reply-title" class="comment-reply-title">',
					'title_reply_after'   => '</span>',
					'comment_notes_after' => '',
					'label_submit'        => esc_html__( 'Submit', 'et_builder' ),
					'submit_button'       => '<button name="%1$s" type="submit" id="%2$s" class="et_pb_button %3$s">%4$s</button>',
					'logged_in_as'        => '',
					'comment_field'       => '',
					'fields'              => [
						'author' => '<p class="comment-form-author">' .
									'<label for="author">' . esc_html__( 'Name', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label> ' .
									'<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" required /></p>',
						'email'  => '<p class="comment-form-email">' .
									'<label for="email">' . esc_html__( 'Email', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label> ' .
									'<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30" required /></p>',
					],
				];

				// Add login link for account page if available.
				$account_page_url = wc_get_page_permalink( 'myaccount' );
				if ( $account_page_url ) {
					/* translators: %1$s opening link tag, %2$s closing link tag */
					$comment_form['must_log_in'] = '<p class="must-log-in">' .
						sprintf(
							esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'woocommerce' ),
							'<a href="' . esc_url( $account_page_url ) . '">',
							'</a>'
						) . '</p>';
				}

				// Add rating field if enabled in WooCommerce settings.
				if ( 'yes' === get_option( 'woocommerce_enable_review_rating' ) ) {
					$comment_form['comment_field'] = '<div class="comment-form-rating">' .
						'<label for="rating">' . esc_html__( 'Your rating', 'et_builder' ) . '</label>' .
						'<select name="rating" id="rating" required>' .
							'<option value="">' . esc_html__( 'Rate&hellip;', 'et_builder' ) . '</option>' .
							'<option value="5">' . esc_html__( 'Perfect', 'et_builder' ) . '</option>' .
							'<option value="4">' . esc_html__( 'Good', 'et_builder' ) . '</option>' .
							'<option value="3">' . esc_html__( 'Average', 'et_builder' ) . '</option>' .
							'<option value="2">' . esc_html__( 'Not that bad', 'et_builder' ) . '</option>' .
							'<option value="1">' . esc_html__( 'Very poor', 'et_builder' ) . '</option>' .
						'</select></div>';
				}

				// Add comment textarea.
				$comment_form['comment_field'] .= '<p class="comment-form-comment">' .
					'<label for="comment">' . esc_html__( 'Your review', 'et_builder' ) . '&nbsp;<span class="required">*</span></label>' .
					'<textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>';

				// Render the comment form with WooCommerce filter support.
				comment_form(
					apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ),
					$product_id
				);

				echo '</div>';
				echo '</div>';
			} else {
				echo '<p class="woocommerce-verification-required">' .
					esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'woocommerce' ) .
					'</p>';
			}
		} else {
			echo '<p class="woocommerce-verification-required">' .
				esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'et_builder' ) .
				'</p>';
		}

		// Get the buffered content.
		$comment_form = ob_get_clean();

		// Restore the original global post.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original global $post data.
		$post = $original_post;

		/**
		 * Filters the product reviews comment form HTML.
		 *
		 * @since ??
		 *
		 * @param string      $comment_form The HTML for the product reviews comment form.
		 * @param \WC_Product $product      The product object.
		 */
		return apply_filters( 'divi_woocommerce_product_reviews_comment_form', $comment_form, $product );
	}

	/**
	 * Gets the reviews title for a product.
	 *
	 * This method returns a formatted title for the product reviews section,
	 * including the review count. It handles cases where there are no reviews
	 * and supports customization through a filter.
	 *
	 * Legacy function: get_reviews_title()
	 *
	 * @since ??
	 *
	 * @param WC_Product|false $product The product object. Default false.
	 * @return string The formatted reviews title.
	 */
	public static function get_reviews_title( $product = false ): string {
		if ( false === $product ) {
			$product = self::get_product( self::get_default_product() );
		}

		if ( ! ( $product instanceof WC_Product ) ) {
			return esc_html__( 'Reviews', 'et_builder' );
		}

		$review_count = $product->get_review_count();

		if ( 0 === $review_count ) {
			$reviews_title = esc_html__( 'Reviews', 'et_builder' );
		} else {
			$reviews_title = sprintf(
				esc_html( _n( '%1$s review for %2$s', '%1$s reviews for %2$s', $review_count, 'et_builder' ) ),
				esc_html( number_format_i18n( $review_count ) ),
				'<span>' . esc_html( $product->get_name() ) . '</span>'
			);
		}

		/**
		 * Filters the product reviews title.
		 *
		 * @since ??
		 *
		 * @param string      $reviews_title The formatted reviews title.
		 * @param \WC_Product $product       The product object.
		 */
		return apply_filters( 'divi_woocommerce_product_reviews_title', $reviews_title, $product );
	}

	/**
	 * Sanitizes text values with octets.
	 *
	 * This function is used to sanitize text values in such a way that octets are preserved.
	 *
	 * This function is based partly on the legacy `et_pb_process_computed_property` function.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized value.
	 */
	public static function sanitize_text_field_values_with_octets( string $value ): string {
		$sanitized_value = $value;

		if ( false !== strpos( $value, '%' ) ) {
			// `sanitize_text_fields()` removes octets `%[a-f0-9]{2}` and would zap/corrupt icon and/or `%date` values,
			// so we prefix octets with `_` to protect them and remove the prefix after sanitization.
			$prepared_value  = preg_replace( '/%([a-f0-9]{2})/', '%_$1', $value );
			$sanitized_value = preg_replace( '/%_([a-f0-9]{2})/', '%$1', sanitize_text_field( $prepared_value ) );
		}

		return $sanitized_value;
	}

	/**
	 * Get the current REST request query params, equivalent to `$_GET` in REST API request.
	 *
	 * @since ??
	 *
	 * @return array|null The current REST request query params, equivalent to `$_GET` in REST API request.
	 */
	public static function get_current_rest_request_query_params(): ?array {
		return self::$_current_rest_request_query_params;
	}

	/**
	 * Validate the product ID.
	 *
	 * Validates the given product ID.
	 * Ideally used in REST API validation callbacks.
	 * This function caches the result of the validation per request using static variable.
	 *
	 * @since ??
	 *
	 * @param mixed                $param   The product ID.
	 * @param WP_REST_Request|null $request Optional. The REST request. Default null.
	 *
	 * @return bool
	 */
	public static function validate_product_id( $param, $request = null ): bool {
		if ( $request instanceof WP_REST_Request ) {
			// Set the current REST request query params, equivalent to `$_GET` in REST API request.
			self::$_current_rest_request_query_params = $request->get_params();
		}

		return 'current' === $param || 'latest' === $param || ( is_numeric( $param ) && absint( $param ) > 0 );
	}

	/**
	 * Validate and sanitize WooCommerce REST request parameters.
	 *
	 * Extracts, validates, and sanitizes common parameters required for WooCommerce module
	 * REST endpoints. Performs permission checks and returns sanitized data or error details.
	 *
	 * Based on the legacy `et_pb_process_computed_property` function.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return array On success, returns sanitized parameters:
	 *               - `conditional_tags` - Filtered conditional tags
	 *               - `current_page` - Filtered current page data
	 *               On error, returns error details:
	 *               - Error code, message, data array, and HTTP status code
	 */
	public static function validate_woocommerce_request_params( WP_REST_Request $request ): array {
		$missing_params = [];
		if ( ! $request->has_param( 'conditionalTags' ) ) {
			$missing_params[] = 'conditionalTags';
		}

		if ( ! $request->has_param( 'currentPage' ) ) {
			$missing_params[] = 'currentPage';
		}

		if ( ! empty( $missing_params ) ) {
			return [
				'invalid_request',
				sprintf( esc_html__( 'Invalid request. Missing required parameters %s.', 'divi' ), implode( ', ', $missing_params ) ),
				[ 'code' => 'invalid_request' ],
				400,
			];
		}

		$conditional_tags = $request->get_param( 'conditionalTags' ) ?? [];
		$current_page     = $request->get_param( 'currentPage' ) ?? [];
		$request_type     = $request->get_param( 'requestType' ) ?? '';

		$utils = ET_Core_Data_Utils::instance();

		// Keep only allowed keys.
		$conditional_tags = array_intersect_key( $conditional_tags, SettingsDataCallbacks::conditional_tags() );
		$current_page     = array_intersect_key( $current_page, SettingsDataCallbacks::current_page() );

		// Sanitize values.
		$conditional_tags = $utils->sanitize_text_fields( $conditional_tags );
		$current_page     = $utils->sanitize_text_fields( $current_page );
		$request_type     = sanitize_text_field( $request_type );
		$product_id       = $current_page['id'] ?? DynamicAssetsUtils::get_current_post_id();

		if ( in_array( $request_type, array( '404', 'archive', 'home' ), true ) ) {
			// For non-singular pages, check theme builder capability.
			if ( ! et_pb_is_allowed( 'theme_builder' ) ) {
				return [
					'rest_forbidden',
					esc_html__( 'You do not have permission to access this Theme Builder page.', 'divi' ),
					[ 'code' => 'rest_forbidden' ],
					403,
				];
			}
		} else {
			// For singular pages, check post edit capability.
			if ( ! current_user_can( 'edit_post', $product_id ) ) {
				return [
					'rest_forbidden',
					esc_html__( 'You do not have permission to edit this post.', 'divi' ),
					[ 'code' => 'rest_forbidden' ],
					403,
				];
			}
		}

		// Check if page ID is present for non-404 requests.
		if ( empty( $product_id ) && '404' !== $request_type ) {
			return [
				'invalid_request',
				esc_html__( 'Missing required parameter: `currentPage.id`.', 'divi' ),
				[ 'code' => 'invalid_request' ],
				400,
			];
		}

		return [
			'conditional_tags' => $conditional_tags,
			'current_page'     => $current_page,
		];
	}

	/**
	 * Force set product's class to `WooCommerceProductVariablePlaceholder` in TB rendering.
	 *
	 * This product classname is specifically filled and will returned TB placeholder data
	 * without retrieving actual value from database.
	 *
	 * @since ??
	 *
	 * @return string The product class name.
	 */
	public static function divi_theme_builder_wc_product_class(): string {
		return 'WooCommerceProductVariablePlaceholder';
	}

	/**
	 * Filters `get_the_terms()` output for Theme Builder layout usage.
	 *
	 * This function uses `get_the_term()` for product tags and categories in WC meta module
	 * and relies on current post's ID to output product's tags and categories.
	 * In TB settings, post ID is irrelevant as the current layout can be used in various pages.
	 * Thus, simply get the first tags and cats then output it for visual preview purpose.
	 *
	 * Based on the legacy function: et_theme_builder_wc_terms()
	 *
	 * @since ??
	 *
	 * @param WP_Term[]|WP_Error $terms    Array of attached terms, or WP_Error, which can be passed here if the taxonomy is invalid.
	 * @param int                $post_id  Post ID.
	 * @param string             $taxonomy Name of the taxonomy.
	 *
	 * @return WP_Term[]|WP_Error Array of attached terms, or WP_Error on failure.
	 */
	public static function theme_builder_wc_terms( $terms, int $post_id, string $taxonomy ) {
		// Only modify `product_cat` and `product_tag` taxonomies;
		// This function is only called in Theme Builder's woocommerceComponent output for current product setting.
		if ( in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) && empty( $terms ) ) {
			$tags = get_categories( array( 'taxonomy' => $taxonomy ) );

			if ( isset( $tags[0] ) ) {
				$terms = array( $tags[0] );
			}
		}

		return $terms;
	}

	/**
	 * Retrieves the WooCommerce components markup for the current page.
	 *
	 * The Woocommerce components markup is passed to the Visual Builder for faster UI rendering.
	 *
	 * Based on the legacy function `et_fb_current_page_woocommerce_components`
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_current_page_woocommerce_components_markup(): array {
		$is_product_cpt        = 'product' === get_post_type();
		$is_tb                 = Conditions::is_tb_enabled();
		$cpt_has_wc_components = $is_product_cpt || $is_tb;
		$has_wc_components     = Conditions::is_woocommerce_enabled() && $cpt_has_wc_components;

		if ( $has_wc_components && $is_tb ) {
			// Set upsells ID for upsell module in TB.
			WooCommerceProductVariablePlaceholder::set_tb_upsells_ids();

			// Remove the legacy hook.
			remove_filter( 'woocommerce_product_class', 'et_theme_builder_wc_product_class' );

			// Force set product's class to WooCommerceProductVariablePlaceholder in TB.
			add_filter( 'woocommerce_product_class', [ self::class, 'divi_theme_builder_wc_product_class' ] );

			// Set product categories and tags in TB.
			add_filter( 'get_the_terms', [ self::class, 'theme_builder_wc_terms' ], 10, 3 );

			// Remove the legacy hook before adding the new one to avoid duplicate functionality.
			remove_filter( 'woocommerce_single_product_image_thumbnail_html', 'et_builder_wc_placeholder_img' );

			// Provides placeholder image HTML for WooCommerce product images.
			// Used when product images are missing or when in the builder.
			add_filter( 'woocommerce_single_product_image_thumbnail_html', [ self::class, 'placeholder_img' ] );
		}

		$woocommerce_components = ! $has_wc_components ? array() : array(
			'divi/woocommerce-breadcrumb'          => WooCommerceBreadcrumbModule::get_breadcrumb(),
			'divi/woocommerce-cart-notice'         => WooCommerceCartNoticeModule::get_cart_notice(),
			'divi/woocommerce-additional-info'     => WooCommerceProductAdditionalInfoModule::get_additional_info(),
			'divi/woocommerce-add-to-cart'         => WooCommerceProductAddToCartModule::get_add_to_cart(),
			'divi/woocommerce-product-description' => WooCommerceProductDescriptionModule::get_description(),
			'divi/woocommerce-product-images'      => WooCommerceProductImagesModule::get_images(),
			'divi/woocommerce-product-gallery'     => WooCommerceProductGalleryModule::get_gallery(),
			'divi/woocommerce-product-meta'        => WooCommerceProductMetaModule::get_meta(),
			'divi/woocommerce-product-price'       => WooCommerceProductPriceModule::get_price(),
			'divi/woocommerce-product-rating'      => WooCommerceProductRatingModule::get_rating(),
			'divi/woocommerce-product-reviews'     => WooCommerceProductReviewsModule::get_reviews_html(),
			'divi/woocommerce-stock'               => WooCommerceProductStockModule::get_stock(),
			'divi/woocommerce-product-tabs'        => WooCommerceProductTabsModule::get_product_tabs(),
			'divi/woocommerce-product-title'       => WooCommerceProductTitleModule::get_title(),
			'divi/woocommerce-upsells'             => WooCommerceProductUpsellModule::get_upsells(),
			'divi/woocommerce-related-products'    => WooCommerceRelatedProductsModule::get_related_products(),
		);

		return $woocommerce_components;
	}

	/**
	 * Renders the product images template for the Product Images module.
	 *
	 * Handles traditional product images with individual toggles for featured image,
	 * gallery, and sale badge. This is the original implementation from the
	 * woocommerce_show_product_images case.
	 *
	 * @since ??
	 *
	 * @param \WC_Product $product       The product object.
	 * @param array       $args          Arguments containing show/hide toggles.
	 * @param string      $function_name The WooCommerce function to call.
	 *
	 * @return void
	 */
	private static function _render_product_images_template( \WC_Product $product, array $args, string $function_name ): void {
		// WC Images module needs to modify global variable's property.
		// This is done here instead of the module class since the $product global might be modified.
		$gallery_ids     = $product->get_gallery_image_ids();
		$image_id        = $product->get_image_id();
		$show_image      = 'on' === ( $args['show_product_image'] ?? 'on' );
		$show_gallery    = 'on' === ( $args['show_product_gallery'] ?? 'on' );
		$show_sale_badge = 'on' === ( $args['show_sale_badge'] ?? 'on' );

		// If featured image is disabled, and gallery is enabled, replace it with first gallery image's ID.
		// If featured image is disabled, and gallery is disabled, replace it with empty string.
		if ( ! $show_image ) {
			if ( $show_gallery && isset( $gallery_ids[0] ) ) {
					$product->set_image_id( $gallery_ids[0] );

					// Remove first image from the gallery because it'll be added as thumbnail and will be duplicated.
					unset( $gallery_ids[0] );
					$product->set_gallery_image_ids( $gallery_ids );
			} else {
				$product->set_image_id( '' );
			}
		}

		// Replace gallery image IDs with an empty array if gallery is disabled.
		if ( ! $show_gallery ) {
			$product->set_gallery_image_ids( array() );
		}

		if ( $show_sale_badge && function_exists( 'woocommerce_show_product_sale_flash' ) ) {
			woocommerce_show_product_sale_flash();
		}

		// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Only allowlisted functions reach here.
		call_user_func( $function_name );

		// Reset product's actual featured image ID.
		if ( ! $show_image ) {
			$product->set_image_id( $image_id );
		}

		// Reset product's actual gallery image ID.
		if ( ! $show_gallery ) {
			$product->set_gallery_image_ids( $gallery_ids );
		}
	}

	/**
	 * Renders the product gallery template for the Product Gallery module.
	 *
	 * Handles gallery-specific rendering based on WooCommerce Blocks ProductGallery
	 * implementation with support for layout modes (grid/slider), pagination,
	 * thumbnail orientation, and gallery-specific features.
	 *
	 * @since ??
	 *
	 * @param \WC_Product $product The product object.
	 * @param array       $args    Arguments containing gallery-specific settings.
	 *
	 * @return void
	 */
	private static function _render_product_gallery_template( \WC_Product $product, array $args ): void {
		// Extract gallery-specific parameters.
		$gallery_layout         = $args['gallery_layout'] ?? 'grid';
		$thumbnail_orientation  = $args['thumbnail_orientation'] ?? 'landscape';
		$show_pagination        = $args['show_pagination'] ?? 'on';
		$show_title_and_caption = $args['show_title_and_caption'] ?? 'off';

		// For Product Gallery, we want to show all gallery images in their gallery context.
		// Unlike Product Images module, we don't manipulate individual image visibility.

		// Ensure WooCommerce gallery features are supported for enhanced functionality.
		// Based on WooCommerce Blocks ProductGallery theme support requirements.
		if ( ! current_theme_supports( 'wc-product-gallery-zoom' ) ) {
			add_theme_support( 'wc-product-gallery-zoom' );
		}
		if ( ! current_theme_supports( 'wc-product-gallery-lightbox' ) ) {
			add_theme_support( 'wc-product-gallery-lightbox' );
		}
		if ( ! current_theme_supports( 'wc-product-gallery-slider' ) ) {
			add_theme_support( 'wc-product-gallery-slider' );
		}

		// For slider mode, ensure we have the right gallery configuration.
		$slider_filter_callback = null;
		if ( 'slider' === $gallery_layout ) {
			// Add slider-specific classes and data attributes through filter.
			$slider_filter_callback = function( $html, $attachment_id ) use ( $thumbnail_orientation ) {
				// Ensure proper thumbnail orientation for slider mode.
				if ( strpos( $html, 'class=' ) !== false ) {
					$html = str_replace( 'class="', 'class="et_pb_gallery_item et_pb_gallery_item_' . esc_attr( $thumbnail_orientation ) . ' ', $html );
				}
				return $html;
			};

			add_filter(
				'woocommerce_single_product_image_thumbnail_html',
				$slider_filter_callback,
				10,
				2
			);
		}

		// Call the WooCommerce function to render the product images/gallery.
		// The context and filters above will ensure it renders appropriately for gallery mode.
		woocommerce_show_product_images();

		// Clean up only our specific filter to avoid affecting other plugins/themes.
		if ( 'slider' === $gallery_layout && null !== $slider_filter_callback ) {
			remove_filter( 'woocommerce_single_product_image_thumbnail_html', $slider_filter_callback, 10 );
		}
	}

	/**
	 * Get placeholders for WooCommerce module in Theme Builder.
	 *
	 * Based on the legacy function: et_is_woocommerce_plugin_active().
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function woocommerce_placeholders(): array {
		return array(
			'title'             => esc_html__( 'Product name', 'et_builder' ),
			'slug'              => 'product-name',
			'short_description' => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris bibendum eget dui sed vehicula. Suspendisse potenti. Nam dignissim at elit non lobortis.', 'et_builder' ),
			'description'       => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris bibendum eget dui sed vehicula. Suspendisse potenti. Nam dignissim at elit non lobortis. Cras sagittis dui diam, a finibus nibh euismod vestibulum. Integer sed blandit felis. Maecenas commodo ante in mi ultricies euismod. Morbi condimentum interdum luctus. Mauris iaculis interdum risus in volutpat. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Praesent cursus odio eget cursus pharetra. Aliquam lacinia lectus a nibh ullamcorper maximus. Quisque at sapien pulvinar, dictum elit a, bibendum massa. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris non pellentesque urna.', 'et_builder' ),
			'status'            => 'publish',
			'comment_status'    => 'open',
		);
	}

	/**
	 * Sort an array of WC_Product objects by total sales in descending order.
	 *
	 * Modules that need "popularity" ordering (Upsells, Related Products, etc.) can
	 * call this helper instead of implementing their own usort logic.
	 *
	 * @since ??
	 *
	 * @param array<WC_Product> $products Products to sort.
	 *
	 * @return array<WC_Product> Sorted products array (highest sales first).
	 */
	public static function sort_products_by_sales_desc( array $products ): array {
		usort(
			$products,
			static function ( $a, $b ) {
				$sales_a = method_exists( $a, 'get_total_sales' ) ? (int) $a->get_total_sales() : (int) get_post_meta( $a->get_id(), 'total_sales', true );
				$sales_b = method_exists( $b, 'get_total_sales' ) ? (int) $b->get_total_sales() : (int) get_post_meta( $b->get_id(), 'total_sales', true );

				// Descending order: highest sales first.
				if ( $sales_a > $sales_b ) {
					return -1;
				} elseif ( $sales_a < $sales_b ) {
					return 1;
				}
				return 0;
			}
		);

		return $products;
	}

	/**
	 * Prevents WooCommerce templates from re-sorting already sorted products.
	 *
	 * This filter callback removes orderby parameters from WooCommerce product queries
	 * to prevent templates from overriding our custom sorting.
	 *
	 * @since ??
	 *
	 * @param array $args    WooCommerce shortcode query args.
	 * @param array $atts    Shortcode attributes.
	 *
	 * @return array Modified query args without sorting parameters.
	 */
	public static function prevent_template_resorting( array $args, array $atts ): array {
		// Remove sorting parameters to prevent template re-sorting.
		unset( $args['orderby'] );
		unset( $args['order'] );
		unset( $args['meta_key'] );

		return $args;
	}

	/**
	 * Renders WooCommerce product list with custom popularity sorting by sales.
	 *
	 * This function bypasses WooCommerce's wc_products_array_orderby which doesn't support
	 * popularity sorting, and implements custom sorting based on total sales.
	 *
	 * @since ??
	 *
	 * @param array $args Module arguments containing product ID and display settings.
	 * @param array $display_args Display arguments (posts_per_page, columns, etc.).
	 *
	 * @return string Rendered product list HTML.
	 */
	public static function render_products_sorted_by_popularity( array $args, array $display_args ): string {
		// Get product from args or fall back to global.
		$product_id = $args['product'] ?? 'current';
		$product_id = self::get_product_id( $product_id );
		$product    = wc_get_product( $product_id );

		if ( ! ( $product instanceof \WC_Product ) ) {
			return '';
		}

		// Extract display settings.
		$limit   = (int) ( $display_args['posts_per_page'] ?? -1 );
		$columns = (int) ( $display_args['columns'] ?? 4 );
		$offset  = (int) ( $args['offset_number'] ?? 0 );

		// Get upsell product IDs.
		$upsell_ids = $product->get_upsell_ids();

		if ( empty( $upsell_ids ) ) {
			return '';
		}

		// Convert IDs to product objects and filter visible ones.
		$upsells = array_filter( array_map( 'wc_get_product', $upsell_ids ), 'wc_products_array_filter_visible' );

		if ( empty( $upsells ) ) {
			return '';
		}

		// Sort by total sales (highest first - popularity sorting).
		$upsells = self::sort_products_by_sales_desc( $upsells );

		// Apply offset first (issue #1 fix).
		if ( $offset > 0 ) {
			$upsells = array_slice( $upsells, $offset );
		}

		// Apply limit after offset.
		if ( $limit > 0 ) {
			$upsells = array_slice( $upsells, 0, $limit );
		}

		// Set loop props required by template.
		wc_set_loop_prop( 'columns', $columns );

		// Prevent template from re-sorting our already sorted products.
		add_filter( 'woocommerce_shortcode_products_query', [ self::class, 'prevent_template_resorting' ], 10, 2 );

		// Get template with our sorted products.
		ob_start();
		wc_get_template(
			'single-product/up-sells.php',
			[
				'upsells'        => $upsells,
				'posts_per_page' => $limit,
				'columns'        => $columns,
			]
		);
		$output = ob_get_clean();

		// Remove filter after use.
		remove_filter( 'woocommerce_shortcode_products_query', [ self::class, 'prevent_template_resorting' ], 10 );

		return $output;
	}

}
