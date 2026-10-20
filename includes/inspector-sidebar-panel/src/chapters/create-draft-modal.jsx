/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { store as coreDataStore } from '@wordpress/core-data';
import {
	Modal,
	Button,
	TextControl,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';

export default function CreateDraftModal({ parentTitle, onDeny, onConfirm }) {
	const { saveEntityRecord } = useDispatch(coreDataStore);
	const [postTitle, setPostTitle] = useState('');

	const createPost = async () => {
		const newDraftPost = await saveEntityRecord('postType', 'post', {
			title: postTitle,
			status: 'draft',
		});
		if (newDraftPost) {
			onConfirm(newDraftPost?.id);
		}
	};

	return (
		<Modal
			title={__(
				'Create New Draft Chapter',
				'prc-platform-post-report-package'
			)}
			onRequestClose={() => {
				onDeny();
			}}
		>
			<VStack spacing={6}>
				<p style={{ margin: 0 }}>
					Create a new draft chapter for{' '}
					<strong>{decodeEntities(parentTitle)}</strong>?
				</p>
				<TextControl
					label={__(
						'Chapter Title',
						'prc-platform-post-report-package'
					)}
					value={postTitle}
					onChange={setPostTitle}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<HStack spacing={3}>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						style={{ width: '100%', justifyContent: 'center' }}
						onClick={onDeny}
					>
						Cancel
					</Button>
					<Button
						variant="primary"
						__next40pxDefaultSize
						style={{ width: '100%', justifyContent: 'center' }}
						onClick={createPost}
						disabled={3 > postTitle.length}
					>
						Create Draft
					</Button>
				</HStack>
			</VStack>
		</Modal>
	);
}
