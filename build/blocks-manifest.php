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
			'hoverBackgroundColor' => array(
				'type' => 'string'
			),
			'hoverTextColor' => array(
				'type' => 'string'
			),
			'customHoverBackgroundColor' => array(
				'type' => 'string'
			),
			'customHoverTextColor' => array(
				'type' => 'string'
			),
			'activeBackgroundColor' => array(
				'type' => 'string'
			),
			'activeTextColor' => array(
				'type' => 'string'
			),
			'customActiveBackgroundColor' => array(
				'type' => 'string'
			),
			'customActiveTextColor' => array(
				'type' => 'string'
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
				'margin' => true,
				'padding' => true,
				'blockGap' => true
			),
			'typography' => array(
				'__experimentalFontFamily' => true,
				'fontSize' => true,
				'lineHeight' => true,
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
		'style' => 'file:./style-index.css'
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
				'type' => 'string'
			),
			'textColor' => array(
				'type' => 'string'
			),
			'itemBackgroundColor' => array(
				'type' => 'string'
			),
			'customItemBackgroundColor' => array(
				'type' => 'string'
			),
			'itemTextColor' => array(
				'type' => 'string'
			),
			'customItemTextColor' => array(
				'type' => 'string'
			),
			'itemBorderColor' => array(
				'type' => 'string'
			),
			'customItemBorderColor' => array(
				'type' => 'string'
			),
			'itemHoverBackgroundColor' => array(
				'type' => 'string'
			),
			'customItemHoverBackgroundColor' => array(
				'type' => 'string'
			),
			'itemActiveBackgroundColor' => array(
				'type' => 'string'
			),
			'customItemActiveBackgroundColor' => array(
				'type' => 'string'
			),
			'nextButtonBackgroundColor' => array(
				'type' => 'string'
			),
			'customNextButtonBackgroundColor' => array(
				'type' => 'string'
			),
			'nextButtonTextColor' => array(
				'type' => 'string'
			),
			'customNextButtonTextColor' => array(
				'type' => 'string'
			),
			'nextButtonBoxShadowColor' => array(
				'type' => 'string'
			),
			'customNextButtonBoxShadowColor' => array(
				'type' => 'string'
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
