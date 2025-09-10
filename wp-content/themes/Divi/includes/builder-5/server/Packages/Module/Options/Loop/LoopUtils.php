<?php
/**
 * Module: LoopUtils class.
 *
 * @package Builder\Packages\Module\Options\Loop
 */

namespace ET\Builder\Packages\Module\Options\Loop;

use WP_Query;
use WP_Term_Query;
use WP_User_Query;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentPosts;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentElements;
use ET\Builder\Packages\ModuleLibrary\LoopQueryRegistry;
use ET\Builder\Packages\Module\Options\Loop\LoopContext;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use WP;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LoopUtils class.
 *
 * @since ??
 */
class LoopUtils {

	/**
	 * Find module by loop ID in BlockParserStore.
	 *
	 * This function searches through all modules in the BlockParserStore to find
	 * a module with a matching loop ID. Used for predictive query generation
	 * when pagination is placed above the loop.
	 *
	 * @since ??
	 *
	 * @param string   $target_loop_id  The loop ID to search for.
	 * @param int|null $store_instance  Optional. The store instance to search in. Default null.
	 *
	 * @return object|null The module object if found, null otherwise.
	 */
	public static function find_module_by_loop_id( $target_loop_id, $store_instance = null ) {
		if ( empty( $target_loop_id ) ) {
			return null;
		}

		$all_modules = BlockParserStore::get_all( $store_instance );

		if ( empty( $all_modules ) ) {
			return null;
		}

		foreach ( $all_modules as $module ) {
			$module_loop_id = $module->attrs['module']['advanced']['loop']['desktop']['value']['loopId'] ?? null;

			if ( ! $module_loop_id ) {
				continue;
			}

			if ( $target_loop_id === $module_loop_id ) {
				return $module;
			}
		}

		return null;
	}

	/**
	 * Generate query predictively when registry is empty.
	 *
	 * This function implements predictive query generation by finding the target
	 * loop module and generating its query before the loop actually renders.
	 * The generated query is stored in the registry for reuse by the loop.
	 *
	 * @since ??
	 *
	 * @param string   $loop_module_id  The loop ID to generate query for.
	 * @param int|null $store_instance  Optional. The store instance to search in. Default null.
	 *
	 * @return WP_Query|null The generated query object, or null if generation failed.
	 */
	public static function generate_predictive_query( $loop_module_id, $store_instance = null ) {
		$target_module = self::find_module_by_loop_id( $loop_module_id, $store_instance );

		if ( ! $target_module ) {
			return null;
		}

		$loop_data = self::get_query_args_from_attrs( $target_module->attrs );

		if ( empty( $loop_data['query_args'] ) ) {
			return null;
		}

		$query_result = self::execute_query( $loop_data['query_args'], $loop_data['query_type'] );
		$loop_query   = $query_result['query_object'] ?? null;

		if (
			$loop_query &&
			( $loop_query instanceof WP_Query || $loop_query instanceof WP_User_Query || $loop_query instanceof WP_Term_Query )
		) {
			LoopQueryRegistry::store( $loop_module_id, $loop_query, $loop_data['query_args'], $loop_data['query_type'] );
		}

		return $loop_query;
	}

