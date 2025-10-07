<?php
// This file is generated. Do not modify it manually.
return array(
	'report-materials' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/report-materials',
		'version' => '1.0.0',
		'title' => 'Report Materials',
		'category' => 'theme',
		'description' => 'Displays a list of all materials from a post report package.',
		'attributes' => array(
			'headingBackgroundColor' => array(
				'type' => 'string',
				'default' => 'ui-black'
			),
			'headingTextColor' => array(
				'type' => 'string',
				'default' => 'ui-white'
			),
			'hoverBackgroundColor' => array(
				'type' => 'string',
				'default' => 'ui-beige-very-light'
			),
			'hoverTextColor' => array(
				'type' => 'string',
				'default' => 'ui-black'
			),
			'activeBackgroundColor' => array(
				'type' => 'string',
				'default' => 'ui-gray-very-light'
			),
			'activeTextColor' => array(
				'type' => 'string',
				'default' => 'ui-black'
			),
			'heading' => array(
				'type' => 'string',
				'default' => 'Report Materials'
			),
			'hideHeading' => array(
				'type' => 'boolean',
				'default' => false
			),
			'style' => array(
				'type' => 'object',
				'default' => array(
					'spacing' => array(
						'blockGap' => 'var:preset|spacing|20'
					)
				)
			)
		),
		'supports' => array(
			'anchor' => true,
			'html' => false,
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom',
					'left',
					'right'
				),
				'blockGap' => true,
				'__experimentalDefaultControls' => array(
					'margin' => true,
					'blockGap' => true
				)
			),
			'typography' => array(
				'__experimentalFontFamily' => true,
				'fontSize' => true,
				'__experimentalDefaultControls' => array(
					'__experimentalFontFamily' => true
				)
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'textdomain' => 'report-materials',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => array(
			'file:./style-index.css',
			'prc-block-library--baseball-card',
			'prc-block-library--additional-color-supports'
		)
	),
	'report-pagination' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/report-pagination',
		'version' => '1.0.0',
		'title' => 'Report Pagination',
		'category' => 'theme',
		'description' => 'Provides a stylized pagination for use with reports',
		'attributes' => array(
			'backgroundColor' => array(
				'type' => 'string',
				'default' => 'white'
			),
			'textColor' => array(
				'type' => 'string',
				'default' => 'white'
			),
			'itemBackgroundColor' => array(
				'type' => 'string',
				'default' => 'white'
			),
			'itemTextColor' => array(
				'type' => 'string',
				'default' => 'ui-black'
			),
			'itemBorderColor' => array(
				'type' => 'string',
				'default' => 'gray'
			),
			'itemHoverBackgroundColor' => array(
				'type' => 'string',
				'default' => 'ui-beige-very-light'
			),
			'itemActiveBackgroundColor' => array(
				'type' => 'string',
				'default' => 'white'
			),
			'nextButtonBackgroundColor' => array(
				'type' => 'string',
				'default' => 'ui-beige-very-light'
			),
			'nextButtonTextColor' => array(
				'type' => 'string',
				'default' => 'ui-black'
			),
			'nextButtonBoxShadowColor' => array(
				'type' => 'string',
				'default' => 'gray-alt'
			)
		),
		'supports' => array(
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true
			),
			'anchor' => true,
			'html' => false,
			'spacing' => array(
				'blockGap' => array(
					'horizontal',
					'vertical'
				),
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'layout' => array(
				'allowSwitching' => false,
				'allowInheriting' => false,
				'allowEditing' => false,
				'default' => array(
					'type' => 'flex',
					'flexWrap' => 'wrap'
				)
			),
			'typography' => array(
				'fontSize' => true,
				'__experimentalFontFamily' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true,
					'__experimentalFontFamily' => true
				)
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'textdomain' => 'report-pagination',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => array(
			'file:./style-index.css',
			'prc-block-library--pagination'
		)
	)
);
