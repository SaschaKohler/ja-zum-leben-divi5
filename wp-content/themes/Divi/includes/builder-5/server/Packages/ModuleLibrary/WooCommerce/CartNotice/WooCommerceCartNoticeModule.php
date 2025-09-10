<?php
/**
 * Module Library: WooCommerceCartNotice Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CartNotice;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\HTMLUtility;
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
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceCartNoticeModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceCartNotice module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCartNoticeModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCartNotice module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCartNoticeEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceCartNotice module.
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
	 * WooCommerceCartNoticeModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Get parameters from attributes.
		$product_id = $attrs['content']['advanced']['product'][ $default_breakpoint ][ $default_state ] ?? WooCommerceUtils::get_default_product();
		$page_type  = $attrs['content']['advanced']['pageType'][ $default_breakpoint ][ $default_state ] ?? 'product';

		$woocommerce_session_empty_notices = ! empty( WC()->session ) && empty( WC()->session->get( 'wc_notices', array() ) );
		$is_cart_or_checkout               = in_array( $page_type, array( 'cart', 'checkout' ), true );

		/*
		 * In front end, do not print cart notice content if no notices exist.
		 * However, we still render the module to ensure styles are generated.
		 * The content will be conditionally hidden using CSS.
		 */
		$should_hide_content = $woocommerce_session_empty_notices && ! $is_cart_or_checkout && ! is_et_pb_preview();

		$cart_notice_html = self::get_cart_notice(
			[
				'product'   => $product_id,
				'page_type' => $page_type,
			]
		);

		// Process custom button icons.
		$button_icons_data = self::process_custom_button_icons( $attrs );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$output = Module::render(
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
							'children'          => $cart_notice_html,
						]
					),
				],
			]
		);

		return $should_hide_content ? '' : $output;
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceCartNotice module.
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
	 * WooCommerceCartNoticeModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$field_width = $attrs['field']['advanced']['width'][ $default_breakpoint ][ $default_state ] ?? false;
		$page_type   = $attrs['content']['advanced']['pageType'][ $default_breakpoint ][ $default_state ] ?? 'product';

		if ( false !== $field_width ) {
			$classnames_instance->add( "et_pb_fields_layout_{$field_width}" );
		}

		// Add custom button icon class if needed.
		$button_icons_data = self::process_custom_button_icons( $attrs );
		if ( $button_icons_data['has_custom_icons'] ) {
			$classnames_instance->add( $button_icons_data['css_classes'], true );
		}

		$is_cart_or_checkout          = in_array( $page_type, array( 'cart', 'checkout' ), true );
		$check_cart_items_is_callable = isset( WC()->cart ) && ! is_null( WC()->cart && method_exists( WC()->cart, 'check_cart_items' ) );

		if ( $is_cart_or_checkout && $check_cart_items_is_callable ) {
			$return = WC()->cart->check_cart_items();

			if ( 'checkout' === $page_type && wc_notice_count( 'error' ) > 0 ) {
				$classnames_instance->add( 'et_pb_hide_module' );
			}
		}

		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'orientation' => false,
				]
			),
			true
		);

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
	 * WooCommerceCartNotice module script data.
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
	 * WooCommerceCartNoticeModule::module_script_data( $args );
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
	 * WooCommerceCartNotice Module's style components.
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
											'selector'  => "{$order_class} .woocommerce-message, {$order_class} .woocommerce-info",
											'attr'      => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class} .woocommerce-message, {$order_class} .woocommerce-info",
														],
													],
												],
											],
											'important' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => true,
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .woocommerce-info",
											'attr'     => $attrs['module']['decoration']['spacing'] ?? [],
											'declarationFunction' => [ self::class, 'extra_theme_padding_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Content.
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					// Button.
					$elements->style(
						[
							'attrName' => 'button',
						]
					),
					// Field.
					FormFieldStyle::style(
						[
							'selector'          => "{$order_class} form .form-row input.input-text",
							'attr'              => array_replace_recursive(
								$attrs['field'] ?? [],
								[
									'advanced' => [
										'focusUseBorder' => [
											'desktop' => [ 'value' => 'on' ],
										],
									],
								]
							),
							'orderClass'        => $order_class,
							'propertySelectors' => [
								'focus' => [
									'border' => [
										'desktop' => [
											'value' => [
												'border' => "{$order_class} form .form-row input.input-text",
											],
										],
									],
								],
							],
						]
					),
					// Field Labels.
					$elements->style(
						[
							'attrName'   => 'fieldLabels',
							'styleProps' => [
								'advancedStyles' => [
									// Required Field Indicator Color.
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'     => $attrs['fieldLabels']['advanced']['requiredFieldIndicatorColor'] ?? [],
											'selector' => "{$order_class} form .form-row .required",
											'declarationFunction' => [ self::class, 'required_field_indicator_color_style_declaration' ],
											'propertySelectors' => [
												'desktop' => [
													'value' => "{$order_class} form .form-row .required",
													'hover' => "{$order_class} form .form-row:hover .required",
												],
											],
										],
									],
								],
							],
						]
					),
					// Form.
					$elements->style(
						[
							'attrName' => 'form',
						]
					),
					// Title.
					$elements->style(
						[
							'attrName' => 'title',
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
	 * Get the custom CSS fields for the Divi WooCommerceCartNotice module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCartNotice module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCartNotice module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCartNotice module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-cart-notice' );

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
	 * Generates the style declaration for required field indicator color in the WooCommerce Cart Notice module.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style declaration params.
	 *
	 *     @type string $attrValue The attribute value for the required field indicator color.
	 * }
	 *
	 * @return string The CSS style declaration.
	 */
	public static function required_field_indicator_color_style_declaration( array $args ): string {
		$attr_value = $args['attrValue'] ?? '';

		if ( empty( $attr_value ) ) {
			return '';
		}

		return "color: {$attr_value};";
	}

	/**
	 * Generates the style declaration for Extra theme specific padding in the WooCommerce Cart Notice module.
	 * Adds 34px to the left padding for Extra theme compatibility.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style declaration params.
	 *
	 *     @type array $attrValue The spacing attribute value.
	 * }
	 *
	 * @return string The CSS style declaration.
	 */
	public static function extra_theme_padding_style_declaration( array $args ): string {
		// Only apply Extra theme specific padding when theme is Extra.
		if ( 'Extra' !== et_core_get_theme_info( 'Name' ) ) {
			return '';
		}

		$attr_value = $args['attrValue'] ?? [];
		$padding    = $attr_value['padding'] ?? [];

		if ( empty( $padding['left'] ) ) {
			return '';
		}

		$left_padding = $padding['left'];

		// Add 34px to the left padding for Extra theme.
		return "padding-left: calc({$left_padding} + 34px) !important;";
	}



	/**
	 * Loads `WooCommerceCartNoticeModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-cart-notice',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/cart-notice/';

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);

		// Remove D4 action first.
		remove_action(
			'wp',
			[ 'ET_Builder_Module_Woocommerce_Cart_Notice', 'disable_default_notice' ],
			100
		);

		/*
		 * Disable default cart notice if needed.
		 *
		 * Priority needs to be set at 100 to that the callback is called after modules are loaded.
		 *
		 * See: et_builder_load_framework()
		 */
		add_action(
			'wp',
			[ self::class, 'disable_default_notice' ],
			100
		);

		// Remove D4 action first.
		remove_action( 'wp_footer', [ 'ET_Builder_Module_Woocommerce_Cart_Notice', 'clear_notices' ] );

		// Clear notices array which was modified during render.
		add_action( 'wp_footer', [ self::class, 'clear_notices' ] );
	}

	/**
	 * Swaps login form template(s).
	 *
	 * This function is used to swap the login form template(s).
	 * By default WooCommerce displays these only when logged-out.
	 * However these templates must be shown in VB when logged-in.
	 * The workaround is to use swapped templates in VB.
	 *
	 * @since ??
	 *
	 * @param string $template      The template.
	 * @param string $template_name The template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default template path.
	 *
	 * @return string The swapped template.
	 */
	public static function swap_template( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$is_template_override = in_array(
			$template_name,
			array(
				'checkout/form-login.php',
				'global/form-login.php',
			),
			true
		);

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Swaps login form template(s).
	 *
	 * This function is used to swap the login form template(s) in FE.
	 * Note: Aligning `Remember me` checkbox vertically requires change in HTML markup.
	 *
	 * @since ??
	 *
	 * @param string $template      The template.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string The swapped template.
	 */
	public static function swap_template_frontend( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$is_template_override = in_array(
			$template_name,
			array(
				'global/form-login.php',
			),
			true
		);

		$template_name_parts = explode( '.', $template_name );

		if ( $is_template_override && 2 === count( $template_name_parts ) ) {
			$template_name_parts[0] = $template_name_parts[0] . '-fe';
			$template_name          = implode( '.', $template_name_parts );

			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Reset any added hooks.
	 *
	 * This function resets the hooks that were added in {@link self::maybe_handle_hooks()}.
	 * These include `wc_get_template` filters and `woocommerce_cart_is_empty` filter actions.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Conditional tags from REST API.
	 *
	 * @return void
	 */
	public static function maybe_reset_hooks( array $conditional_tags = [] ): void {
		$is_tb              = ArrayUtility::get_value( $conditional_tags, 'is_tb', false );
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		remove_filter(
			'wc_get_template',
			[
				self::class,
				'swap_template_frontend',
			],
			10,
			5
		);

		if ( Conditions::is_rest_api_request() || $is_use_placeholder ) {
			remove_filter(
				'wc_get_template',
				[
					self::class,
					'swap_template',
				]
			);
		}
	}

	/**
	 * Handles any added hooks.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags from REST API.
	 *
	 *     @type bool $is_tb Optional. Whether the Theme Builder is enabled.
	 * }
	 *
	 * @return void
	 */
	public static function maybe_handle_hooks( array $conditional_tags = [] ): void {
		$is_tb              = ArrayUtility::get_value( $conditional_tags, 'is_tb', false );
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		/*
		 * Aligning `Remember me` checkbox vertically requires change in HTML markup.
		 */
		add_filter(
			'wc_get_template',
			[
				self::class,
				'swap_template_frontend',
			],
			10,
			5
		);

		if ( Conditions::is_rest_api_request() || $is_use_placeholder ) {
			/*
			 * Show Login form in VB.
			 *
			 * The swapped login form will display irrespective of the user logged-in status.
			 *
			 * Previously swapped template (FE) would only display the form when
			 * a user is not logged-in. Hence we use a different template in VB.
			 */
			add_filter(
				'wc_get_template',
				[
					self::class,
					'swap_template',
				],
				10,
				5
			);
		}
	}

	/**
	 * Gets the cart message based on the page type and product.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the cart notice.
	 *
	 *     @type string $product   Optional. The product identifier.
	 *                             Default 'current' or 'latest', retrieved from `WooCommerceUtils::get_default_product_value()`.
	 *     @type string $page_type Optional. The page type. One of 'product', 'checkout' or 'cart'. Default 'product'.
	 * }
	 *
	 * @return string The cart message.
	 */
	public static function get_cart_message( array $args = [] ): string {
		$default_product_id = WooCommerceUtils::get_default_product_value();

		$page_type  = ArrayUtility::get_value( $args, 'page_type', 'product' );
		$product_id = ArrayUtility::get_value( $args, 'product', $default_product_id );

		if ( 'cart' === $page_type ) {
			$message = wp_kses_post( apply_filters( 'wc_empty_cart_message', __( 'Your cart is currently empty.', 'woocommerce' ) ) );
		} elseif ( 'checkout' === $page_type ) {
			$message = apply_filters( 'woocommerce_checkout_login_message', esc_html__( 'Returning customer?', 'woocommerce' ) ) . ' <a href="#" class="showlogin">' . esc_html__( 'Click here to login', 'woocommerce' ) . '</a>';
		} else {
			// Since the default Page type is `Product`, the conditional `if` is ignored.
			$product = WooCommerceUtils::get_product( $product_id );

			if ( ! empty( $product ) && function_exists( 'wc_add_to_cart_message' ) ) {
				$message = wc_add_to_cart_message( $product->get_id(), false, true );
			} else {
				// A fallback.
				$message = sprintf(
					'&ldquo;%s&rdquo; %s',
					esc_html__( 'Product Name' ),
					esc_html__( 'has been added to cart.' )
				);
			}
		}

		return $message;
	}

	/**
	 * Retrieves the cart notice for a given set of arguments.
	 *
	 * This function checks if the theme builder is enabled and returns a placeholder
	 * cart notice if so. Otherwise, it uses the WooCommerceUtils to render the module template
	 * for the cart notice based on the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the cart notice.
	 *
	 *     @type string $product   Optional. The product identifier. Default 'current'.
	 *     @type string $page_type Optional. The page type.
	 * }
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags.
	 *
	 *     @type bool $is_tb Optional. Whether the theme builder is enabled.
	 * }
	 *
	 * @return string The rendered cart notice or a placeholder if in theme builder mode.
	 *
	 * @example:
	 * ```php
	 * $notice = WooCommerceCartNoticeController::get_cart_notice();
	 * // Returns the cart notice for the current product.
	 *
	 * $notice = WooCommerceCartNoticeController::get_cart_notice( [ 'product' => 123, 'page_type' => 'checkout' ] );
	 * // Returns the cart notice for the checkout page.
	 * ```
	 */
	public static function get_cart_notice( array $args = [], array $conditional_tags = [] ): string {
		$message   = self::get_cart_message( $args );
		$page_type = ArrayUtility::get_value( $args, 'page_type', 'product' );

		$is_tb      = ArrayUtility::get_value( $conditional_tags ?? [], 'is_tb', false );
		$is_builder = Conditions::is_rest_api_request() || $is_tb || is_et_pb_preview();

		$args = wp_parse_args(
			array(
				'wc_cart_message' => $message,
				'page_type'       => $page_type,
				'is_builder'      => $is_builder,
			),
			$args
		);

		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		self::maybe_handle_hooks( $conditional_tags );

		if ( $is_builder || Conditions::is_vb_enabled() ) {
			if ( 'checkout' === $page_type ) {
				$markup = WooCommerceUtils::render_module_template(
					'woocommerce_checkout_login_form',
					$args
				);
			} elseif ( 'cart' === $page_type ) {
				$markup = WooCommerceUtils::render_module_template( 'wc_cart_empty_template' );
			} else {
				$markup = WooCommerceUtils::render_module_template( 'wc_print_notice', $args );
			}
		} else {
			if ( 'checkout' === $page_type ) {
				$notices_markup = WooCommerceUtils::render_module_template(
					'woocommerce_output_all_notices'
				);

				$form_markup = WooCommerceUtils::render_module_template(
					'woocommerce_checkout_login_form',
					$args
				);

				$markup = sprintf( '%s%s', $notices_markup, $form_markup );
			} elseif ( 'cart' === $page_type && ( is_null( WC()->cart ) || WC()->cart->is_empty() ) ) {
				$markup = WooCommerceUtils::render_module_template( 'wc_cart_empty_template' );
			} else {
				$markup = WooCommerceUtils::render_module_template( 'woocommerce_output_all_notices', $args );

				return $markup;
			}
		}

		self::maybe_reset_hooks( $conditional_tags );

		return $markup;
	}

	/**
	 * Disables default WooCommerce notice(s).
	 *
	 * Disables default WooCommerce notice(s) if the current page's main query post content contains
	 * CartNotice module to prevent duplicate cart notices being rendered.
	 * This also ensures that the CartNotice module renders the notices correctly
	 * (notices are cleared once they are rendered).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function disable_default_notice(): void {
		global $post;

		$remove_default_notices     = false;
		$theme_builder_layouts      = et_theme_builder_get_template_layouts();
		$theme_builder_layout_types = et_theme_builder_get_layout_post_types();

		// Check if a TB layout outputs the notices.
		foreach ( $theme_builder_layout_types as $post_type ) {
			$id      = ArrayUtility::get_value_by_array_path( $theme_builder_layouts, array( $post_type, 'id' ), 0 );
			$enabled = ArrayUtility::get_value_by_array_path( $theme_builder_layouts, array( $post_type, 'enabled' ), 0 );

			if ( ! $id || ! $enabled ) {
				continue;
			}

			$content = get_post_field( 'post_content', $id );

			if ( has_block( 'divi/woocommerce-cart-notice', $content ) ) {
				$remove_default_notices = true;
				break;
			}
		}

		// Check if the product itself outputs the notices.
		if ( isset( $post->post_content ) && has_block( 'divi/woocommerce-cart-notice', $post->post_content ) ) {
			$remove_default_notices = true;
		}

		if ( $remove_default_notices ) {
			remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
		}
	}

	/**
	 * Clears WooCommerce notices.
	 *
	 * Clears WooCommerce notice [array] after Woo Product is fully rendered to avoid
	 * duplicated notifications on subsequent page loads.
	 *
	 * Notice this only runs if WooCommerce session is not empty.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function clear_notices(): void {
		if ( ! empty( WC()->session ) ) {
			WC()->session->set( 'wc_notices', null );
		}
	}
}
