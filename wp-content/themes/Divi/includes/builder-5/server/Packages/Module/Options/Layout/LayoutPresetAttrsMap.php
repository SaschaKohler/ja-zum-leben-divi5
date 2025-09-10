<?php
/**
 * Module: LayoutPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Layout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LayoutPresetAttrsMap class.
 *
 * This class provides the static map for the layout preset attributes.
 *
 * @since ??
 */
class LayoutPresetAttrsMap {
	/**
	 * Get the map for the layout preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the layout preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__alignContent"   => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'alignContent',
			],
			"{$attr_name}__alignItems"     => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'alignItems',
			],
			"{$attr_name}__columnGap"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			"{$attr_name}__display"        => [
				'attrName' => $attr_name,
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			"{$attr_name}__flexDirection"  => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			"{$attr_name}__flexWrap"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			"{$attr_name}__justifyContent" => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			"{$attr_name}__rowGap"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
		];
	}
}
