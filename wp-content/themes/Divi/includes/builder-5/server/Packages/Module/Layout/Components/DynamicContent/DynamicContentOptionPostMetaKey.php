<?php
/**
 * Module: DynamicContentOptionPostMetaKey class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Module: DynamicContentOptionPostMetaKey class.
 *
 * @since ??
 */
class DynamicContentOptionPostMetaKey extends DynamicContentOptionBase implements DynamicContentOptionInterface {

	/**
	 * Get the name of the post meta key option.
	 *
	 * @since ??
	 *
	 * @return string The name of the post meta key option.
	 */
	public function get_name(): string {
		return 'post_meta_key';
	}

	/**
	 * Get the label for the post meta key option.
	 *
	 * This function retrieves the localized label for the post meta key option,
	 * which is used to describe the post meta key in user interfaces.
	 *
	 * @since ??
	 *
	 * @return string The label for the post meta key option.
	 */
	public function get_label(): string {
		// The 'Custom Field' is the official label name for custom meta option.
		// key. So, we keep the same key name and not rename it into 'Option'.
		return esc_html__( 'Custom Field', 'et_builder' );
	}

	/**
	 * Callback for registering post meta key option with dropdown system.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for post meta key by adding them to the options array passed to the function.
	 * It provides a sophisticated dropdown with ACF grouping similar to the loop version.
	 *
	 * @since ??
	 *
	 * @param array  $options The options array to be registered.
	 * @param int    $post_id The post ID.
	 * @param string $context The context in which the options are retrieved e.g `edit`, `display`.
	 *
	 * @return array The options array.
	 */
	public function register_option_callback( array $options, int $post_id, string $context ): array {
		if ( ! isset( $options[ $this->get_name() ] ) ) {
			// Build meta key options dropdown like the loop version.
			$prefix = 'custom_meta_';

			// Get most used meta keys for posts.
			$used_meta_keys = DynamicContentOptions::get_most_used_meta_keys();

			// Start with manual input option.
			$meta_key_options = [
				$prefix . 'group_manual' => [
					'label'   => esc_html__( 'Manual Input', 'et_builder' ),
					'options' => [
						$prefix . 'manual_custom_field_value' => [
							'label' => esc_html__( 'Enter Custom Meta Key', 'et_builder' ),
						],
					],
				],
			];

			// Add discovered meta keys with ACF grouping.
			if ( ! empty( $used_meta_keys ) ) {
				$final_options    = DynamicContentACFUtils::build_meta_key_options( 'post', $prefix, $used_meta_keys );
				$meta_key_options = array_merge( $meta_key_options, $final_options );
			}

			$fields = array_merge(
				[
					'before'          => [
						'label'   => esc_html__( 'Before', 'et_builder' ),
						'type'    => 'text',
						'default' => '',
					],
					'after'           => [
						'label'   => esc_html__( 'After', 'et_builder' ),
						'type'    => 'text',
						'default' => '',
					],
					'select_meta_key' => [
						'label'   => esc_html__( 'Select Custom Field', 'et_builder' ),
						'type'    => 'select',
						'options' => $meta_key_options,
						'default' => $prefix . 'manual_custom_field_value',
					],
					'meta_key'        => [
						'label'   => esc_html__( 'Field Name', 'et_builder' ),
						'type'    => 'text',
						'show_if' => [
							'select_meta_key' => $prefix . 'manual_custom_field_value',
						],
					],
				],
				DynamicContentUtils::get_date_format_fields()
			);

			if ( current_user_can( 'unfiltered_html' ) ) {
				$fields['enable_html'] = [
					'label'   => esc_html__( 'Enable Raw HTML', 'et_builder' ),
					'type'    => 'yes_no_button',
					'options' => [
						'on'  => et_builder_i18n( 'Yes' ),
						'off' => et_builder_i18n( 'No' ),
					],
					'default' => 'off',
					'show_on' => 'text',
				];
			}

			$options[ $this->get_name() ] = [
				'id'     => $this->get_name(),
				'label'  => $this->get_label(),
				'type'   => 'any',
				'custom' => false,
				// Use 'Default' group so it appears in Dynamic Content section.
				'group'  => esc_html__( 'Default', 'et_builder' ),
				'fields' => $fields,
			];
		}

		return $options;
	}



