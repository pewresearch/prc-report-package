<?php
/**
 * Rest API
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

/**
 * Rest API
 *
 * @package PRC\Platform\Report_Package
 */
class Rest_API {
	/**
	 * The meta key for "chapters".
	 *
	 * @var string
	 */
	public static $package_chapters_meta_key = 'multiSectionReport';
	// @TODO: change these to snake case to `package_chapters` * when we do this, we should also adopt the Design and larger Center-wide schema for what we call "internal" or just "chapters" to be "sections". This would require changing our current core/heading language to reflect this and mass-update the existing `isChapter` attribute to `isSection`. This should be considered before finishing the lgeacy-self-healing-system @sethrubenstein

	/**
	 * The package chapters schema properties.
	 *
	 * @var array
	 */
	public static $chapters_schema_properties = array(
		'key'    => array(
			'type' => 'string',
		),
		'postId' => array(
			'type' => 'integer',
		),
	);

	/**
	 * The meta key for "parts".
	 *
	 * @var string
	 */
	public static $package_parts_meta_key = 'package_parts';

	/**
	 * The package parts schema properties.
	 *
	 * @var array
	 */
	public static $toc_parts_schema_properties = array(
		'key'   => array(
			'type'     => 'string',
			'required' => false,
		),
		'items' => array(
			'type'     => 'array',
			'required' => false,
		),
		'label' => array(
			'type'     => 'string',
			'required' => false,
		),
	);

	/**
	 * The meta key for report materials.
	 *
	 * @var string
	 */
	public static $package_materials_meta_key = 'reportMaterials';
	// @TODO: change these to snake case
	// Change this to package_materials. This is more generic, as this system could be used more broadly for "attachments" or "materials" in the future for other post types, like Fact Sheet, and Press Release.

	/**
	 * The report materials schema properties.
	 *
	 * @var array
	 */
	public static $package_materials_schema_properties = array(
		'key'          => array(
			'type'     => 'string',
			'required' => false,
		),
		'type'         => array(
			'type'     => 'string',
			'required' => false,
		),
		'url'          => array(
			'type'     => 'string',
			'required' => false,
		),
		'label'        => array(
			'type'     => 'string',
			'required' => false,
		),
		'attachmentId' => array(
			'type'     => 'integer',
			'required' => false,
		),
		'icon'         => array(
			'type'     => 'string',
			'required' => false,
		),
	);


	/**
	 * Construct the class.
	 *
	 * @param mixed $loader The loader.
	 */
	public function __construct( $loader = null ) {
		if ( null !== $loader ) {
			$this->init( $loader );
		}
	}

	/**
	 * Initialize the hooks.
	 *
	 * @param mixed $loader The loader.
	 */
	public function init( $loader ) {
		$loader->add_action( 'init', $this, 'register_meta_fields' );
		$loader->add_action( 'rest_api_init', $this, 'register_rest_fields' );
		$loader->add_action( 'rest_api_init', $this, 'register_writable_rest_fields' );
	}

