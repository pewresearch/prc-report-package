/* eslint-disable max-lines-per-function */
/**
 * External Dependencies
 */
import clsx from 'clsx'
import { Icon } from '@prc/icons';

/**
 * WordPress Dependencies
 */
import { useBlockProps, RichText, withColors } from '@wordpress/block-editor';

/**
 * Internal Dependencies
 */
import useReportMaterials from './use-report-materials';
import { getItemLabel, getItemIcon } from './utils';
import Controls from './controls';
import StyleEngine from './style-engine';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object}   props                           Properties passed to the function.
 * @param {Object}   props.attributes                Available block attributes.
 * @param            props.context
 * @param            props.clientId
 * @param            props.isSelected
 * @param            props.headingBackgroundColor
 * @param            props.setHeadingBackgroundColor
 * @param            props.headingTextColor
 * @param            props.setHeadingTextColor
 * @param            props.activeBackgroundColor
 * @param            props.setActiveBackgroundColor
 * @param            props.activeTextColor
 * @param            props.setActiveTextColor
 * @param            props.hoverBackgroundColor
 * @param            props.setHoverBackgroundColor
 * @param            props.hoverTextColor
 * @param            props.setHoverTextColor
 * @param {Function} props.setAttributes             Function that updates individual attributes.
 *
 * @return {WPElement} Element to render.
 */
function Edit({
	attributes,
	setAttributes,
	context,
	clientId,
	isSelected,
	headingBackgroundColor,
	setHeadingBackgroundColor,
	headingTextColor,
	setHeadingTextColor,
	activeBackgroundColor,
	setActiveBackgroundColor,
	activeTextColor,
	setActiveTextColor,
	hoverBackgroundColor,
	setHoverBackgroundColor,
	hoverTextColor,
	setHoverTextColor,
}) {
	const { postId, postType } = context;
	const { className } = attributes;
	const {
		reportMaterials = [],
		parentId,
		parentTitle,
	} = useReportMaterials({ postId, postType });

	const blockProps = useBlockProps({
		className: clsx(className, 'common-block-style--baseball-card', 'wp-block-prc-block-report-materials__list'),
	});

	return (
		<>
			<Controls
				{...{
					attributes,
					setAttributes,
					colors: {
						hoverBackgroundColor,
						setHoverBackgroundColor,
						hoverTextColor,
						setHoverTextColor,
						activeBackgroundColor,
						setActiveBackgroundColor,
						activeTextColor,
						setActiveTextColor,
					},
					clientId,
				}}
			/>
			<ul {...blockProps}>
				<StyleEngine attributes={attributes} clientId={clientId} />
				{0 !== reportMaterials.length &&
				reportMaterials.map((material, i) => {
					const type = material?.type;
					const icon = type ? getItemIcon(type) : null;
					return (
						<li
							key={i}
							className="wp-block-prc-block-report-materials__list-item flex-align-center"
						>
							{null !== icon && <Icon icon={icon} />}
							<span>{getItemLabel(material)}</span>
						</li>
					);
				})}
			</ul>
		</>
	);
}

export default withColors(
	{ activeBackgroundColor: 'color' },
	{ activeTextColor: 'color' },
	{ hoverBackgroundColor: 'color' },
	{ hoverTextColor: 'color' },
)(Edit);
