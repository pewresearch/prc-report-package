<?php
/**
 * Report Package Utils
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

/**
 * Report Package Utils
 *
 * @package PRC\Platform\Report_Package
 */

/**
 * Object cache group for assembled report package chapter lists.
 */
const REPORT_PACKAGE_CACHE_GROUP = 'prc_report_package';

/**
 * Cache TTL for assembled report package chapter lists.
 */
const REPORT_PACKAGE_CACHE_TTL = HOUR_IN_SECONDS;

/**
 * Cache key for a package parent's chapter rows.
 *
 * @param int $parent_id Package parent post ID.
 * @return string
 */
function get_report_package_chapters_cache_key( int $parent_id ): string {
	return 'report_package_chapters_' . $parent_id;
}

/**
 * Delete cached chapter rows for a report package parent.
 *
 * @param int $parent_id Package parent post ID.
 * @return void
 */
function clear_report_package_chapters_cache( int $parent_id ): void {
	wp_cache_delete( get_report_package_chapters_cache_key( $parent_id ), REPORT_PACKAGE_CACHE_GROUP );
}

/**
 * Given a post_id, return parent's ID if this post is a child.
 *
 * @param int $post_id
 * @return int
 */
function get_package_id( int $post_id ) {
	$parent_id = wp_get_post_parent_id( $post_id );
	if ( 0 !== $parent_id && is_int( $parent_id ) ) {
		$post_id = $parent_id;
	}
	return $post_id;
}

/**
 * Determine if this post is part of a report package.
 *
 * @param int $post_id The post ID to check if it is part of a report package.
 * @return bool
 */
function is_part_of_a_report_package( int $post_id ) {
	$parent_id = wp_get_post_parent_id( $post_id );
	if ( 0 !== $parent_id && is_int( $parent_id ) ) {
		return is_chapter_part_of_report_package( $parent_id );
	}
	return is_report_package( $post_id );
}

/**
 * Determine if this post is in a post-package.
 *
 * @param int $post_id The post ID to check if it is considered a chapter of a post-package.
 * @return bool
 */
function is_chapter_part_of_report_package( int $post_id ) {
	$post_id = get_package_id( $post_id );
	if ( ! empty( get_post_meta( $post_id, Rest_API::$package_chapters_meta_key, true ) ) ) {
		return true;
	}
	return false;
}

/**
 * Determine if this post is the parent, the package post.
 *
 * @param int $post_id The post ID to check if it is considered the main package post.
 * @return bool
 */
function is_report_package( int $post_id ) {
	$parent_id    = wp_get_post_parent_id( $post_id );
	$package_data = get_post_meta( $post_id, Rest_API::$package_chapters_meta_key, true );
	if ( 0 === $parent_id && ! empty( $package_data ) ) {
		return true;
	}
	return false;
}

/**
 * Get the report pacakge materials for a given post.
 *
 * @param mixed $post_id The post id.
 * @return array The report package materials.
 */
function get_package_materials( $post_id ) {
	$parent_id = wp_get_post_parent_id( $post_id );
	if ( 0 != $parent_id ) {
		$post_id = $parent_id;
	}

	$materials = get_post_meta( $post_id, Rest_API::$package_materials_meta_key, true );

	// Normalize materials array structure.
	// WordPress's preview filter can mangle revisions_enabled meta, returning a single
	// material object {key, type, ...} instead of an array [{key, type, ...}].
	// Detect and fix this malformed structure.
	if ( is_array( $materials ) && isset( $materials['key'] ) && ( isset( $materials['type'] ) || isset( $materials['url'] ) || isset( $materials['label'] ) ) ) {
		$materials = array( $materials );
	}

	// get_post_meta( ..., true ) returns '' when the key is absent. Casting '' to
	// an array yields array( '' ), which later fatals in the Report Materials
	// render loop (array_key_exists on a string). Normalize non-arrays to [].
	if ( ! is_array( $materials ) ) {
		$materials = array();
	}

	$materials = apply_filters(
		'prc_platform_post_report_package_materials',
		$materials,
		$post_id,
	);

	if ( ! is_array( $materials ) ) {
		$materials = array();
	}

	// If Print Engine beta is active, display the Print Engine beta activation link first.
	if ( true == get_query_var( 'printEngineBeta', false ) ) {
		$print_engine_material = array(
			array(
				'type'  => 'printEngineBeta',
				'label' => 'Print Engine (Beta)',
				'url'   => get_permalink( $post_id ) . '?pdf=true',
			),
		);
		$materials             = array_merge(
			$print_engine_material,
			$materials
		);
	}

	return $materials;
}

