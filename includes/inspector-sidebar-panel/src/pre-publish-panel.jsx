/**
 * WordPress Dependencies
 */
import { PluginPrePublishPanel } from '@wordpress/editor';

/**
 * Internal Dependencies
 */
import { ProvidePostReportPackage, useReportPackageContents } from './context';
import ChaptersPanel from './chapters';
import MaterialsPanel from './materials';

export default function PrePublishPanel({ postType, postId }) {
	const { hasMaterials, hasChapters } = useReportPackageContents(
		postType,
		postId
	);

	if (!hasMaterials && !hasChapters) {
		return null;
	}

	// The provider lives inside the fill because slot-fill renders children in
	// the slot's React tree, so context from outside the fill does not reach them.
	return (
		<PluginPrePublishPanel title="Review Report Package" icon={false}>
			<ProvidePostReportPackage
				{...{ postType, parentId: postId, postId }}
			>
				{hasMaterials && <MaterialsPanel />}
				{hasChapters && <ChaptersPanel />}
			</ProvidePostReportPackage>
		</PluginPrePublishPanel>
	);
}