	/**
	 * Build WP_Query arguments from module $attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs The block attributes that were saved by the Visual Builder.
	 *
	 * @return array The WP_Query arguments array.
	 */
	public static function get_query_args_from_attrs( $attrs ) {
		$loop = isset( $attrs['module']['advanced']['loop'] )
			? $attrs['module']['advanced']['loop']
			: [];

		$loop_enabled = isset( $loop['desktop']['value']['enable'] )
			? sanitize_key( $loop['desktop']['value']['enable'] )
			: '';

		$query_type = isset( $loop['desktop']['value']['queryType'] )
			? sanitize_key( $loop['desktop']['value']['queryType'] )
			: 'post_types';

		// Handle query type mapping - post_taxonomies should be treated as terms query.
		if ( 'post_taxonomies' === $query_type ) {
			$query_type = 'terms';
		}

		if ( 'post_types' === $query_type ) {
			// For post types query, extract post types from subTypes if available.
			$post_type = self::_extract_sub_type_values( $loop );
			// Allow empty post_type to be handled by _build_post_query_args which will set it to 'any' for all post types.
		} else {
			// For other query types, extract post types from subTypes.
			$post_type = self::_extract_sub_type_values( $loop );
		}

		$order_by_raw = isset( $loop['desktop']['value']['orderBy'] )
			? sanitize_key( $loop['desktop']['value']['orderBy'] )
			: 'date';

		$order_raw = isset( $loop['desktop']['value']['order'] )
			? sanitize_key( self::_extract_order_by_value( $loop['desktop']['value']['order'] ) )
			: 'DESC';

		$post_per_page = isset( $loop['desktop']['value']['postPerPage'] )
			? absint( $loop['desktop']['value']['postPerPage'] )
			: get_option( 'posts_per_page' ); // No sanitization needed - WP sanitizes posts_per_page on storage.

		$post_offset = isset( $loop['desktop']['value']['postOffset'] )
			? absint( $loop['desktop']['value']['postOffset'] )
			: 0;

		// Check if we're on a paginated page and adjust offset.
		$current_page = 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
		if ( isset( $_GET ) && is_array( $_GET ) ) {
			// Look for loop-specific page parameter.
			$loop_id = isset( $loop['desktop']['value']['loopId'] ) ? $loop['desktop']['value']['loopId'] : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
			if ( $loop_id && isset( $_GET[ $loop_id ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
				$current_page = max( 1, (int) $_GET[ $loop_id ] );
			}
		}

		// Calculate pagination offset if we're on a page other than 1.
		if ( $current_page > 1 ) {
			$pagination_offset = ( $current_page - 1 ) * $post_per_page;
			$post_offset      += $pagination_offset;
		}

		$ignore_stickys_post = isset( $loop['desktop']['value']['ignoreStickysPost'] )
			? sanitize_key( $loop['desktop']['value']['ignoreStickysPost'] )
			: '';

		$exclude_current_post = isset( $loop['desktop']['value']['excludeCurrentPost'] )
			? sanitize_key( $loop['desktop']['value']['excludeCurrentPost'] )
			: 'off';

		// Get advanced filtering attributes.
		// NOTE: These arrays are sanitized downstream in their respective _build_* functions:
		// - Taxonomy arrays: categoryId sanitized with sanitize_key(), term IDs with intval() in _build_taxonomy_query().
		// - Post ID arrays: values sanitized with intval() in _build_post_inclusion_exclusion_query().
		$include_post_with_specific_terms = isset( $loop['desktop']['value']['includePostWithSpecificTerms'] )
			? $loop['desktop']['value']['includePostWithSpecificTerms']
			: [];

		$exclude_post_with_specific_terms = isset( $loop['desktop']['value']['excludePostWithSpecificTerms'] )
			? $loop['desktop']['value']['excludePostWithSpecificTerms']
			: [];

		$include_specific_posts = isset( $loop['desktop']['value']['includeSpecificPosts'] )
			? $loop['desktop']['value']['includeSpecificPosts']
			: [];

		$exclude_specific_posts = isset( $loop['desktop']['value']['excludeSpecificPosts'] )
			? $loop['desktop']['value']['excludeSpecificPosts']
			: [];

		// Get meta query attributes.
		// NOTE: Meta query arrays are sanitized downstream in _build_meta_query():
		// - metaKey/metaValue sanitized with sanitize_text_field().
		// - compare operators validated against allowlist, type validated against allowlist.
		$meta_query_attrs = isset( $loop['desktop']['value']['metaQuery'] )
			? $loop['desktop']['value']['metaQuery']
			: [];

		// Get search attribute.
		$search = isset( $loop['desktop']['value']['search'] )
			? sanitize_text_field( $loop['desktop']['value']['search'] )
			: '';

		$params = [
			'loop_enabled'                     => $loop_enabled,
			'post_type'                        => $post_type,
			'query_type'                       => $query_type,
			'order_by_raw'                     => $order_by_raw,
			'order_raw'                        => $order_raw,
			'post_per_page'                    => $post_per_page,
			'post_offset'                      => $post_offset,
			'ignore_stickys_post'              => $ignore_stickys_post,
			'exclude_current_post'             => $exclude_current_post,
			'include_post_with_specific_terms' => $include_post_with_specific_terms,
			'exclude_post_with_specific_terms' => $exclude_post_with_specific_terms,
			'include_specific_posts'           => $include_specific_posts,
			'exclude_specific_posts'           => $exclude_specific_posts,
			'meta_query_attrs'                 => $meta_query_attrs,
			'search'                           => $search,
		];

		// Check if this is a user query and handle accordingly.
		if ( self::_is_user_query( $query_type ) ) {
			return self::_build_user_query_args( $params );
		}

		// Check if this is a terms query and handle accordingly.
		if ( self::_is_terms_query( $query_type ) ) {
			return self::_build_terms_query_args( $params );
		}

		if ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return DynamicContentACFUtils::build_repeater_query_args( $params );
		}

		// Default to post query handling.
		return self::_build_post_query_args( $params );
	}

	/**
	 * Check if the query type is a user query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a user query, false otherwise.
	 */
	private static function _is_user_query( $query_type ) {
		$user_query_types = [ 'user_roles', 'users' ];
		return in_array( $query_type, $user_query_types, true );
	}

	/**
	 * Check if the query type is a terms query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a terms query, false otherwise.
	 */
	private static function _is_terms_query( $query_type ) {
		$terms_query_types = [ 'terms', 'post_taxonomies' ];
		return in_array( $query_type, $terms_query_types, true );
	}



	/**
	 * Build WP_User_Query arguments for user queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	private static function _build_user_query_args( $params ) {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		// Build WP_User_Query arguments.
		$query_args = [
			'orderby' => $params['order_by_raw'],
			'order'   => $params['order_raw'],
		];

		// Handle user roles (post_type contains roles for user queries).
		$roles = $params['post_type'];
		if ( ! empty( $roles ) ) {
			$query_args['role__in'] = array_map( 'sanitize_key', $roles );
		}

		// Handle pagination.
		if ( $params['post_per_page'] > 0 ) {
			$query_args['number'] = $params['post_per_page'];
		}

		if ( $params['post_offset'] > 0 ) {
			$query_args['offset'] = $params['post_offset'];
		}

		// Handle search.
		if ( ! empty( $params['search'] ) ) {
			$query_args['search'] = '*' . $params['search'] . '*';
		}

		// Handle meta query.
		if ( ! empty( $params['meta_query_attrs'] ) ) {
			$meta_query = self::build_meta_query( $params['meta_query_attrs'] );
			if ( ! empty( $meta_query ) ) {
				$query_args['meta_query'] = $meta_query;
			}
		}

		// Handle user inclusion/exclusion.
		$user_include = [];
		$user_exclude = [];

		if ( ! empty( $params['include_specific_posts'] ) && is_array( $params['include_specific_posts'] ) ) {
			$user_include = array_map( 'intval', array_filter( array_column( $params['include_specific_posts'], 'value' ) ) );
		}

		if ( ! empty( $params['exclude_specific_posts'] ) && is_array( $params['exclude_specific_posts'] ) ) {
			$user_exclude = array_map( 'intval', array_filter( array_column( $params['exclude_specific_posts'], 'value' ) ) );
		}

		if ( ! empty( $user_include ) ) {
			$query_args['include'] = $user_include;
		}

		if ( ! empty( $user_exclude ) ) {
			$query_args['exclude'] = $user_exclude;
		}

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $params['post_type'], // Contains roles for user queries.
		];

		return $result;
	}

	/**
	 * Build WP_Query arguments for post queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	private static function _build_post_query_args( $params ) {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		$post_type = $params['post_type'];

		// Handle empty post_type parameter - set to 'any' to query all post types.
		if ( empty( $post_type ) ) {
			$post_type = [ 'any' ];
		} else {
			$post_type = array_map( 'sanitize_key', $post_type );
		}

		// Build WP_Query arguments.
		$query_args = [
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'orderby'     => $params['order_by_raw'],
			'order'       => $params['order_raw'],
		];

		// Handle post status for 'any' post type and arrays.
		if ( in_array( 'attachment', $post_type, true ) || in_array( 'any', $post_type, true ) ) {
			$query_args['post_status'] = [ 'publish', 'inherit', 'private' ];
		}

		// Only include posts_per_page if set by attribute (not default from get_option).
		if ( $params['post_per_page'] > 0 ) {
			$query_args['posts_per_page'] = $params['post_per_page'];
		}

		// Only include offset if not 0.
		if ( 0 !== $params['post_offset'] ) {
			$query_args['offset'] = $params['post_offset'];
		}

		// Handle taxonomy filtering with intersection logic.
		$tax_query_parts = self::_build_taxonomy_query( $params['include_post_with_specific_terms'], $params['exclude_post_with_specific_terms'] );
		if ( ! empty( $tax_query_parts ) ) {
			$query_args['tax_query'] = $tax_query_parts;
		}

		// Handle post inclusion/exclusion filtering.
		$post_query_args = self::_build_post_inclusion_exclusion_query( $params['include_specific_posts'], $params['exclude_specific_posts'] );
		$query_args      = array_merge( $query_args, $post_query_args );

		// Handle meta query parameters.
		// Note: $params['meta_query_attrs'] is sanitized within the build_meta_query method.
		$meta_query = self::build_meta_query( $params['meta_query_attrs'] );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		// Add search query if specified.
		if ( ! empty( $params['search'] ) ) {
			$query_args['s'] = $params['search'];
		}

		// Handle sticky posts for post type 'post' or when querying all post types ('any').
		$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
		if ( 'any' === $post_type || in_array( 'post', $post_types, true ) ) {
			// Always set ignore_sticky_posts to 1 when ordering by non-date fields.
			if ( 'date' !== $params['order_by_raw'] ) {
				$query_args['ignore_sticky_posts'] = 1;
			}

			// Handle explicit ignore_sticky_posts parameter.
			if ( 'on' === $params['ignore_stickys_post'] ) {
				$query_args['ignore_sticky_posts'] = 1;

				// Get all sticky posts.
				$sticky_posts = get_option( 'sticky_posts' );

				if ( ! empty( $sticky_posts ) ) {
					if ( isset( $query_args['post__not_in'] ) ) {
						$query_args['post__not_in'] = array_unique(
							array_merge( $query_args['post__not_in'], $sticky_posts )
						);
					} else {
						$query_args['post__not_in'] = $sticky_posts;
					}
				}
			}
		}

		// Handle post exclusions.
		$excluded_ids = [];

		// Always exclude the current post to prevent infinite recursion.
		$current_post_id = get_the_ID();

		// Handle exclude_current_post setting.
		if ( 'on' === $params['exclude_current_post'] && $current_post_id ) {
			$excluded_ids[] = $current_post_id;
		}

		// Apply post exclusions if any.
		if ( ! empty( $excluded_ids ) ) {
			$excluded_ids = array_unique( $excluded_ids );
			if ( isset( $query_args['post__not_in'] ) ) {
				$query_args['post__not_in'] = array_unique(
					array_merge( $query_args['post__not_in'], $excluded_ids )
				);
			} else {
				$query_args['post__not_in'] = $excluded_ids;
			}
		}

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $post_type,
		];

		return $result;
	}

	/**
	 * Build taxonomy query from include/exclude specific terms.
	 *
	 * @since ??
	 *
	 * @param array $include_terms Array of term inclusion data.
	 * @param array $exclude_terms Array of term exclusion data.
	 *
	 * @return array Formatted tax_query array for WP_Query.
	 */
	private static function _build_taxonomy_query( $include_terms, $exclude_terms ) {
		$include_taxonomies = [];
		$exclude_taxonomies = [];

		// Process inclusion terms.
		if ( ! empty( $include_terms ) && is_array( $include_terms ) ) {
			foreach ( $include_terms as $term_group ) {
				if ( ! isset( $term_group['categoryId'], $term_group['selectedOptions'] ) ) {
					continue;
				}

				$taxonomy = sanitize_key( $term_group['categoryId'] );
				$terms    = [];

				if ( is_array( $term_group['selectedOptions'] ) ) {
					$terms = array_map( 'intval', array_filter( array_column( $term_group['selectedOptions'], 'value' ) ) );
				}

				if ( ! empty( $terms ) ) {
					$include_taxonomies[ $taxonomy ] = $terms;
				}
			}
		}

		// Process exclusion terms.
		if ( ! empty( $exclude_terms ) && is_array( $exclude_terms ) ) {
			foreach ( $exclude_terms as $term_group ) {
				if ( ! isset( $term_group['categoryId'], $term_group['selectedOptions'] ) ) {
					continue;
				}

				$taxonomy = sanitize_key( $term_group['categoryId'] );
				$terms    = [];

				if ( is_array( $term_group['selectedOptions'] ) ) {
					$terms = array_map( 'intval', array_filter( array_column( $term_group['selectedOptions'], 'value' ) ) );
				}

				if ( ! empty( $terms ) ) {
					$exclude_taxonomies[ $taxonomy ] = $terms;
				}
			}
		}

		// Build taxonomy queries with proper include/exclude logic.
		$tax_query_parts = [];

		// Process inclusion queries (remove any terms that also appear in exclude for same taxonomy).
		foreach ( $include_taxonomies as $taxonomy => $include_terms_list ) {
			// If this taxonomy also has exclusions, remove conflicting terms from includes.
			if ( isset( $exclude_taxonomies[ $taxonomy ] ) ) {
				$final_include_terms = array_diff( $include_terms_list, $exclude_taxonomies[ $taxonomy ] );
			} else {
				$final_include_terms = $include_terms_list;
			}

			if ( ! empty( $final_include_terms ) ) {
				$tax_query_parts[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array_values( $final_include_terms ),
					'operator' => 'IN',
				];
			}
		}

		// Process exclusion queries (create separate NOT IN clauses for all exclude conditions).
		foreach ( $exclude_taxonomies as $taxonomy => $exclude_terms_list ) {
			if ( ! empty( $exclude_terms_list ) ) {
				$tax_query_parts[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $exclude_terms_list,
					'operator' => 'NOT IN',
				];
			}
		}

		// Apply relation logic.
		if ( ! empty( $tax_query_parts ) ) {
			if ( count( $tax_query_parts ) === 1 ) {
				// Single taxonomy condition.
				return $tax_query_parts;
			} else {
				// Multiple taxonomy conditions.
				$has_exclude_conditions = false;
				foreach ( $tax_query_parts as $part ) {
					if ( 'NOT IN' === $part['operator'] ) {
						$has_exclude_conditions = true;
						break;
					}
				}

				if ( $has_exclude_conditions ) {
					// When exclude conditions exist, use AND relation so excludes take priority.
					$final_tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query_parts );
				} else {
					// Only include conditions, use OR relation.
					$final_tax_query = array_merge( [ 'relation' => 'OR' ], $tax_query_parts );
				}

				return $final_tax_query;
			}
		}

		return [];
	}

