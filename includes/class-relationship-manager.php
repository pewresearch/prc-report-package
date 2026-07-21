<?php
/**
 * Relationship Manager
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

use WP_Error;

/**
 * Relationship Manager. Ensures that the children of a post are updated when the parent is updated and enforces the report-package structure.
 *
 * @package PRC\Platform\Report_Package
 */
class Relationship_Manager {
	/**
	 * Construct the "relationships" class.
	 *
	 * @param mixed $loader The loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the hooks.
	 *
	 * @param mixed $loader The loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'prc_platform_on_incremental_save', $this, 'enqueue_child_post_parent_reconcile', 10, 1 );
			$loader->add_action( 'prc_platform_async_on_incremental_save', $this, 'reconcile_child_post_parents', 10, 1 );
			$loader->add_action( 'prc_platform_on_update', $this, 'update_children', 10, 1 );
			$loader->add_action( 'prc_platform_on_publish', $this, 'update_children_on_publish', 10, 1 );
			$loader->add_action( 'prc_platform_on_update', $this, 'clear_chapters_cache_on_update', 20, 1 );
			$loader->add_action( 'prc_platform_on_publish', $this, 'clear_chapters_cache_on_update', 20, 1 );
			$loader->add_filter(
				'get_next_post_where',
				$this,
				'filter_next_post',
				10,
				5
			);
			$loader->add_filter(
				'get_previous_post_where',
				$this,
				'filter_prev_post',
				10,
				5
			);
		}
	}

	/**
	 * When a parent post is published, sync chapters then enqueue async publish side-effects
	 * for chapters that actually transitioned into publish (pipeline was suppressed during sync).
	 *
	 * @hook prc_platform_on_publish
	 *
	 * @param mixed $post The post object.
	 */
	public function update_children_on_publish( $post ) {
		$prior_statuses = array();
		if ( is_object( $post ) && isset( $post->ID ) ) {
			foreach ( $this->get_child_ids( (int) $post->ID ) as $child_id ) {
				$child_id = (int) $child_id;
				if ( $child_id > 0 ) {
					$prior_statuses[ $child_id ] = get_post_status( $child_id );
				}
			}
		}

		$result = $this->update_children( $post );
		if ( ! is_array( $result ) || empty( $result['success'] ) ) {
			return;
		}

		if ( ! function_exists( '\PRC\Platform\Post_Publish_Pipeline\enqueue_async_event' ) ) {
			return;
		}

		foreach ( array_unique( array_map( 'intval', $result['success'] ) ) as $child_id ) {
			if ( $child_id <= 0 ) {
				continue;
			}
			// Already-published chapters (including no-ops) must not fan out another publish.
			if ( isset( $prior_statuses[ $child_id ] ) && 'publish' === $prior_statuses[ $child_id ] ) {
				continue;
			}
			\PRC\Platform\Post_Publish_Pipeline\enqueue_async_event( $child_id, 'publish' );
		}
	}

