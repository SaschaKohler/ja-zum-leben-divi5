<?php
/**
 * Module Library: WooCommerceProductImages Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductImages;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductImagesModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductImages module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductImagesModule implements DependencyInterface {

	/**
	 * Generates the style declaration for forcing a fullwidth image in the WooCommerce Product Image module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue  Whether to force fullwidth. Accepts 'on' or 'off'. Default 'off'.
	 *     @type string $returnType Optional. The return type for the style declarations.
	 * }
	 *
	 * @return string CSS style declaration.
	 *
	 * @example
	 * ```php
	 * // Example of forcing a fullwidth image.
	 * $params = [
	 *   'attrValue' => 'on',
	 * ];
	 * $style = WooCommerceProductImagesModule::fullwidth_module_style_declaration( $params );
	 * // Result: 'width: 100%;'
	 * ```
	 */
	public static function fullwidth_module_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $params['returnType'] ?? 'array',
				'important'  => false,
			]
		);

		$force_fullwidth = $params['attrValue'] ?? 'off';

		if ( 'on' === $force_fullwidth ) {
			$style_declarations->add( 'width', '100%' );
		}

		return $style_declarations->value();
	}

	/**
	 * Style declaration for toggling the featured image visibility in the WooCommerce Product Image module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue The toggle value for image visibility. Accepts 'on' or 'off'. Default 'on'.
	 *     @type string $returnType Optional. The return type for the style declarations.
	 * }
	 *
	 * @return string CSS style declaration.
	 *
	 * @example
	 * ```php
	 * // Example of hiding the featured image.
	 * $params = [
	 *   'attrValue' => 'off',
	 * ];
	 * $style = WooCommerceProductImagesModule::toggle_featured_image_style_declaration( $params );
	 * // Result: 'visibility: hidden;'
	 * ```
	 */
	public static function toggle_featured_image_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $params['returnType'] ?? 'array',
				'important'  => false,
			]
		);

		$show_product_image = $params['attrValue'] ?? 'on';

		if ( 'off' === $show_product_image ) {
			$style_declarations->add( 'visibility', 'hidden' );
		}

		return $style_declarations->value();
	}

	/**
	 * Get the style declaration for thumbnail overflow attribute.
	 *
	 * This function gets the style declaration for the thumbnail overflow attribute.
	 * It applies overflow:hidden to thumbnail containers only when border radius is set
	 * to prevent images from extending beyond rounded corners.
	 *
	 * @param array $params {
	 *     An array of parameters.
	 *
	 *     @type array $attrValue {
	 *         An array of attribute values containing border radius settings.
	 *    }
	 * }
	 *
	 * @return string|array The style declaration for the overflow attribute.
	 *
	 * @example:
	 * ```php
	 *   $params = [
	 *       'attrValue' => [
	 *           'radius' => [
	 *               'top' => '10px',
	 *               'right' => '10px',
	 *               'bottom' => '10px',
	 *               'left' => '10px',
	 *           ],
	 *       ],
	 *   ];
	 *   $styleDeclaration = thumbnail_overflow_style_declaration( $params );
	 *
	 *   // Output: 'overflow: hidden;'
	 * ```
	 */
	public static function thumbnail_overflow_style_declaration( array $params ) {
		$radius      = $params['attrValue']['radius'] ?? [];
		$return_type = $params['returnType'] ?? 'string';

		if ( ! $radius ) {
			return 'string' === $return_type ? '' : [];
		}

		$all_corners_zero = true;

		// Check whether all corners are zero.
		// If any corner is not zero, update the variable and break the loop.
		foreach ( $radius as $corner => $value ) {
			if ( 'sync' === $corner ) {
				continue;
			}

			$corner_value = SanitizerUtility::numeric_parse_value( $value ?? '' );
			if ( 0.0 !== ( $corner_value['valueNumber'] ?? 0.0 ) ) {
				$all_corners_zero = false;
				break;
			}
		}

		if ( $all_corners_zero ) {
			return 'string' === $return_type ? '' : [];
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $return_type,
				'important'  => false,
			]
		);

		// Add overflow hidden when any corner's border radius is not zero.
		$style_declarations->add( 'overflow', 'hidden' );

		return $style_declarations->value();
	}

	/**
	 * Render callback for the WooCommerceProductImages module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductImagesEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductImages module.
	 *
	 * @example
	 * ```php
	 * $attrs = [
	 *   'attrName' => 'value',
	 *   //...
	 * ];
	 * $content = 'The block content.';
	 * $block = new WP_Block();
	 * $elements = new ModuleElements();
	 *
	 * WooCommerceProductImagesModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get parameters from attributes.
		$product_id           = $attrs['content']['advanced']['product']['desktop']['value'] ?? WooCommerceUtils::get_default_product();
		$show_product_image   = $attrs['elements']['advanced']['showProductImage']['desktop']['value'] ?? 'off';
		$show_product_gallery = $attrs['elements']['advanced']['showProductGallery']['desktop']['value'] ?? 'off';
		$show_sale_badge      = $attrs['elements']['advanced']['showSaleBadge']['desktop']['value'] ?? 'off';

		// Get the HTML.
		$images_html = self::get_images(
			[
				'product'              => $product_id,
				'show_product_image'   => $show_product_image,
				'show_product_gallery' => $show_product_gallery,
				'show_sale_badge'      => $show_sale_badge,
			]
		);

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'children'            => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [
								'class' => 'et_pb_module_inner',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							// Use the generated HTML.
							'children'          => $images_html,
						]
					),
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductImages module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-classnames moduleClassnames}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Module classnames instance.
	 *     @type array  $attrs              Block attributes data for rendering the module.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * $args = [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => $attrs,
	 * ];
	 *
	 * WooCommerceProductImagesModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $attrs['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * WooCommerceProductImages module script data.
	 *
	 * This function assigns variables and sets script data options for the module.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs ModuleScriptData}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for setting the module script data.
	 *
	 *     @type string         $id            The module ID.
	 *     @type string         $name          The module name.
	 *     @type string         $selector      The module selector.
	 *     @type array          $attrs         The module attributes.
	 *     @type int            $storeInstance The ID of the instance where this block is stored in the `BlockParserStore` class.
	 *     @type ModuleElements $elements      The `ModuleElements` instance.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 * // Generate the script data for a module with specific arguments.
	 * $args = array(
	 *     'id'             => 'my-module',
	 *     'name'           => 'My Module',
	 *     'selector'       => '.my-module',
	 *     'attrs'          => array(
	 *         'portfolio' => array(
	 *             'advanced' => array(
	 *                 'showTitle'       => false,
	 *                 'showCategories'  => true,
	 *                 'showPagination' => true,
	 *             )
	 *         )
	 *     ),
	 *     'elements'       => $elements,
	 *     'store_instance' => 123,
	 * );
	 *
	 * WooCommerceProductImagesModule::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$elements = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
	}

	/**
	 * WooCommerceProductImages Module's style components.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-styles moduleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name              Module name.
	 *      @type string $attrs             Module attributes.
	 *      @type string $parentAttrs       Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings          Custom settings.
	 *      @type string $state             Attributes state.
	 *      @type string $mode              Style mode.
	 *      @type ModuleElements $elements  ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$order_class = $args['orderClass'];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					// Elements, FE only style output to hide the featured image visibility.
					$elements->style(
						[
							'attrName'   => 'elements',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'     => $attrs['elements']['advanced']['showProductImage'] ?? [],
											'selector' => "{$args['orderClass']} .woocommerce-product-gallery__image--placeholder img[src*=\"woocommerce-placeholder\"]",
											'declarationFunction' => [ self::class, 'toggle_featured_image_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'     => $attrs['image']['advanced']['forceFullwidth'] ?? [],
											'selector' => "{$args['orderClass']} .woocommerce-product-gallery__image img",
											'declarationFunction' => [ self::class, 'fullwidth_module_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'selector' => "{$order_class} div.images ol.flex-control-thumbs.flex-control-nav li, {$order_class} .flex-viewport, {$order_class} .woocommerce-product-gallery--without-images .woocommerce-product-gallery__wrapper, {$order_class} .woocommerce-product-gallery > div:not(.flex-viewport) .woocommerce-product-gallery__image, {$order_class} .woocommerce-product-gallery > .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image, {$order_class} .woocommerce-product-gallery .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image",
											'declarationFunction' => [ self::class, 'thumbnail_overflow_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Sale Badge.
					$elements->style(
						[
							'attrName' => 'saleBadge',
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Get the custom CSS fields for the Divi WooCommerceProductImages module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductImages module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductImages module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductImages module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-images' )->customCssFields;
	}

	/**
	 * Loads `WooCommerceProductImagesModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		/*
		 * Bail if  WooCommerce plugin is not active or the feature-flag `wooProductPageModules` is disabled.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Add a filter for processing dynamic attribute defaults.
		add_filter(
			'divi_module_library_module_default_attributes_divi/woocommerce-product-images',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-images/';

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Retrieves the product images for a given set of arguments.
	 *
	 * This function uses the WooCommerceUtils to render the module template
	 * for the product images based on the provided arguments.
	 *
	 * Additionally, this function handles the YITH Badge Management plugin (which
	 * executes only when do_action( 'woocommerce_product_thumbnails' ) returns FALSE)
	 * compatibility when multiple Woo Images modules are placed on the same page
	 * by resetting the 'woocommerce_product_thumbnails' action.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the product images.
	 *
	 *     @type string $product              Optional. The product identifier. Default 'current'.
	 *     @type string $show_product_image   Optional. Whether to show the product image. One of 'on' or 'off'. Default 'on'.
	 *     @type string $show_product_gallery Optional. Whether to show the product gallery. One of 'on' or 'off'. Default 'on'.
	 *     @type string $show_sale_badge      Optional. Whether to show the sale badge. One of 'on' or 'off'. Default 'on'.
	 * }
	 *
	 * @return string The rendered product images.
	 *
	 * @example:
	 * ```php
	 * $images = WooCommerceProductImagesModule::get_images();
	 * // Returns the product images for the current product.
	 *
	 * $images = WooCommerceProductImagesModule::get_images( [ 'product' => 123, 'show_product_image' => 'off' ] );
	 * // Returns the product images for the product with ID 123.
	 * ```
	 */
	public static function get_images( array $args = array() ): string {
		/*
		 * YITH Badge Management plugin executes only when
		 * do_action( 'woocommerce_product_thumbnails' ) returns FALSE.
		 *
		 * The above won't be the case when multiple Woo Images modules are placed on the same page.
		 * The workaround is to reset the 'woocommerce_product_thumbnails' action.
		 *
		 * {@link https://github.com/elegantthemes/Divi/issues/18530}
		 */
		global $wp_actions;

		$tag   = 'woocommerce_product_thumbnails';
		$reset = false;
		$value = 0;

		if ( isset( $wp_actions[ $tag ] ) ) {
			$value = $wp_actions[ $tag ];
			$reset = true;
			unset( $wp_actions[ $tag ] );
		}

		$defaults = array(
			'product'              => 'current',
			'show_product_image'   => 'on',
			'show_product_gallery' => 'on',
			'show_sale_badge'      => 'on',
		);
		$args     = wp_parse_args( $args, $defaults );

		// Handle 'current' value for product.
		if ( 'current' === $args['product'] ) {
			$args['product'] = WooCommerceUtils::get_product_id( $args['product'] );
		}

		$images = WooCommerceUtils::render_module_template(
			'woocommerce_show_product_images',
			$args,
			array( 'product', 'post' )
		);

		/*
		 * Reset changes made for YITH Badge Management plugin.
		 * {@link https://github.com/elegantthemes/Divi/issues/18530}
		 */
		if ( $reset && ! isset( $wp_actions[ $tag ] ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is a fix for compatibility with YITH Badge Management plugin.
			$wp_actions[ $tag ] = $value;
		}

		return $images;
	}
}
