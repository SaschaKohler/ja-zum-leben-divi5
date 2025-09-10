<?php
/**
 * Legacy Attribute Names Handler
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET_Builder_Module_Settings_Migration;

/**
 * Handles Legacy Attribute Names
 *
 * @since ??
 */
class LegacyAttributeNames {

	/**
	 * Option name for storing legacy attribute names
	 */
	const OPTION_NAME = 'et_divi_legacy_attribute_names';

	/**
	 * Legacy attribute names
	 *
	 * @var array
	 */
	public static $attributes = [
		'background_url',
		'max_width',
		'title_font_color',
		'content_font_color',
		'subhead_font_color',
		'button_one_text_size_hover',
		'button_two_text_size_hover',
		'button_one_text_color_hover',
		'button_two_text_color_hover',
		'button_one_border_width_hover',
		'button_two_border_width_hover',
		'button_one_border_color_hover',
		'button_two_border_color_hover',
		'button_one_border_radius_hover',
		'button_two_border_radius_hover',
		'button_one_letter_spacing_hover',
		'button_two_letter_spacing_hover',
		'button_one_bg_color_hover',
		'button_two_bg_color_hover',
		'animation',
		'sticky',
		'use_border_color',
		'border_color',
		'border_width',
		'border_style',
		'always_center_on_mobile',
		'transparent_background',
		'transparent_background_fb',
		'padding',
		'padding_bottom',
		'padding_left',
		'padding_right',
		'padding_top',
		'padding_mobile',
		'make_fullwidth',
		'use_custom_width',
		'width_unit',
		'custom_width_px',
		'custom_width_px__hover',
		'custom_width_px__hover_enabled',
		'custom_width_percent',
		'custom_width_percent__hover',
		'custom_width_percent__hover_enabled',
		'parallax_effect',
		'module_bg_color',
		'icon_placement',
		'image_max_width',
		'icon_font_size',
		'icon_placement_tablet',
		'image_max_width_tablet',
		'icon_font_size_tablet',
		'icon_placement_phone',
		'image_max_width_phone',
		'icon_font_size_phone',
		'image_max_width__hover',
		'icon_font_size__hover',
		'image_max_width__hover_enabled',
		'icon_font_size__hover_enabled',
		'icon_placement_last_edited',
		'image_max_width_last_edited',
		'icon_font_size_last_edited',
		'image_max_width__sticky_enabled',
		'icon_font_size__sticky_enabled',
		'image_max_width__sticky',
		'icon_font_size__sticky',
		'use_circle',
		'use_circle_border',
		'circle_border_color',
		'circle_border_color_tablet',
		'circle_border_color_phone',
		'circle_border_color__hover',
		'circle_border_color__hover_enabled',
		'circle_border_color_last_edited',
		'circle_border_color__sticky_enabled',
		'circle_border_color__sticky',
		'circle_color',
		'circle_color_tablet',
		'circle_color_phone',
		'circle_color__hover',
		'circle_color__hover_enabled',
		'circle_color_last_edited',
		'circle_color__sticky_enabled',
		'circle_color__sticky',
		'text_orientation',
		'text_text_align',
		'field_bg',
		'hide_button',
		'button_text_size_hover',
		'button_text_color_hover',
		'button_border_width_hover',
		'button_border_color_hover',
		'button_border_radius_hover',
		'button_letter_spacing_hover',
		'button_bg_color_hover',
		'input_text_color',
		'input_text_color__hover_enabled',
		'input_text_color__hover',
		'input_font',
		'input_text_align',
		'input_font_size',
		'input_font_size_last_edited',
		'input_font_size_tablet',
		'input_font_size_phone',
		'input_font_size__hover_enabled',
		'input_font_size__hover',
		'input_letter_spacing',
		'input_letter_spacing_last_edited',
		'input_letter_spacing_tablet',
		'input_letter_spacing_phone',
		'input_letter_spacing__hover_enabled',
		'input_letter_spacing__hover',
		'input_line_height',
		'input_line_height_last_edited',
		'input_line_height_tablet',
		'input_line_height_phone',
		'input_line_height__hover_enabled',
		'input_line_height__hover',
		'input_text_shadow_horizontal_length',
		'input_text_shadow_horizontal_length__hover_enabled',
		'input_text_shadow_horizontal_length__hover',
		'input_text_shadow_vertical_length',
		'input_text_shadow_vertical_length__hover_enabled',
		'input_text_shadow_vertical_length__hover',
		'input_text_shadow_blur_strength',
		'input_text_shadow_blur_strength__hover_enabled',
		'input_text_shadow_blur_strength__hover',
		'input_text_shadow_color',
		'input_text_shadow_color__hover_enabled',
		'input_text_shadow_color__hover',
		'input_text_shadow_style',
		'bg_color',
		'bar_top_padding',
		'bar_bottom_padding',
		'bar_top_padding_tablet',
		'bar_bottom_padding_tablet',
		'bar_top_padding_phone',
		'bar_bottom_padding_phone',
		'bar_top_padding_last_edited',
		'bar_bottom_padding_last_edited',
		'border_radius',
		'label_color',
		'percentage_color',
		'top_padding',
		'bottom_padding',
		'top_padding_tablet',
		'bottom_padding_tablet',
		'top_padding_phone',
		'bottom_padding_phone',
		'top_padding_last_edited',
		'bottom_padding_last_edited',
		'remove_inner_shadow',
		'hide_content_on_mobile',
		'hide_cta_on_mobile',
		'box_shadow_style',
		'show_inner_shadow',
		'video_bg_mp4',
		'video_bg_webm',
		'video_bg_width',
		'video_bg_height',
		'hide_on_mobile',
		'hide_prev',
		'hide_next',
		'remove_featured_drop_shadow',
		'center_list_items',
		'remove_border',
		'use_dropshadow',
		'input_border_radius',
		'form_background_color',
		'form_background_color__hover_enabled',
		'form_background_color__hover',
		'field_background_color',
		'field_background_color__hover_enabled',
		'field_background_color__hover',
		'use_focus_border_color',
		'focus_border_color',
		'content',
		'focus_background_color',
		'focus_text_color',
		'fields_text_shadow_horizontal_length',
		'fields_text_shadow_horizontal_length__hover_enabled',
		'fields_text_shadow_horizontal_length__hover',
		'fields_text_shadow_vertical_length',
		'fields_text_shadow_vertical_length__hover_enabled',
		'fields_text_shadow_vertical_length__hover',
		'fields_text_shadow_blur_strength',
		'fields_text_shadow_blur_strength__hover_enabled',
		'fields_text_shadow_blur_strength__hover',
		'fields_text_shadow_color',
		'fields_text_shadow_color__hover_enabled',
		'fields_text_shadow_color__hover',
		'fields_text_shadow_style',
		'focus_background_color__hover_enabled',
		'focus_background_color__hover',
		'focus_text_color__hover_enabled',
		'focus_text_color__hover',
		'icon_hover_color',
		'portrait_border_radius',
		'use_icon_font_size',
		'icon_color',
		'icon_color_tablet',
		'icon_color_phone',
		'icon_color__hover',
		'icon_color__hover_enabled',
		'icon_color_last_edited',
		'link_shape',
		'grayscale_filter_amount',
		'pricing_item_excluded_color',
		'pricing_item_excluded_color__hover_enabled',
		'pricing_item_excluded_color__hover',
		'body_font',
		'body_font_last_edited',
		'body_font_tablet',
		'body_font_phone',
		'body_text_color',
		'body_text_color_last_edited',
		'body_text_color_tablet',
		'body_text_color_phone',
		'body_text_color__hover_enabled',
		'body_text_color__hover',
		'body_font_size',
		'body_font_size_last_edited',
		'body_font_size_tablet',
		'body_font_size_phone',
		'body_font_size__hover_enabled',
		'body_font_size__hover',
		'body_letter_spacing',
		'body_letter_spacing_last_edited',
		'body_letter_spacing_tablet',
		'body_letter_spacing_phone',
		'body_letter_spacing__hover_enabled',
		'body_letter_spacing__hover',
		'body_line_height',
		'body_line_height_last_edited',
		'body_line_height_tablet',
		'body_line_height_phone',
		'body_line_height__hover_enabled',
		'body_line_height__hover',
		'body_text_shadow_style',
		'body_text_shadow_horizontal_length',
		'body_text_shadow_horizontal_length_last_edited',
		'body_text_shadow_horizontal_length_tablet',
		'body_text_shadow_horizontal_length_phone',
		'body_text_shadow_horizontal_length__hover_enabled',
		'body_text_shadow_horizontal_length__hover',
		'body_text_shadow_vertical_length',
		'body_text_shadow_vertical_length_last_edited',
		'body_text_shadow_vertical_length_tablet',
		'body_text_shadow_vertical_length_phone',
		'body_text_shadow_vertical_length__hover_enabled',
		'body_text_shadow_vertical_length__hover',
		'body_text_shadow_blur_strength',
		'body_text_shadow_blur_strength_last_edited',
		'body_text_shadow_blur_strength_tablet',
		'body_text_shadow_blur_strength_phone',
		'body_text_shadow_blur_strength__hover_enabled',
		'body_text_shadow_blur_strength__hover',
		'body_text_shadow_color',
		'body_text_shadow_color_last_edited',
		'body_text_shadow_color_tablet',
		'body_text_shadow_color_phone',
		'body_text_shadow_color__hover_enabled',
		'body_text_shadow_color__hover',
		'use_background_color',
		'saved_tabs',
		'_unique_id',
		'x',
		'y',
		'transform_styles',
		'form_field_text_align',
		'collapsed',

		// Background Gradient Attributes (from BackgroundGradientStops.php and BackgroundGradientOverlaysImage.php)
		'background_color_gradient_start',
		'background_color_gradient_start_position',
		'background_color_gradient_end',
		'background_color_gradient_end_position',
		'use_background_color_gradient',
		'background_color_gradient_overlays_image',
		'background_color_gradient_type',
		'background_color_gradient_direction',
		'background_color_gradient_direction_radial',
		'background_color_gradient_stops',
		'background_color_gradient_unit',

		// Responsive variations of background gradient attributes
		'background_color_gradient_start_tablet',
		'background_color_gradient_start_phone',
		'background_color_gradient_start__hover',
		'background_color_gradient_start__sticky',
		'background_color_gradient_end_tablet',
		'background_color_gradient_end_phone',
		'background_color_gradient_end__hover',
		'background_color_gradient_end__sticky',
		'background_color_gradient_type_tablet',
		'background_color_gradient_type_phone',
		'background_color_gradient_type__hover',
		'background_color_gradient_type__sticky',
		'background_color_gradient_direction_tablet',
		'background_color_gradient_direction_phone',
		'background_color_gradient_direction__hover',
		'background_color_gradient_direction__sticky',
		'background_color_gradient_direction_radial_tablet',
		'background_color_gradient_direction_radial_phone',
		'background_color_gradient_direction_radial__hover',
		'background_color_gradient_direction_radial__sticky',
		'background_color_gradient_start_position_tablet',
		'background_color_gradient_start_position_phone',
		'background_color_gradient_start_position__hover',
		'background_color_gradient_start_position__sticky',
		'background_color_gradient_end_position_tablet',
		'background_color_gradient_end_position_phone',
		'background_color_gradient_end_position__hover',
		'background_color_gradient_end_position__sticky',
		'use_background_color_gradient_tablet',
		'use_background_color_gradient_phone',
		'use_background_color_gradient__hover',
		'use_background_color_gradient__sticky',
		'background_color_gradient_overlays_image_tablet',
		'background_color_gradient_overlays_image_phone',
		'background_color_gradient_overlays_image__hover',
		'background_color_gradient_overlays_image__sticky',
		'background_color_gradient_stops_tablet',
		'background_color_gradient_stops_phone',
		'background_color_gradient_stops__hover',
		'background_color_gradient_stops__sticky',
		'background_color_gradient_unit_tablet',
		'background_color_gradient_unit_phone',
		'background_color_gradient_unit__hover',
		'background_color_gradient_unit__sticky',

		// Button gradient attributes and variations
		'button_bg_color_gradient_start',
		'button_bg_color_gradient_start_position',
		'button_bg_color_gradient_end',
		'button_bg_color_gradient_end_position',
		'button_bg_color_gradient_type',
		'button_bg_color_gradient_direction',
		'button_bg_color_gradient_direction_radial',
		'button_bg_color_gradient_stops',
		'button_bg_color_gradient_unit',
		'button_bg_color_gradient_overlays_image',
		'button_bg_color_gradient_start_tablet',
		'button_bg_color_gradient_start_phone',
		'button_bg_color_gradient_start__hover',
		'button_bg_color_gradient_start__sticky',
		'button_bg_color_gradient_end_tablet',
		'button_bg_color_gradient_end_phone',
		'button_bg_color_gradient_end__hover',
		'button_bg_color_gradient_end__sticky',
		'button_bg_color_gradient_type_tablet',
		'button_bg_color_gradient_type_phone',
		'button_bg_color_gradient_type__hover',
		'button_bg_color_gradient_type__sticky',
		'button_bg_color_gradient_direction_tablet',
		'button_bg_color_gradient_direction_phone',
		'button_bg_color_gradient_direction__hover',
		'button_bg_color_gradient_direction__sticky',
		'button_bg_color_gradient_direction_radial_tablet',
		'button_bg_color_gradient_direction_radial_phone',
		'button_bg_color_gradient_direction_radial__hover',
		'button_bg_color_gradient_direction_radial__sticky',
		'button_bg_color_gradient_start_position_tablet',
		'button_bg_color_gradient_start_position_phone',
		'button_bg_color_gradient_start_position__hover',
		'button_bg_color_gradient_start_position__sticky',
		'button_bg_color_gradient_end_position_tablet',
		'button_bg_color_gradient_end_position_phone',
		'button_bg_color_gradient_end_position__hover',
		'button_bg_color_gradient_end_position__sticky',
		'use_button_bg_color_gradient',
		'use_button_bg_color_gradient_tablet',
		'use_button_bg_color_gradient_phone',
		'use_button_bg_color_gradient__hover',
		'use_button_bg_color_gradient__sticky',

		// Button One gradient attributes (for fullwidth header)
		'button_one_bg_color_gradient_start',
		'button_one_bg_color_gradient_start_position',
		'button_one_bg_color_gradient_end',
		'button_one_bg_color_gradient_end_position',
		'button_one_bg_color_gradient_type',
		'button_one_bg_color_gradient_direction',
		'button_one_bg_color_gradient_direction_radial',
		'button_one_bg_color_gradient_stops',
		'button_one_bg_color_gradient_unit',
		'button_one_bg_color_gradient_overlays_image',
		'button_one_bg_color_gradient_start_tablet',
		'button_one_bg_color_gradient_start_phone',
		'button_one_bg_color_gradient_start__hover',
		'button_one_bg_color_gradient_start__sticky',
		'button_one_bg_color_gradient_end_tablet',
		'button_one_bg_color_gradient_end_phone',
		'button_one_bg_color_gradient_end__hover',
		'button_one_bg_color_gradient_end__sticky',
		'button_one_bg_color_gradient_type_tablet',
		'button_one_bg_color_gradient_type_phone',
		'button_one_bg_color_gradient_type__hover',
		'button_one_bg_color_gradient_type__sticky',
		'button_one_bg_color_gradient_direction_tablet',
		'button_one_bg_color_gradient_direction_phone',
		'button_one_bg_color_gradient_direction__hover',
		'button_one_bg_color_gradient_direction__sticky',
		'button_one_bg_color_gradient_direction_radial_tablet',
		'button_one_bg_color_gradient_direction_radial_phone',
		'button_one_bg_color_gradient_direction_radial__hover',
		'button_one_bg_color_gradient_direction_radial__sticky',
		'button_one_bg_color_gradient_start_position_tablet',
		'button_one_bg_color_gradient_start_position_phone',
		'button_one_bg_color_gradient_start_position__hover',
		'button_one_bg_color_gradient_start_position__sticky',
		'button_one_bg_color_gradient_end_position_tablet',
		'button_one_bg_color_gradient_end_position_phone',
		'button_one_bg_color_gradient_end_position__hover',
		'button_one_bg_color_gradient_end_position__sticky',
		'use_button_one_bg_color_gradient',
		'use_button_one_bg_color_gradient_tablet',
		'use_button_one_bg_color_gradient_phone',
		'use_button_one_bg_color_gradient__hover',
		'use_button_one_bg_color_gradient__sticky',

		// Button Two gradient attributes (for fullwidth header)
		'button_two_bg_color_gradient_start',
		'button_two_bg_color_gradient_start_position',
		'button_two_bg_color_gradient_end',
		'button_two_bg_color_gradient_end_position',
		'button_two_bg_color_gradient_type',
		'button_two_bg_color_gradient_direction',
		'button_two_bg_color_gradient_direction_radial',
		'button_two_bg_color_gradient_stops',
		'button_two_bg_color_gradient_unit',
		'button_two_bg_color_gradient_overlays_image',
		'button_two_bg_color_gradient_start_tablet',
		'button_two_bg_color_gradient_start_phone',
		'button_two_bg_color_gradient_start__hover',
		'button_two_bg_color_gradient_start__sticky',
		'button_two_bg_color_gradient_end_tablet',
		'button_two_bg_color_gradient_end_phone',
		'button_two_bg_color_gradient_end__hover',
		'button_two_bg_color_gradient_end__sticky',
		'button_two_bg_color_gradient_type_tablet',
		'button_two_bg_color_gradient_type_phone',
		'button_two_bg_color_gradient_type__hover',
		'button_two_bg_color_gradient_type__sticky',
		'button_two_bg_color_gradient_direction_tablet',
		'button_two_bg_color_gradient_direction_phone',
		'button_two_bg_color_gradient_direction__hover',
		'button_two_bg_color_gradient_direction__sticky',
		'button_two_bg_color_gradient_direction_radial_tablet',
		'button_two_bg_color_gradient_direction_radial_phone',
		'button_two_bg_color_gradient_direction_radial__hover',
		'button_two_bg_color_gradient_direction_radial__sticky',
		'button_two_bg_color_gradient_start_position_tablet',
		'button_two_bg_color_gradient_start_position_phone',
		'button_two_bg_color_gradient_start_position__hover',
		'button_two_bg_color_gradient_start_position__sticky',
		'button_two_bg_color_gradient_end_position_tablet',
		'button_two_bg_color_gradient_end_position_phone',
		'button_two_bg_color_gradient_end_position__hover',
		'button_two_bg_color_gradient_end_position__sticky',
		'use_button_two_bg_color_gradient',
		'use_button_two_bg_color_gradient_tablet',
		'use_button_two_bg_color_gradient_phone',
		'use_button_two_bg_color_gradient__hover',
		'use_button_two_bg_color_gradient__sticky',

		// Background color gradient with _1, _2, _3 suffixes
		'background_color_gradient_type_1',
		'background_color_gradient_type_2',
		'background_color_gradient_type_3',
		'background_color_gradient_type_tablet_1',
		'background_color_gradient_type_tablet_2',
		'background_color_gradient_type_tablet_3',
		'background_color_gradient_type_phone_1',
		'background_color_gradient_type_phone_2',
		'background_color_gradient_type_phone_3',
		'background_color_gradient_type__hover_1',
		'background_color_gradient_type__hover_2',
		'background_color_gradient_type__hover_3',
		'background_color_gradient_type__sticky_1',
		'background_color_gradient_type__sticky_2',
		'background_color_gradient_type__sticky_3',
		'background_color_gradient_stops_1',
		'background_color_gradient_stops_2',
		'background_color_gradient_stops_3',
		'background_color_gradient_stops_tablet_1',
		'background_color_gradient_stops_tablet_2',
		'background_color_gradient_stops_tablet_3',
		'background_color_gradient_stops_phone_1',
		'background_color_gradient_stops_phone_2',
		'background_color_gradient_stops_phone_3',
		'background_color_gradient_stops__hover_1',
		'background_color_gradient_stops__hover_2',
		'background_color_gradient_stops__hover_3',
		'background_color_gradient_stops__sticky_1',
		'background_color_gradient_stops__sticky_2',
		'background_color_gradient_stops__sticky_3',

		// Divi AI attributes
		'template_type',
		// Additional legacy attributes from other migration files
		'bg_img',
		'image_icon_width',
		'image_icon_custom_padding',
		'border_width_all_image',
		'orderby',
	];

