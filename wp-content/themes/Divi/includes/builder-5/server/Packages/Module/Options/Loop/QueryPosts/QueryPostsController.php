<?php
/**
 * Loop QueryPosts: QueryPostsController.
 *
 * @package Builder\Packages\Module\Options\Loop\QueryPosts
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop\QueryPosts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Query;

/**
 * Query Posts REST Controller class.
 *
 * Simplified controller for getting posts data with minimal parameters.
 * Similar to WordPress REST API endpoints like wp-json/wp/v2/posts.
 *
 * @since ??
 */
class QueryPostsController extends RESTController {

	/**
	 * Default items per page.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Return posts based on the specified post_type(s).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$result = self::_get_posts_results( $params );

		return self::response_success( $result );
	}

	/**
	 * Get posts query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Posts query results.
	 */
	private static function _get_posts_results( array $params ): array {
		$post_type      = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : 'post';
		$posts_per_page = isset( $params['posts_per_page'] ) && '' !== $params['posts_per_page'] ?
			max( 1, (int) $params['posts_per_page'] ) : self::DEFAULT_PER_PAGE;
		$page           = isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1;
		$offset         = ( $page - 1 ) * $posts_per_page;
		$search_term    = isset( $params['search'] ) && ! empty( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		if ( 'current_page' === $post_type ) {
			// Generate dummy posts for current_page as this is a special case for TB.
			return self::_format_pagination_response(
				LoopUtils::generate_dummy_posts( $posts_per_page ),
				$posts_per_page * 3,
				$posts_per_page,
				1
			);
		}

		// Handle multiple post types.
		if ( is_string( $post_type ) && strpos( $post_type, ',' ) !== false ) {
			// Filter out non-public post types.
			$post_type = array_filter(
				array_map( 'sanitize_key', array_map( 'trim', explode( ',', $post_type ) ) ),
				function( $post_type ) {
					return et_builder_is_post_type_public( $post_type );
				}
			);
		} elseif ( 'any' !== $post_type && ! et_builder_is_post_type_public( $post_type ) ) {
			$post_type = [];
		}

		// Handle empty post_type parameter - set to 'any' to query all post types.
		if ( empty( $post_type ) ) {
			$post_type = 'any';
		}

		$query_args = [
			'post_type'      => $post_type,
			'posts_per_page' => $posts_per_page,
			'offset'         => $offset,
			'post_status'    => 'publish',
		];

		// Handle post status for attachments and 'any' post type.
		if ( 'any' === $post_type ) {
			// When querying all post types, include all relevant statuses.
			$query_args['post_status'] = [ 'publish', 'inherit', 'private' ];
		} elseif ( is_array( $post_type ) ) {
			if ( in_array( 'attachment', $post_type, true ) ) {
				$query_args['post_status'] = [ 'publish', 'inherit', 'private' ];
			}
		} elseif ( 'attachment' === $post_type ) {
			$query_args['post_status'] = [ 'inherit', 'private' ];
		}

		// Add custom title-only search if specified.
		if ( ! empty( $search_term ) ) {
			add_filter(
				'posts_where',
				function( $where ) use ( $search_term ) {
					global $wpdb;
					$search_term_escaped = $wpdb->esc_like( $search_term );
					$where              .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", '%' . $search_term_escaped . '%' );
					return $where;
				}
			);
		}

		$query = new WP_Query( $query_args );

		// Remove the filter after query to avoid affecting other queries.
		if ( ! empty( $search_term ) ) {
			remove_all_filters( 'posts_where' );
		}

		$posts = [];

		foreach ( $query->posts as $post ) {
			$post_data = [
				'id'            => $post->ID,
				'title'         => $post->post_title,
				'excerpt'       => get_the_excerpt( $post ),
				'permalink'     => get_permalink( $post->ID ),
				'date'          => get_the_date( '', $post ),
				'post_modified' => get_the_modified_date( '', $post ),
				'post_type'     => $post->post_type,
			];

			// Add author information if post type supports it.
			if ( post_type_supports( $post->post_type, 'author' ) ) {
				$post_data['author'] = get_the_author_meta( 'display_name', $post->post_author );
			}

			// Add thumbnail if available.
			$thumbnail_size = isset( $params['thumbnail_size'] ) ? sanitize_text_field( $params['thumbnail_size'] ) : 'large';
			$thumbnail      = get_the_post_thumbnail_url( $post->ID, $thumbnail_size );
			if ( $thumbnail ) {
				$post_data['thumbnail'] = $thumbnail;
			}

			// Add both categories and tags data for loop terms support.
			// Frontend will choose which to display based on taxonomy_type setting.

			// Get categories.
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				$category_objects = array();
				foreach ( $categories as $category ) {
					$category_objects[] = array(
						'name' => $category->name,
						'url'  => get_category_link( $category->term_id ),
					);
				}
				$post_data['categories'] = wp_json_encode( $category_objects );
			} else {
				$post_data['categories'] = wp_json_encode( array() );
			}

			// Get tags.
			$tags = get_the_tags( $post->ID );
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
				$tag_objects = array();
				foreach ( $tags as $tag ) {
					$tag_objects[] = array(
						'name' => $tag->name,
						'url'  => get_tag_link( $tag->term_id ),
					);
				}
				$post_data['tags'] = wp_json_encode( $tag_objects );
			} else {
				$post_data['tags'] = wp_json_encode( array() );
			}

			// Get all custom taxonomies for this post type.
			$post_taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
			if ( ! empty( $post_taxonomies ) ) {
				foreach ( $post_taxonomies as $taxonomy_slug => $taxonomy_object ) {
					// Skip core taxonomies (already handled above) and non-public taxonomies.
					if ( in_array( $taxonomy_slug, [ 'category', 'post_tag' ], true ) || ! $taxonomy_object->public ) {
						continue;
					}

					// Get terms for this taxonomy.
					$terms = get_the_terms( $post->ID, $taxonomy_slug );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$term_objects = array();
						foreach ( $terms as $term ) {
							$term_objects[] = array(
								'name' => $term->name,
								'url'  => get_term_link( $term->term_id, $taxonomy_slug ),
							);
						}
						$post_data[ $taxonomy_slug ] = wp_json_encode( $term_objects );
					} else {
						$post_data[ $taxonomy_slug ] = wp_json_encode( array() );
					}
				}
			}

