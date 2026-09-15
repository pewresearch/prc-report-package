/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useCallback, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import AddChildModal from './add-child-modal';
import CreateDraftModal from './create-draft-modal';
import { getPendingChapterFromEntity } from './get-pending-chapter';
import { usePostReportPackage } from '../context';

const CHAPTER_ENTITY_STATUS = [
	'publish',
	'draft',
	'future',
	'pending',
	'private',
];

export default function ChapterPostSearch({ hocOnChange = false }) {
	const { parentId, parentPostTitle, postId } = usePostReportPackage();
	const [addChildModalOpen, setAddChildModalOpen] = useState(false);
	const [createDraftModalOpen, setCreateDraftModalOpen] = useState(false);
	const [pendingChild, setPendingChild] = useState(null);

	const handleSelectEntity = useCallback(
		(entity) => {
			const next = getPendingChapterFromEntity(entity, parentId, postId);
			if (!next) {
				return;
			}
			setPendingChild(next);
			setAddChildModalOpen(true);
		},
		[parentId, postId]
	);

	const handleDenyLink = useCallback(() => {
		setAddChildModalOpen(false);
		setPendingChild(null);
	}, []);

	const handleConfirmLink = useCallback(() => {
		const entityId = pendingChild?.entityId;
		setAddChildModalOpen(false);
		setPendingChild(null);
		if (false !== hocOnChange && entityId) {
			hocOnChange(entityId);
		}
	}, [hocOnChange, pendingChild]);

	const handleDenyDraft = useCallback(() => {
		setCreateDraftModalOpen(false);
	}, []);

	const handleConfirmDraft = useCallback(
		(newPostId) => {
			setCreateDraftModalOpen(false);
			if (false !== hocOnChange && newPostId) {
				hocOnChange(newPostId);
			}
		},
		[hocOnChange]
	);

	return (
		<Fragment>
			<WPEntitySearch
				placeholder={__(
					'Search by title or paste an edit URL…',
					'prc-platform-post-report-package'
				)}
				entityType="postType"
				entitySubType="post"
				entityStatus={CHAPTER_ENTITY_STATUS}
				hideChildren={true}
				clearOnSelect={true}
				showExcerpt={true}
				onSelect={handleSelectEntity}
			>
				<Button
					variant="tertiary"
					__next40pxDefaultSize
					style={{ width: '100%', justifyContent: 'center' }}
					onClick={() => {
						setCreateDraftModalOpen(true);
					}}
				>
					{__('Create New Draft', 'prc-platform-post-report-package')}
				</Button>
			</WPEntitySearch>
			{true === createDraftModalOpen && (
				<CreateDraftModal
					parentTitle={parentPostTitle}
					parentId={parentId}
					onConfirm={handleConfirmDraft}
					onDeny={handleDenyDraft}
				/>
			)}
			{true === addChildModalOpen && pendingChild && (
				<AddChildModal
					parentTitle={parentPostTitle}
					childTitle={pendingChild.entityName}
					onConfirm={handleConfirmLink}
					onDeny={handleDenyLink}
				/>
			)}
		</Fragment>
	);
}