	/**
	 * Get all legacy attribute names from migration classes
	 *
	 * @return array Array of legacy attribute names
	 */
	public static function get_legacy_attribute_names(): array {
		if ( self::$attributes ) {
			return self::$attributes;
		}

		// Try to get from option first.
		$legacy_attribute_names = et_get_option( self::OPTION_NAME, [] );

		// If we already have the data, return it.
		if ( ! empty( $legacy_attribute_names ) ) {
			return $legacy_attribute_names;
		}

		// Make sure the migration class is loaded.
		if ( ! class_exists( 'ET_Builder_Module_Settings_Migration' ) ) {
			require_once ET_BUILDER_DIR . 'module/settings/Migration.php';
		}

		// Initialize migrations to populate field_name_migrations.
		ET_Builder_Module_Settings_Migration::init();

		// Get all migrations.
		$migrations = ET_Builder_Module_Settings_Migration::get_migrations( 'all' );

		// Process each migration to populate field_name_migrations.
		foreach ( $migrations as $migration ) {
			$fields  = $migration->get_fields();
			$modules = $migration->get_modules();

			foreach ( $modules as $module_slug ) {
				$migration->handle_field_name_migrations( [], $module_slug );
			}
		}

		// Get all legacy attribute names from field_name_migrations.
		$legacy_attribute_names = [];

		if ( ! empty( ET_Builder_Module_Settings_Migration::$field_name_migrations ) ) {
			foreach ( ET_Builder_Module_Settings_Migration::$field_name_migrations as $module_slug => $field_mappings ) {
				foreach ( $field_mappings as $new_name => $old_names ) {
					foreach ( $old_names as $old_name ) {
						$legacy_attribute_names[] = $old_name;
					}
				}
			}
		}

		// Add known legacy attribute names that might not be captured by field_name_migrations.
		$additional_legacy_attributes = array(
			'use_background_color',
			'saved_tabs',
			'_unique_id',
			'x',
			'y',
			'transform_styles',
			'form_field_text_align',
		);

		$legacy_attribute_names = array_merge( $legacy_attribute_names, $additional_legacy_attributes );

		// Remove duplicates.
		$legacy_attribute_names = array_values( array_unique( $legacy_attribute_names ) );

		// Store in option for future use.
		et_update_option( self::OPTION_NAME, $legacy_attribute_names );

		return $legacy_attribute_names;
	}

	/**
	 * Check if an attribute name is a legacy attribute name
	 *
	 * @param string $attribute_name The attribute name to check.
	 * @return bool True if it's a legacy attribute name, false otherwise.
	 */
	public static function is_legacy_attribute( string $attribute_name ): bool {
		return in_array( $attribute_name, self::get_legacy_attribute_names(), true );
	}
}