	/**
	 * When this post changes the children should also change to match for specific items (namely taxonomy, post_date, post_status)
	 *
	 * @hook prc_platform_on_update
	 *
	 * @param mixed $post The post object.
	 */
	public function update_children( $post ) {
		if ( ! in_array( $post->post_type, PRC_REPORT_PACKAGE_ENABLED_POST_TYPES ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, '_prc_fork_parent', true ) ) {
			return;
		}
		$parent_post_id = wp_get_post_parent_id( $post->ID );
		// If this is a child post, return early.
		if ( 0 !== $parent_post_id ) {
			return;
		}
		$post_id  = $post->ID;
		$children = $this->get_child_ids( $post_id );
		// If there are no children, return early.
		if ( empty( $children ) ) {
			return;
		}

		$available_taxonomies       = get_object_taxonomies( $post->post_type );
		$parent_post_taxonomy_terms = array();
		foreach ( $available_taxonomies as $taxonomy ) {
			$parent_post_taxonomy_terms[ $taxonomy ] = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		}
		$parent_post_status = get_post_status( $post_id );
		$parent_post_date   = get_post_field( 'post_date', $post_id );

		$errors  = array();
		$success = array();

		// Suppress nested prc_platform_on_* fan-out while mirroring chapter fields.
		$skip_pipeline = static function () {
			return false;
		};
		add_filter( 'prc_platform_post_publish_pipeline_should_process', $skip_pipeline );

		try {
			foreach ( $children as $child_id ) {
				$child_id = (int) $child_id;
				if ( $child_id <= 0 ) {
					continue;
				}

				$child_status = get_post_status( $child_id );
				$child_date   = get_post_field( 'post_date', $child_id );
				$needs_post_update = (
					$child_status !== $parent_post_status ||
					$child_date !== $parent_post_date
				);

				$taxonomies_to_update = array();
				foreach ( $parent_post_taxonomy_terms as $taxonomy => $parent_terms ) {
					$child_terms = wp_get_post_terms( $child_id, $taxonomy, array( 'fields' => 'ids' ) );
					if ( is_wp_error( $child_terms ) ) {
						$errors[] = new WP_Error(
							'post-report-package::failed-to-read-child-post-terms',
							'Failed to read child post terms.',
							$child_terms
						);
						continue;
					}
					if ( ! $this->term_ids_match( $parent_terms, $child_terms ) ) {
						$taxonomies_to_update[ $taxonomy ] = $parent_terms;
					}
				}

				if ( ! $needs_post_update && empty( $taxonomies_to_update ) ) {
					$success[] = $child_id;
					continue;
				}

				$child_updated = $child_id;
				if ( $needs_post_update ) {
					$child_updated = wp_update_post(
						array(
							'ID'          => $child_id,
							'post_status' => $parent_post_status,
							'post_date'   => $parent_post_date,
						),
						true
					);
				}

				if ( is_wp_error( $child_updated ) ) {
					$errors[] = new WP_Error(
						'post-report-package::failed-to-update-child-post-state',
						'Failed to update child post state.',
						$child_updated
					);
					continue;
				}

				$success[] = $child_updated;

				foreach ( $taxonomies_to_update as $taxonomy => $terms ) {
					$terms_updated = wp_set_post_terms( $child_updated, $terms, $taxonomy );
					if ( is_wp_error( $terms_updated ) ) {
						$errors[] = new WP_Error(
							'post-report-package::failed-to-update-child-post-terms',
							'Failed to update child post terms.',
							$terms_updated
						);
					}
				}
			}
		} finally {
			remove_filter( 'prc_platform_post_publish_pipeline_should_process', $skip_pipeline );
		}

		return array(
			'success' => $success,
			'errors'  => $errors,
		);
	}

	/**
	 * Enqueue async reconciliation of chapter post_parent values after an incremental save.
	 *
	 * @hook prc_platform_on_incremental_save
	 *
	 * @param mixed $post The post object.
	 */
	public function enqueue_child_post_parent_reconcile( $post ) {
		if ( ! $this->is_report_package_root( $post ) ) {
			return;
		}

		// Always enqueue: sync incremental_save runs before REST persists meta.
		if ( ! function_exists( '\PRC\Platform\Post_Publish_Pipeline\enqueue_async_event' ) ) {
			return;
		}

		\PRC\Platform\Post_Publish_Pipeline\enqueue_async_event( (int) $post->ID, 'incremental_save' );
	}

	/**
	 * Assign post_parent for listed chapters and clear it for detached chapters.
	 *
	 * @hook prc_platform_async_on_incremental_save
	 *
	 * @param mixed $post The post object.
	 */
	public function reconcile_child_post_parents( $post ) {
		if ( ! $this->is_report_package_root( $post ) ) {
			return;
		}

		$parent_id        = (int) $post->ID;
		$post_type        = $post->post_type;
		$desired_ids      = $this->get_desired_chapter_ids( $parent_id );
		$desired_lookup   = array_fill_keys( $desired_ids, true );
		$current_child_ids = $this->get_current_child_post_ids( $parent_id, $post_type );

		$skip_pipeline = static function () {
			return false;
		};
		add_filter( 'prc_platform_post_publish_pipeline_should_process', $skip_pipeline );

		try {
			foreach ( $desired_ids as $child_id ) {
				if ( (int) wp_get_post_parent_id( $child_id ) === $parent_id ) {
					continue;
				}

				wp_update_post(
					array(
						'ID'          => $child_id,
						'post_parent' => $parent_id,
					),
					true
				);
			}

			foreach ( $current_child_ids as $child_id ) {
				if ( isset( $desired_lookup[ $child_id ] ) ) {
					continue;
				}

				if ( (int) wp_get_post_parent_id( $child_id ) !== $parent_id ) {
					continue;
				}

				wp_update_post(
					array(
						'ID'          => $child_id,
						'post_parent' => 0,
					),
					true
				);
			}
		} finally {
			remove_filter( 'prc_platform_post_publish_pipeline_should_process', $skip_pipeline );
		}
	}

