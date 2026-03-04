<?php
/**
 * Distributor Integration
 *
 * Provides Distributor support for report packages, enabling automatic cascade
 * distribution of chapter posts and attachments when a report package is pushed.
 *
 * @package PRC\Platform\Report_Package
 */

declare(strict_types=1);

namespace PRC\Platform\Report_Package;

use Distributor\DistributorPost;
use WP_Error;
use WP_Post;

/**
 * Distributor Integration class.
 *
 * Handles:
 * - ID remapping for multiSectionReport, reportMaterials, and package_parts meta
 * - Automatic distribution of related chapters and attachments via Distributor's registered data handlers
 * - Restoration of post_parent relationships after distribution
 *
 * @package PRC\Platform\Report_Package
 */
class Distributor {
	/**
	 * Construct the Distributor integration class.
	 *
	 * @param mixed $loader The loader instance.
	 */
	public function __construct( $loader = null ) {
		$this->init( $loader );
	}

	/**
	 * Initialize hooks.
	 *
	 * @param mixed $loader The loader instance.
	 */
	public function init( $loader = null ): void {
		if ( null !== $loader ) {
			// Register data handlers on init (after Distributor loads).
			$loader->add_action( 'init', $this, 'register_distributor_data', 20 );

			// Restore post_parent relationships after distribution.
			$loader->add_action( 'dt_process_distributor_attributes', $this, 'restore_post_parent_relationships', 10, 3 );
		}
	}

	/**
	 * Check if the Distributor plugin is active.
	 *
	 * @return bool True if Distributor is active.
	 */
	public static function is_distributor_active(): bool {
		return function_exists( 'distributor_register_data' );
	}

	/**
	 * Register custom data handlers with Distributor.
	 *
	 * These handlers use Distributor's built-in 'post' and 'media' type callbacks
	 * to automatically distribute related content and remap IDs.
	 *
	 * @hook init
	 */
	public function register_distributor_data(): void {
		if ( ! self::is_distributor_active() ) {
			return;
		}

		// Register handler for chapters (multiSectionReport meta).
		// Uses custom callbacks that wrap Distributor's built-in post handling.
		distributor_register_data(
			'prc_report_package_chapters',
			array(
				'location'           => 'post_meta',
				'attributes'         => array( 'meta_key' => Rest_API::$package_chapters_meta_key ),
				'pre_distribute_cb'  => array( self::class, 'chapters_pre_distribute' ),
				'post_distribute_cb' => array( self::class, 'chapters_post_distribute' ),
			)
		);

		// Register handler for materials (reportMaterials meta).
		// Uses custom callbacks that wrap Distributor's built-in media handling.
		distributor_register_data(
			'prc_report_package_materials',
			array(
				'location'           => 'post_meta',
				'attributes'         => array( 'meta_key' => Rest_API::$package_materials_meta_key ),
				'pre_distribute_cb'  => array( self::class, 'materials_pre_distribute' ),
				'post_distribute_cb' => array( self::class, 'materials_post_distribute' ),
			)
		);

		// Register handler for parts (package_parts meta).
		distributor_register_data(
			'prc_report_package_parts',
			array(
				'location'           => 'post_meta',
				'attributes'         => array( 'meta_key' => Rest_API::$package_parts_meta_key ),
				'pre_distribute_cb'  => array( self::class, 'parts_pre_distribute' ),
				'post_distribute_cb' => array( self::class, 'parts_post_distribute' ),
			)
		);
	}

