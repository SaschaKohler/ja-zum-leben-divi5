<?php
/**
 * Module Library: Sidebar Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Sidebar;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class SidebarPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Sidebar
 */
class SidebarPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Sidebar module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/sidebar' !== $module_name ) {
			return $map;
		}

		return [
			'sidebar.innerContent__area'                => [
				'attrName' => 'sidebar.innerContent',
				'preset'   => 'content',
				'subName'  => 'area',
			],
			'sidebar.advanced.layout__layoutStyle'      => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'layoutStyle',
			],
			'sidebar.advanced.layout__alignment'        => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'alignment',
			],
			'sidebar.advanced.layout__showBorder'       => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'showBorder',
			],
			'sidebarWidgets.advanced.flexType'          => [
				'attrName' => 'sidebarWidgets.advanced.flexType',
				'preset'   => [ 'html' ],
			],
			'sidebar.decoration.layout__alignContent'   => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignContent',
			],
			'sidebar.decoration.layout__alignItems'     => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignItems',
			],
			'sidebar.decoration.layout__columnGap'      => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'sidebar.decoration.layout__display'        => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			'sidebar.decoration.layout__flexDirection'  => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			'sidebar.decoration.layout__flexWrap'       => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			'sidebar.decoration.layout__justifyContent' => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			'sidebar.decoration.layout__rowGap'         => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
			'module.decoration.layout__display'         => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			'module.decoration.layout__flexDirection'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			'module.decoration.layout__flexWrap'        => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			'module.decoration.layout__justifyContent'  => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			'module.decoration.layout__rowGap'          => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
		];
	}
}
