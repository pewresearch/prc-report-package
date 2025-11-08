<?php
/**
 * Report Materials Block
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

use WP_Error;
use WP_HTML_Heading_Processor;

/**
 * Block Name:        Report Materials
 * Description:       Displays a list of materials from a post report package.
 * Version:           3.0
 * Requires at least: 6.1
 * Requires PHP:      8.1
 * Author:            Seth Rubenstein
 *
 * @package           prc-report-package
 */
class Report_Materials {
	/**
	 * Constructor
	 *
	 * @param mixed $loader Loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @param mixed $loader Loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
		}
	}

	/**
	 * Get the item label
	 *
	 * @param mixed $item Item.
	 * @return string
	 */
	public function get_item_label( $item ) {
		if ( array_key_exists( 'label', $item ) && ! empty( $item['label'] ) ) {
			return $item['label'];
		}
		switch ( $item['type'] ) {
			case 'detailTable':
				return 'Data Table';
			case 'link':
				return 'Link';
			case 'presentation':
				return 'Presentation';
			case 'pressRelease':
				return 'Press Release';
			case 'promo':
				return 'Promo';
			case 'qA':
				return 'Q & A';
			case 'questionnaire':
				return 'Questionnaire';
			case 'report':
				return 'Report PDF';
			case 'supplemental':
				return 'Supplemental';
			case 'topline':
				return 'Topline';
			default:
				return 'Unknown';
		}
	}

	/**
	 * Get the item icon
	 *
	 * @param mixed $item Item.
	 * @return string
	 */
	public function get_item_icon( $item ) {
		$icon_slug = array_key_exists( 'icon', $item ) ? $item['icon'] : $item['type'];
		switch ( $icon_slug ) {
			case 'detailTable':
				return 'table';
			case 'link':
				return 'link';
			case 'presentation':
				return 'presentation-screen';
			case 'pressRelease':
				return 'file';
			case 'promo':
				return 'file';
			case 'qA':
				return 'file';
			case 'questionnaire':
				return 'clipboard';
			case 'report':
				return 'file';
			case 'supplemental':
				return 'file';
			case 'topline':
				return 'clipboard';
			case 'dataset':
				return 'download';
			case 'video':
				return 'video';
			default:
				return 'file';
		}
	}

	/**
	 * Generate CSS custom properties for color styles
	 *
	 * @param array $attributes Block attributes
	 * @return string CSS string with color custom properties
	 */
	private function generate_styles( array $attributes ): string {
		$hover_bg    = $attributes['customHoverBackgroundColor'] ?? '';
		$hover_text  = $attributes['customHoverTextColor'] ?? '';
		$active_bg   = $attributes['customActiveBackgroundColor'] ?? '';
		$active_text = $attributes['customActiveTextColor'] ?? '';
		$block_gap   = \PRC\Platform\Block_Utils\get_block_gap_support_value( $attributes );

		$styles = array(
			'--hover-background-color'  => $hover_bg,
			'--hover-text-color'        => $hover_text,
			'--active-background-color' => $active_bg,
			'--active-text-color'       => $active_text,
			'--block-gap'               => $block_gap,
		);

		$style_string = array_map(
			static function ( string $key, string $value ): string {
				return ! empty( $value ) ? $key . ': ' . $value . ';' : '';
			},
			array_keys( $styles ),
			$styles
		);

		return implode( ' ', array_filter( $style_string ) );
	}

	/**
	 * Render the block
	 *
	 * @param mixed $attributes Block attributes.
	 * @param mixed $content Block content.
	 * @param mixed $block Block.
	 * @return string
	 */
	public function render_block_callback( $attributes, $content, $block ) {
		$post_id = $block->context['postId'];

		$materials = get_package_materials( $post_id );
		if ( is_string( $materials ) && ! empty( $materials ) ) {
			$materials = json_decode( $materials, true );
		}

		if ( empty( $materials ) ) {
			return '';
		}

		$list_item_classnames = 'wp-block-prc-block-report-materials__list-item flex-align-center';

		foreach ( $materials as $material ) {
			$icon     = \PRC\Platform\Icons\render(
				'solid',
				$this->get_item_icon( $material )
			);
			$content .= wp_sprintf(
				'<li class="%1$s" data-material-type="%2$s">%3$s<a href="%4$s" target="_blank">%5$s</a></li>',
				$list_item_classnames,
				$material['type'],
				$icon,
				$material['url'],
				$this->get_item_label( $material )
			);
		}

		$block_attrs = get_block_wrapper_attributes(
			array(
				'class' => 'wp-block-prc-block-report-materials__list',
				'role'  => 'list',
			)
		);

		$output = wp_sprintf(
			'<ul %1$s>%2$s</ul>',
			$block_attrs,
			$content
		);

		// Use WP_HTML_Tag_Processor to append color styles to existing style attribute
		$tag_processor = new \WP_HTML_Tag_Processor( $output );
		if ( $tag_processor->next_tag( array( 'class_name' => 'wp-block-prc-block-report-materials' ) ) ) {
			$style  = (string) $tag_processor->get_attribute( 'style' );
			$style .= ' ' . $this->generate_styles( $attributes );
			$tag_processor->set_attribute( 'style', $style );
		}

		return $tag_processor->get_updated_html();
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @hook init
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function block_init() {
		register_block_type_from_metadata(
			PRC_REPORT_PACKAGE_BLOCKS_DIR . '/report-materials',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}
}
