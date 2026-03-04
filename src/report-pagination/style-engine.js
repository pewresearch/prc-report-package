/**
 * WordPress dependencies
 */
import { useStyleOverride } from '@wordpress/block-editor';

/**
 * Gets the color styles for the report-pagination block.
 *
 * @param {Object} options            - Options object.
 * @param {Object} options.attributes - Block attributes.
 * @return {Object} Color variable map
 */
function getColorStyles({ attributes } = {}) {
	const {
		customItemBackgroundColor,
		customItemTextColor,
		customItemBorderColor,
		customItemHoverBackgroundColor,
		customItemActiveBackgroundColor,
		customNextButtonBackgroundColor,
		customNextButtonTextColor,
		customNextButtonBoxShadowColor,
	} = attributes || {};

	// Helper to normalize color objects (preset { slug } vs direct value).
	function getColorValue(color) {
		if (!color) {
			return null;
		}
		if (typeof color === 'object' && color.slug) {
			return `var(--wp--preset--color--${color.slug})`;
		}
		return color;
	}

	const colorVarMap = {
		'--item-background-color': getColorValue(customItemBackgroundColor),
		'--item-text-color': getColorValue(customItemTextColor),
		'--item-brdr-color': getColorValue(customItemBorderColor),
		'--item-hover-background-color': getColorValue(
			customItemHoverBackgroundColor
		),
		'--item-active-background-color': getColorValue(
			customItemActiveBackgroundColor
		),
		'--next-button-background-color': getColorValue(
			customNextButtonBackgroundColor
		),
		'--next-button-text-color': getColorValue(customNextButtonTextColor),
		'--next-button-box-shadow-color': getColorValue(
			customNextButtonBoxShadowColor
		),
	};

	return colorVarMap;
}

/**
 * Injects color CSS custom properties for the report-pagination block.
 *
 * @param {Object} props
 * @param {Object} props.attributes Block attributes
 * @param {string} props.clientId   Block client ID
 * @return {null} No UI output
 */
export default function StyleEngine({ attributes, clientId }) {
	const colorVarMap = getColorStyles({ attributes });

	const declarations = Object.entries(colorVarMap)
		.filter(([, value]) => !!value)
		.map(([name, value]) => `\t${name}: ${value};`)
		.join('\n');

	const css =
		clientId && declarations.length
			? `#block-${clientId} {\n${declarations}\n}`
			: '';

	useStyleOverride({ css });

	return null;
}
