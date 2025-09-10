<?php
/**
 * Module Library: WooCommerce Product Gallery Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductGallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WooCommerce Product Gallery REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductGalleryController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Gallery module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 *                                  If the request is invalid, a `WP_Error` object is returned.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$product_id = $request->get_param( 'productId' ) ?? 'current';

		// Validate product exists for numeric product IDs.
		$product = WooCommerceUtils::get_product( $product_id );

		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				array( 'status' => 404 ),
				404
			);
		}

		// Prepare arguments for the unified gallery method.
		$args = [
			'product'                => $product_id,
			'gallery_layout'         => $request->get_param( 'galleryLayout' ) ?? 'grid',
			'thumbnail_orientation'  => $request->get_param( 'thumbnailOrientation' ) ?? 'landscape',
			'show_pagination'        => $request->get_param( 'showPagination' ) ?? 'on',
			'show_title_and_caption' => $request->get_param( 'showTitleAndCaption' ) ?? 'on',
			'hover_icon'             => $request->get_param( 'hoverIcon' ) ?? '',
			'hover_icon_tablet'      => $request->get_param( 'hoverIconTablet' ) ?? '',
			'hover_icon_phone'       => $request->get_param( 'hoverIconPhone' ) ?? '',
			'posts_number'           => 4,
		];

		// Use the unified gallery method to generate HTML.
		$gallery_html = WooCommerceProductGalleryModule::get_gallery( $args );

		$response = [
			'html' => $gallery_html,
		];

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
		return [
			'productId'            => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'current',
				'sanitize_callback' => function( $param ) {
					$param = sanitize_text_field( $param );

					// Handle empty strings by defaulting to 'current'.
					if ( empty( $param ) ) {
						return 'current';
					}
					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			],
			'galleryLayout'        => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'grid',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, [ 'grid', 'slider' ], true );
				},
			],
			'thumbnailOrientation' => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'landscape',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, [ 'landscape', 'portrait' ], true );
				},
			],
			'showPagination'       => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'on',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'showTitleAndCaption'  => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'hoverIcon'            => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'hoverIconTablet'      => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'hoverIconPhone'       => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
		];
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

	/**
	 * Generate gallery HTML output (moved from module for better separation of concerns)
	 *
	 * Based on D4 ET_Builder_Module_Gallery::render()
	 *
	 * @see includes/builder/module/Gallery.php:612-713+ (D4)
	 *
	 * Key D4 patterns followed:
	 * - Line 612-623: Gallery wrapper with proper classes and data attributes
	 * - Line 635-642: Overlay output generation for grid layout
	 * - Line 664-742: Gallery item structure and image output
	 * - Line 747-773: Pagination output for grid layout (not fullwidth)
	 * - Line 694-700: Gallery item classes and positioning
	 *
	 * Adapted for D5 POST requests and WooCommerce context with HTMLUtility pattern
	 *
	 * @since ??
	 *
	 * @param array $attachments Gallery attachments with metadata.
	 * @param array $args        Gallery rendering arguments.
	 * @param array $attrs       Module attributes.
	 * @param array $icon_data   Icon data for enhanced overlay rendering.
	 *
	 * @return string The gallery HTML output.
	 */
	public static function generate_gallery_html( array $attachments, array $args, array $attrs, array $icon_data = [] ): string {
		// Extract parameters (from D4 Gallery render() method).
		$posts_number           = $args['posts_number'] ?? 4;
		$fullwidth              = $args['fullwidth'] ?? 'off';
		$show_title_and_caption = $args['show_title_and_caption'] ?? 'off';
		$show_pagination        = $args['show_pagination'] ?? 'on';
		$orientation            = $args['orientation'] ?? 'landscape';

		// D5 Enhancement: Extract icon data for enhanced overlay.
		$icon        = $icon_data['icon'] ?? '';
		$icon_tablet = $icon_data['icon_tablet'] ?? '';
		$icon_phone  = $icon_data['icon_phone'] ?? '';

		// D5 Enhancement: Generate enhanced overlay using HTMLUtility
		// @see includes/builder-5/server/Packages/ModuleLibrary/Gallery/GalleryModule.php:772-790.
		$overlay_classes = [
			'et_overlay'               => true,
			'et_pb_inline_icon'        => ! empty( $icon ),
			'et_pb_inline_icon_tablet' => ! empty( $icon_tablet ),
			'et_pb_inline_icon_phone'  => ! empty( $icon_phone ),
		];

		$overlay_output = HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class'            => HTMLUtility::classnames( $overlay_classes ),
					'data-icon'        => $icon,
					'data-icon-tablet' => $icon_tablet,
					'data-icon-phone'  => $icon_phone,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Generate CSS classes for D5 HTMLUtility pattern.
		$gallery_classes = [
			'et_pb_gallery'    => true,
			'et_pb_wc_gallery' => true,
		];

		if ( 'on' === $fullwidth ) {
			$gallery_classes['et_pb_slider']            = true;
			$gallery_classes['et_pb_gallery_fullwidth'] = true;
		} else {
			$gallery_classes['et_pb_gallery_grid'] = true;
		}

		// Generate gallery items wrapper with D5 HTMLUtility pattern.
		$gallery_items = [];

		// Gallery items container attributes.
		$gallery_items_classes = [ 'et_pb_gallery_items', 'et_post_gallery', 'clearfix' ];

		// Add flex grid classes for proper 4-per-row layout (D5 Gallery pattern).
		if ( 'on' !== $fullwidth ) {
			$gallery_items_classes[] = 'et_pb_grid_items';
			$gallery_items_classes[] = 'et_flex_grid';
		}

		// For pagination OFF: set data-per_page to total attachments to show all items.
		// For pagination ON: use posts_number to limit items per page.
		$per_page_value = ( 'on' !== $show_pagination ) ? count( $attachments ) : $posts_number;

		$gallery_items_attrs = [
			'class'         => implode( ' ', $gallery_items_classes ),
			'data-per_page' => $per_page_value,
		];

		// Generate gallery items (based on D4 Gallery render() lines 664-713+).
		$images_count = 0;

		// Pagination Logic: Include all images but let JavaScript handle pagination (Gallery module pattern).
		// The Gallery script calculates pages based on total DOM items vs data-per_page attribute.
		// Don't limit attachments here - JavaScript will hide/show items for pagination.

		foreach ( $attachments as $attachment ) {
			// Prefer WooCommerce's canonical image markup to maximize compatibility with extensions.
			// Treat the first image as main image when in slider (fullwidth) mode.
			$is_main_image = ( 'on' === $fullwidth && 0 === $images_count );

			// Fallback to WP_Post->ID if custom object.
			$attachment_id = 0;
			if ( isset( $attachment->ID ) ) {
				$attachment_id = (int) $attachment->ID;
			} elseif ( isset( $attachment->id ) ) {
				$attachment_id = (int) $attachment->id; // Defensive.
			}

			// Generate orientation-specific thumbnails (Gallery module pattern).
			$width  = 400;
			$height = ( 'landscape' === $orientation ) ? 284 : 516;

			// Apply Divi filters for image sizing.
			$width  = (int) apply_filters( 'et_pb_gallery_image_width', $width );
			$height = (int) apply_filters( 'et_pb_gallery_image_height', $height );

			$wc_image_html = '';
			if ( $attachment_id > 0 ) {
				// Get properly sized thumbnail for orientation.
				$image_src_full  = wp_get_attachment_image_src( $attachment_id, 'full' );
				$image_src_thumb = wp_get_attachment_image_src( $attachment_id, [ $width, $height ] );

				if ( $image_src_full && $image_src_thumb ) {
					// Generate WooCommerce-compatible image HTML with orientation-specific sizing.
					$image_alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
					$image_title = get_the_title( $attachment_id );

					$wc_image_html = sprintf(
						'<div data-thumb="%1$s" data-thumb-alt="%2$s" data-thumb-srcset="" data-thumb-sizes="" class="woocommerce-product-gallery__image">
							<a href="%3$s">
								<img width="%4$d" height="%5$d" src="%1$s" class="" alt="%2$s" data-caption="%6$s" data-src="%3$s" data-large_image="%3$s" data-large_image_width="%7$d" data-large_image_height="%8$d" decoding="async" loading="lazy" />
							</a>
						</div>',
						esc_url( $image_src_thumb[0] ),
						esc_attr( $image_alt ),
						esc_url( $image_src_full[0] ),
						(int) $image_src_thumb[1],
						(int) $image_src_thumb[2],
						esc_attr( $image_title ),
						(int) $image_src_full[1],
						(int) $image_src_full[2]
					);
				}
			}

			// If WooCommerce did not return markup for any reason, fall back to minimal anchor+img.
			if ( empty( $wc_image_html ) ) {
				$full_src  = $attachment->image_src_full[0] ?? '';
				$thumb_src = $attachment->image_src_thumb[0] ?? '';
				$alt       = $attachment->image_alt_text ?? '';

				$wc_image_html = sprintf(
					'<a href="%1$s" title="%2$s">
                        <img src="%3$s" alt="%4$s">
                    </a>',
					esc_url( $full_src ),
					esc_attr( $attachment->post_title ?? '' ),
					esc_url( $thumb_src ),
					esc_attr( $alt )
				);
			}

			// Append Divi overlay on top of WooCommerce image markup, preserving our structure/classes.
			$image_output = $wc_image_html . $overlay_output;

			// Generate gallery item classes following D4 pattern.
			// @see includes/builder/module/Gallery.php:694-700 (D4).
			$item_classes = [
				'et_pb_gallery_item' => true,
			];

			// Add grid item classes for grid layout (D4 + D5 pattern).
			if ( 'on' !== $fullwidth ) {
				$item_classes['et_pb_grid_item'] = true;
				// Add D5 flex column classes for 4-per-row layout (6 out of 24 columns = 25% width).
				$item_classes['et_flex_column']      = true;
				$item_classes['et_flex_column_6_24'] = true;
			}

			// D4 Pattern: Add gallery order and count classes
			// @see includes/builder/module/Gallery.php:691-692 (D4).
			// Note: Gallery order would typically come from module rendering context in D4
			// For D5 REST API context, we use a simplified approach.
			$gallery_order       = 'wc_gallery'; // Simplified for REST API context.
			$item_class_specific = "et_pb_gallery_item_{$gallery_order}_{$images_count}";

			// Note: first_in_row, last_in_row, and on_last_row classes are added dynamically by JavaScript
			// via et_pb_set_responsive_grid() function, not in PHP. This matches D4 behavior.

			// Use user-selected orientation setting.
			// Respect user preference - don't override with automatic detection.
			$actual_orientation = $orientation;

			// Build gallery image container with D5 HTMLUtility pattern.
			$image_container = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames(
							[
								'et_pb_gallery_image' => true,
								'landscape'           => 'portrait' !== $actual_orientation,
								'portrait'            => 'portrait' === $actual_orientation,
							]
						),
					],
					'children'          => $image_output,
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			// Build title and caption elements if enabled.
			$item_children = [ $image_container ];

			if ( 'on' !== $fullwidth && 'on' === $show_title_and_caption ) {
				if ( ! empty( $attachment->post_title ) ) {
					$item_children[] = HTMLUtility::render(
						[
							'tag'        => 'h3',
							'attributes' => [
								'class' => 'et_pb_gallery_title',
							],
							'children'   => wptexturize( $attachment->post_title ),
						]
					);
				}

				if ( ! empty( $attachment->post_excerpt ) ) {
					$item_children[] = HTMLUtility::render(
						[
							'tag'        => 'p',
							'attributes' => [
								'class' => 'et_pb_gallery_caption',
							],
							'children'   => wptexturize( $attachment->post_excerpt ),
						]
					);
				}
			}

			// Build complete gallery item with D5 HTMLUtility pattern.
			$gallery_items[] = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames( $item_classes ) . ' ' . $item_class_specific,
						'style' => 'display: block;',
					],
					'children'          => implode( '', $item_children ),
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			$images_count++;
		}

		// Build gallery items container with D5 HTMLUtility pattern.
		$gallery_items_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => $gallery_items_attrs,
				'children'          => implode( '', $gallery_items ),
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// D4 Pattern: Add pagination for grid layout (not fullwidth/slider)
		// @see includes/builder/module/Gallery.php:747-773 (D4).
		$pagination_output = '';
		$show_pagination   = $args['show_pagination'] ?? 'on';

		if ( 'on' !== $fullwidth && 'on' === $show_pagination ) {
			$pagination_output = HTMLUtility::render(
				[
					'tag'        => 'div',
					'attributes' => [
						'class' => 'et_pb_gallery_pagination',
					],
					'children'   => '', // Pagination content would be generated by JavaScript.
				]
			);
		}

		// Build complete gallery wrapper with D5 HTMLUtility pattern.
		$gallery_children = $gallery_items_container . $pagination_output;

		$output = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => HTMLUtility::classnames( $gallery_classes ),
				],
				'children'          => $gallery_children,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		return $output;
	}
}
