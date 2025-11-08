/**
 * External Dependencies
 */

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { 
	InspectorControls,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients
} from '@wordpress/block-editor';

/**
 * Internal Dependencies
 */

export default function Controls({ attributes, setAttributes, colors, clientId, }) {
	const colorSettings = useMultipleOriginColorsAndGradients();

	const {
		customItemBackgroundColor,
		customItemTextColor,
		customItemBorderColor,
		customItemHoverBackgroundColor,
		customItemActiveBackgroundColor,
		customNextButtonBackgroundColor,
		customNextButtonTextColor,
		customNextButtonBoxShadowColor,
	} = attributes;

	const {
		textColor,
		setTextColor,
		backgroundColor,
		setBackgroundColor,
		itemBackgroundColor,
		setItemBackgroundColor,
		itemTextColor,
		setItemTextColor,
		itemBorderColor,
		setItemBorderColor,
		itemHoverBackgroundColor,
		setItemHoverBackgroundColor,
		itemActiveBackgroundColor,
		setItemActiveBackgroundColor,
		nextButtonBackgroundColor,
		setNextButtonBackgroundColor,
		nextButtonTextColor,
		setNextButtonTextColor,
		nextButtonBoxShadowColor,
		setNextButtonBoxShadowColor,
	} = colors;

	return (
		<InspectorControls group="color">
			<ColorGradientSettingsDropdown
				settings={ [
					{
						colorValue: textColor?.color,
						onColorChange: setTextColor,
						label: __('Text'),
					},
					{
						colorValue: backgroundColor?.color,
						onColorChange: setBackgroundColor,
						label: __('Background'),
					},
					{
						colorValue: itemBackgroundColor?.color ?? customItemBackgroundColor,
						onColorChange: (value) => {
							setItemBackgroundColor(value);
							setAttributes({ customItemBackgroundColor: value });
						},
						label: __('Item Background'),
					},
					{
						colorValue: itemTextColor?.color ?? customItemTextColor,
						onColorChange: (value) => {
							setItemTextColor(value);
							setAttributes({ customItemTextColor: value });
						},
						label: __('Item Text'),
					},
					{
						colorValue: itemBorderColor?.color ?? customItemBorderColor,
						onColorChange: (value) => {
							setItemBorderColor(value);
							setAttributes({ customItemBorderColor: value });
						},
						label: __('Item Border'),
					},
					{
						colorValue: itemHoverBackgroundColor?.color ?? customItemHoverBackgroundColor,
						onColorChange: (value) => {
							setItemHoverBackgroundColor(value);
							setAttributes({ customItemHoverBackgroundColor: value });
						},
						label: __('Item Hover Background'),
					},
					{
						colorValue: itemActiveBackgroundColor?.color ?? customItemActiveBackgroundColor,
						onColorChange: (value) => {
							setItemActiveBackgroundColor(value);
							setAttributes({ customItemActiveBackgroundColor: value });
						},
						label: __('Item Active Background'),
					},
					{
						colorValue: nextButtonBackgroundColor?.color ?? customNextButtonBackgroundColor,
						onColorChange: (value) => {
							setNextButtonBackgroundColor(value);
							setAttributes({ customNextButtonBackgroundColor: value });
						},
						label: __('Next Button Background'),
					},
					{
						colorValue: nextButtonTextColor?.color ?? customNextButtonTextColor,
						onColorChange: (value) => {
							setNextButtonTextColor(value);
							setAttributes({ customNextButtonTextColor: value });
						},
						label: __('Next Button Text'),
					},
					{
						colorValue: nextButtonBoxShadowColor?.color ?? customNextButtonBoxShadowColor,
						onColorChange: (value) => {
							setNextButtonBoxShadowColor(value);
							setAttributes({ customNextButtonBoxShadowColor: value });
						},
						label: __('Next Button Box Shadow'),
					},
				] }
				panelId={ clientId }
				hasColorsOrGradients={ false }
				disableCustomColors={ true }
				__experimentalIsRenderedInSidebar
				{ ...colorSettings }
			/>
		</InspectorControls>
	);
}