/**
 * Helper function to construct a chapter.
 *
 * @param int $chapter_id The chapter id.
 * @param int $requesting_id The requesting id.
 * @return array The chapter.
 */
function construct_chapter( $chapter_id, $requesting_id ) {
	return array_merge(
		construct_chapter_row( $chapter_id ),
		array(
			'is_active' => $chapter_id === $requesting_id,
		)
	);
}

/**
 * Build chapter row data without the active-state flag.
 *
 * @param int $chapter_id The chapter id.
 * @return array
 */
function construct_chapter_row( $chapter_id ) {
	return array(
		'id'    => $chapter_id,
		'title' => html_entity_decode( get_the_title( $chapter_id ) ),
		'slug'  => get_post_field( 'post_name', $chapter_id ),
		'link'  => get_permalink( $chapter_id ),
	);
}

/**
 * Get cached chapter rows for a package parent (without is_active).
 *
 * @param int $parent_id Package parent post ID.
 * @return array
 */
function get_cached_chapter_rows_for_parent( $parent_id ) {
	$parent_id = (int) $parent_id;
	if ( $parent_id <= 0 ) {
		return array();
	}

	$use_cache = ! is_user_logged_in() && ! is_preview();
	$cache_key = get_report_package_chapters_cache_key( $parent_id );

	if ( $use_cache ) {
		$cached = wp_cache_get( $cache_key, REPORT_PACKAGE_CACHE_GROUP );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
	}

	$chapters = get_post_meta( $parent_id, Rest_API::$package_chapters_meta_key, true );

	if ( is_array( $chapters ) && isset( $chapters['key'] ) && isset( $chapters['postId'] ) ) {
		$chapters = array( $chapters );
	}

	if ( empty( $chapters ) || ! is_array( $chapters ) ) {
		return array();
	}

	$formatted_chapters = array();
	foreach ( $chapters as $chapter ) {
		if ( ! is_array( $chapter ) || empty( $chapter['postId'] ) ) {
			continue;
		}
		$formatted_chapters[] = construct_chapter_row( (int) $chapter['postId'] );
	}

	if ( $use_cache ) {
		wp_cache_set( $cache_key, $formatted_chapters, REPORT_PACKAGE_CACHE_GROUP, REPORT_PACKAGE_CACHE_TTL );
	}

	return $formatted_chapters;
}

/**
 * Get the chapters for a given post package.
 * If a child id is given, the package will be referenced from the parent.
 *
 * @param mixed $parent_id The parent id.
 * @param mixed $post_id The post id.
 * @return array The chapters.
 */
function get_chapters( $parent_id, $post_id ) {
	$parent_id = (int) $parent_id;
	$post_id   = (int) $post_id;
	$rows      = get_cached_chapter_rows_for_parent( $parent_id );

	if ( empty( $rows ) ) {
		return array();
	}

	return array_map(
		static function ( $row ) use ( $post_id ) {
			return array_merge(
				$row,
				array(
					'is_active' => $row['id'] === $post_id,
				)
			);
		},
		$rows
	);
}

/**
 * Gets the full list of chapters for a given post-package.
 * This includes chapters as well as the parent post.
 *
 * @param mixed $post_id The post ID to get the chapters for.
 * @return array
 */
function get_package_chapters( $post_id ) {
	$parent_id = get_package_id( $post_id );
	$chapters  = get_chapters( $parent_id, $post_id );

	if ( empty( $chapters ) ) {
		return array();
	}

	$package_root = construct_chapter( $parent_id, $post_id );

	$constructed_toc = array_merge(
		array(
			$package_root,
		),
		$chapters
	);

	return $constructed_toc;
}

/**
 * Get the post-package pagination for a given post.
 * The pagination walker/class is in platform core.
 *
 * @param mixed $post_id The post id.
 * @return array
 */
function get_pagination( $post_id ) {
	$items      = get_package_chapters( $post_id );
	$pagination = new \PRC\BlockUtils\Pagination( $items );
	$to_return  = array(
		'current_post'     => $pagination->get_current_item(),
		'next_post'        => $pagination->get_next_item(),
		'previous_post'    => $pagination->get_previous_item(),
		'pagination_items' => $pagination->get_items(),
	);
	return $to_return;
}
