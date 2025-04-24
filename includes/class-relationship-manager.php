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
			$loader->add_action( 'prc_platform_on_incremental_save', $this, 'set_child_posts', 10, 1 );
			$loader->add_action( 'prc_platform_on_update', $this, 'update_children', 10, 1 );
			$loader->add_action( 'prc_platform_on_publish', $this, 'update_children', 10, 1 );
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

		foreach ( $children as $child_id ) {
			$new_updates = array(
				'ID'          => $child_id,
				'post_status' => $parent_post_status,
				'post_date'   => $parent_post_date,
			);

			// Update the child post to match the parent post.
			$child_updated = wp_update_post( $new_updates, true );

			$terms_updated = false;
			if ( ! is_wp_error( $child_updated ) ) {
				foreach ( $parent_post_taxonomy_terms  as $taxonomy => $terms ) {
					$terms_updated = wp_set_post_terms( $child_updated, $terms, $taxonomy );
				}
			}

			if ( is_wp_error( $child_updated ) ) {
				$errors[] = new WP_Error( 'post-report-package::failed-to-update-child-post-state', 'Failed to update child post state.', $child_updated );
			} else {
				$success[] = $child_updated;
			}
			if ( is_wp_error( $terms_updated ) ) {
				$errors[] = new WP_Error( 'post-report-package::failed-to-update-child-post-terms', 'Failed to update child post terms.', $terms_updated );
			}
		}

		$to_return = array(
			'success' => $success,
			'errors'  => $errors,
		);

		return $to_return;
	}

	/**
	 * Assigns a child post to a parent post.
	 *
	 * @param int $child_post_id The child post id.
	 * @param int $parent_post_id The parent post id.
	 *
	 * @return int|WP_Error
	 */
	public function assign_child_to_parent( $child_post_id, $parent_post_id ) {
		$updated = wp_update_post(
			array(
				'ID'          => $child_post_id,
				'post_parent' => $parent_post_id,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return new WP_Error( 'post-report-package::failed-to-assign-child', 'Failed to assign child post to parent.', $updated );
		}
		return $updated;
	}

	/**
	 * On incremental saves assigns the child posts to the parent.
	 *
	 * @hook prc_platform_on_incremental_save
	 *
	 * @param mixed $post The post object.
	 */
	public function set_child_posts( $post ) {
		if ( ! in_array( $post->post_type, PRC_REPORT_PACKAGE_ENABLED_POST_TYPES ) && 0 !== wp_get_post_parent_id( $post->ID ) ) {
			return;
		}
		$errors   = array();
		$success  = array();
		$chapters = get_post_meta( $post->ID, Rest_API::$package_chapters_meta_key, true );
		if ( empty( $chapters ) ) {
			return;
		}
		foreach ( $chapters as $chapter ) {
			$assigned = $this->assign_child_to_parent( $chapter['postId'], $post->ID );
			if ( is_wp_error( $assigned ) ) {
				$errors[] = $assigned;
			} else {
				$success[] = $assigned;
			}
		}
		// we should run through successes and do the updates then...
		return array(
			'success' => $success,
			'errors'  => $errors,
		);
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
}
