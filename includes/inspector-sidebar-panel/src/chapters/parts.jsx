/**
 * External Dependencies
 */
import { List } from 'react-movable';
import styled from '@emotion/styled';

/**
 * WordPress Dependencies
 */
import { BaseControl, Button, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal Dependencies
 */
import { randomId } from '../utils';
import { usePostReportPackage } from '../context';
import ListItem from '../list-item';

const StyledSelect = styled(SelectControl)`
	option {
		overflow-y: hidden;
		padding: 0.5em;
		&:hover {
			background: #f1f1f1;
		}
	}
`;

const StyledBaseControl = styled(BaseControl)`
	margin-top: 1em;
`;

export default function Parts() {
	const ITEMS_TYPE = 'parts';
	const {
		parts,
		reorder,
		append,
		remove,
		updateItem,
		chapters,
		isResolving,
		postType,
		allowEditing,
	} = usePostReportPackage();

	const chapterOptions = useSelect(
		(select) => {
			if (isResolving || !Array.isArray(chapters)) {
				return [];
			}
			return chapters
				.filter((ch) => ch.postId)
				.map((ch) => {
					const post = select('core').getEntityRecord(
						'postType',
						postType,
						ch.postId
					);
					if (!post) {
						return null;
					}
					return {
						value: ch.postId,
						label: decodeEntities(post.title.rendered),
					};
				})
				.filter(Boolean);
		},
		[chapters, isResolving, postType]
	);

	return (
		<StyledBaseControl label="Parts" id="parts-list">
			<List
				lockVertically
				values={parts ?? []}
				onChange={({ oldIndex, newIndex }) =>
					reorder(oldIndex, newIndex, ITEMS_TYPE)
				}
				renderList={({ children, props }) => (
					<div {...props}>{children}</div>
				)}
				renderItem={({ value, props, index }) => (
					<div {...props}>
						<ListItem
							key={value.key}
							defaultLabel="Part"
							label={value.label}
							displayLabelAsInput
							onLabelUpdate={(newLabel) =>
								updateItem(index, 'label', newLabel, ITEMS_TYPE)
							}
							index={index}
							onRemove={() => remove(index, ITEMS_TYPE)}
						>
							<div>
								<p>Chapters</p>
								<StyledSelect
									multiple
									label={'Chapters'}
									value={value.items ?? []}
									onChange={(selectedChapters) => {
										updateItem(
											index,
											'items',
											selectedChapters,
											ITEMS_TYPE
										);
									}}
									options={chapterOptions}
									__nextHasNoMarginBottom
								/>
							</div>
						</ListItem>
					</div>
				)}
			/>
			<Button
				variant="primary"
				disabled={!allowEditing}
				onClick={() =>
					append(
						randomId(),
						{
							label: '',
						},
						ITEMS_TYPE
					)
				}
			>
				Add Part
			</Button>
		</StyledBaseControl>
	);
}
