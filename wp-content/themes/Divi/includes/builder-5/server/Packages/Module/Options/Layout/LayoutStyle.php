<?php
/**
 * Module: LayoutStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Layout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * LayoutStyle class.
 *
 * This class has functionality for handling styles and layout for the layout component.
 *
 * @since ??
 */
class LayoutStyle {

	/**
	 * Get layout style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js-beta/divi-module/functions/LayoutStyle LayoutStyle} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string        $selector                 The CSS selector.
	 *     @type array         $selectors                Optional. An array of selectors for each breakpoint and state. Default `[]`.
	 *     @type callable      $selectorFunction         Optional. The function to be called to generate CSS selector. Default `null`.
	 *     @type array         $propertySelectors        Optional. The property selectors that you want to unpack. Default `[]`.
	 *     @type array         $attr                     An array of module attribute data.
	 *     @type array         $defaultPrintedStyleAttr  Optional. An array of default printed style attribute data. Default `[]`.
	 *     @type array|bool    $important               Optional. Whether to apply "!important" flag to the style declarations.
	 *                                                   Default `false`.
	 *     @type bool          $asStyle                  Optional. Whether to wrap the style declaration with style tag or not.
	 *                                                   Default `true`
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 *     @type string        $atRules                  Optional. CSS at-rules to wrap the style declarations in. Default `''`.
	 * }
	 *
	 * @return string|array The layout style component.
	 */
	public static function style( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'         => [],
				'propertySelectors' => [],
				'selectorFunction'  => null,
				'important'         => false,
				'asStyle'           => true,
				'orderClass'        => null,
				'returnType'        => 'array',
				'atRules'           => '',
			]
		);

		$selector           = $args['selector'];
		$selectors          = $args['selectors'];
		$selector_function  = $args['selectorFunction'];
		$property_selectors = $args['propertySelectors'];
		$attr               = $args['attr'];
		$important          = $args['important'];
		$as_style           = $args['asStyle'];
		$order_class        = $args['orderClass'];

		$attr_normalized = self::normalize_attr( $attr );

		$is_inside_sticky_module = $args['isInsideStickyModule'] ?? false;

		// Bail, if noting is there to process.
		if ( empty( $attr_normalized ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		$children = Utils::style_statements(
			[
				'selectors'               => ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ],
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr_normalized,
				'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttr'] ?? [],
				'important'               => $important,
				'declarationFunction'     => function( $params ) {
					return Declarations::layout_style_declaration( $params );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'returnType'              => $args['returnType'],
				'atRules'                 => $args['atRules'],
			]
		);

		return Utils::style_wrapper(
			[
				'attr'     => $attr_normalized,
				'asStyle'  => $as_style,
				'children' => $children,
			]
		);
	}

	/**
	 * Normalize the layout attributes.
	 *
	 * Some attributes are not available in all breakpoints and states. This function
	 * will normalize the attributes by filling the missing attributes with the
	 * inherited values.
	 *
	 * @since ??
	 *
	 * @param array $attr The array of attributes to be normalized.
	 * @return array The normalized array of attributes.
	 */
	public static function normalize_attr( array $attr ):array {
		if ( ! $attr ) {
			return [];
		}

		$default_attr           = [
			'desktop' => [
				'value' => [
					'display' => 'flex',
				],
			],
		];

		$attr_with_default = array_replace_recursive( $default_attr, $attr );

		$attr_normalized = [];

		foreach ( $attr_with_default as $breakpoint => $states ) {
			foreach ( $states as $state => $values ) {
				if ( 'desktop' === $breakpoint && 'value' === $state ) {
					$attr_normalized[ $breakpoint ][ $state ] = $values;
				} else {
					$inherit = ModuleUtils::use_attr_value(
						[
							'attr'       => $attr_with_default,
							'breakpoint' => $breakpoint,
							'state'      => $state,
							'mode'       => 'getAndInheritAll',
						]
					);

					$attr_normalized[ $breakpoint ][ $state ] = array_replace_recursive(
						$inherit,
						$values
					);
				}
			}
		}

		return $attr_normalized;
	}
}