	/**
	 * Pre-distribute callback for chapters meta.
	 *
	 * Collects chapter post data for distribution using Distributor's built-in
	 * post pre-distribute callback. Also collects chart and media data from
	 * chapter content for nested distribution.
	 *
	 * @param mixed $meta_value     The meta value (multiSectionReport array).
	 * @param int   $source_post_id The source post ID.
	 * @return array Extra data for each chapter.
	 */
	public static function chapters_pre_distribute( $meta_value, int $source_post_id ): array {
		if ( empty( $meta_value ) || ! is_array( $meta_value ) ) {
			return array();
		}

		$extra_data = array();

		foreach ( $meta_value as $index => $chapter ) {
			if ( empty( $chapter['postId'] ) ) {
				$extra_data[ $index ] = array();
				continue;
			}

			$chapter_id = (int) $chapter['postId'];

			// Use Distributor's built-in post pre-distribute callback.
			if ( function_exists( 'distributor_post_pre_distribute_callback' ) ) {
				$post_data = distributor_post_pre_distribute_callback( $chapter_id, $source_post_id );
			} else {
				$post      = get_post( $chapter_id );
				$post_data = array(
					'source_post_id' => $chapter_id,
					'post_type'      => $post ? $post->post_type : 'post',
					'post_title'     => $post ? $post->post_title : '',
				);
			}

			// Collect chart and media data from chapter content.
			$chapter_post = get_post( $chapter_id );
			if ( $chapter_post && ! empty( $chapter_post->post_content ) ) {
				$post_data['_chart_data'] = self::collect_chart_data_from_content( $chapter_post->post_content, $chapter_id );
			}

			$extra_data[ $index ] = $post_data;
		}

		return $extra_data;
	}

	/**
	 * Collect chart and media data from post content.
	 *
	 * Uses WP_Block_Processor for efficient streaming traversal of blocks,
	 * collecting chart post IDs and attachment IDs for distribution.
	 * Falls back to parse_blocks() for older WordPress versions.
	 *
	 * @param string $content The post content.
	 * @param int    $post_id The post ID.
	 * @return array Chart data with pre-distribute info.
	 */
	private static function collect_chart_data_from_content( string $content, int $post_id ): array {
		$chart_data = array(
			'synced_charts'     => array(),
			'chart_attachments' => array(),
		);

		// Use WP_Block_Processor if available (WordPress 6.9+).
		// This provides efficient streaming traversal without recursive arrays.
		if ( class_exists( 'WP_Block_Processor' ) ) {
			$processor = new \WP_Block_Processor( $content );

			while ( $processor->next_block() ) {
				// Handle synced-chart blocks (ref = chart post ID).
				if ( $processor->is_block_type( 'prc-chart-builder/synced-chart' ) ) {
					$attrs = $processor->allocate_and_return_parsed_attributes();
					$ref   = $attrs['ref'] ?? null;

					if ( ! empty( $ref ) && is_numeric( $ref ) ) {
						$ref = (int) $ref;
						// Collect post data for the referenced chart.
						if ( function_exists( 'distributor_post_pre_distribute_callback' ) ) {
							$chart_post_data = distributor_post_pre_distribute_callback( $ref, $post_id );
							if ( ! empty( $chart_post_data ) ) {
								$chart_data['synced_charts'][ $ref ] = $chart_post_data;
							}
						}
					}
				}

				// Handle chart blocks (io.staticImageId and io.pngId).
				if ( $processor->is_block_type( 'prc-chart-builder/chart' ) ) {
					$attrs = $processor->allocate_and_return_parsed_attributes();
					$io    = $attrs['io'] ?? array();

					// Static image ID.
					if ( ! empty( $io['staticImageId'] ) && is_numeric( $io['staticImageId'] ) ) {
						$static_id = (int) $io['staticImageId'];
						if ( function_exists( 'distributor_media_pre_distribute_callback' ) ) {
							$media_data = distributor_media_pre_distribute_callback( $static_id, $post_id );
							if ( ! empty( $media_data ) ) {
								$chart_data['chart_attachments'][ $static_id ] = $media_data;
							}
						}
					}

					// PNG ID.
					if ( ! empty( $io['pngId'] ) && is_numeric( $io['pngId'] ) ) {
						$png_id = (int) $io['pngId'];
						if ( function_exists( 'distributor_media_pre_distribute_callback' ) ) {
							$media_data = distributor_media_pre_distribute_callback( $png_id, $post_id );
							if ( ! empty( $media_data ) ) {
								$chart_data['chart_attachments'][ $png_id ] = $media_data;
							}
						}
					}
				}
			}
		}

		return $chart_data;
	}