	/**
	 * Register the meta fields for the post report package constiuent parts (report materials, back chapters, and TOC parts).
	 */
	public function register_meta_fields() {
		// Report Materials.
		register_post_meta(
			'post',
			self::$package_materials_meta_key,
			array(
				'single'            => true,
				'type'              => 'array',
				'description'       => 'Array of package materials.',
				'show_in_rest'      => array(
					// This sanitizes the data, making sure empty keys are removed.
					'prepare_callback' => function ( $value, $rest_request ) {
						$procssed = array();
						foreach ( $value as $obj ) {
							$keys = array_keys( $obj );
							foreach ( $keys as $key ) {
								if ( empty( $obj[ $key ] ) ) {
									unset( $obj[ $key ] );
								}
							}
							$procssed[] = $obj;
						}
						return $procssed;
					},
					'schema'           => array(
						'items' => array(
							'type'       => 'object',
							'properties' => self::$package_materials_schema_properties,
						),
					),
				),
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'revisions_enabled' => true,
			)
		);

		// Chapters.
		register_post_meta(
			'post',
			self::$package_chapters_meta_key,
			array(
				'single'            => true,
				'type'              => 'array',
				'description'       => 'Array of chapter objects.',
				'show_in_rest'      => array(
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => self::$chapters_schema_properties,
						),
					),
				),
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'revisions_enabled' => true,
			)
		);

		// TOC "Parts".
		register_post_meta(
			'post',
			self::$package_parts_meta_key . '__enabled',
			array(
				'single'            => true,
				'type'              => 'boolean',
				'description'       => 'Whether the TOC parts are enabled.',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'revisions_enabled' => true,
			)
		);
		register_post_meta(
			'post',
			self::$package_parts_meta_key,
			array(
				'single'            => true,
				'type'              => 'array',
				'description'       => 'Array of TOC parts.',
				'show_in_rest'      => array(
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => self::$toc_parts_schema_properties,
						),
					),
				),
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'revisions_enabled' => true,
			)
		);
	}

	/**
	 * Register the rest fields for the post report package constiuent parts (report materials, report pagination, toc, parent info).
	 * This is used in the interface and wherever useEntityProp is referencing report package data.
	 */
	public function register_rest_fields() {
		// Register the quick Table of Contents field for all public posts types.
		$public_post_types = get_post_types(
			array(
				'public' => true,
			)
		);
		foreach ( $public_post_types as $post_type ) {
			register_rest_field(
				$post_type,
				'table_of_contents',
				array(
					'get_callback' => array( $this, 'get_table_of_contents_field' ),
					'description'  => 'The table of contents for this post.',
				)
			);
		}

		// Register the other constiuent fields for the report package.
		register_rest_field(
			'post',
			'report_materials',
			array(
				'get_callback' => array( $this, 'get_report_materials_field' ),
				'description'  => 'The full report package; materials and chapters.',
			)
		);

		register_rest_field(
			'post',
			'report_pagination',
			array(
				'get_callback' => array( $this, 'get_report_pagination_field' ),
				'description'  => 'Pagination for report packages.',
			)
		);

		/**
		 * @TODO: We should move this somewhere more genreal...
		 */
		register_rest_field(
			'post',
			'parent_info',
			array(
				'get_callback' => array( $this, 'get_parent_info_field' ),
				'description'  => 'Parent info for a child post',
			)
		);
	}

	/**
	 * Get the table of contents for a given post.
	 *
	 * @param mixed $object The object.
	 * @return array
	 */
	public function get_table_of_contents_field( $object ) {
		$post_id = $object['id'];
		return get_package_chapters( $post_id );
	}

	/**
	 * Get the report materials for a given post.
	 *
	 * @param mixed $object The object.
	 * @return array
	 */
	public function get_report_materials_field( $object ) {
		$post_id = $object['id'];
		return get_package_materials( $post_id );
	}

	/**
	 * Get the report pagination for a given post.
	 *
	 * @param mixed $object The object.
	 * @return array
	 */
	public function get_report_pagination_field( $object ) {
		$post_id = $object['id'];
		return get_pagination( $post_id );
	}

	/**
	 * Get the parent info for a given post.
	 *
	 * @param mixed $object The object.
	 * @return array
	 */
	public function get_parent_info_field( $object ) {
		$post_id   = $object['id'];
		$parent_id = get_package_id( $post_id );
		return array(
			'parent_title' => get_the_title( $parent_id ),
			'parent_id'    => $parent_id,
		);
	}

	/**
	 * Register writable REST fields for editor use (RTC-safe).
	 * Each field maps to an underlying post meta key with get/update callbacks
	 * so the editor uses a single editEntityRecord() call per mutation.
	 *
	 * @hook rest_api_init
	 */
	public function register_writable_rest_fields() {
		$fields = array(
			'materialsOrdered' => array(
				'get_callback'    => array( $this, 'get_materials_ordered' ),
				'update_callback' => array( $this, 'update_materials_ordered' ),
				'schema'          => array(
					'description' => 'Ordered report materials for RTC.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'key'          => array( 'type' => 'string' ),
							'type'         => array( 'type' => 'string' ),
							'url'          => array( 'type' => 'string' ),
							'label'        => array( 'type' => 'string' ),
							'attachmentId' => array( 'type' => array( 'integer', 'null' ) ),
							'icon'         => array( 'type' => 'string' ),
						),
					),
				),
			),
			'chaptersOrdered'  => array(
				'get_callback'    => array( $this, 'get_chapters_ordered' ),
				'update_callback' => array( $this, 'update_chapters_ordered' ),
				'schema'          => array(
					'description' => 'Ordered chapter list for RTC.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => self::$chapters_schema_properties,
					),
				),
			),
			'partsOrdered'     => array(
				'get_callback'    => array( $this, 'get_parts_ordered' ),
				'update_callback' => array( $this, 'update_parts_ordered' ),
				'schema'          => array(
					'description' => 'Ordered TOC parts for RTC.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'key'   => array( 'type' => 'string' ),
							'items' => array(
								'type'  => 'array',
								'items' => array( 'type' => array( 'string', 'integer' ) ),
							),
							'label' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'partsEnabled'     => array(
				'get_callback'    => array( $this, 'get_parts_enabled' ),
				'update_callback' => array( $this, 'update_parts_enabled' ),
				'schema'          => array(
					'description' => 'Whether TOC parts are enabled.',
					'type'        => 'boolean',
				),
			),
		);

		foreach ( $fields as $field_name => $args ) {
			register_rest_field( 'post', $field_name, $args );
		}
	}

	// ------------------------------------------------------------------
	// Writable REST field helpers
	// ------------------------------------------------------------------

	/**
	 * Get post ID from REST object (array or WP_Post).
	 *
	 * @param mixed $object The object.
	 * @return int
	 */
	private function get_post_id_from_rest_object( $object ) {
		if ( $object instanceof \WP_Post ) {
			return (int) $object->ID;
		}
		if ( is_array( $object ) && isset( $object['id'] ) ) {
			return (int) $object['id'];
		}
		return 0;
	}

	// ------------------------------------------------------------------
	// materialsOrdered  ↔  reportMaterials meta
	// ------------------------------------------------------------------

	/**
	 * Sanitize materials array.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private function sanitize_materials_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = array();
			if ( isset( $row['key'] ) ) {
				$item['key'] = sanitize_text_field( (string) $row['key'] );
			}
			if ( isset( $row['type'] ) ) {
				$item['type'] = sanitize_text_field( (string) $row['type'] );
			}
			if ( isset( $row['url'] ) ) {
				$item['url'] = esc_url_raw( (string) $row['url'] );
			}
			if ( isset( $row['label'] ) ) {
				$item['label'] = sanitize_text_field( (string) $row['label'] );
			}
			if ( isset( $row['attachmentId'] ) && null !== $row['attachmentId'] ) {
				$item['attachmentId'] = (int) $row['attachmentId'];
			}
			if ( isset( $row['icon'] ) ) {
				$item['icon'] = sanitize_text_field( (string) $row['icon'] );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * REST get: materialsOrdered.
	 *
	 * @param mixed $object Prepared post.
	 * @return array
	 */
	public function get_materials_ordered( $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, self::$package_materials_meta_key, true );
		return is_array( $raw ) ? $this->sanitize_materials_array( $raw ) : array();
	}

	/**
	 * REST update: materialsOrdered — persist to reportMaterials meta.
	 *
	 * @param mixed $value  New value.
	 * @param mixed $object Post object.
	 * @return true|\WP_Error
	 */
	public function update_materials_ordered( $value, $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_post', 'Invalid post for materialsOrdered.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this post.' );
		}
		$sanitized = $this->sanitize_materials_array( $value );
		update_post_meta( $post_id, self::$package_materials_meta_key, $sanitized );
		return true;
	}

	// ------------------------------------------------------------------
	// chaptersOrdered  ↔  multiSectionReport meta
	// ------------------------------------------------------------------

	/**
	 * Sanitize chapters array.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private function sanitize_chapters_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key     = isset( $row['key'] ) ? sanitize_text_field( (string) $row['key'] ) : '';
			$post_id = isset( $row['postId'] ) ? (int) $row['postId'] : 0;
			$out[]   = array(
				'key'    => $key,
				'postId' => $post_id,
			);
		}
		return $out;
	}

	/**
	 * REST get: chaptersOrdered.
	 *
	 * @param mixed $object Prepared post.
	 * @return array
	 */
	public function get_chapters_ordered( $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, self::$package_chapters_meta_key, true );
		return is_array( $raw ) ? $this->sanitize_chapters_array( $raw ) : array();
	}

	/**
	 * REST update: chaptersOrdered — persist to multiSectionReport meta.
	 *
	 * @param mixed $value  New value.
	 * @param mixed $object Post object.
	 * @return true|\WP_Error
	 */
	public function update_chapters_ordered( $value, $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_post', 'Invalid post for chaptersOrdered.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this post.' );
		}
		$sanitized = $this->sanitize_chapters_array( $value );
		update_post_meta( $post_id, self::$package_chapters_meta_key, $sanitized );
		return true;
	}

	// ------------------------------------------------------------------
	// partsOrdered  ↔  package_parts meta
	// ------------------------------------------------------------------

	/**
	 * Sanitize parts array.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private function sanitize_parts_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = array(
				'key' => isset( $row['key'] ) ? sanitize_text_field( (string) $row['key'] ) : '',
			);
			if ( isset( $row['items'] ) && is_array( $row['items'] ) ) {
				$item['items'] = array_values( $row['items'] );
			}
			if ( isset( $row['label'] ) ) {
				$item['label'] = sanitize_text_field( (string) $row['label'] );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * REST get: partsOrdered.
	 *
	 * @param mixed $object Prepared post.
	 * @return array
	 */
	public function get_parts_ordered( $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, self::$package_parts_meta_key, true );
		return is_array( $raw ) ? $this->sanitize_parts_array( $raw ) : array();
	}

	/**
	 * REST update: partsOrdered — persist to package_parts meta.
	 *
	 * @param mixed $value  New value.
	 * @param mixed $object Post object.
	 * @return true|\WP_Error
	 */
	public function update_parts_ordered( $value, $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_post', 'Invalid post for partsOrdered.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this post.' );
		}
		$sanitized = $this->sanitize_parts_array( $value );
		update_post_meta( $post_id, self::$package_parts_meta_key, $sanitized );
		return true;
	}

	// ------------------------------------------------------------------
	// partsEnabled  ↔  package_parts__enabled meta
	// ------------------------------------------------------------------

	/**
	 * REST get: partsEnabled.
	 *
	 * @param mixed $object Prepared post.
	 * @return bool
	 */
	public function get_parts_enabled( $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return false;
		}
		return (bool) get_post_meta( $post_id, self::$package_parts_meta_key . '__enabled', true );
	}

	/**
	 * REST update: partsEnabled — persist to package_parts__enabled meta.
	 *
	 * @param mixed $value  New value.
	 * @param mixed $object Post object.
	 * @return true|\WP_Error
	 */
	public function update_parts_enabled( $value, $object ) {
		$post_id = $this->get_post_id_from_rest_object( $object );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_post', 'Invalid post for partsEnabled.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this post.' );
		}
		update_post_meta( $post_id, self::$package_parts_meta_key . '__enabled', (bool) $value );
		return true;
	}
}