	/**
	 * Render callback for post meta key option with dropdown and ACF support.
	 *
	 * Retrieves the value of post meta key option based on the provided arguments and settings.
	 * This is a callback for `divi_module_dynamic_content_resolved_value` filter.
	 *
	 * @since ??
	 *
	 * @param mixed $value     The current value of the post meta key option.
	 * @param array $data_args {
	 *     Optional. An array of arguments for retrieving the post meta key.
	 *     Default `[]`.
	 *
	 *     @type string  $name            Optional. Option name. Default empty string.
	 *     @type array   $settings        Optional. Option settings. Default `[]`.
	 *     @type integer $post_id         Optional. Post Id. Default `null`.
	 *     @type string  $loop_query_type Optional. The loop query type. Default `null`.
	 *     @type mixed   $loop_object     Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 * }
	 *
	 * @return string The formatted value of the post meta key option.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		$name            = $data_args['name'] ?? '';
		$settings        = $data_args['settings'] ?? [];
		$post_id         = $data_args['post_id'] ?? null;
		$loop_query_type = $data_args['loop_query_type'] ?? null;
		$loop_object     = $data_args['loop_object'] ?? null;

		// Handle custom field names - both old simple format and legacy complex format.
		if ( StringUtility::starts_with( $name, 'custom_meta_' ) ) {
			// Simple format: custom_meta_field_name (preferred).
			if ( 'custom_meta_manual_custom_field_value' === $name ) {
				$meta_key = $settings['meta_key'] ?? '';
			} else {
				// Strip prefix to get actual meta key.
				$meta_key = str_replace( 'custom_meta_', '', $name );
			}
		} elseif ( $name === $this->get_name() ) {
			// Legacy complex format: name is "post_meta_key", actual field is in settings.
			// This is for backward compatibility with old saved content.
			$selected_meta_key = $settings['select_meta_key'] ?? '';

			// Handle both legacy formats.
			// 1. New legacy: select_meta_key with custom_meta_ prefix  .
			// 2. Old legacy: direct meta_key (from main branch).
			if ( ! empty( $selected_meta_key ) ) {
				if ( 'custom_meta_manual_custom_field_value' === $selected_meta_key ) {
					$meta_key = $settings['meta_key'] ?? '';
				} elseif ( StringUtility::starts_with( $selected_meta_key, 'custom_meta_' ) ) {
					// Strip prefix to get actual meta key.
					$meta_key = str_replace( 'custom_meta_', '', $selected_meta_key );
				} else {
					// Unknown format.
					return $value;
				}
			} elseif ( ! empty( $settings['meta_key'] ) ) {
				// Handle original main branch format: direct meta_key in settings.
				$meta_key = $settings['meta_key'];
			} else {
				// Unknown format.
				return $value;
			}
		} else {
			// Not our responsibility.
			return $value;
		}

		// TODO feat(D5, Theme Builder): Replace it once the Theme Builder is implemented in D5.
		// @see https://github.com/elegantthemes/Divi/issues/25149.
		$is_fe = 'fe' === et_builder_get_current_builder_type() && ! is_et_theme_builder_template_preview() && ! Conditions::is_rest_api_request() ? true : false;

		if ( empty( $meta_key ) ) {
			$before = $settings['before'] ?? '';
			$after  = $settings['after'] ?? '';
			return DynamicContentElements::get_wrapper_element(
				[
					'post_id'  => $post_id,
					'name'     => $name,
					'value'    => $before . '' . $after,
					'settings' => $settings,
				]
			);
		}

		// Ensure we have a valid post ID for meta retrieval.
		if ( null === $post_id ) {
			$post_id = get_the_ID();
			if ( false === $post_id || 0 === $post_id ) {
				// No valid post context, return empty value with wrapper.
				$before = $settings['before'] ?? '';
				$after  = $settings['after'] ?? '';
				return DynamicContentElements::get_wrapper_element(
					[
						'post_id'  => null,
						'name'     => $name,
						'value'    => $before . '' . $after,
						'settings' => $settings,
					]
				);
			}
		}

		if (
			StringUtility::starts_with( $loop_query_type ?? '', 'repeater' ) &&
			null !== $loop_object &&
			is_array( $loop_object )
		) {
			$value = $loop_object[ $meta_key ] ?? '';
		} else {
			$value = DynamicContentACFUtils::get_meta_value_by_type( 'post', $post_id, $meta_key );
		}

		// Handle array values (like ACF checkboxes).
		$value = ArrayUtility::is_array_of_strings( $value ) ? implode( ', ', $value ) : $value;

		/**
		 * Filters custom meta value allowing third party to format the values.
		 *
		 * @since ??
		 *
		 * @param string  $value     Custom meta option value.
		 * @param string  $meta_key  Custom meta option key.
		 * @param integer $post_id   Post ID.
		 */
		$value = apply_filters( 'divi_module_dynamic_content_resolved_custom_meta_value', $value, $meta_key, $post_id );

