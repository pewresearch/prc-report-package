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
		$loader->add_action( 'rest_api_init', $this, 'register_toplines_route' );
	}

	/**
	 * Register the public catalog of published topline PDFs.
	 *
	 * @hook rest_api_init
	 */
	public function register_toplines_route() {
		register_rest_route(
			'prc-api/v3',
			'report-package/toplines',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_toplines' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'description'       => 'Current page of the collection.',
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'description'       => 'Maximum number of posts to query per page.',
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => array( $this, 'sanitize_toplines_per_page' ),
					),
					'year'     => array(
						'description'       => 'Filter by post publish year.',
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_toplines_year_month' ),
						'validate_callback' => array( $this, 'validate_toplines_year' ),
					),
					'month'    => array(
						'description'       => 'Filter by post publish month (1–12). Requires year.',
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_toplines_year_month' ),
						'validate_callback' => array( $this, 'validate_toplines_month' ),
					),
				),
			)
		);
	}

	/**
	 * Clamp per_page to 1–100.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_toplines_per_page( $value ) {
		$per_page = absint( $value );
		if ( $per_page < 1 ) {
			return 20;
		}
		return min( 100, $per_page );
	}

	/**
	 * Sanitize optional year/month query args.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	public function sanitize_toplines_year_month( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return absint( $value );
	}

	/**
	 * Validate the year query arg.
	 *
	 * @param mixed            $value   Sanitized value.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public function validate_toplines_year( $value, $request, $param ) {
		unset( $request, $param );
		if ( null === $value ) {
			return true;
		}
		if ( $value < 1 ) {
			return new \WP_Error(
				'rest_invalid_param',
				'year must be a positive integer.',
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Validate the month query arg.
	 *
	 * @param mixed            $value   Sanitized value.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public function validate_toplines_month( $value, $request, $param ) {
		unset( $param );
		if ( null === $value ) {
			return true;
		}
		if ( null === $request->get_param( 'year' ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				'month requires year.',
				array( 'status' => 400 )
			);
		}
		if ( $value < 1 || $value > 12 ) {
			return new \WP_Error(
				'rest_invalid_param',
				'month must be between 1 and 12.',
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Enforce per-IP rate limiting for the public toplines catalog.
	 *
	 * @param string $endpoint_key Unique key for this endpoint bucket.
	 * @return true|\WP_Error
	 */
	private function enforce_ip_rate_limit( string $endpoint_key ) {
		if ( ! function_exists( '\\PRC\\Platform\\rate_limit_hit' ) ) {
			return true;
		}

		$ip = function_exists( '\\PRC\\Platform\\get_client_ip' )
			? \PRC\Platform\get_client_ip()
			: '';

		$ip = (string) apply_filters( 'prc_report_package_toplines_client_ip', $ip );

		if ( '' === $ip ) {
			return true;
		}

		if ( \PRC\Platform\rate_limit_hit(
			'report_package_' . $endpoint_key . '_' . md5( $ip ),
			30,
			MINUTE_IN_SECONDS,
			'prc_report_package_throttle'
		) ) {
			return new \WP_Error(
				'rate_limited',
				'Too many requests. Please try again later.',
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * GET /prc-api/v3/report-package/toplines
	 *
	 * Pagination is by published posts that have a topline material. A post
	 * with two toplines contributes two items on the same page.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_toplines( $request ) {
		$throttled = $this->enforce_ip_rate_limit( 'toplines' );
		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = $this->sanitize_toplines_per_page( $request->get_param( 'per_page' ) );
		$year     = $request->get_param( 'year' );
		$month    = $request->get_param( 'month' );

		$query_args = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => self::$package_materials_meta_key,
					'value'   => 's:4:"type";s:7:"topline"',
					'compare' => 'LIKE',
				),
			),
		);

		if ( null !== $year ) {
			$query_args['year'] = (int) $year;
		}
		if ( null !== $month ) {
			$query_args['monthnum'] = (int) $month;
		}

		$query = new \WP_Query( $query_args );

		$items = array();
		foreach ( $query->posts as $post_id ) {
			$items = array_merge( $items, get_topline_materials_for_post( (int) $post_id ) );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
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
					// Normalize corrupt/legacy meta (empty string, scalar URL) before REST output.
					'prepare_callback' => function ( $value ) {
						return $this->sanitize_materials_array( $value );
					},
					'schema'           => array(
						'items' => array(
							'type'       => 'object',
							'properties' => self::$package_materials_schema_properties,
						),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_materials_array' ),
				'auth_callback'     => array( $this, 'authorize_materials_meta' ),
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
		 * Parent info for child posts in a report package.
		 *
		 * @TODO: We should move this somewhere more general.
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
	 * Sanitize a materials field that must be a string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function stringify_materials_field( $value ) {
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}
		return '';
	}

	/**
	 * Authorize writes to reportMaterials meta.
	 *
	 * @param bool   $allowed   Whether the user can add the object meta.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Object ID.
	 * @return bool
	 */
	public function authorize_materials_meta( $allowed, $meta_key, $object_id ) {
		unset( $allowed, $meta_key );
		$object_id = (int) $object_id;
		if ( $object_id > 0 ) {
			return current_user_can( 'edit_post', $object_id );
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize materials array.
	 *
	 * Neutralizes javascript: and other disallowed URL protocols via esc_url_raw,
	 * and strips tags from text fields. Used as the reportMaterials sanitize_callback.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public function sanitize_materials_array( $value ) {
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
				$item['key'] = $this->stringify_materials_field( $row['key'] );
			}
			if ( isset( $row['type'] ) ) {
				$item['type'] = $this->stringify_materials_field( $row['type'] );
			}
			if ( isset( $row['url'] ) ) {
				$url = $row['url'];
				if ( is_scalar( $url ) ) {
					$item['url'] = esc_url_raw( (string) $url );
				}
			}
			if ( isset( $row['label'] ) ) {
				$item['label'] = $this->stringify_materials_field( $row['label'] );
			}
			if ( isset( $row['attachmentId'] ) && null !== $row['attachmentId'] ) {
				$item['attachmentId'] = (int) $row['attachmentId'];
			}
			if ( isset( $row['icon'] ) ) {
				$item['icon'] = $this->stringify_materials_field( $row['icon'] );
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