	/**
	 * Build post inclusion/exclusion query parameters.
	 *
	 * @since ??
	 *
	 * @param array $include_posts Array of post inclusion data.
	 * @param array $exclude_posts Array of post exclusion data.
	 *
	 * @return array Query arguments for post inclusion/exclusion.
	 */
	private static function _build_post_inclusion_exclusion_query( $include_posts, $exclude_posts ) {

		$query_args = [];
		$post_in    = [];
		$post_out   = [];

		// Parse include posts.
		if ( ! empty( $include_posts ) && is_array( $include_posts ) ) {
			$post_in = array_map( 'intval', array_filter( array_column( $include_posts, 'value' ) ) );
		}

		// Parse exclude posts.
		if ( ! empty( $exclude_posts ) && is_array( $exclude_posts ) ) {
			$post_out = array_map( 'intval', array_filter( array_column( $exclude_posts, 'value' ) ) );
		}

		// Apply exclude-override-include logic.
		if ( ! empty( $post_in ) && ! empty( $post_out ) ) {
			// Remove any post IDs that appear in both include and exclude from the include list.
			$final_post_in = array_diff( $post_in, $post_out );

			// Apply include condition only if there are remaining posts to include.
			if ( ! empty( $final_post_in ) ) {
				$query_args['post__in'] = array_values( $final_post_in );
			}

			// Always apply exclude condition.
			$query_args['post__not_in'] = $post_out;
		} elseif ( ! empty( $post_in ) ) {
			// Only inclusion specified.
			$query_args['post__in'] = $post_in;
		} elseif ( ! empty( $post_out ) ) {
			// Only exclusion specified.
			$query_args['post__not_in'] = $post_out;
		}

		return $query_args;
	}

	/**
	 * Build meta query from meta query attributes (unified utility).
	 *
	 * This is a reusable utility function that can be used by both LoopUtils
	 * and QueryResultsController to build meta queries consistently.
	 *
	 * @since ??
	 *
	 * @param array $meta_query_items Array of meta query items.
	 *
	 * @return array Formatted meta_query array for WP_Query/WP_User_Query.
	 */
	public static function build_meta_query( array $meta_query_items ): array {
		$meta_query = [];

		// Meta query is optional - return empty array if not provided.
		if ( empty( $meta_query_items ) ) {
			return $meta_query;
		}

		// Process each meta query item.
		foreach ( $meta_query_items as $index => $meta_item ) {
			if ( ! is_array( $meta_item ) ) {
				continue;
			}

			// Support both field name formats: key/value and metaKey/metaValue.
			$meta_key   = $meta_item['key'] ?? $meta_item['metaKey'] ?? '';
			$meta_value = $meta_item['value'] ?? $meta_item['metaValue'] ?? '';

			// Validate required fields.
			if ( empty( $meta_key ) || empty( $meta_value ) ) {
				continue;
			}

			// Build meta query clause.
			$meta_clause = [
				'key'   => sanitize_text_field( $meta_key ),
				'value' => sanitize_text_field( $meta_value ),
			];

			// Add compare operator if provided.
			if ( isset( $meta_item['compare'] ) && ! empty( $meta_item['compare'] ) ) {
				$valid_compares = [
					'=',
					'!=',
					'>',
					'>=',
					'<',
					'<=',
					'LIKE',
					'NOT LIKE',
					'IN',
					'NOT IN',
					'BETWEEN',
					'NOT BETWEEN',
					'EXISTS',
					'NOT EXISTS',
					'REGEXP',
					'NOT REGEXP',
					'RLIKE',
				];

				// Validate compare operator first, then sanitize only if valid.
				// Don't use sanitize_text_field() as it converts < to &lt; which breaks validation.
				$compare = strtoupper( trim( $meta_item['compare'] ) );

				if ( in_array( $compare, $valid_compares, true ) ) {
					$meta_clause['compare'] = $compare;

					// Convert value to array for operators that require arrays.
					if ( in_array( $compare, [ 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ], true ) ) {
						// Split comma-separated values into array.
						$value = sanitize_text_field( $meta_value );
						if ( str_contains( $value, ',' ) ) {
							$meta_clause['value'] = array_map( 'trim', explode( ',', $value ) );
						} else {
							$meta_clause['value'] = [ $value ];
						}
					}
				}
			}

			// Add type if provided.
			if ( isset( $meta_item['type'] ) && ! empty( $meta_item['type'] ) ) {
				$valid_types = [
					'NUMERIC',
					'BINARY',
					'CHAR',
					'DATE',
					'DATETIME',
					'DECIMAL',
					'SIGNED',
					'TIME',
					'UNSIGNED',
				];

				$type = strtoupper( sanitize_text_field( $meta_item['type'] ) );

				if ( in_array( $type, $valid_types, true ) ) {
					// If DECIMAL type is selected, use DECIMAL(10,3) for proper decimal handling.
					if ( 'DECIMAL' === $type ) {
						$meta_clause['type'] = 'DECIMAL(10,3)';
					} else {
						$meta_clause['type'] = $type;
					}
				}
			}

			$meta_query[] = $meta_clause;
		}

		// Set relation to OR for multiple meta query clauses.
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'OR';
		}