	/**
	 * Legacy: Recursively extract chart data from blocks.
	 *
	 * Used as fallback for WordPress versions before 6.9 that don't have
	 * WP_Block_Processor.
	 *
	 * @param array $blocks     The blocks to process.
	 * @param int   $post_id    The post ID.
	 * @param array $chart_data Reference to chart data array.
	 */
	private static function extract_chart_data_from_blocks_legacy( array $blocks, int $post_id, array &$chart_data ): void {
		foreach ( $blocks as $block ) {
			// Handle synced-chart blocks (ref = chart post ID).
			if ( 'prc-chart-builder/synced-chart' === $block['blockName'] ) {
				$ref = $block['attrs']['ref'] ?? null;
				if ( ! empty( $ref ) && is_numeric( $ref ) ) {
					$ref = (int) $ref;
					// Collect post data for the referenced chart.
					if ( function_exists( 'distributor_post_pre_distribute_callback' ) ) {
						$chart_post_data = distributor_post_pre_distribute_callback( $ref, $post_id );
						if ( ! empty( $chart_post_data ) ) {
							$chart_data['synced_charts'][ $ref ] = $chart_post_data;
						}
					}
				}
			}

			// Handle chart blocks (io.staticImageId and io.pngId).
			if ( 'prc-chart-builder/chart' === $block['blockName'] ) {
				$io = $block['attrs']['io'] ?? array();

				// Static image ID.
				if ( ! empty( $io['staticImageId'] ) && is_numeric( $io['staticImageId'] ) ) {
					$static_id = (int) $io['staticImageId'];
					if ( function_exists( 'distributor_media_pre_distribute_callback' ) ) {
						$media_data = distributor_media_pre_distribute_callback( $static_id, $post_id );
						if ( ! empty( $media_data ) ) {
							$chart_data['chart_attachments'][ $static_id ] = $media_data;
						}
					}
				}

				// PNG ID.
				if ( ! empty( $io['pngId'] ) && is_numeric( $io['pngId'] ) ) {
					$png_id = (int) $io['pngId'];
					if ( function_exists( 'distributor_media_pre_distribute_callback' ) ) {
						$media_data = distributor_media_pre_distribute_callback( $png_id, $post_id );
						if ( ! empty( $media_data ) ) {
							$chart_data['chart_attachments'][ $png_id ] = $media_data;
						}
					}
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::extract_chart_data_from_blocks_legacy( $block['innerBlocks'], $post_id, $chart_data );
			}
		}
	}

	/**
	 * Post-distribute callback for chapters meta.
	 *
	 * Remaps chapter post IDs to their distributed equivalents using Distributor's
	 * built-in post handling which will pull/find the posts on the target site.
	 * Also updates chart and media IDs in chapter content.
	 *
	 * @param array $extra_data      The extra data from pre-distribute.
	 * @param mixed $original_meta   The original meta value.
	 * @param array $post_data       The post data being distributed.
	 * @param array $connection_data The connection data.
	 * @return mixed The updated meta value with remapped IDs.
	 */
	public static function chapters_post_distribute( array $extra_data, $original_meta, array $post_data, array $connection_data = array() ) {
		if ( empty( $original_meta ) || ! is_array( $original_meta ) ) {
			return $original_meta;
		}

		$updated_meta   = array();
		$new_id_mapping = array();

		foreach ( $original_meta as $index => $chapter ) {
			$new_chapter = $chapter;

			if ( ! empty( $chapter['postId'] ) ) {
				$source_id         = (int) $chapter['postId'];
				$chapter_extra     = $extra_data[ $index ] ?? array();
				$chart_data        = $chapter_extra['_chart_data'] ?? array();
				$chapter_extra_raw = $chapter_extra;
				unset( $chapter_extra_raw['_chart_data'] );

				// Use Distributor's built-in post post-distribute callback to pull/find the post.
				if ( ! empty( $chapter_extra_raw ) && function_exists( 'distributor_post_post_distribute_callback' ) ) {
					$new_id = distributor_post_post_distribute_callback(
						$chapter_extra_raw,
						$source_id,
						$post_data,
						$connection_data
					);

					if ( $new_id && ! is_wp_error( $new_id ) && is_numeric( $new_id ) ) {
						$new_chapter['postId']        = (int) $new_id;
						$new_id_mapping[ $source_id ] = (int) $new_id;

						// Update chart and media IDs in the chapter's content.
						if ( ! empty( $chart_data ) ) {
							self::update_chapter_chart_content( (int) $new_id, $chart_data, $post_data, $connection_data );
						}
					}
				}
			}

			$updated_meta[] = $new_chapter;
		}

		// Store the ID mapping in a transient for post_parent restoration.
		if ( ! empty( $new_id_mapping ) && ! empty( $post_data['distributor_original_post_id'] ) ) {
			$source_parent_id = (int) $post_data['distributor_original_post_id'];
			set_transient(
				'prc_report_package_chapter_map_' . $source_parent_id,
				$new_id_mapping,
				HOUR_IN_SECONDS
			);
		}

		return $updated_meta;
	}

	/**
	 * Update chart and media IDs in a chapter's content.
	 *
	 * After a chapter is distributed, this updates its block content with
	 * remapped chart post IDs and attachment IDs.
	 *
	 * @param int   $chapter_id      The distributed chapter post ID.
	 * @param array $chart_data      The chart data from pre-distribute.
	 * @param array $post_data       The post data being distributed.
	 * @param array $connection_data The connection data.
	 */
	private static function update_chapter_chart_content( int $chapter_id, array $chart_data, array $post_data, array $connection_data ): void {
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || empty( $chapter->post_content ) ) {
			return;
		}

		$content = $chapter->post_content;
		$updated = false;

		// Build ID mapping for synced charts (chart post IDs).
		$chart_id_map = array();
		if ( ! empty( $chart_data['synced_charts'] ) ) {
			foreach ( $chart_data['synced_charts'] as $source_chart_id => $chart_extra ) {
				if ( function_exists( 'distributor_post_post_distribute_callback' ) ) {
					$new_chart_id = distributor_post_post_distribute_callback(
						$chart_extra,
						$source_chart_id,
						$post_data,
						$connection_data
					);
					if ( $new_chart_id && ! is_wp_error( $new_chart_id ) && is_numeric( $new_chart_id ) ) {
						$chart_id_map[ $source_chart_id ] = (int) $new_chart_id;
					}
				}
			}
		}

		// Build ID mapping for chart attachments (media IDs).
		$media_id_map = array();
		if ( ! empty( $chart_data['chart_attachments'] ) ) {
			foreach ( $chart_data['chart_attachments'] as $source_media_id => $media_extra ) {
				if ( function_exists( 'distributor_media_post_distribute_callback' ) ) {
					$new_media_id = distributor_media_post_distribute_callback(
						$media_extra,
						$source_media_id,
						$post_data
					);
					if ( $new_media_id && ! is_wp_error( $new_media_id ) && is_numeric( $new_media_id ) ) {
						$media_id_map[ $source_media_id ] = (int) $new_media_id;
					}
				}
			}
		}

		// Update block content with remapped IDs.
		if ( ! empty( $chart_id_map ) || ! empty( $media_id_map ) ) {
			$blocks         = parse_blocks( $content );
			$updated_blocks = self::remap_chart_ids_in_blocks( $blocks, $chart_id_map, $media_id_map, $updated );

			if ( $updated ) {
				$new_content = serialize_blocks( $updated_blocks );
				wp_update_post(
					array(
						'ID'           => $chapter_id,
						'post_content' => $new_content,
					)
				);
			}
		}
	}