	/**
	 * Whether the post is a report-package root (not a chapter or fork).
	 *
	 * @param mixed $post Post object.
	 * @return bool
	 */
	private function is_report_package_root( $post ) {
		if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_type ) ) {
			return false;
		}

		if ( ! in_array( $post->post_type, PRC_REPORT_PACKAGE_ENABLED_POST_TYPES, true ) ) {
			return false;
		}

		if ( 0 !== wp_get_post_parent_id( (int) $post->ID ) ) {
			return false;
		}

		if ( get_post_meta( $post->ID, '_prc_fork_parent', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Chapter IDs listed in multiSectionReport meta.
	 *
	 * @param int $parent_id Parent post ID.
	 * @return int[]
	 */
	private function get_desired_chapter_ids( $parent_id ) {
		$parent_id = (int) $parent_id;
		$ids       = array_map( 'intval', $this->get_child_ids( $parent_id ) );

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $parent_id ) {
					return $id > 0 && $id !== $parent_id;
				}
			)
		);
	}

	/**
	 * Post IDs that currently have post_parent set to the package root.
	 *
	 * @param int    $parent_id Parent post ID.
	 * @param string $post_type Post type.
	 * @return int[]
	 */
	private function get_current_child_post_ids( $parent_id, $post_type ) {
		$child_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_parent'    => (int) $parent_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return array_map( 'intval', (array) $child_ids );
	}

	/**
	 * Compare two term ID lists for equality (order-independent).
	 *
	 * @param array $a Term IDs.
	 * @param array $b Term IDs.
	 * @return bool
	 */
	private function term_ids_match( $a, $b ) {
		$normalize = static function ( $ids ) {
			$ids = array_map( 'intval', (array) $ids );
			$ids = array_values( array_unique( $ids ) );
			sort( $ids, SORT_NUMERIC );
			return $ids;
		};
		return $normalize( $a ) === $normalize( $b );
	}

	/**
	 * Get the children for a given post.
	 *
	 * @param int $post_id The post id.
	 * @return array The child ids.
	 */
	public function get_child_ids( $post_id ) {
		$child_posts = get_post_meta( $post_id, Rest_API::$package_chapters_meta_key, true );
		if ( empty( $child_posts ) ) {
			return array();
		}
		return array_map(
			function ( $child ) {
				return $child['postId'];
			},
			$child_posts
		);
	}

	/**
	 * Helper function for getting the "adjacent" post in a report-package.
	 *
	 * @param mixed  $where
	 * @param mixed  $post
	 * @param string $adjacent
	 * @return mixed
	 */
	private function filter_adjacent_post( $where, $post, $adjacent = 'next_post' ) {
		$is_chapter_of_post_package = is_chapter_part_of_report_package( $post->ID );
		if ( ! $is_chapter_of_post_package ) {
			return $where;
		}

		$pagination = get_pagination( $post->ID );
		$next_post  = $pagination[ $adjacent ];

		if ( ! $next_post ) {
			return $where;
		}
		global $wpdb;
		$where = $wpdb->prepare( 'WHERE p.ID = %s AND p.post_type = %s', $next_post['id'], $post->post_type );
		return $where;
	}

	/**
	 * Filter the next post where.
	 *
	 * @hook get_next_post_where
	 *
	 * @param mixed $where
	 * @param mixed $in_same_term
	 * @param mixed $excluded_terms
	 * @param mixed $taxonomy
	 * @param mixed $post
	 * @return mixed
	 */
	public function filter_next_post( $where, $in_same_term, $excluded_terms, $taxonomy, $post ) {
		return $this->filter_adjacent_post( $where, $post, 'next_post' );
	}

	/**
	 * Filter the previous post where.
	 *
	 * @hook get_previous_post_where
	 *
	 * @param mixed $where
	 * @param mixed $in_same_term
	 * @param mixed $excluded_terms
	 * @param mixed $taxonomy
	 * @param mixed $post
	 * @return mixed
	 */
	public function filter_prev_post( $where, $in_same_term, $excluded_terms, $taxonomy, $post ) {
		return $this->filter_adjacent_post( $where, $post, 'previous_post' );
	}

	/**
	 * Clear cached chapter rows when a report package or chapter changes.
	 *
	 * @hook prc_platform_on_update
	 * @hook prc_platform_on_publish
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function clear_chapters_cache_on_update( $post ) {
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, PRC_REPORT_PACKAGE_ENABLED_POST_TYPES, true ) ) {
			return;
		}
		clear_report_package_chapters_cache( get_package_id( (int) $post->ID ) );
	}
}