		return $meta_query;
	}

	/**
	 * Execute a query with the generated args, or extract results from existing query object.
	 *
	 * @since ??
	 *
	 * @param array      $query_args     Query arguments.
	 * @param string     $query_type     Optional. The type of query to execute ('post_types', 'user_roles', etc.).
	 * @param mixed|null $existing_query Optional. Existing query object to extract results from, or null for fresh query.
	 *
	 * @return array Query results array with 'results', 'total_pages', and optionally 'query_object'.
	 */
	public static function execute_query( $query_args, $query_type = 'post_types', $existing_query = null ) {
		// Handle existing (cached) query objects - eliminates code duplication.
		if ( $existing_query ) {
			return self::_extract_results_from_existing_query( $existing_query, $query_type, $query_args );
		}

		// Handle fresh queries based on type.
		if ( self::_is_user_query( $query_type ) ) {
			return self::_execute_user_query( $query_args );
		}

		if ( self::_is_terms_query( $query_type ) ) {
			return self::_execute_terms_query( $query_args );
		}

		if ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return DynamicContentACFUtils::execute_repeater_query( $query_args );
		}

		// Default to post query.
		return self::_execute_post_query( $query_args );
	}

	/**
	 * Extract results from an existing (cached) query object.
	 *
	 * This function uses the same formatting logic as fresh queries, ensuring
	 * perfect consistency between cached and fresh query result processing.
	 *
	 * @since ??
	 *
	 * @param mixed  $query_object The existing query object (WP_Query, WP_User_Query, WP_Term_Query).
	 * @param string $query_type   The query type (for context/validation).
	 * @param array  $query_args   Original query arguments (needed for pagination calculations).
	 *
	 * @return array Query results array with 'results', 'query_object', and 'total_pages'.
	 */
	private static function _extract_results_from_existing_query( $query_object, $query_type, $query_args ) {
		// NOTE: Repeater queries are not true WordPress queries - they are ACF field data
		// processors that retrieve and process meta field values. Unlike WP_Query, WP_User_Query,
		// and WP_Term_Query, repeater queries don't create reusable query objects and instead
		// perform custom field data retrieval, processing, and manual pagination via array_slice().
		// This is why repeater queries are never cached in LoopQueryRegistry.

		// Use the same formatting logic as fresh queries to ensure perfect consistency.
		if ( $query_object instanceof WP_Query ) {
			return self::_format_post_query_results( $query_object, $query_args );
		} elseif ( $query_object instanceof WP_User_Query ) {
			return self::_format_user_query_results( $query_object, $query_args );
		} elseif ( $query_object instanceof WP_Term_Query ) {
			return self::_format_terms_query_results( $query_object, $query_args );
		}

		// Fallback for unexpected query object types.
		return [
			'results'      => [],
			'total_pages'  => 0,
			'query_object' => $query_object,
		];
	}

	/**
	 * Format results from a WP_Query object.
	 *
	 * This function extracts and formats results from a WP_Query object,
	 * ensuring consistent result structure for both fresh and cached queries.
	 *
	 * @since ??
	 *
	 * @param WP_Query $query      The WP_Query object.
	 * @param array    $query_args Original query arguments (for consistency).
	 *
	 * @return array The formatted query result array.
	 */
	private static function _format_post_query_results( $query, $query_args ) {
		if ( is_wp_error( $query ) || empty( $query->posts ) ) {
			return [
				'results'      => null,
				'total_pages'  => null,
				'query_object' => null,
			];
		}

		// Return array structure for backward compatibility, but include query object.
		return [
			'results'      => $query->posts,
			'total_pages'  => $query->max_num_pages,
			'query_object' => $query,
		];
	}

	/**
	 * Execute a WP_Query for posts.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_Query arguments.
	 *
	 * @return array The query result array.
	 */
	private static function _execute_post_query( $query_args ) {
		$query = new WP_Query( $query_args );
		return self::_format_post_query_results( $query, $query_args );
	}

	/**
	 * Format results from a WP_User_Query object.
	 *
	 * This function extracts and formats results from a WP_User_Query object,
	 * ensuring consistent result structure for both fresh and cached queries.
	 *
	 * @since ??
	 *
	 * @param WP_User_Query $query      The WP_User_Query object.
	 * @param array         $query_args Original query arguments (needed for pagination calculation).
	 *
	 * @return array The formatted query result array.
	 */
	private static function _format_user_query_results( $query, $query_args ) {
		$users = $query->get_results();

		if ( is_wp_error( $query ) || empty( $users ) ) {
			return [
				'results'      => null,
				'total_pages'  => null,
				'query_object' => null,
			];
		}

		// Calculate total pages manually since WP_User_Query doesn't have max_num_pages.
		$total_pages = 0;
		if ( isset( $query_args['number'] ) && $query_args['number'] > 0 ) {
			$total_users = $query->get_total();
			if ( $total_users > 0 ) {
				$total_pages = ceil( $total_users / $query_args['number'] );
			}
		}

		// Return array structure for backward compatibility, but include query object.
		return [
			'results'      => $users,
			'total_pages'  => $total_pages,
			'query_object' => $query,
		];
	}

	/**
	 * Execute a WP_User_Query for users.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_User_Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	private static function _execute_user_query( $query_args ) {
		$query = new WP_User_Query( $query_args );
		return self::_format_user_query_results( $query, $query_args );
	}

	/**
	 * Format results from a WP_Term_Query object.
	 *
	 * This function extracts and formats results from a WP_Term_Query object,
	 * ensuring consistent result structure for both fresh and cached queries.
	 *
	 * @since ??
	 *
	 * @param WP_Term_Query $query      The WP_Term_Query object.
	 * @param array         $query_args Original query arguments (needed for pagination calculation).
	 *
	 * @return array The formatted query result array.
	 */
	private static function _format_terms_query_results( $query, $query_args ) {
		$terms = $query->get_terms();

		if ( is_wp_error( $query ) || empty( $terms ) ) {
			return [
				'results'      => null,
				'total_pages'  => null,
				'query_object' => null,
			];
		}

		// Calculate total pages manually since WP_Term_Query doesn't have max_num_pages.
		$total_pages = 0;
		if ( isset( $query_args['number'] ) && $query_args['number'] > 0 ) {
			// Create count query args by removing pagination parameters.
			$count_args = $query_args;
			unset( $count_args['number'] );
			unset( $count_args['offset'] );
			$count_args['fields'] = 'count';

			$total_terms = wp_count_terms( $count_args );
			if ( ! is_wp_error( $total_terms ) && $total_terms > 0 ) {
				$total_pages = ceil( $total_terms / $query_args['number'] );
			}
		}

		// Return array structure for backward compatibility, but include query object.
		return [
			'results'      => $terms,
			'total_pages'  => $total_pages,
			'query_object' => $query,
		];
	}

	/**
	 * Execute a WP_Term_Query for terms.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_Term_Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	private static function _execute_terms_query( $query_args ) {
		$query = new WP_Term_Query( $query_args );
		return self::_format_terms_query_results( $query, $query_args );
	}

	/**
	 * Execute a Repeater query.
	 *
	 * @since ??
	 *
	 * @param array $query_args Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	private static function _execute_repeater_query( $query_args ) {
		return self::get_repeater_results( $query_args );
	}

	/**
	 * Render the standardized 'No Results Found' message for Loop Builder modules.
	 *
	 * This should be used by any module implementing loop queries to ensure consistent UI.
	 *
	 * @since ??
	 *
	 * @return string The rendered HTML for the no results message.
	 */
	public static function render_no_results_found_message() {
		// Use HTMLUtility for consistent markup and escaping.
		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [ 'class' => 'entry' ],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => HTMLUtility::render(
					[
						'tag'        => 'h2',
						'attributes' => [ 'class' => 'not-found-title' ],
						'children'   => __( 'No Results Found.', 'et_builder' ),
					]
				) . HTMLUtility::render(
					[
						'tag'      => 'p',
						'children' => __( 'The page you requested could not be found.', 'et_builder' ) . ' ' . __( 'Try refining your search, or use the navigation above to locate the post.', 'et_builder' ),
					]
				),
			]
		);
	}

	/**
	 * Generate dummy posts with lorem ipsum content.
	 *
	 * @since ??
	 *
	 * @param int $per_page Number of dummy posts to generate.
	 *
	 * @return array Array of dummy post objects.
	 */
	public static function generate_dummy_posts( int $per_page ): array {
		$posts        = [];
		$current_user = wp_get_current_user();
		$home_url     = get_option( 'home' );
		$current_date = current_time( 'F j, Y' );

		$lorem_title   = 'Lorem Ipsum Dolor Sit Amet';
		$lorem_excerpt = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

		for ( $i = 0; $i < $per_page; $i++ ) {
			$post_id = 100000 + $i + 1; // Generate unique sequential IDs starting from 100001.

			// Generate permalink using home URL and post ID.
			$permalink = trailingslashit( $home_url );

			$posts[] = [
				'id'         => $post_id,
				'title'      => $lorem_title,
				'excerpt'    => $lorem_excerpt,
				'permalink'  => $permalink,
				'date'       => $current_date,
				'categories' => '[{"name":"Technology","url":"#"},{"name":"Web Design","url":"#"}]',
				'tags'       => '[{"name":"WordPress","url":"#"},{"name":"Divi","url":"#"},{"name":"Tutorial","url":"#"}]',
				'terms'      => '[{"name":"Technology","url":"#"},{"name":"Web Design","url":"#"}]',
				'post_type'  => 'post',
				'thumbnail'  => ET_BUILDER_PLACEHOLDER_LANDSCAPE_IMAGE_DATA,
				'author'     => $current_user->display_name ? $current_user->display_name : 'admin',
			];
		}

		return $posts;
	}



















	/**
	 * Extract and sanitize sub-type values from loop configuration.
	 *
	 * @param array $loop The loop configuration array.
	 * @return string Comma-separated string of sanitized values, or empty string if none found.
	 */
	private static function _extract_sub_type_values( array $loop ): array {
		$sub_types = $loop['desktop']['value']['subTypes'] ?? null;

		if ( ! $sub_types || ! is_array( $sub_types ) ) {
			return [];
		}

		$values = array_filter(
			array_map(
				static fn( $item ) => isset( $item['value'] ) ? sanitize_key( $item['value'] ) : null,
				$sub_types
			)
		);

		return $values;
	}

	/**
	 * Extract and sanitize order by value from loop configuration.
	 *
	 * @param string $order The order value from loop configuration.
	 * @return string The sanitized order value.
	 */
	private static function _extract_order_by_value( string $order ): string {
		if ( empty( $order ) || 'descending' === $order ) {
			return 'DESC';
		}

		return 'ASC';
	}

	/**
	 * Get excluded taxonomies.
	 *
	 * @since ??
	 *
	 * @return array The excluded taxonomies.
	 */
	public static function get_excluded_taxonomies(): array {
		$excluded_taxonomies = [
			'nav_menu',
			'link_category',
			'post_format',
			'layout_category',
			'layout_pack',
			'layout_type',
			'scope',
			'module_width',
			'wp_theme',
		];

		return apply_filters( 'et_builder_loop_terms_excluded_taxonomies', $excluded_taxonomies );
	}

	/**
	 * Build WP_Term_Query arguments for terms queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	private static function _build_terms_query_args( $params ) {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		$taxonomy = $params['post_type']; // For terms queries, taxonomy is stored in post_type.

		// If no taxonomy is selected, use all taxonomies.
		if ( empty( $taxonomy ) ) {
			$excluded_taxonomies = self::get_excluded_taxonomies();

			$taxonomy = array_values( array_diff( get_taxonomies(), $excluded_taxonomies ) );
		}

		// Handle multiple taxonomies.
		if ( ! empty( $taxonomy ) ) {
			$taxonomy = array_map( 'sanitize_key', $taxonomy );
		}

		// Build WP_Term_Query arguments.
		$query_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => $params['order_by_raw'],
			'order'      => $params['order_raw'],
		];

		// Handle pagination.
		if ( $params['post_per_page'] > 0 ) {
			$query_args['number'] = $params['post_per_page'];
		}

		if ( $params['post_offset'] > 0 ) {
			$query_args['offset'] = $params['post_offset'];
		}

		// Handle search.
		if ( ! empty( $params['search'] ) ) {
			$query_args['search'] = $params['search'];
		}

		// Handle meta query parameters.
		// Note: $params['meta_query_attrs'] is sanitized within the build_meta_query method.
		$meta_query = self::build_meta_query( $params['meta_query_attrs'] );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $taxonomy, // For terms queries, this represents the taxonomy.
		];

		return $result;
	}



	/**
	 * Gets the actual content for a loop name.
	 *
	 * @since ??
	 *
	 * @param string $name       The loop name (e.g., "loop_post_title").
	 * @param string $query_type The type of query.
	 * @param mixed  $post       The loop object (WP_Post, WP_User, WP_Term, ACF Repeater, etc.).
	 * @param array  $settings   Optional. Field settings for customization. Default [].
	 *
	 * @return string The actual content.
	 */
	public static function get_loop_content_by_variable_name( string $name, string $query_type, $post = null, array $settings = [] ): string {
		// Ensure we have a valid post object.
		if ( null === $post ) {
			return '';
		}

		if ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return DynamicContentACFUtils::get_repeater_field_content( $name, $post );
		}

		// Delegate to specific handler methods based on query type.
		switch ( $query_type ) {
			case 'post_types':
				return self::_get_post_loop_content( $name, $post, $settings );

			case 'current_page':
				return self::_get_post_loop_content( $name, $post, $settings );

			case 'terms':
				return self::_get_term_loop_content( $name, $post );

			case 'user_roles':
				return self::_get_user_loop_content( $name, $post );

			default:
				return ''; // Return empty string for unknown query types.
		}
	}

	/**
	 * Get loop content for post type queries.
	 *
	 * @since ??
	 *
	 * @param string $name     The loop variable name.
	 * @param mixed  $post     The WP_Post object.
	 * @param array  $settings Optional. Field settings for customization. Default [].
	 *
	 * @return string The loop content.
	 */
	private static function _get_post_loop_content( string $name, $post, array $settings = [] ): string {
		// Validate that we have a proper WP_Post object.
		if ( ! is_object( $post ) || ! isset( $post->ID ) || ! is_a( $post, 'WP_Post' ) ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_post_title':
				return isset( $post->post_title ) ? esc_html( $post->post_title ) : '';

			case 'loop_post_excerpt':
				$value = '';

				if ( isset( $post->post_excerpt ) && ! empty( $post->post_excerpt ) ) {
					// Apply the_excerpt filter to manual excerpts to ensure proper formatting.
					$value = apply_filters( 'the_excerpt', $post->post_excerpt );
				} else {
					// Fall back to auto-generated excerpt if no manual excerpt exists.
					$value = wp_trim_excerpt( '', $post );
				}

				// Handle word limits if specified in settings.
				$words = isset( $settings['words'] ) ? absint( $settings['words'] ) : 0;
				if ( $words > 0 ) {
					$value = wp_trim_words( $value, $words );
				}

				// Handle read more text if specified in settings.
				$read_more_label = $settings['read_more_label'] ?? '';
				if ( ! empty( $read_more_label ) ) {
					$permalink = get_permalink( $post->ID );
					if ( $permalink ) {
						$value .= sprintf(
							' <a href="%1$s">%2$s</a>',
							esc_url( $permalink ),
							esc_html( $read_more_label )
						);
					}
				}

				return wp_kses_post( $value );

			case 'loop_post_date':
				$timestamp = get_post_timestamp( $post->ID );
				return esc_html( ModuleUtils::format_date( $timestamp, $settings ) );

			case 'loop_post_modified_date':
				$timestamp = get_post_timestamp( $post->ID, 'modified' );
				return esc_html( ModuleUtils::format_date( $timestamp, $settings ) );

			case 'loop_post_author':
				if ( ! isset( $post->post_author ) ) {
					return '';
				}

				$links_enabled = ( $settings['link'] ?? 'off' ) === 'on';
				$author_name   = get_the_author_meta( 'display_name', $post->post_author );

				if ( ! $author_name ) {
					return '';
				}

				if ( $links_enabled ) {
					$link_destination = $settings['link_destination'] ?? 'author_archive';

					if ( 'author_website' === $link_destination ) {
						$author_url = get_the_author_meta( 'user_url', $post->post_author );
						if ( $author_url ) {
							return '<a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a>';
						}
					} else {
						$author_archive_url = get_author_posts_url( $post->post_author );
						if ( $author_archive_url ) {
							return '<a href="' . esc_url( $author_archive_url ) . '">' . esc_html( $author_name ) . '</a>';
						}
					}
				}
				return esc_html( $author_name );

			case 'loop_post_author_bio':
				if ( ! isset( $post->post_author ) ) {
					return '';
				}
				$author_bio = get_the_author_meta( 'description', $post->post_author );
				return $author_bio ? wp_kses_post( $author_bio ) : '';

			case 'loop_post_link':
				$permalink = get_the_permalink( $post->ID );
				return $permalink ? esc_url( $permalink ) : '';

			case 'loop_post_comment_count':
				return (string) get_comments_number( $post->ID );

			case 'loop_post_thumbnail':
				$thumbnail = get_the_post_thumbnail( $post->ID, 'full' );
				return $thumbnail ? $thumbnail : '';

			case 'loop_post_featured_image':
				$thumbnail_size     = $settings['thumbnail_size'] ?? 'large';
				$featured_image_url = get_the_post_thumbnail_url( $post->ID, $thumbnail_size );
				if ( $featured_image_url ) {
					// Store attachment ID for later processing.
					$attachment_id = get_post_thumbnail_id( $post->ID );
					if ( $attachment_id ) {
						// Store this in a global variable for the filter to use.
						global $divi_loop_image_ids;
						if ( ! isset( $divi_loop_image_ids ) ) {
							$divi_loop_image_ids = [];
						}
						$divi_loop_image_ids[ esc_url( $featured_image_url ) ] = $attachment_id;
					}
				}
				return $featured_image_url ? esc_url( $featured_image_url ) : '';

			case 'loop_post_terms':
				// Get settings.
				$taxonomy_type = $settings['taxonomy_type'] ?? 'category';
				$separator     = $settings['separator'] ?? ', ';
				$links_enabled = ( $settings['links'] ?? 'off' ) === 'on';

				// Handle any taxonomy type.
				if ( 'category' === $taxonomy_type ) {
					// Use WordPress native category function.
					if ( $links_enabled ) {
						// get_the_category_list() automatically handles links and separators.
						$content = get_the_category_list( $separator, '', $post->ID );
					} else {
						// Get categories without links.
						$categories = get_the_category( $post->ID );
						if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
							$terms_list = [];
							foreach ( $categories as $category ) {
								$terms_list[] = esc_html( $category->name );
							}
							$content = implode( $separator, $terms_list );
						} else {
							$content = '';
						}
					}
				} elseif ( 'post_tag' === $taxonomy_type ) {
					// Use WordPress native tags function.
					if ( $links_enabled ) {
						// get_the_tag_list() automatically handles links and separators.
						$content = get_the_tag_list( '', $separator, '', $post->ID );
					} else {
						// Get tags without links.
						$tags = get_the_tags( $post->ID );
						if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
							$terms_list = [];
							foreach ( $tags as $tag ) {
								$terms_list[] = esc_html( $tag->name );
							}
							$content = implode( $separator, $terms_list );
						} else {
							$content = '';
						}
					}
				} else {
					// Handle any custom taxonomy.
					$taxonomy_object = get_taxonomy( $taxonomy_type );
					$terms           = ( $taxonomy_object && $taxonomy_object->public ) ? get_the_terms( $post->ID, $taxonomy_type ) : [];
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$terms_list = [];
						foreach ( $terms as $term ) {
							if ( $links_enabled ) {
								$term_link    = get_term_link( $term, $taxonomy_type );
								$terms_list[] = ! is_wp_error( $term_link )
									? '<a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>'
									: esc_html( $term->name );
							} else {
								$terms_list[] = esc_html( $term->name );
							}
						}
						$content = implode( $separator, $terms_list );
					} else {
						$content = '';
					}
				}

				return $content;

			default:
				if ( StringUtility::starts_with( $name, 'loop_product_' ) ) {
					return WooCommerceLoopHandler::get_loop_content( $name, $post, $settings );
				}

				return '';
		}
	}

	/**
	 * Get loop content for term queries.
	 *
	 * @since ??
	 *
	 * @param string $name The loop variable name.
	 * @param mixed  $term The WP_Term object.
	 *
	 * @return string The loop content.
	 */
	private static function _get_term_loop_content( string $name, $term ): string {
		// Validate that we have a proper WP_Term object.
		if ( ! is_object( $term ) || ! isset( $term->term_id ) || ! is_a( $term, 'WP_Term' ) ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_term_name':
				return isset( $term->name ) ? esc_html( $term->name ) : '';

			case 'loop_term_description':
				return isset( $term->description ) ? wp_kses_post( $term->description ) : '';

			case 'loop_term_permalink':
				$term_link = get_term_link( $term->term_id );
				return ! is_wp_error( $term_link ) ? esc_url( $term_link ) : '';

			case 'loop_term_count':
				return isset( $term->count ) ? (string) $term->count : '0';

			case 'loop_term_taxonomy':
				return isset( $term->taxonomy ) ? esc_html( $term->taxonomy ) : '';

			case 'loop_term_featured_image':
				$attachment_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				if ( $attachment_id > 0 ) {
					$img_url = wp_get_attachment_url( $attachment_id );
					return $img_url ? esc_url( $img_url ) : '';
				}
				return '';

			default:
				return ''; // Return empty string for unknown fields.
		}
	}

	/**
	 * Get loop content for user queries.
	 *
	 * @since ??
	 *
	 * @param string $name The loop variable name.
	 * @param mixed  $user The WP_User object.
	 *
	 * @return string The loop content.
	 */
	private static function _get_user_loop_content( string $name, $user ): string {
		// Validate that we have a proper WP_User object.
		if ( ! is_object( $user ) || ! isset( $user->ID ) || ! is_a( $user, 'WP_User' ) ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_user_name':
				return isset( $user->display_name ) ? esc_html( $user->display_name ) : '';

			case 'loop_user_username':
				return isset( $user->user_login ) ? esc_html( $user->user_login ) : '';

			case 'loop_user_email':
				return isset( $user->user_email ) ? esc_html( $user->user_email ) : '';

			case 'loop_user_avatar':
				$avatar_url = get_avatar_url( $user->ID );
				return $avatar_url ? esc_url( $avatar_url ) : '';

			case 'loop_user_description':
				return isset( $user->description ) ? wp_kses_post( $user->description ) : '';

			case 'loop_user_url':
				$author_url = get_author_posts_url( $user->ID );
				return $author_url ? esc_url( $author_url ) : '';

			default:
				return ''; // Return empty string for unknown fields.
		}
	}

	/**
	 * Recursively search for specific key values in array data.
	 *
	 * @since ??
	 *
	 * @param mixed  $data   The data to search through.
	 * @param string $target The key to search for.
	 *
	 * @return array Array of found values.
	 */
	private static function _find_key_values( $data, string $target ): array {
		$results = [];

		if ( ! is_array( $data ) ) {
			return $results;
		}

		array_walk_recursive(
			$data,
			function( $value, $key ) use ( $target, &$results ) {
				if ( $key === $target ) {
					$results[] = $value;
				}
			}
		);

		return $results;
	}

	/**
	 * Extract loop position from module attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attrs     Module attributes.
	 * @param string $view_mode View mode (desktop, tablet, phone).
	 *
	 * @return int|null Loop position (0-based) or null if not found.
	 */
	public static function extract_loop_position_from_attrs( array $attrs, string $view_mode = 'desktop' ): ?int {
		if ( empty( $attrs ) || ! is_array( $attrs ) ) {
			return null;
		}

		foreach ( $attrs as $attr_key => $attr_value ) {
			if ( ! is_array( $attr_value ) ) {
				continue;
			}

			$inner_content_value = $attr_value['innerContent'][ $view_mode ]['value'] ?? null;

			if ( $inner_content_value && is_string( $inner_content_value ) ) {
				$json_data = DynamicData::get_data_value( $inner_content_value );

				if ( $json_data && isset( $json_data['settings']['loop_position'] ) ) {
					$position_1_based = intval( $json_data['settings']['loop_position'] );
					$position_0_based = max( 0, $position_1_based - 1 );

					return $position_0_based;
				}
			}
		}

		$loop_position_results = self::_find_key_values( $attrs, 'loop_position' );

		foreach ( $loop_position_results as $position_value ) {
			if ( is_numeric( $position_value ) ) {
				$position_1_based = intval( $position_value );
				$position_0_based = max( 0, $position_1_based - 1 );

				return $position_0_based;
			}
		}

		return null;
	}

	/**
	 * Detect the number of columns per row from block attributes.
	 *
	 * @since ??
	 *
	 * @param array $block_attrs Block attributes.
	 *
	 * @return int Number of columns (minimum 1).
	 */
	public static function detect_columns_per_row( array $block_attrs ): int {
		if ( empty( $block_attrs ) ) {
			return 1;
		}

		$structure_paths = [
			'module.advanced.flexColumnStructure.desktop.value',
			'module.advanced.columnStructure.desktop.value',
		];

		foreach ( $structure_paths as $path ) {
			$column_structure = self::_get_nested_value( $block_attrs, $path );

			if ( $column_structure && is_string( $column_structure ) ) {
				if ( strpos( $column_structure, 'equal-columns_' ) === 0 ) {
					$parts = explode( '_', $column_structure );
					if ( isset( $parts[1] ) && is_numeric( $parts[1] ) ) {
						$columns = max( 1, intval( $parts[1] ) );
						return $columns;
					}
				} elseif ( strpos( $column_structure, ',' ) !== false ) {
					$columns = max( 1, count( explode( ',', $column_structure ) ) );
					return $columns;
				}
			}
		}

		return 1;
	}

	/**
	 * Get nested value from array using dot notation path.
	 *
	 * @since ??
	 *
	 * @param array  $array The array to search.
	 * @param string $path  Dot notation path (e.g., 'module.advanced.loop.enable').
	 *
	 * @return mixed The found value or null.
	 */
	private static function _get_nested_value( array $array, string $path ) {
		$keys  = explode( '.', $path );
		$value = $array;

		foreach ( $keys as $key ) {
			if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
				return null;
			}
			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Calculate the loop post index using the position formula.
	 *
	 * @since ??
	 *
	 * @param int $loop_position         Loop position (0-based).
	 * @param int $parent_loop_iteration Current parent loop iteration.
	 * @param int $columns_per_row       Number of columns per row.
	 *
	 * @return int Calculated post index.
	 */
	public static function calculate_loop_post_index( int $loop_position, int $parent_loop_iteration, int $columns_per_row = 1 ): int {
		$calculated_index = ( $parent_loop_iteration * $columns_per_row ) + $loop_position;

		return $calculated_index;
	}

	/**
	 * Checks if the content includes any loop-enabled blocks.
	 *
	 * Performs a fast string search to detect loop patterns before expensive parsing.
	 * This optimization prevents unnecessary processing when no loops are present.
	 *
	 * @param string $content The block serialized content to be checked.
	 *
	 * @return bool True if any loop-enabled blocks are found, false otherwise.
	 */
	public static function has_any_loop_enabled_blocks( $content ) {
		// Bail early if content is empty.
		if ( empty( $content ) ) {
			return false;
		}
		// Check if content contains loop-enabled blocks using regex pattern.
		// Regex101 link: https://regex101.com/r/y2G9oA/1.
		$has_loop_enabled = preg_match(
			'/"loop"\s*:\s*\{[^}]*"enable"\s*:\s*\{[^}]*"desktop"\s*:\s*\{[^}]*"value"\s*:\s*"on"/',
			$content
		) === 1;

		return $has_loop_enabled;
	}

	/**
	 * Get the current post ID based on the query type and query item.
	 *
	 * @since ??
	 *
	 * @param string $query_type The type of query.
	 * @param object $query_item The query item.
	 *
	 * @return int The current post ID.
	 */
	private static function _get_current_post_id( $query_type, $query_item ) {
		if ( self::_is_terms_query( $query_type ) ) {
			return $query_item->term_id;
		} elseif ( self::_is_user_query( $query_type ) ) {
			return $query_item->ID;
		} elseif ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return (int) $query_item['_post_id'];
		}

		return $query_item->ID;
	}

	/**
	 * Process dynamic content for modules, handling loop context automatically.
	 *
	 * This is a public utility that modules can use to process dynamic content.
	 * It automatically detects and handles loop contexts when available.
	 *
	 * @since ??
	 *
	 * @param string $content The content to process.
	 * @param array  $attrs   Optional. Module attributes for loop context detection.
	 *
	 * @return string The processed content.
	 */
	public static function process_dynamic_content( string $content, array $attrs = [] ): string {
		if ( empty( $content ) || false === strpos( $content, '$variable(' ) ) {
			return $content;
		}

		$loop_iteration = $attrs['__loop_iteration'] ?? null;
		$loop_context   = LoopContext::get();

		if ( null === $loop_iteration || ! $loop_context ) {
			// Fallback to standard processing.
			return DynamicData::get_processed_dynamic_data( $content, get_the_ID() );
		}

		$loop_object = $loop_context->get_result_for_position( 0 );
		$query_type  = $loop_context->get_query_type();

		return self::_process_loop_dynamic_content( $content, $query_type, $loop_object );
	}

	/**
	 * Process loop dynamic content with the correct context.
	 *
	 * This is a reusable utility that handles the common pattern of processing
	 * dynamic content within loop contexts by determining the correct post ID
	 * and applying the appropriate loop context parameters.
	 *
	 * @since ??
	 *
	 * @param string $content     The content to process.
	 * @param string $query_type  The type of query.
	 * @param mixed  $query_item  The query item (post, term, user, etc.).
	 *
	 * @return string The processed content.
	 */
	private static function _process_loop_dynamic_content( string $content, string $query_type, $query_item ): string {
		$current_post_id = self::_get_current_post_id( $query_type, $query_item );

		return DynamicData::get_processed_dynamic_data(
			$content,
			null,
			false,
			$current_post_id,
			$query_type,
			$query_item
		);
	}

	/**
	 * Checks if loop is enabled for the current module.
	 *
	 * This is a lightweight helper method that only checks the loop enabled flag
	 * without building the complete query args.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return bool True if loop is enabled, false otherwise.
	 */
	public static function is_loop_enabled( $attrs ): bool {
		$loop = isset( $attrs['module']['advanced']['loop'] )
			? $attrs['module']['advanced']['loop']
			: [];

		$loop_enabled = isset( $loop['desktop']['value']['enable'] )
			? $loop['desktop']['value']['enable']
			: '';

		return 'on' === $loop_enabled;
	}

	/**
	 * Handles loop rendering by iterating over query results.
	 *
	 * @since ??
	 *
	 * @param callable $callback                     The render callback to execute for each item.
	 * @param array    $attrs                        Module attributes.
	 * @param string   $content                      Module content.
	 * @param WP_Block $block                        Block instance.
	 * @param object   $elements                     ModuleElements instance.
	 * @param array    $default_printed_style_attrs  Default printed style attributes.
	 *
	 * @return string The rendered output for all loop iterations.
	 */
	public static function handle_loop_rendering( callable $callback, $attrs, $content, $block, $elements, $default_printed_style_attrs ): string {
		// Get the loop data.
		$loop_data = self::get_query_args_from_attrs( $attrs );

		// Handle current page query type which is applied on index page.
		if ( 'current_page' === $loop_data['query_type'] ) {
			// Check if we're on a single post/page.
			if ( is_singular() ) {
				// Get the current post/page.
				$current_post = get_queried_object();

				if ( $current_post && isset( $current_post->ID ) ) {
					$loop_data['post_type'] = $current_post->post_type;

					// Set query args to get only the current post.
					$loop_data['query_args'] = [
						'post_type'      => $current_post->post_type,
						'post_status'    => 'publish',
						'p'              => $current_post->ID,
						'posts_per_page' => 1,
					];
				}
			} else {
				// We're on an archive page - set up loop query.
				$loop_data['post_type'] = 'post';

				// Add context-specific parameters for current page.
				$query_args = &$loop_data['query_args'];

				// Check for taxonomy archives first (most comprehensive approach).
				$queried_object   = get_queried_object();
				$taxonomy_handled = false;

				// Handle any taxonomy archive (including custom taxonomies like WooCommerce).
				if ( $queried_object && isset( $queried_object->taxonomy, $queried_object->term_id ) ) {
					$taxonomy = $queried_object->taxonomy;

					// Handle core WordPress taxonomies with specific parameters for better performance.
					if ( 'category' === $taxonomy ) {
						$query_args['cat'] = $queried_object->term_id;
					} elseif ( 'post_tag' === $taxonomy ) {
						$query_args['tag_id'] = $queried_object->term_id;
					} else {
						// Handle all other taxonomies (custom taxonomies, WooCommerce, etc.).
						$query_args['tax_query'] = [
							[
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => $queried_object->term_id,
							],
						];

						// Try to determine post type from taxonomy.
						$tax_object = get_taxonomy( $taxonomy );
						if ( $tax_object && ! empty( $tax_object->object_type ) ) {
							$loop_data['post_type']  = $tax_object->object_type;
							$query_args['post_type'] = $tax_object->object_type;
						}
					}
					$taxonomy_handled = true;
				}

				// Handle post type archives (e.g., /projects/, /products/, etc.).
				if ( ! $taxonomy_handled && $queried_object && isset( $queried_object->name ) && is_a( $queried_object, 'WP_Post_Type' ) ) {
					$post_type_name = $queried_object->name;

					// Set the post type for the query.
					$loop_data['post_type']  = [ $post_type_name ];
					$query_args['post_type'] = [ $post_type_name ];

					$taxonomy_handled = true;
				}

				// Only check other conditions if taxonomy wasn't handled.
				if ( ! $taxonomy_handled ) {
					if ( is_author() ) {
						// Author archive.
						$query_args['author'] = get_queried_object_id();
					} elseif ( is_date() ) {
						// Date archive.
						if ( is_year() ) {
							$query_args['year'] = get_query_var( 'year' );
						} elseif ( is_month() ) {
							$query_args['year']     = get_query_var( 'year' );
							$query_args['monthnum'] = get_query_var( 'monthnum' );
						} elseif ( is_day() ) {
							$query_args['year']     = get_query_var( 'year' );
							$query_args['monthnum'] = get_query_var( 'monthnum' );
							$query_args['day']      = get_query_var( 'day' );
						}
					} elseif ( is_search() ) {
						// Search results.
						$search_query = get_search_query();
						if ( ! empty( $search_query ) ) {
							$query_args['s'] = $search_query;

							// Include all public and searchable post types in search results.
							// Using 'any' automatically excludes post types with 'exclude_from_search' => true.
							$query_args['post_type'] = [ 'any' ];
						}
					}
				}

				// Handle additional query vars that might be set.
				$context_vars = [ 'author_name', 'post_format' ];
				foreach ( $context_vars as $var ) {
					$value = get_query_var( $var );
					if ( ! empty( $value ) ) {
						$query_args[ $var ] = $value;
					}
				}
			}
		}

		// Extract loop ID from attributes for registry storage.
		$loop_id = isset( $attrs['module']['advanced']['loop']['desktop']['value']['loopId'] )
					? $attrs['module']['advanced']['loop']['desktop']['value']['loopId']
		: null;

		$existing_query = null;
		if ( ! empty( $loop_id ) ) {
			$existing_query = LoopQueryRegistry::get_query_if_matches(
				$loop_id,
				$loop_data['query_args'],
				$loop_data['query_type']
			);
		}

		// Use unified execute_query function for both fresh and cached queries.
		$query = self::execute_query( $loop_data['query_args'], $loop_data['query_type'], $existing_query );

		// Store new queries in registry (only if not already cached or mismatched).
		if ( null === $existing_query ) {
			$query_object   = isset( $query['query_object'] ) ? $query['query_object'] : null;
			$is_valid_query = $query_object instanceof WP_Query ||
				$query_object instanceof WP_User_Query ||
				$query_object instanceof WP_Term_Query;

			if ( ! empty( $loop_id ) && $is_valid_query ) {
				LoopQueryRegistry::store( $loop_id, $query_object, $loop_data['query_args'], $loop_data['query_type'] );
			}
		}

		// Get the query type.
		$query_type = $loop_data['query_type'];

		if ( empty( $query['results'] ) ) {
			// For child modules with no loop results, return empty string instead of rendering.
			if ( ModuleRegistration::is_child_module( $block->name ) ) {
				return '';
			}

			// Generate "No Results Found" content and render it within the module wrapper.
			$no_results_content = self::render_no_results_found_message();

			// Execute the original callback to get the module wrapper.
			$output = call_user_func(
				$callback,
				$attrs,
				$no_results_content,
				$block,
				$elements,
				$default_printed_style_attrs
			);

			LoopContext::clear();

			return $output;
		}

		// Handle Accordion Item special behavior.
		// If the current module is an Accordion Item, we need to set the open state based on iteration.
		$is_accordion_item = 'divi/accordion-item' === $block->name;

		$output          = '';
		$columns_per_row = self::detect_columns_per_row( $attrs );
		$iteration       = 0;

		foreach ( $query['results'] as $index => $result ) {
			LoopContext::set_position_context(
				$query['results'],
				$columns_per_row,
				$index,
				$query_type
			);

			$loop_attrs = $attrs;

			// For accordion items, ensure only the first looped item is open.
			if ( $is_accordion_item && isset( $attrs['module']['advanced']['open'] ) ) {
				$is_first_accordion_item = BlockParserStore::is_first( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

				// Ensure only one Accordion item is open at a time by opening only the first iteration of the first Accordion item.
				$loop_attrs['module']['advanced']['open']['desktop']['value'] = $is_first_accordion_item
				&& ( 0 === $iteration )
				? 'on'
				: 'off';
			}

			// Pass iteration to modules so they can handle loop-specific behaviors.
			// For example, tabs use this to ensure only the first tab in the first iteration is active,
			// similar to how accordion items use it to ensure only the first item in the first iteration is open.
			$loop_attrs['__loop_iteration'] = $iteration;

			// Call the original render callback.
			$module_output = call_user_func(
				$callback,
				$loop_attrs,
				$content,
				$block,
				$elements,
				$default_printed_style_attrs
			);

			// Process the rendered output to replace loop JSON strings with actual content.
			$processed_output = self::_process_loop_dynamic_content( $module_output, $query_type, $result );
			$output          .= $processed_output;

			$iteration++;
		}

		LoopContext::clear();

		// Process the final output to fix loop image attributes.
		$output = self::_fix_loop_image_attributes( $output );

		return $output;
	}


	/**
	 * Fix loop image attributes to include proper wp-image classes and responsive attributes.
	 *
	 * @since ??
	 *
	 * @param string $content The rendered content.
	 *
	 * @return string The content with fixed image attributes.
	 */
	public static function _fix_loop_image_attributes( string $content ): string {
		global $divi_loop_image_ids;

		if ( empty( $divi_loop_image_ids ) || ! is_array( $divi_loop_image_ids ) ) {
			return $content;
		}

		// Process each loop image URL.
		foreach ( $divi_loop_image_ids as $image_url => $attachment_id ) {
			// Find image tags with this URL.
			$pattern = '/(<img[^>]*src=["\']' . preg_quote( $image_url, '/' ) . '["\'][^>]*)(>)/i';

			$content = preg_replace_callback(
				$pattern,
				function( $matches ) use ( $attachment_id ) {
					$img_tag = $matches[1];
					$closing = $matches[2];

					// Check if wp-image class already exists.
					if ( strpos( $img_tag, 'wp-image-' ) !== false ) {
						return $matches[0]; // Already has wp-image class.
					}

					// Add wp-image class.
					if ( strpos( $img_tag, 'class=' ) !== false ) {
						// Class attribute exists, append to it.
						$img_tag = preg_replace( '/class=["\']([^"\']*)["\']/', 'class="$1 wp-image-' . $attachment_id . '"', $img_tag );
					} else {
						// No class attribute, add one.
						$img_tag .= ' class="wp-image-' . $attachment_id . '"';
					}

					return $img_tag . $closing;
				},
				$content
			);
		}

		// Clear the global variable after processing.
		$divi_loop_image_ids = [];

		return $content;
	}

}