	/**
	 * Recursively remap chart and media IDs in blocks.
	 *
	 * @param array $blocks        The blocks to process.
	 * @param array $chart_id_map  Mapping of source chart IDs to new IDs.
	 * @param array $media_id_map  Mapping of source media IDs to new IDs.
	 * @param bool  $updated       Reference flag set to true if any changes made.
	 * @return array Updated blocks.
	 */
	private static function remap_chart_ids_in_blocks( array $blocks, array $chart_id_map, array $media_id_map, bool &$updated ): array {
		foreach ( $blocks as &$block ) {
			// Handle synced-chart blocks.
			if ( 'prc-chart-builder/synced-chart' === $block['blockName'] ) {
				$ref = $block['attrs']['ref'] ?? null;
				if ( ! empty( $ref ) && isset( $chart_id_map[ (int) $ref ] ) ) {
					$block['attrs']['ref'] = $chart_id_map[ (int) $ref ];
					$updated               = true;
				}
			}

			// Handle chart blocks.
			if ( 'prc-chart-builder/chart' === $block['blockName'] ) {
				$io = $block['attrs']['io'] ?? array();

				// Remap staticImageId.
				if ( ! empty( $io['staticImageId'] ) && isset( $media_id_map[ (int) $io['staticImageId'] ] ) ) {
					$new_id                                = $media_id_map[ (int) $io['staticImageId'] ];
					$block['attrs']['io']['staticImageId'] = $new_id;
					// Update URL if present.
					$new_url = wp_get_attachment_url( $new_id );
					if ( $new_url ) {
						$block['attrs']['io']['staticImageUrl'] = $new_url;
					}
					$updated = true;
				}

				// Remap pngId.
				if ( ! empty( $io['pngId'] ) && isset( $media_id_map[ (int) $io['pngId'] ] ) ) {
					$new_id                        = $media_id_map[ (int) $io['pngId'] ];
					$block['attrs']['io']['pngId'] = $new_id;
					// Update URL if present.
					$new_url = wp_get_attachment_url( $new_id );
					if ( $new_url ) {
						$block['attrs']['io']['pngUrl'] = $new_url;
					}
					$updated = true;
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::remap_chart_ids_in_blocks( $block['innerBlocks'], $chart_id_map, $media_id_map, $updated );
			}
		}

		return $blocks;
	}

	/**
	 * Pre-distribute callback for materials meta.
	 *
	 * Collects attachment data for distribution using Distributor's built-in
	 * media pre-distribute callback.
	 *
	 * @param mixed $meta_value     The meta value (reportMaterials array).
	 * @param int   $source_post_id The source post ID.
	 * @return array Extra data for each material with an attachment.
	 */
	public static function materials_pre_distribute( $meta_value, int $source_post_id ): array {
		if ( empty( $meta_value ) || ! is_array( $meta_value ) ) {
			return array();
		}

		$extra_data = array();

		foreach ( $meta_value as $index => $material ) {
			if ( empty( $material['attachmentId'] ) ) {
				$extra_data[ $index ] = array();
				continue;
			}

			$attachment_id = (int) $material['attachmentId'];

			// Use Distributor's built-in media pre-distribute callback.
			if ( function_exists( 'distributor_media_pre_distribute_callback' ) ) {
				$media_data = distributor_media_pre_distribute_callback( $attachment_id, $source_post_id );
			} else {
				$media_data = array(
					'source_attachment_id' => $attachment_id,
					'url'                  => wp_get_attachment_url( $attachment_id ),
				);
			}

			$extra_data[ $index ] = $media_data;
		}

		return $extra_data;
	}

	/**
	 * Post-distribute callback for materials meta.
	 *
	 * Remaps attachment IDs to their distributed equivalents using Distributor's
	 * built-in media handling.
	 *
	 * @param array $extra_data      The extra data from pre-distribute.
	 * @param mixed $original_meta   The original meta value.
	 * @param array $post_data       The post data being distributed.
	 * @param array $connection_data The connection data (unused but required by interface).
	 * @return mixed The updated meta value with remapped IDs.
	 */
	public static function materials_post_distribute( array $extra_data, $original_meta, array $post_data, array $connection_data = array() ) {
		if ( empty( $original_meta ) || ! is_array( $original_meta ) ) {
			return $original_meta;
		}

		$updated_meta = array();

		foreach ( $original_meta as $index => $material ) {
			$new_material = $material;

			if ( ! empty( $material['attachmentId'] ) ) {
				$source_id = (int) $material['attachmentId'];

				// Use Distributor's built-in media post-distribute callback.
				if ( ! empty( $extra_data[ $index ] ) && function_exists( 'distributor_media_post_distribute_callback' ) ) {
					$new_id = distributor_media_post_distribute_callback(
						$extra_data[ $index ],
						$source_id,
						$post_data
					);

					if ( $new_id && ! is_wp_error( $new_id ) && is_numeric( $new_id ) ) {
						$new_material['attachmentId'] = (int) $new_id;
					}
				}
			}

			$updated_meta[] = $new_material;
		}

		return $updated_meta;
	}

	/**
	 * Pre-distribute callback for parts meta.
	 *
	 * The parts meta contains items arrays with chapter post IDs.
	 * We collect chapter data similar to chapters_pre_distribute.
	 *
	 * @param mixed $meta_value     The meta value (package_parts array).
	 * @param int   $source_post_id The source post ID.
	 * @return array Extra data for parts.
	 */
	public static function parts_pre_distribute( $meta_value, int $source_post_id ): array {
		if ( empty( $meta_value ) || ! is_array( $meta_value ) ) {
			return array();
		}

		$extra_data = array();

		foreach ( $meta_value as $part_index => $part ) {
			$part_data = array();

			if ( ! empty( $part['items'] ) && is_array( $part['items'] ) ) {
				foreach ( $part['items'] as $item_index => $item_id ) {
					$chapter_id = (int) $item_id;

					if ( function_exists( 'distributor_post_pre_distribute_callback' ) ) {
						$post_data = distributor_post_pre_distribute_callback( $chapter_id, $source_post_id );
					} else {
						$post      = get_post( $chapter_id );
						$post_data = array(
							'source_post_id' => $chapter_id,
							'post_type'      => $post ? $post->post_type : 'post',
							'post_title'     => $post ? $post->post_title : '',
						);
					}

					$part_data[ $item_index ] = $post_data;
				}
			}

			$extra_data[ $part_index ] = $part_data;
		}

		return $extra_data;
	}

	/**
	 * Post-distribute callback for parts meta.
	 *
	 * Remaps chapter post IDs in the items arrays.
	 *
	 * @param array $extra_data      The extra data from pre-distribute.
	 * @param mixed $original_meta   The original meta value.
	 * @param array $post_data       The post data being distributed.
	 * @param array $connection_data The connection data.
	 * @return mixed The updated meta value with remapped IDs.
	 */
	public static function parts_post_distribute( array $extra_data, $original_meta, array $post_data, array $connection_data = array() ) {
		if ( empty( $original_meta ) || ! is_array( $original_meta ) ) {
			return $original_meta;
		}

		$updated_meta = array();

		foreach ( $original_meta as $part_index => $part ) {
			$new_part = $part;

			if ( ! empty( $part['items'] ) && is_array( $part['items'] ) ) {
				$new_items = array();

				foreach ( $part['items'] as $item_index => $item_id ) {
					$source_id       = (int) $item_id;
					$part_extra_data = $extra_data[ $part_index ] ?? array();
					$item_extra_data = $part_extra_data[ $item_index ] ?? array();

					// Use Distributor's built-in post post-distribute callback.
					if ( ! empty( $item_extra_data ) && function_exists( 'distributor_post_post_distribute_callback' ) ) {
						$new_id = distributor_post_post_distribute_callback(
							$item_extra_data,
							$source_id,
							$post_data,
							$connection_data
						);

						if ( $new_id && ! is_wp_error( $new_id ) && is_numeric( $new_id ) ) {
							$new_items[] = (int) $new_id;
						} else {
							// Keep original ID if remapping failed.
							$new_items[] = $source_id;
						}
					} else {
						$new_items[] = $source_id;
					}
				}

				$new_part['items'] = $new_items;
			}

			$updated_meta[] = $new_part;
		}

		return $updated_meta;
	}

	/**
	 * Restore post_parent relationships after distribution.
	 *
	 * When chapters are distributed, they need their post_parent updated
	 * to point to the distributed parent report.
	 *
	 * @hook dt_process_distributor_attributes
	 *
	 * @param int   $new_post_id      The new post ID on the target site.
	 * @param int   $original_post_id The original post ID from the source site.
	 * @param array $post_data        The post data.
	 */
	public function restore_post_parent_relationships( int $new_post_id, int $original_post_id, array $post_data ): void {
		$chapters = null;

		// First, try to get chapters from the processed post_data meta.
		// The meta key in post_data could be in 'distributor_meta' or 'meta'.
		$meta_key = Rest_API::$package_chapters_meta_key;

		if ( ! empty( $post_data['distributor_meta'][ $meta_key ] ) ) {
			$chapters = $post_data['distributor_meta'][ $meta_key ];
			// Handle serialized or array format.
			if ( is_array( $chapters ) && isset( $chapters[0] ) && is_string( $chapters[0] ) ) {
				$chapters = maybe_unserialize( $chapters[0] );
			}
		} elseif ( ! empty( $post_data['meta'][ $meta_key ] ) ) {
			$chapters = $post_data['meta'][ $meta_key ];
			if ( is_array( $chapters ) && isset( $chapters[0] ) && is_string( $chapters[0] ) ) {
				$chapters = maybe_unserialize( $chapters[0] );
			}
		}

		// Fall back to reading from database if not found in post_data.
		if ( empty( $chapters ) ) {
			$chapters = get_post_meta( $new_post_id, $meta_key, true );
		}

		if ( empty( $chapters ) || ! is_array( $chapters ) ) {
			// Schedule a delayed check in case meta is saved later.
			$this->schedule_post_parent_update( $new_post_id, $original_post_id );
			return;
		}

		$this->update_chapter_post_parents( $new_post_id, $chapters );

		// Clean up the transient if it exists.
		delete_transient( 'prc_report_package_chapter_map_' . $original_post_id );
	}

	/**
	 * Update post_parent for all chapters to point to the parent report.
	 *
	 * @param int   $parent_id The parent report post ID.
	 * @param array $chapters  The chapters array with remapped IDs.
	 */
	private function update_chapter_post_parents( int $parent_id, array $chapters ): void {
		foreach ( $chapters as $chapter ) {
			if ( empty( $chapter['postId'] ) ) {
				continue;
			}

			$chapter_id = (int) $chapter['postId'];

			// Verify the chapter exists.
			$chapter_post = get_post( $chapter_id );
			if ( ! $chapter_post ) {
				continue;
			}

			// Only update if post_parent is different.
			if ( (int) $chapter_post->post_parent !== $parent_id ) {
				wp_update_post(
					array(
						'ID'          => $chapter_id,
						'post_parent' => $parent_id,
					)
				);
			}
		}
	}

	/**
	 * Schedule a delayed post_parent update for cases where meta isn't immediately available.
	 *
	 * @param int $new_post_id      The new post ID on the target site.
	 * @param int $original_post_id The original post ID from the source site.
	 */
	private function schedule_post_parent_update( int $new_post_id, int $original_post_id ): void {
		// Use a one-time action to check again after all hooks have run.
		add_action(
			'shutdown',
			function () use ( $new_post_id, $original_post_id ) {
				$chapters = get_post_meta( $new_post_id, Rest_API::$package_chapters_meta_key, true );

				if ( ! empty( $chapters ) && is_array( $chapters ) ) {
					$this->update_chapter_post_parents( $new_post_id, $chapters );
				}

				// Clean up the transient.
				delete_transient( 'prc_report_package_chapter_map_' . $original_post_id );
			}
		);
	}
}
