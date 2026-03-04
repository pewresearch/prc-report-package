/* eslint-disable max-lines-per-function */
/**
 * External Dependencies
 */

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useState } from '@wordpress/element';
import {
	Button,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { upload, connection } from '@wordpress/icons';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal Dependencies
 */
import { AISuggestButton } from '@prc/components';
import { TypeSelect, getLabel, getOptions } from './type-select';
import { usePostReportPackage } from '../context';
import ListItem from '../list-item';

const ALLOWED_MEDIA_TYPES = [
	'image',
	'application/pdf',
	'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'application/vnd.ms-powerpoint',
	'application/vnd.ms-excel',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

// Types that support each field, used when changing type to determine which
// fields to clear vs. preserve.
const TYPES_WITH_LABEL = ['link', 'promo', 'qA', 'supplemental', 'video'];
const TYPES_WITH_ICON_SELECT = ['link'];
const TYPES_WITH_FILE_UPLOAD = [
	'report',
	'questionnaire',
	'detailedTable',
	'powerpoint',
	'presentation',
	'pressRelease',
	'topline',
];
const TYPES_WITH_ICON_UPLOAD = ['promo'];
const TYPES_WITH_ATTACHMENT = [
	...TYPES_WITH_FILE_UPLOAD,
	...TYPES_WITH_ICON_UPLOAD,
];

const Item = ({ type, url, attachmentId, label, icon, index }) => {
	const ITEMS_TYPE = 'materials';
	const { allowEditing, updateItem, remove, parentId } =
		usePostReportPackage();
	const [popoverVisible, toggleVisibility] = useState(false);
	const [isConverting, setIsConverting] = useState(false);

	const handleConvertTopline = async () => {
		if (!parentId || !attachmentId) {
			return;
		}
		setIsConverting(true);
		try {
			await apiFetch({
				path: '/prc-pdf-extraction/v1/convert',
				method: 'POST',
				data: {
					post_id: parentId,
					attachment_id: attachmentId,
				},
			});
			dispatch(noticesStore).createNotice(
				'info',
				__('Topline extraction is being processed in the background.'),
				{ type: 'snackbar', isDismissible: true }
			);
		} catch (error) {
			dispatch(noticesStore).createNotice(
				'error',
				__('Failed to start topline extraction. Please try again.'),
				{ type: 'snackbar', isDismissible: true }
			);
		} finally {
			setIsConverting(false);
		}
	};

	const UploadFileButton = ({ title, value }) => {
		return (
			<MediaUploadCheck>
				<MediaUpload
					title={
						(value === '' || value === null
							? __('Upload')
							: __('Change')) +
						' ' +
						title
					}
					allowedTypes={ALLOWED_MEDIA_TYPES}
					value={value}
					onSelect={(img) => {
						updateItem(index, 'url', img.url, ITEMS_TYPE);
						updateItem(index, 'attachmentId', img.id, ITEMS_TYPE);
					}}
					render={({ open }) => {
						return (
							<Button
								variant="secondary"
								icon={upload}
								label={
									value === null || value === ''
										? __('Upload File')
										: __('Change File')
								}
								text={
									value === null || value === ''
										? __('Upload')
										: __('Change')
								}
								disabled={!allowEditing}
								onClick={open}
							/>
						);
					}}
				/>
			</MediaUploadCheck>
		);
	};

	const UploadIconButton = ({ title, value }) => {
		return (
			<MediaUploadCheck>
				<MediaUpload
					title={
						(value === null || value === ''
							? __('Upload')
							: __('Change')) +
						' ' +
						title
					}
					value={value}
					onSelect={(img) => {
						updateItem(index, 'icon', img.url, ITEMS_TYPE);
						updateItem(index, 'attachmentId', img.id, ITEMS_TYPE);
					}}
					render={({ open }) => {
						return (
							<Button
								variant="secondary"
								icon={upload}
								label={
									value === null || value === ''
										? __('Upload Icon')
										: __('Change Icon')
								}
								text={
									value === null || value === ''
										? __('Upload')
										: __('Change')
								}
								disabled={!allowEditing}
								onClick={open}
							/>
						);
					}}
				/>
			</MediaUploadCheck>
		);
	};

	return (
		<ListItem
			label={getLabel(type)}
			index={index}
			onRemove={() => remove(index, ITEMS_TYPE)}
		>
			<div
				style={{
					paddingTop: '10px',
				}}
			>
				{['presentation', 'pressRelease'].includes(type) && (
					<TextControl
						autoComplete={false}
						label="URL"
						value={url}
						onChange={(u) =>
							updateItem(index, 'url', u, ITEMS_TYPE)
						}
						disabled={!allowEditing}
					/>
				)}
				{['link', 'promo', 'qA', 'supplemental', 'video'].includes(
					type
				) && (
					<Fragment>
						<TextControl
							autoComplete={false}
							label="Label"
							value={label}
							onChange={(c) =>
								updateItem(index, 'label', c, ITEMS_TYPE)
							}
							disabled={!allowEditing}
						/>
						<TextControl
							autoComplete={false}
							label="URL"
							value={url}
							onChange={(u) =>
								updateItem(index, 'url', u, ITEMS_TYPE)
							}
							disabled={!allowEditing}
						/>
						{'link' === type && (
							<SelectControl
								label="Icon"
								value={icon}
								options={getOptions()}
								onChange={(t) =>
									updateItem(index, 'icon', t, ITEMS_TYPE)
								}
								disabled={!allowEditing}
							/>
						)}
					</Fragment>
				)}
				<VStack spacing={2}>
					<HStack spacing={2} role="group" justify="flex-start">
						<Button
							variant="secondary"
							icon={connection}
							label={__('Change Type')}
							onClick={() => {
								toggleVisibility(true);
							}}
							disabled={!allowEditing}
						/>
						{popoverVisible && (
							<TypeSelect
								type={type}
								onChange={(newType) => {
									// Update the type.
									updateItem(
										index,
										'type',
										newType,
										ITEMS_TYPE
									);
									// Only clear fields the new type doesn't support;
									// preserve everything else so users don't lose data
									// when switching between similar types.
									if (!TYPES_WITH_LABEL.includes(newType)) {
										updateItem(
											index,
											'label',
											'',
											ITEMS_TYPE
										);
									}
									if (
										!TYPES_WITH_ICON_SELECT.includes(
											newType
										)
									) {
										updateItem(
											index,
											'icon',
											'',
											ITEMS_TYPE
										);
									}
									if (
										!TYPES_WITH_ATTACHMENT.includes(newType)
									) {
										updateItem(
											index,
											'attachmentId',
											null,
											ITEMS_TYPE
										);
									}
									toggleVisibility(false);
								}}
								toggleVisibility={toggleVisibility}
							/>
						)}
						{[
							'report',
							'questionnaire',
							'detailedTable',
							'powerpoint',
							'presentation',
							'pressRelease',
							'topline',
						].includes(type) && (
							<UploadFileButton
								title={getLabel(type)}
								value={attachmentId}
							/>
						)}
						{'promo' === type && (
							<UploadIconButton
								title={getLabel(type)}
								value={attachmentId}
							/>
						)}
						{'topline' === type && attachmentId && (
							<AISuggestButton
								variant="primary"
								disabled={!allowEditing}
								isLoading={isConverting}
								onClick={handleConvertTopline}
								label={
									isConverting
										? __('Extracting…')
										: __('Extract with AI')
								}
								text={null}
								fullWidth={false}
							/>
						)}
					</HStack>
				</VStack>
			</div>
		</ListItem>
	);
};

export default Item;
