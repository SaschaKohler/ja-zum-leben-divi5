<?php
/**
 * Module Library: Icon List Item Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconListItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress uses snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\IconListItem\IconListItemPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleLibrary\IconList\Styles\FontStyle;
use ET\Builder\Packages\ModuleLibrary\IconList\Styles\TextStyle;

/**
 * IconListItemModule class.
 *
 * This class implements the functionality of an icon list item component in a
 * frontend application. It provides functions for rendering the icon list item,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class IconListItemModule implements DependencyInterface {

	/**
	 * Render callback for the Icon List Item module.
	 *
	 * This function is responsible for the module's server-side HTML rendering on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ IconListItemEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Icon List Item module.
	 */
	public static function render_callback( array $attrs, $content, WP_Block $block, ModuleElements $elements ) {
		// Get the actual icon data and process it.
		$icon_attr  = isset( $attrs['icon']['innerContent']['desktop']['value'] ) ? $attrs['icon']['innerContent']['desktop']['value'] : [];
		$icon_value = Utils::process_font_icon( $icon_attr );

		// Get parent module attributes for fallback values.
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$parent_attrs = isset( $parent->attrs ) ? $parent->attrs : [];

		// Render the icon manually (like Icon and Blurb modules do).
		$icon = ! empty( $icon_value ) ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [ 'class' => 'et-pb-icon' ],
				'childrenSanitizer' => 'esc_html',
				'children'          => $icon_value,
			]
		) : '';

		// Render content (text) normally.
		$text_content = $elements->render(
			[
				'attrName' => 'content',
			]
		);

		// Create the <li> content first.
		$li_content = $elements->style_components(
			[
				'attrName' => 'module',
			]
		) . $icon . $text_content;

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
				'parentAttrs'         => $parent_attrs,
				'parentId'            => isset( $parent->id ) ? $parent->id : '',
				'parentName'          => isset( $parent->blockName ) ? $parent->blockName : '',
				'tag'                 => 'li', // Render as <li> instead of <div>.
				'hasModuleClassName'  => false,
				'children'            => $li_content,
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Icon List Item
	 * module.
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
	 */
	public static function module_classnames( array $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Add default Divi module classes.
		$classnames_instance->add( 'et_pb_icon_list_item' );
		$classnames_instance->add( 'et_pb_module' );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						isset( $attrs['module']['decoration'] ) ? $attrs['module']['decoration'] : [],
						[
							'link' => isset( $attrs['module']['advanced']['link'] ) ? $attrs['module']['advanced']['link'] : [],
						]
					),
				]
			)
		);
	}

	/**
	 * Icon List Item module script data.
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
	 *     @type array $attrs Module attributes.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ) {
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
	 * Icon List Item module styles.
	 *
	 * This function generates the styles for the Icon List Item module.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/ModuleStyles ModuleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for generating the module styles.
	 *
	 *     @type array          $attrs                       Module attributes.
	 *     @type array          $defaultPrintedStyleAttrs    Default printed style attributes.
	 *     @type ModuleElements $elements                    Module elements instance.
	 *     @type string         $mode                        Rendering mode.
	 *     @type string         $state                       Module state.
	 *     @type string         $orderClass                  Order class.
	 *     @type bool           $noStyleTag                  Whether to exclude style tag.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ) {
		$attrs    = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$elements = $args['elements'];
		$settings = isset( $args['settings'] ) ? $args['settings'] : [];

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
									'disabledModuleVisibility' => isset( $settings['disabledModuleVisibility'] ) ? $settings['disabledModuleVisibility'] : null,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => $args['orderClass'],
											'attr'     => isset( $attrs['module']['advanced']['text'] ) ? $attrs['module']['advanced']['text'] : null,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_icon_list_item .et_pb_icon_list_text',
											'attr'     => isset( $attrs['module']['advanced']['text']['text'] ) ? $attrs['module']['advanced']['text']['text'] : null,
											'declarationFunction' => [ TextStyle::class, 'text_orientation_declaration' ],
										],
									],
								],
							],
						]
					),
					// Content.
					$elements->style(
						[
							'attrName'   => 'content',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_icon_list_item .et_pb_icon_list_text',
											'attr'     => isset( $attrs['content']['decoration']['font']['font'] ) ? $attrs['content']['decoration']['font']['font'] : null,
											'declarationFunction' => [ FontStyle::class, 'text_alignment_declaration' ],
										],
									],
								],
							],
						]
					),
					// Icon.
					$elements->style(
						[
							'attrName'   => 'icon',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_icon_list_item .et-pb-icon',
											'attr'     => isset( $attrs['icon']['innerContent'] ) ? $attrs['icon']['innerContent'] : null,
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_icon_list_item .et-pb-icon',
											'attr'     => isset( $attrs['icon']['advanced']['color'] ) ? $attrs['icon']['advanced']['color'] : null,
											'property' => 'color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_icon_list_item .et-pb-icon',
											'attr'     => isset( $attrs['icon']['advanced']['size'] ) ? $attrs['icon']['advanced']['size'] : null,
											'property' => 'font-size',
										],
									],
								],
							],
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector' => $args['orderClass'],
							'attr'     => isset( $attrs['css'] ) ? $attrs['css'] : [],
						]
					),
				],
			]
		);
	}

	/**
	 * Icon style declaration
	 *
	 * This function will declare icon style for Icon List Item module.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments.
	 *
	 * @return string The CSS for icon style.
	 */
	public static function icon_style_declaration( array $params ): string {
		$icon_attr = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
					'font-weight' => true,
				],
			]
		);

		if ( isset( $icon_attr['type'] ) ) {
			$font_family = 'fa' === $icon_attr['type'] ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', $font_family );
		}

		if ( ! empty( $icon_attr['weight'] ) ) {
			$style_declarations->add( 'font-weight', $icon_attr['weight'] );
		}

		return $style_declarations->value();
	}

	/**
	 * Load the Icon List Item module.
	 *
	 * This function is responsible for loading the Icon List Item module by registering it.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/visual-builder/packages/module-library/src/components/icon-list-item/';

		add_filter( 'divi_conversion_presets_attrs_map', [ IconListItemPresetAttrsMap::class, 'get_map' ], 10, 2 );

		// Register module directly like Accordion, Text, and Icon modules do.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
