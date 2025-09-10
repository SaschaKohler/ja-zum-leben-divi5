<?php
/**
 * Module Library: BarCounters Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\BarCounters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class BarCountersPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\BarCounters
 */
class BarCountersPresetAttrsMap {
	/**
	 * Get the preset attributes map for the BarCounters module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/counters' !== $module_name ) {
			return $map;
		}

		return array_merge(
			$map,
			[
				'barProgress.advanced.usePercentages'      => [
					'attrName' => 'barProgress.advanced.usePercentages',
					'preset'   => [ 'html' ],
				],
				'children.barProgress.decoration.background__color' => [
					'attrName' => 'children.barProgress.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'module.decoration.scroll__gridMotion.enable' => [
					'attrName' => 'module.decoration.scroll',
					'preset'   => [ 'script' ],
					'subName'  => 'gridMotion.enable',
				],
				'module.decoration.layout__display'        => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'display',
				],
				'module.decoration.layout__flexDirection'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexDirection',
				],
				'module.decoration.layout__flexWrap'       => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexWrap',
				],
				'module.decoration.layout__justifyContent' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'justifyContent',
				],
				'module.decoration.layout__alignItems'     => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignItems',
				],
				'module.decoration.layout__alignContent'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignContent',
				],
				'module.decoration.layout__columnGap'      => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'module.decoration.layout__rowGap'         => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'rowGap',
				],
			]
		);

	}
}
