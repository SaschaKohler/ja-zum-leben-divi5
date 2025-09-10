<?php
/**
 * Module: DynamicContentACFUtils class.
 *
 * Shared utility functions for handling Advanced Custom Fields (ACF) integration
 * across different dynamic content option classes.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\Framework\Utility\StringUtility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentACFUtils class.
 *
 * @since ??
 */
class DynamicContentACFUtils {

	/**
	 * Default items per page for repeater queries.
	 *
	 * @var int
	 */
	const DEFAULT_REPEATER_PER_PAGE = 10;

	/**
	 * Check if ACF plugin is active.
	 *
	 * @since ??
	 *
	 * @return bool True if ACF is active, false otherwise.
	 */
	public static function is_acf_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' );
	}

	/**
	 * Get ACF field information for a given meta type.
	 *
	 * @since ??
	 *
	 * @param string $meta_type The meta type: 'post', 'user', or 'term'.
	 *
	 * @return array Array of ACF field info with field names as keys and labels as values.
	 */
	public static function get_acf_field_info( string $meta_type ): array {
		if ( ! self::is_acf_active() || ! function_exists( 'acf_get_field_groups' ) ) {
			return [];
		}

		// Check permissions for user meta access.
		if ( 'user' === $meta_type && ! current_user_can( 'manage_users' ) ) {
			return [];
		}

		$acf_fields = [];

		// Get all ACF field groups - we'll be more inclusive now.
		$field_groups = acf_get_field_groups();

		if ( empty( $field_groups ) ) {
			return [];
		}

		foreach ( $field_groups as $group ) {
			// Get fields from this group - don't filter by location rules for now.
			// This ensures we catch all ACF fields regardless of complex location rules.
			$fields = acf_get_fields( $group['ID'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$field_type  = $field['type'] ?? 'unknown';
				$field_name  = $field['name'] ?? '';
				$field_label = $field['label'] ?? $field_name;

				// Skip repeater and group fields for now (they have their own handling).
				if ( in_array( $field_type, [ 'repeater', 'group', 'flexible_content' ], true ) ) {
					continue;
				}

				if ( ! empty( $field_name ) ) {
					$acf_fields[ $field_name ] = $field_label;
				}
			}
		}

		return $acf_fields;
	}

	/**
	 * Check if a meta key is an ACF field.
	 *
	 * @since ??
	 *
	 * @param string $meta_key  The meta key to check.
	 * @param array  $acf_fields Array of ACF field names.
	 *
	 * @return bool True if the meta key is an ACF field, false otherwise.
	 */
	public static function is_acf_field( string $meta_key, array $acf_fields ): bool {
		// First check our collected ACF fields array.
		if ( isset( $acf_fields[ $meta_key ] ) ) {
			return true;
		}

		// Check if it's an ACF reference field (starts with underscore and has corresponding field).
		if ( StringUtility::starts_with( $meta_key, '_' ) ) {
			$field_name = ltrim( $meta_key, '_' );
			if ( isset( $acf_fields[ $field_name ] ) ) {
				return true;
			}
		}

		// Use ACF's built-in function as a fallback to catch any fields we might have missed.
		if ( self::is_acf_active() && function_exists( 'acf_get_field' ) ) {
			// Try with $meta_key and, if needed, without a leading underscore.
			foreach ( [ $meta_key, ltrim( $meta_key, '_' ) ] as $key ) {
				if ( acf_get_field( $key ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the proper label for an ACF field.
	 *
	 * @since ??
	 *
	 * @param string $meta_key   The meta key to get label for.
	 * @param array  $acf_fields Array of pre-collected ACF field names and labels.
	 *
	 * @return string The ACF field label or meta key as fallback.
	 */
	public static function get_acf_field_label( string $meta_key, array $acf_fields ): string {
		// First check our pre-collected ACF fields array.
		$field_name = '_' === substr( $meta_key, 0, 1 ) ? ltrim( $meta_key, '_' ) : $meta_key;
		if ( isset( $acf_fields[ $field_name ] ) ) {
			return $acf_fields[ $field_name ];
		}

		// Use ACF's built-in function to get field information.
		if ( self::is_acf_active() && function_exists( 'acf_get_field' ) ) {
			// Try with $meta_key and, if needed, without a leading underscore.
			foreach ( [ $meta_key, ltrim( $meta_key, '_' ) ] as $key ) {
				$field = acf_get_field( $key );
				if ( false !== $field && isset( $field['label'] ) ) {
					return esc_html( $field['label'] );
				}
			}
		}

		// Fallback to the meta key itself.
		return esc_html( $meta_key );
	}

	/**
	 * Get meta value by type with ACF field processing.
	 *
	 * @since ??
	 *
	 * @param string $type     The meta type: 'post', 'user', or 'term'.
	 * @param int    $id       The object ID.
	 * @param string $meta_key The meta key.
	 *
	 * @return mixed The meta value.
	 */
	public static function get_meta_value_by_type( string $type, int $id, string $meta_key ) {
		// Check if this is an ACF field and use ACF's functions for proper processing.
		if ( self::is_acf_active() && function_exists( 'get_field' ) ) {
			// Get ACF field info to determine if this is an ACF field.
			$acf_fields = self::get_acf_field_info( $type );

			// Remove underscore prefix for ACF field lookup.
			$field_name = StringUtility::starts_with( $meta_key, '_' ) ? ltrim( $meta_key, '_' ) : $meta_key;

			if ( self::is_acf_field( $meta_key, $acf_fields ) ) {
				// Use ACF's get_field() function which handles field type processing.
				switch ( $type ) {
					case 'post':
						$value = get_field( $field_name, $id );
						break;
					case 'user':
						// Check if current user has permission to access user meta.
						if ( ! current_user_can( 'manage_users' ) ) {
							return '';
						}
						$value = get_field( $field_name, 'user_' . $id );
						break;
					case 'term':
						$value = get_field( $field_name, 'term_' . $id );
						break;
					default:
						$value = '';
				}

				// ACF get_field() handles the processing, but we may need to convert some types for display.
				// For single image fields, ACF returns array with URL and other image data.
				if ( is_array( $value ) && isset( $value['url'] ) ) {
					return $value['url'];
				}

				// For gallery/multiple image fields, return first image URL or all URLs.
				if ( is_array( $value ) ) {
					// Check if it's a gallery field (array of image objects).
					if ( isset( $value[0] ) && is_array( $value[0] ) && isset( $value[0]['url'] ) ) {
						// Gallery field - return first image URL for now.
						return $value[0]['url'];
					} elseif ( isset( $value[0] ) && is_string( $value[0] ) ) {
						// Simple array of strings - join with commas (e.g., checkbox values).
						return implode( ', ', $value );
					}
					// For other complex arrays, fall through to return as-is.
				}

				return $value;
			}
		}

		// Fallback to standard WordPress meta functions for non-ACF fields.
		switch ( $type ) {
			case 'post':
				return get_post_meta( $id, $meta_key, true );
			case 'user':
				// Check if current user has permission to access user meta.
				if ( ! current_user_can( 'manage_users' ) ) {
					return '';
				}
				return get_user_meta( $id, $meta_key, true );
			case 'term':
				return get_term_meta( $id, $meta_key, true );
			default:
				return '';
		}
	}

	/**
	 * Build meta key options with ACF grouping for dropdown menus.
	 *
	 * @since ??
	 *
	 * @param string $type         The meta type: 'post', 'user', or 'term'.
	 * @param string $prefix       The option key prefix.
	 * @param array  $used_meta_keys Array of meta keys to process.
	 *
	 * @return array The organized meta key options with subgroups.
	 */
	public static function build_meta_key_options( string $type, string $prefix, array $used_meta_keys ): array {
		if ( empty( $used_meta_keys ) ) {
			return [];
		}

		// Check permissions for user meta access.
		if ( 'user' === $type && ! current_user_can( 'manage_users' ) ) {
			return [];
		}

		// Get ACF field information for enhanced display.
		$acf_fields = self::get_acf_field_info( $type );

		// Separate ACF fields, standard keys, and underscore keys.
		$acf_keys        = [];
		$standard_keys   = [];
		$underscore_keys = [];

		// Prioritize non-underscore meta keys when duplicates exist.
		$added_keys = [];
		foreach ( $used_meta_keys as $meta_key ) {
			$key_without_underscore = ltrim( $meta_key, '_' );

			// If we haven't seen this key (without underscore) before, or if this is the non-underscore version, add it.
			if ( ! isset( $added_keys[ $key_without_underscore ] ) || 0 !== strpos( $meta_key, '_' ) ) {
				// Check if this is an ACF field.
				if ( self::is_acf_field( $meta_key, $acf_fields ) ) {
					// Get ACF field label using multiple methods.
					$acf_label = self::get_acf_field_label( $meta_key, $acf_fields );

					// Only add non-underscore ACF fields (skip reference fields).
					if ( ! StringUtility::starts_with( $meta_key, '_' ) ) {
						$acf_keys[ $prefix . $meta_key ] = $acf_label;
					}
				} else {
					// Separate standard keys from underscore keys.
					if ( StringUtility::starts_with( $meta_key, '_' ) ) {
						$underscore_keys[ $prefix . $meta_key ] = esc_html( $meta_key );
					} else {
						$standard_keys[ $prefix . $meta_key ] = esc_html( $meta_key );
					}
				}
				$added_keys[ $key_without_underscore ] = esc_html( $meta_key );
			}
		}

		// Build the final options with proper subgroup structure for et-vb-subgroup-title.
		$final_options = [];

		// Add ACF fields section with subgroup structure.
		if ( ! empty( $acf_keys ) ) {
			// Convert string values to proper option format.
			$acf_options = [];
			foreach ( $acf_keys as $key => $value ) {
				if ( is_string( $value ) ) {
					$acf_options[ $key ] = [ 'label' => esc_html( $value ) ];
				} else {
					$acf_options[ $key ] = $value;
				}
			}

			$final_options[ $prefix . 'group_acf' ] = [
				'label'   => esc_html__( 'Advanced Custom Fields', 'et_builder' ),
				'options' => $acf_options,
			];
		}

		// Add standard meta keys section with subgroup structure.
		if ( ! empty( $standard_keys ) ) {
			// Convert string values to proper option format.
			$standard_options = [];
			foreach ( $standard_keys as $key => $value ) {
				if ( is_string( $value ) ) {
					$standard_options[ $key ] = [ 'label' => esc_html( $value ) ];
				} else {
					$standard_options[ $key ] = $value;
				}
			}

			$final_options[ $prefix . 'group_standard' ] = [
				'label'   => esc_html__( 'Standard Meta Keys', 'et_builder' ),
				'options' => $standard_options,
			];
		}

		// Add underscore keys section with subgroup structure.
		if ( ! empty( $underscore_keys ) ) {
			// Convert string values to proper option format.
			$underscore_options = [];
			foreach ( $underscore_keys as $key => $value ) {
				if ( is_string( $value ) ) {
					$underscore_options[ $key ] = [ 'label' => esc_html( $value ) ];
				} else {
					$underscore_options[ $key ] = $value;
				}
			}

			$final_options[ $prefix . 'group_underscore' ] = [
				'label'   => esc_html__( 'Non-Standard Meta Keys', 'et_builder' ),
				'options' => $underscore_options,
			];
		}

		return $final_options;
	}

	/**
	 * Check if the query type is a repeater query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a repeater query, false otherwise.
	 */
	public static function is_repeater_query( $query_type ) {
		return StringUtility::starts_with( $query_type, 'repeater_' );
	}

	/**
	 * Build repeater query arguments for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	public static function build_repeater_query_args( $params ) {
		$query_type    = $params['query_type'];
		$repeater_name = '';

		// Remove 'repeater_' prefix.
		$repeater_name = substr( $query_type, 9 );

		$query_args = [
			'repeater_name'     => $repeater_name,
			'repeater_per_page' => $params['post_per_page'],
			'repeater_offset'   => $params['post_offset'],
		];

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $query_type,
			'post_type'    => $repeater_name,
		];

		return $result;
	}

	/**
	 * Execute a repeater query.
	 *
	 * @since ??
	 *
	 * @param array $query_args Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	public static function execute_repeater_query( $query_args ) {
		$repeater_response = self::get_repeater_results( $query_args );

		return [
			'results'     => $repeater_response['items'] ?? [],
			'total_pages' => $repeater_response['total_pages'],
		];
	}

	/**
	 * Get repeater query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Repeater query results.
	 */
	public static function get_repeater_results( array $params ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$repeater_field = $params['repeater_name'] ?? '';
		if ( empty( $repeater_field ) ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$pagination = self::get_repeater_pagination_params( $params );

		$repeater_field_object = self::find_repeater_field_object( $repeater_field );
		if ( ! $repeater_field_object ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$all_repeater_values = self::get_all_repeater_values_from_database( $repeater_field_object );
		if ( empty( $all_repeater_values ) ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$processed_values = self::process_repeater_values( $all_repeater_values, $repeater_field_object );
		$total_items      = count( $processed_values );
		$items            = array_slice( $processed_values, $pagination['offset'], $pagination['per_page'] );

		return self::format_repeater_pagination_response(
			$items,
			$total_items,
			$pagination['per_page'],
			$pagination['page'],
			$pagination['offset']
		);
	}

	/**
	 * Get pagination parameters for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Array containing per_page, page, and offset.
	 */
	public static function get_repeater_pagination_params( array $params ): array {
		$per_page = isset( $params['per_page'] ) && '' !== $params['per_page'] ?
			(int) $params['per_page'] : self::DEFAULT_REPEATER_PER_PAGE;

		if ( isset( $params['repeater_per_page'] ) && '' !== $params['repeater_per_page'] ) {
			$per_page = (int) $params['repeater_per_page'];
		}

		// Ensure per_page is at least 1 to prevent errors.
		$per_page = max( 1, $per_page );

		$page = isset( $params['page'] ) ? (int) $params['page'] : 1;

		if ( isset( $params['repeater_offset'] ) && '' !== $params['repeater_offset'] ) {
			$offset = (int) $params['repeater_offset'];
			$page   = floor( $offset / $per_page ) + 1;
		} else {
			$offset = ( $page - 1 ) * $per_page;
		}

		return [
			'per_page' => $per_page,
			'page'     => $page,
			'offset'   => $offset,
		];
	}

	/**
	 * Get empty pagination response for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Empty pagination response.
	 */
	public static function get_empty_repeater_pagination_response( array $params ): array {
		$pagination = self::get_repeater_pagination_params( $params );
		return self::format_repeater_pagination_response( [], 0, $pagination['per_page'], $pagination['page'], $pagination['offset'] );
	}

	/**
	 * Format pagination response for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $items      Result items.
	 * @param int   $total      Total number of items.
	 * @param int   $per_page   Items per page.
	 * @param int   $page       Current page.
	 * @param int   $offset     Applied offset.
	 *
	 * @return array Formatted response with pagination info.
	 */
	public static function format_repeater_pagination_response( array $items, int $total, int $per_page, int $page, int $offset = 0 ): array {
		// Adjust total items and pages when offset is applied.
		$adjusted_total = max( 0, $total - $offset );
		$adjusted_pages = $adjusted_total > 0 ? ceil( $adjusted_total / $per_page ) : 0;

		return [
			'items'       => $items,
			'total_items' => $adjusted_total,
			'total_pages' => $adjusted_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Find repeater field object by name, key, or label.
	 *
	 * @since ??
	 *
	 * @param string $repeater_field The repeater field identifier.
	 *
	 * @return array|null The repeater field object or null if not found.
	 */
	public static function find_repeater_field_object( string $repeater_field ): ?array {
		$field_groups = acf_get_field_groups();

		if ( empty( $field_groups ) ) {
			return null;
		}

		foreach ( $field_groups as $group ) {
			$fields = acf_get_fields( $group['ID'] );
			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( 'repeater' !== $field['type'] ) {
					continue;
				}

				if ( $field['name'] === $repeater_field ||
					$field['key'] === $repeater_field ||
					$field['label'] === $repeater_field
				) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * Get all repeater field values from database efficiently.
	 *
	 * Uses direct wpdb queries for performance - more faster than WP_Query approach.
	 * Only retrieves needed postmeta data, not full post objects.
	 *
	 * @since ??
	 *
	 * @param array $repeater_field_object The validated repeater field object from ACF.
	 *
	 * @return array Array of all repeater values from all posts or options.
	 */
	public static function get_all_repeater_values_from_database( array $repeater_field_object ): array {
		global $wpdb;

		$repeater_name = $repeater_field_object['name'];
		$sub_fields    = $repeater_field_object['sub_fields'] ?? [];

		if ( empty( $repeater_name ) || ! is_string( $repeater_name ) ) {
			return [];
		}

		$is_theme_options = self::is_repeater_field_assigned_to_theme_options( $repeater_field_object );

		if ( $is_theme_options ) {
			return self::get_theme_options_repeater_values( $repeater_name, $sub_fields );
		}

		$posts_with_repeater = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value as row_count 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = %s 
				AND meta_value > 0
				AND meta_value REGEXP '^[0-9]+$'
				ORDER BY post_id",
				$repeater_name
			)
		);

		if ( empty( $posts_with_repeater ) ) {
			return [];
		}

		$all_repeater_values = [];

		foreach ( $posts_with_repeater as $post_data ) {
			$post_id   = (int) $post_data->post_id;
			$row_count = (int) $post_data->row_count;

			$post = get_post( $post_id );
			if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
				continue;
			}

			// Performance limit: Prevent extremely large repeaters from causing memory issues.
			$row_count = min( $row_count, 1000 );

			for ( $i = 0; $i < $row_count; $i++ ) {
				$row_data = [
					'_post_id'   => $post_id,
					'_row_index' => $i,
				];

				foreach ( $sub_fields as $sub_field ) {
					$sub_field_name = $sub_field['name'];
					$meta_key       = sanitize_key( $repeater_name . '_' . $i . '_' . $sub_field_name );

					if ( in_array( $sub_field['type'], [ 'image', 'true_false' ], true ) && function_exists( 'get_field' ) ) {
						$field_key = $repeater_name . '_' . $i . '_' . $sub_field_name;
						$value     = get_field( $field_key, $post_id );

						if ( false === $value || null === $value ) {
							$value = get_post_meta( $post_id, $meta_key, true );
						}
					} else {
						$value = get_post_meta( $post_id, $meta_key, true );

						if ( 'link' === $sub_field['type'] ) {
							$value = $value['url'] ?? '';
						}
					}

					$row_data[ $sub_field_name ] = $value;
				}

				$all_repeater_values[] = $row_data;
			}
		}

		return $all_repeater_values;
	}

	/**
	 * Process repeater values to handle special field types.
	 *
	 * @since ??
	 *
	 * @param array $repeater_values The raw repeater values.
	 * @param array $repeater_field_object The repeater field object.
	 *
	 * @return array Processed repeater values.
	 */
	public static function process_repeater_values( array $repeater_values, array $repeater_field_object ): array {
		if ( empty( $repeater_values ) || empty( $repeater_field_object['sub_fields'] ) ) {
			return [];
		}

		return array_map(
			function( $row ) use ( $repeater_field_object ) {
				$processed_row = [
					'_post_id'   => $row['_post_id'] ?? 0,
					'_row_index' => $row['_row_index'] ?? 0,
				];

				foreach ( $repeater_field_object['sub_fields'] as $sub_field ) {
					$field_name  = $sub_field['name'];
					$field_value = $row[ $field_name ] ?? null;

					$processed_row[ $field_name ] = self::process_repeater_field_value( $field_value, $sub_field );
				}

				return $processed_row;
			},
			$repeater_values
		);
	}

	/**
	 * Process a single repeater field value based on its type.
	 *
	 * @since ??
	 *
	 * @param object $value The field value to process.
	 * @param array  $field The field configuration.
	 *
	 * @return object The processed field value.
	 */
	public static function process_repeater_field_value( $value, array $field ) {
		if ( 'image' === $field['type'] ) {
			if ( is_array( $value ) ) {
				return ! empty( $value['url'] ) ? esc_url( $value['url'] ) : '';
			} elseif ( is_numeric( $value ) && $value > 0 ) {
				$url = wp_get_attachment_url( (int) $value );
				return $url ? esc_url( $url ) : '';
			} elseif ( is_string( $value ) && ! empty( $value ) ) {
				return esc_url( $value );
			}

			return '';
		}

		if ( 'true_false' === $field['type'] ) {
			// Convert raw database values (0/1) to boolean strings.
			return $value ? 'true' : 'false';
		}

		return $value;
	}

	/**
	 * Gets the content for a repeater field.
	 *
	 * @since ??
	 *
	 * @param string $name The repeater field name.
	 * @param object $loop_object The loop object.
	 *
	 * @return string The repeater field content.
	 */
	public static function get_repeater_field_content( string $name, $loop_object ): string {
		// e.g., loop_acf_dynamic_repeater_name|||artist_name -> artist_name.
		$field = explode( '|||', $name )[1] ?? '';
		$value = '';

		if ( is_array( $loop_object ) ) {
			$value = $loop_object[ $field ] ?? '';
		} elseif ( is_object( $loop_object ) && property_exists( $loop_object, $field ) ) {
			$value = $loop_object->$field ?? '';
		}

		return esc_html( $value );
	}

	/**
	 * Check if a repeater field is assigned to Theme Options (options page).
	 *
	 * @since ??
	 *
	 * @param array $repeater_field_object The ACF repeater field object.
	 *
	 * @return bool True if assigned to Theme Options, false otherwise.
	 */
	public static function is_repeater_field_assigned_to_theme_options( array $repeater_field_object ) {
		if ( ! function_exists( 'acf_get_field_group' ) ) {
			return false;
		}

		// Get the parent field group ID from the field object.
		$parent_group_id = $repeater_field_object['parent'] ?? null;
		if ( empty( $parent_group_id ) ) {
			return false;
		}

		$field_group = acf_get_field_group( $parent_group_id );
		if ( ! $field_group || empty( $field_group['location'] ) ) {
			return false;
		}

		// Check location rules for options_page assignment.
		foreach ( $field_group['location'] as $rule_group ) {
			foreach ( $rule_group as $rule ) {
				if ( isset( $rule['param'] ) && 'options_page' === $rule['param'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get repeater field values from Theme Options (options table).
	 *
	 * @since ??
	 *
	 * @param string $repeater_name The repeater field name.
	 * @param array  $sub_fields    The repeater sub-fields configuration.
	 *
	 * @return array Array of repeater values from Theme Options.
	 */
	public static function get_theme_options_repeater_values( string $repeater_name, array $sub_fields ) {
		$option_key = 'options_' . $repeater_name;
		$row_count  = (int) get_option( $option_key, 0 );

		if ( $row_count <= 0 ) {
			return [];
		}

		// Performance limit: Prevent extremely large repeaters from causing memory issues.
		$row_count = min( $row_count, 1000 );

		$all_repeater_values = [];

		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data = [
				'_options_page' => true, // Identifier for Theme Options data.
				'_row_index'    => $i,
				'_post_id'      => 0, // Theme Options don't have a post ID, but set to 0 for compatibility.
			];

			foreach ( $sub_fields as $sub_field ) {
				$sub_field_name = $sub_field['name'];
				$option_key     = 'options_' . $repeater_name . '_' . $i . '_' . $sub_field_name;

				if (
					in_array( $sub_field['type'], [ 'image', 'true_false' ], true ) &&
					function_exists( 'get_field' )
				) {
					$field_key = $repeater_name . '_' . $i . '_' . $sub_field_name;
					$value     = get_field( $field_key, 'option' );

					if ( false === $value || null === $value ) {
						$value = get_option( $option_key, '' );
					}
				} else {
					$value = get_option( $option_key, '' );

					if ( 'link' === $sub_field['type'] && is_array( $value ) ) {
						$value = $value['url'] ?? '';
					}
				}

				$row_data[ $sub_field_name ] = $value;
			}

			$all_repeater_values[] = $row_data;
		}

		return $all_repeater_values;
	}
}
