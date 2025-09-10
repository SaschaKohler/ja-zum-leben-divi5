<?php
/**
 * Module Library: WooCommerceProductAddToCart Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAddToCart;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductAddToCartModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductAddToCart module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductAddToCartModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductAddToCart module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductAddToCartEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductAddToCart module.
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
	 * WooCommerceProductAddToCartModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Get parameters from attributes.
		$product_id = $attrs['content']['advanced']['product'][ $default_breakpoint ][ $default_state ] ?? WooCommerceUtils::get_default_product();

		$add_to_cart_html = self::get_add_to_cart(
			[
				'product' => $product_id,
			]
		);

		// Render empty string if no output is generated to avoid unwanted vertical space.
		if ( '' === $add_to_cart_html ) {
			return '';
		}

		// Process custom button icons.
		$button_icons_data = self::process_custom_button_icons( $attrs );

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
				'htmlAttrs'           => $button_icons_data['html_attrs'],
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
							'children'          => $add_to_cart_html,
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductAddToCart module.
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
	 * WooCommerceProductAddToCartModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$show_quantity        = $attrs['elements']['advanced']['showQuantity'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_stock           = $attrs['elements']['advanced']['showStock'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$use_focus_border     = $attrs['dropdownMenus']['advanced']['focusUseBorder'][ $default_breakpoint ][ $default_state ] ?? 'off';
		$field_label_position = $attrs['fieldLabels']['advanced']['fieldLabelPosition'][ $default_breakpoint ][ $default_state ] ?? 'inline';

		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'color'       => false,
					'orientation' => true,
				]
			),
			true
		);

		if ( 'off' === $show_quantity ) {
			$classnames_instance->add( 'et_pb_hide_input_quantity' );
		}

		if ( 'off' === $show_stock ) {
			$classnames_instance->add( 'et_pb_hide_stock' );
		}

		if ( 'on' === $use_focus_border ) {
			$classnames_instance->add( 'et_pb_with_focus_border' );
		}

		$classnames_instance->add( "et_pb_fields_label_position_{$field_label_position}" );

		// Add custom button icon class if needed.
		$button_icons_data = self::process_custom_button_icons( $attrs );
		if ( $button_icons_data['has_custom_icons'] ) {
			$classnames_instance->add( $button_icons_data['css_classes'], true );
		}

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
	 * WooCommerceProductAddToCart module script data.
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
	 * WooCommerceProductAddToCartModule::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$store_instance = $args['storeInstance'] ?? null;
		$elements       = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName'        => 'module',
				'scriptDataProps' => [
					'animation' => [
						'selector' => $selector,
					],
				],
			]
		);

		// Add responsive class names for show/hide settings.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setClassName'  => [
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_hide_input_quantity' => $attrs['elements']['advanced']['showQuantity'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_hide_stock' => $attrs['elements']['advanced']['showStock'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_with_focus_border' => $attrs['dropdownMenus']['advanced']['focusUseBorder'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_fields_label_position_inline' => $attrs['fieldLabels']['advanced']['fieldLabelPosition'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'inline' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_fields_label_position_stacked' => $attrs['fieldLabels']['advanced']['fieldLabelPosition'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'stacked' === $value ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}

	/**
	 * Dropdown arrow positioning style declaration.
	 *
	 * Calculates dropdown arrow margin values based on the dropdown menu's margin values.
	 * The Dropdown's arrow margin values depend on the actual Dropdown margin values.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/dropdown-arrow-positioning dropdownArrowPositioningStyleDeclaration}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The attribute value containing margin information.
	 *     @type bool  $important Optional. Whether to add `!important` to the CSS. Default `false`.
	 * }
	 *
	 * @return string The generated CSS style declaration.
	 */
	public static function dropdown_arrow_positioning_style_declaration( array $args ): string {
		$attr_value = $args['attrValue'] ?? [];
		$important  = $args['important'] ?? false;

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => $important,
			]
		);

		$margin = $attr_value['margin'] ?? null;

		if ( $margin ) {
			$margin_bottom = $margin['bottom'] ?? null;
			$margin_left   = $margin['left'] ?? null;

			// Only add styles if we have bottom or left margin values.
			if ( $margin_bottom || $margin_left ) {
				$bottom_value = empty( $margin_bottom ) ? '0px' : $margin_bottom;
				$left_value   = empty( $margin_left ) ? '0px' : $margin_left;

				$style_declarations->add( 'margin-top', "calc(3px - {$bottom_value})" );
				$style_declarations->add( 'right', "calc(10px - {$left_value})" );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Processes custom button icons for WooCommerce modules.
	 *
	 * This function checks if custom button icons are enabled and returns the necessary
	 * data attributes and CSS class to apply custom icons to WooCommerce buttons.
	 *
	 * This function is equivalent to the D4 function
	 * {@link ET_Builder_Module_Helper_Woocommerce_Modules::process_custom_button_icons}
	 * located in `includes/builder/module/helpers/WoocommerceModules.php`.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array {
	 *     Array containing button icon data.
	 *
	 *     @type bool  $has_custom_icons Whether the module has custom button icons.
	 *     @type array $html_attrs       HTML data attributes for button icons.
	 *     @type array $css_classes      CSS classes to add to the module.
	 * }
	 */
	public static function process_custom_button_icons( array $attrs ): array {
		static $cache = [];

		// Create cache key based on button attributes that affect the result.
		$button_attrs = $attrs['button']['decoration']['button'] ?? [];
		$cache_key    = md5( wp_json_encode( $button_attrs ) );

		// Return cached result if available.
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		// Enhancement(D5, Button Icons) The button icons needs a comprehensive update that is in line with D5 including support for customizable breakpoints.
		// https://github.com/elegantthemes/Divi/issues/44873.
		$has_custom_button = 'on' === ( $attrs['button']['decoration']['button']['desktop']['value']['enable'] ?? 'off' );

		// Get icon values for all devices.
		$icon_desktop = $has_custom_button
			? ( $attrs['button']['decoration']['button']['desktop']['value']['icon']['settings'] ?? '' )
			: '';
		$icon_tablet  = $has_custom_button
			? ( $attrs['button']['decoration']['button']['tablet']['value']['icon']['settings'] ?? '' )
			: '';
		$icon_phone   = $has_custom_button
			? ( $attrs['button']['decoration']['button']['phone']['value']['icon']['settings'] ?? '' )
			: '';

		// Check if any custom icon is defined.
		$has_custom_icons = $has_custom_button && ( ! empty( $icon_desktop ) || ! empty( $icon_tablet ) || ! empty( $icon_phone ) );

		if ( ! $has_custom_icons ) {
			$result = [
				'has_custom_icons' => false,
				'html_attrs'       => [],
				'css_classes'      => [],
			];

			// Cache and return result.
			$cache[ $cache_key ] = $result;
			return $result;
		}

		// Process icons using the same function as D4.
		$processed_icon_desktop = ! empty( $icon_desktop ) ? esc_attr( Utils::process_font_icon( $icon_desktop ) ) : '';
		$processed_icon_tablet  = ! empty( $icon_tablet ) ? esc_attr( Utils::process_font_icon( $icon_tablet ) ) : '';
		$processed_icon_phone   = ! empty( $icon_phone ) ? esc_attr( Utils::process_font_icon( $icon_phone ) ) : '';

		$result = [
			'has_custom_icons' => true,
			'html_attrs'       => [
				'data-button-class'       => 'single_add_to_cart_button',
				'data-button-icon'        => $processed_icon_desktop,
				'data-button-icon-tablet' => $processed_icon_tablet,
				'data-button-icon-phone'  => $processed_icon_phone,
			],
			'css_classes'      => [ 'et_pb_woo_custom_button_icon' ],
		];

		// Cache and return result.
		$cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * WooCommerceProductAddToCart Module's style components.
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
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		// Extract the order class.
		$order_class = $args['orderClass'] ?? '';

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
								'disabledOn'     => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => "{$order_class} td.label",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class} td.label",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} form.cart .variations td.value span:after",
											'attr'     => $attrs['dropdownMenus']['decoration']['spacing'] ?? [],
											'declarationFunction' => [ self::class, 'dropdown_arrow_positioning_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Button.
					$elements->style(
						[
							'attrName' => 'button',
						]
					),
					// Dropdown Menus.
					FormFieldStyle::style(
						[
							'selector'          => "{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select",
							'attr'              => $attrs['dropdownMenus'] ?? [],
							'orderClass'        => $order_class,
							'propertySelectors' => [
								'spacing' => [
									'desktop' => [
										'value' => [
											'margin'  => "{$order_class} select",
											'padding' => "{$order_class} select",
										],
									],
								],
								'focus'   => [
									'font' => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => "{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select, {$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select option, {$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select + label",
												],
											],
										],
									],
								],
							],
							'important'         => [
								'border'  => true,
								'font'    => true,
								'spacing' => true,
								'focus'   => [
									'border' => true,
									'font'   => true,
								],
							],
						]
					),
					// Field Labels.
					$elements->style(
						[
							'attrName' => 'fieldLabels',
						]
					),
					// Form Fields.
					FormFieldStyle::style(
						[
							'selector'   => implode(
								', ',
								[
									"{$order_class} input",
									"{$order_class} .quantity input.qty",
								]
							),
							'selectors'  => [
								'desktop' => [
									'value' => "{$order_class} input, {$order_class} .quantity input.qty",
									'hover' => "{$order_class} input:hover, {$order_class} .quantity input.qty:hover",
								],
							],
							'attr'       => $attrs['field'] ?? [],
							'orderClass' => $order_class,
							'important'  => [
								'border'  => true,
								'font'    => true,
								'spacing' => true,
								'focus'   => [
									'border' => true,
								],
							],
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
	 * Get the custom CSS fields for the Divi WooCommerceProductAddToCart module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductAddToCart module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductAddToCart module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductAddToCart module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-add-to-cart' );

		if ( ! $registered_block ) {
			return [];
		}

		$custom_css_fields = $registered_block->customCssFields;

		if ( ! is_array( $custom_css_fields ) ) {
			return [];
		}

		return $custom_css_fields;
	}

	/**
	 * Loads `WooCommerceProductAddToCartModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-product-add-to-cart',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-add-to-cart/';

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
	 * Replaces the Add-to-Cart form's action.
	 *
	 * This function replaces the add to cart form's action with the current page's permalink.
	 * If the current page ID is not a valid post ID (absolute integer), the function returns the original permalink.
	 *
	 * @since ??
	 *
	 * @param string $permalink The original form action permalink.
	 *
	 * @return string The form action permalink.
	 */
	public static function replace_add_to_cart_form_action( $permalink ) {
		$the_id = et_core_page_resource_get_the_ID();

		if ( 0 === absint( $the_id ) ) {
			return $permalink;
		}

		$link = get_permalink( $the_id );

		// Return the link if it exists, otherwise return the original permalink.
		return $link ? $link : $permalink;
	}

	/**
	 * Retrieves the add to cart markup for a given set of arguments.
	 *
	 * This function uses the `WooCommerceUtils::render_module_template()` to render the module template
	 * for the add to cart markup based on the provided arguments.
	 *
	 * If the theme builder is enabled, the function applies the `replace_add_to_cart_form_action` filter to the add to cart form action.
	 * This filter replaces the add to cart form action with the current page's permalink.
	 * Compatibility with WooCommerce Product Add-ons is added.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the add to cart markup.
	 *
	 *     @type string $product Optional. The product identifier.
	 * }
	 *
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags.
	 *
	 *     @type string $is_tb Optional.  Whether the theme builder is enabled. Default 'false'.
	 *     @type string $is_bfb Optional. Whether the builder is in the frontend builder. Default 'false'.
	 *     @type string $is_bfb_activated Optional. Whether the builder is activated. Default 'false'.
	 * }
	 *
	 * @return string The rendered add to cart markup or a placeholder if in theme builder mode.
	 *
	 * @example:
	 * ```php
	 * $add_to_cart = WooCommerceProductAddToCartModule::get_add_to_cart();
	 * // Returns the add to cart markup for the current product.
	 *
	 * $add_to_cart = WooCommerceProductAddToCartModule::get_add_to_cart( [ 'product' => 123 ] );
	 * // Returns the add to cart markup for the product with ID 123.
	 * ```
	 */
	public static function get_add_to_cart( array $args = [], array $conditional_tags = [] ): string {
		$is_tb            = 'true' === ArrayUtility::get_value( $conditional_tags, 'is_tb', 'false' );
		$is_bfb           = 'true' === ArrayUtility::get_value( $conditional_tags, 'is_bfb', 'false' );
		$is_bfb_activated = 'true' === ArrayUtility::get_value( $conditional_tags, 'is_bfb_activated', 'false' );
		$is_builder       = $is_tb || $is_bfb || $is_bfb_activated || is_et_pb_preview();

		if ( ! $is_builder ) {
			add_filter(
				'woocommerce_add_to_cart_form_action',
				array(
					self::class,
					// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
					'replace_add_to_cart_form_action'
				)
			);
		}

		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		$output = WooCommerceUtils::render_module_template(
			'woocommerce_template_single_add_to_cart',
			$args,
			array( 'product', 'post' )
		);

		if ( ! $is_builder ) {
			remove_filter(
				'woocommerce_add_to_cart_form_action',
				array(
					self::class,
					// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
					'replace_add_to_cart_form_action'
				)
			);
		}

		return $output;
	}
}