			// Keep 'terms' for backward compatibility - default to categories.
			$post_data['terms'] = ! empty( $post_data['categories'] ) && '[]' !== $post_data['categories'] ? $post_data['categories'] : $post_data['tags'];

			// Add featured image for attachments.
			if ( 'attachment' === $post->post_type ) {
				$post_data['attachment_url'] = wp_get_attachment_url( $post->ID );
				$post_data['mime_type']      = get_post_mime_type( $post->ID );
			}

			$posts[] = $post_data;
		}

		return self::_format_pagination_response(
			$posts,
			$query->found_posts,
			$posts_per_page,
			$page
		);
	}



	/**
	 * Format pagination response.
	 *
	 * @since ??
	 *
	 * @param array $items      Result items.
	 * @param int   $total      Total number of items.
	 * @param int   $per_page   Items per page.
	 * @param int   $page       Current page.
	 *
	 * @return array Formatted response with pagination info.
	 */
	private static function _format_pagination_response( array $items, int $total, int $per_page, int $page ): array {
		$total_pages = $total > 0 ? ceil( $total / $per_page ) : 0;

		return [
			'items'       => $items,
			'total_items' => $total,
			'total_pages' => $total_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Index action arguments.
	 *
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'post_type'      => [
				'type'        => 'string',
				'description' => esc_html__( 'Post type to query. Can be a single post type or comma-separated list (e.g., "post", "post,product,media"). If empty, queries all post types.', 'et_builder' ),
				'default'     => 'post',
			],
			'search'         => [
				'type'        => 'string',
				'description' => esc_html__( 'Search term to filter posts by title only.', 'et_builder' ),
			],
			'posts_per_page' => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of posts per page.', 'et_builder' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'page'           => [
				'type'        => 'integer',
				'description' => esc_html__( 'Current page number.', 'et_builder' ),
				'default'     => 1,
				'minimum'     => 1,
			],
			'thumbnail_size' => [
				'type'        => 'string',
				'description' => esc_html__( 'WordPress image size to use for thumbnails (e.g., thumbnail, medium, large, full).', 'et_builder' ),
				'default'     => 'large',
			],
		];
	}

	/**
	 * Index action permission.
	 *
	 * Endpoint permission callback as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
