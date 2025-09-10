<?php
/**
 * Module Library: WooCommerceProductTabs Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTabs;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductTabsModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductTabs module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductTabsModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductTabs module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductTabsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductTabs module.
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
	 * WooCommerceProductTabsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get product and includeTabs attributes.
		$product_id   = $attrs['content']['advanced']['product']['desktop']['value'] ?? WooCommerceUtils::get_default_product();
		$include_tabs = $attrs['content']['advanced']['includeTabs']['desktop']['value'] ?? [];

		// Get product tabs data.
		$tabs_data = self::get_product_tabs(
			[
				'product'      => $product_id,
				'include_tabs' => $include_tabs,
			]
		);

		// Generate tab navigation HTML.
		$tab_nav_html = self::render_tab_nav( $tabs_data );

		// Generate tab content HTML.
		$tab_content_html = self::render_tab_content( $tabs_data );

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
					$elements->style_components(
						[
							'attrName' => 'content',
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
							'children'          => [
								HTMLUtility::render(
									[
										'tag'        => 'ul',
										'tagEscaped' => true,
										'attributes' => [
											'class' => 'et_pb_tabs_controls clearfix',
										],
										'childrenSanitizer' => 'et_core_esc_previously',
										'children'   => $tab_nav_html,
									]
								),
								HTMLUtility::render(
									[
										'tag'        => 'div',
										'tagEscaped' => true,
										'attributes' => [
											'class' => 'et_pb_all_tabs',
										],
										'childrenSanitizer' => 'et_core_esc_previously',
										'children'   => $tab_content_html,
									]
								),
							],
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductTabs module.
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
	 * WooCommerceProductTabsModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( 'et_pb_tabs' );

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
	 * WooCommerceProductTabs module script data.
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
	 * WooCommerceProductTabsModule::module_script_data( $args );
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
	 * WooCommerceProductTabs Module's style components.
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

		// Check if box shadow is inner type.
		$is_inner_box_shadow = 'inner' === ( $attrs['module']['decoration']['boxShadow']['desktop']['value']['position'] ?? '' );

		// Main tab wrapper class.
		$main_selector = "{$order_class}.et_pb_tabs";

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
								'background'     => [
									'selector'         => "{$main_selector} .et_pb_all_tabs",
									'featureSelectors' => [
										'mask'    => [
											'desktop' => [
												'value' => "{$main_selector} > .et_pb_background_mask",
											],
										],
										'pattern' => [
											'desktop' => [
												'value' => "{$main_selector} > .et_pb_background_pattern",
											],
										],
									],
								],
								'disabledOn'     => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'boxShadow'      => [
									'selector' => $is_inner_box_shadow
										? "{$order_class} .et-pb-active-slide,{$order_class} .et_pb_active_content"
										: $order_class,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_tabs",
											'attr'     => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => [ self::class, 'overflow_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// All Tabs Content.
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					// Active Tab.
					$elements->style(
						[
							'attrName' => 'activeTab',
						]
					),
					// Inactive Tab.
					$elements->style(
						[
							'attrName' => 'inactiveTab',
						]
					),
					// Tab.
					$elements->style(
						[
							'attrName' => 'tab',
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
	 * Generate overflow style declaration for border radius handling.
	 *
	 * This function generates CSS overflow declarations to handle border radius
	 * properly when applied to the tabs module container.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $selector CSS selector.
	 *     @type array  $attr     Border attributes.
	 * }
	 *
	 * @return array CSS declarations for overflow handling.
	 */
	public static function overflow_style_declaration( array $args ): array {
		$selector = $args['selector'] ?? '';
		$attr     = $args['attr'] ?? [];

		$declarations = [];

		// Check if border radius is set and add overflow hidden if needed.
		$border_radius = $attr['radius'] ?? [];
		if ( ! empty( $border_radius ) ) {
			$has_radius = false;

			// Check all responsive modes for border radius.
			foreach ( [ 'desktop', 'tablet', 'phone' ] as $mode ) {
				if ( ! empty( $border_radius[ $mode ]['value'] ) ) {
					$radius_values = $border_radius[ $mode ]['value'];
					if ( is_array( $radius_values ) ) {
						foreach ( $radius_values as $value ) {
							if ( ! empty( $value ) && '0px' !== $value ) {
								$has_radius = true;
								break 2;
							}
						}
					} elseif ( '0px' !== $radius_values ) {
						$has_radius = true;
						break;
					}
				}
			}

			// Add overflow hidden if border radius is present.
			if ( $has_radius ) {
				$declarations[] = [
					'selector'    => $selector,
					'declaration' => 'overflow: hidden;',
				];
			}
		}

		return $declarations;
	}

	/**
	 * Get the custom CSS fields for the Divi WooCommerceProductTabs module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductTabs module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductTabs module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductTabs module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-tabs' )->customCssFields;
	}

	/**
	 * Loads `WooCommerceProductTabsModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-product-tabs',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-tabs/';

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
	 * Load comments template.
	 *
	 * This method ensures the correct comment template is loaded for WooCommerceProductTabs' review tab
	 * in the Theme Builder environment.
	 * It searches for the appropriate template file in the theme and WooCommerce template directories.
	 *
	 * Based on the legacy `ET_Builder_Module_Woocommerce_Tabs::comments_template_loader` method.
	 *
	 * @since ??
	 *
	 * @param string $template Template to load.
	 *
	 * @return string The template path.
	 */
	public static function comments_template_loader( string $template ): string {
		if ( ! et_builder_tb_enabled() ) {
			return $template;
		}

		$check_dirs = array(
			trailingslashit( get_stylesheet_directory() ) . WC()->template_path(),
			trailingslashit( get_template_directory() ) . WC()->template_path(),
			trailingslashit( get_stylesheet_directory() ),
			trailingslashit( get_template_directory() ),
			trailingslashit( WC()->plugin_path() ) . 'templates/',
		);

		if ( WC_TEMPLATE_DEBUG_MODE ) {
			$check_dirs = array( array_pop( $check_dirs ) );
		}

		foreach ( $check_dirs as $dir ) {
			if ( file_exists( trailingslashit( $dir ) . 'single-product-reviews.php' ) ) {
				return trailingslashit( $dir ) . 'single-product-reviews.php';
			}
		}

		return $template;
	}

	/**
	 * Render tab navigation HTML.
	 *
	 * This function generates the HTML for tab navigation based on the provided tabs data.
	 * It creates list items with proper CSS classes and links for each tab.
	 *
	 * @since ??
	 *
	 * @param array $tabs_data Array of tab data containing name, title, and content.
	 *
	 * @return array Array of HTMLUtility::render() calls for tab navigation items.
	 */
	public static function render_tab_nav( array $tabs_data ): array {
		$nav_items = [];
		$index     = 0;

		foreach ( $tabs_data as $name => $tab ) {
			$index++;

			$nav_items[] = HTMLUtility::render(
				[
					'tag'               => 'li',
					'tagEscaped'        => true,
					'attributes'        => [
						'class'   => HTMLUtility::classnames(
							[
								'et_pb_tab_nav_item' => true,
								'et_pb_tab_nav_item_' . esc_attr( $name ) => true,
								'et_pb_tab_active'   => 1 === $index,
							]
						),
						'data-id' => esc_attr( $name ),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => HTMLUtility::render(
						[
							'tag'        => 'a',
							'tagEscaped' => true,
							'attributes' => [
								'href' => '#tab-' . esc_attr( $name ),
							],
							'children'   => esc_html( $tab['title'] ),
						]
					),
				]
			);
		}

		return $nav_items;
	}

	/**
	 * Render tab content HTML.
	 *
	 * This function generates the HTML for tab content panels based on the provided tabs data.
	 * It creates div elements with proper CSS classes for each tab content.
	 *
	 * @since ??
	 *
	 * @param array $tabs_data Array of tab data containing name, title, and content.
	 *
	 * @return array Array of HTMLUtility::render() calls for tab content panels.
	 */
	public static function render_tab_content( array $tabs_data ): array {
		$content_items = [];
		$index         = 0;

		foreach ( $tabs_data as $name => $tab ) {
			$index++;

			$content_items[] = HTMLUtility::render(
				[
					'tag'               => 'div',
					'tagEscaped'        => true,
					'attributes'        => [
						'class' => HTMLUtility::classnames(
							[
								esc_attr( $name ) . '_tab' => true,
								'et_pb_tab'                => true,
								'clearfix'                 => true,
								'et_pb_active_content'     => 1 === $index,
							]
						),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [
								'class' => 'et_pb_tab_content',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $tab['content'],
						]
					),
				]
			);
		}

		return $content_items;
	}

	/**
	 * Gets product tabs data.
	 *
	 * This function retrieves an array of product tabs data that includes:
	 * - additional_information
	 * - description
	 * - reviews
	 * The returned data also includes the tab Name, Title, and Content,
	 * which is the HTML template output of the respective tab.
	 * This function also avoids fetching Tabs content using `the_content` when editing TB layout.
	 *
	 * This function is based on the legacy `ET_Builder_Module_Woocommerce_Tabs::get_tabs()` function.
	 *
	 * @since ??
	 *
	 * @param array $args Additional args.
	 *
	 * @return array Product tabs data.
	 */
	public static function get_product_tabs( array $args = array() ): array {
		global $product, $post, $wp_query;

		/*
		 * Visual builder fetches all tabs data and filters the included tab on the app to reduce
		 * requests between app and server for faster user experience. The frontend passes `includes_tab` to
		 * this method, so it only processes required tabs.
		 */
		$defaults     = array(
			'product' => 'current',
		);
		$args         = wp_parse_args( $args, $defaults );
		$product_tabs = array();
		$product_id   = $args['product'];

		// Handle 'current' and 'latest' values for product.
		if ( in_array( $args['product'], [ 'current', 'latest' ], true ) ) {
			// Convert both 'current' and 'latest' to actual product ID using existing utility.
			$product_id = WooCommerceUtils::get_product_id_by_prop( $args['product'] );

			if ( ! $product_id ) {
				return $product_tabs;
			}
		}

		// Determine whether current tabs data needs global variable overwrite or not.
		$overwrite_global = WooCommerceUtils::need_overwrite_global( $product_id );

		// Check if TB is used.
		$is_tb              = Conditions::is_tb_enabled();
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		if ( $is_use_placeholder ) {
			WooCommerceUtils::set_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			// Save current global variable for later reset.
			$original_product  = $product;
			$original_post     = $post;
			$original_wp_query = $wp_query;

			// Overwrite global variable.
			$post     = get_post( $product_id ); // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- Intentionally override global $post, will be restored/reset later.
			$product  = wc_get_product( $product_id );
			$wp_query = new \WP_Query( array( 'p' => $product_id ) ); // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- Intentionally override global $wp_query, will be restored/reset later.
		}

		if ( ! is_a( $post, 'WP_Post' ) || ! is_a( $product, 'WC_Product' ) ) {
			return $product_tabs;
		}

		/**
		 * Gets and filters the WooCommerce product tabs.
		 *
		 * @param array $tabs The product tabs.
		 *
		 * @return array The filtered product tabs.
		 */
		$all_tabs    = apply_filters( 'woocommerce_product_tabs', array() );
		$active_tabs = $args['include_tabs'] ?? [];

		// Get product tabs data.
		foreach ( $all_tabs as $name => $tab ) {
			// Skip if current tab is not included, based on `include_tabs` attribute value.
			if ( ! empty( $active_tabs ) && ! in_array( $name, $active_tabs, true ) ) {
				continue;
			}

			if ( 'description' === $name ) {
				if ( ! $is_use_placeholder && ! et_pb_is_pagebuilder_used( $product_id ) ) {
					$layouts = et_theme_builder_get_template_layouts();

					// If selected product doesn't use builder, retrieve post content.
					if ( ! empty( $layouts ) && $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] ) {
						/**
						 * This filter parses the tab content and processes any shortcodes in the content.
						 * This filter is used in place of `the_content` filter because it adds content wrapper.
						 *
						 * This filter is based on the legacy `et_builder_wc_description` filter.
						 *
						 * @param string $content The post content.
						 */
						$tab_content = apply_filters( 'et_builder_wc_description', $post->post_content );
					} else {
						$tab_content = $post->post_content;
					}
				} else {
					/*
					 * Description can't use built in callback data because it gets `the_content`
					 * which might cause infinite loop; get Divi's long description from
					 * post meta instead.
					 */
					if ( $is_use_placeholder ) {
						$placeholders = WooCommerceUtils::woocommerce_placeholders();

						$tab_content = $placeholders['description'];
					} else {
						$tab_content = get_post_meta( $product_id, ET_BUILDER_WC_PRODUCT_LONG_DESC_META_KEY, true );

						/**
						 * This filter parses the tab content and processes any shortcodes in the content.
						 * This filter is used in place of `the_content` filter because it adds content wrapper.
						 *
						 * This filter is based on the legacy `et_builder_wc_description` filter.
						 *
						 * @param string $content The tab content.
						 */
						$tab_content = apply_filters( 'et_builder_wc_description', $tab_content );
					}
				}
			} else {
				// Skip if the 'callback' key does not exist.
				if ( ! isset( $tab['callback'] ) ) {
					continue;
				}

				// Get tab value based on defined product tab's callback attribute.
				ob_start();
				// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- The callable function is hard-coded.
				call_user_func( $tab['callback'], $name, $tab );
				$tab_content = ob_get_clean();
			}

			// Populate product tab data.
			$product_tabs[ $name ] = array(
				'name'    => $name,
				'title'   => $tab['title'],
				'content' => $tab_content,
			);
		}

		// Reset overwritten global variable.
		if ( $is_use_placeholder ) {
			WooCommerceUtils::reset_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			$product  = $original_product; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- Intentionally override global $product, restoring previously overridden value.
			$post     = $original_post; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- Intentionally override global $post, restoring previously overridden value.
			$wp_query = $original_wp_query; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- Intentionally override global $wp_query, restoring previously overridden value.
		}

		return $product_tabs;
	}
}
