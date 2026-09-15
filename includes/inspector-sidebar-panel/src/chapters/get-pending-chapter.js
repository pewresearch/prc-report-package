/**
 * Map a WPEntitySearch result to a chapter-link candidate.
 * Returns null when the entity is not a post, or is the package parent / current post.
 *
 * @param {Object} entity          WPEntitySearch item.
 * @param {number} parentId        Report package parent post id.
 * @param {number} [currentPostId] Post currently being edited.
 * @return {{entityId: number, entityName: string}|null} Pending chapter, or null.
 */
export function getPendingChapterFromEntity(entity, parentId, currentPostId) {
	if (entity?.entityType !== 'postType' || entity?.entitySubType !== 'post') {
		return null;
	}
	const entityId = Number(entity?.entityId);
	if (!Number.isFinite(entityId) || entityId <= 0) {
		return null;
	}
	const blockedIds = [parentId, currentPostId]
		.map((id) => Number(id))
		.filter((id) => Number.isFinite(id) && id > 0);
	if (blockedIds.includes(entityId)) {
		return null;
	}
	const entityName =
		typeof entity?.entityName === 'string' ? entity.entityName : '';
	return { entityId, entityName };
}