		// Handle custom field date formatting.
		if ( ! is_array( $value ) ) {
			$value = DynamicContentUtils::handle_custom_field_date_format( $settings, $value, $meta_key );
		}

		if ( ( $is_fe && empty( $value ) ) || empty( $meta_key ) ) {
			$value = '';
		} else {
			if ( empty( $meta_key ) && empty( $value ) ) {
				$value = '';
			} elseif ( empty( $value ) && ! empty( $meta_key ) ) {
				$value = DynamicContentUtils::get_custom_meta_label( $meta_key );
			} else {
				// Sanitize HTML contents with oembed-aware sanitization.
				$value = $this->_sanitize_dynamic_content_value( $value );

				if ( 'on' !== ( $settings['enable_html'] ?? 'off' ) ) {
					// Escape HTML if not explicitly enabled.
					$value = esc_html( $value );
				}
			}
		}

		return DynamicContentElements::get_wrapper_element(
			[
				'post_id'  => $post_id,
				'name'     => $name,
				'value'    => $value,
				'settings' => $settings,
			]
		);
	}

	/**
	 * Sanitize dynamic content value with oembed-aware sanitization.
	 *
	 * This function provides smart sanitization that detects oembed content (iframe tags)
	 * and applies appropriate sanitization. For oembed content, it allows safe iframe
	 * attributes while still removing dangerous elements. For regular content, it uses
	 * standard wp_kses_post sanitization.
	 *
	 * @since ??
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized value.
	 */
	private function _sanitize_dynamic_content_value( string $value ): string {
		// Check if the value contains iframe tags (likely oembed content).
		if ( false !== strpos( $value, '<iframe' ) ) {
			// Define allowed iframe attributes for oembed content.
			$allowed_iframe_tags = [
				'iframe' => [
					'title'           => true,
					'width'           => true,
					'height'          => true,
					'src'             => true,
					'allow'           => true,
					'frameborder'     => true,
					'allowfullscreen' => true,
					'referrerpolicy'  => true,
					'loading'         => true,
				],
			];

			// Merge with default wp_kses_post allowed tags.
			$allowed_tags = array_merge( wp_kses_allowed_html( 'post' ), $allowed_iframe_tags );

			// Apply oembed-aware sanitization.
			return wp_kses( $value, $allowed_tags );
		}

		// For non-oembed content, use standard wp_kses_post sanitization.
		return wp_kses_post( $value );
	}

}
