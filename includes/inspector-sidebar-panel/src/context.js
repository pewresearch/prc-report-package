/* eslint-disable max-lines-per-function */
/**
 * WordPress Dependencies
 */
import {
	useContext,
	createContext,
	useRef,
	useCallback,
	useMemo,
} from '@wordpress/element';
import {
	useEntityRecord,
	useResourcePermissions,
	useEntityProp,
} from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';

const postReportPackageContext = createContext();

const META_KEY_MAP = {
	materials: 'reportMaterials',
	chapters: 'multiSectionReport',
	parts: 'package_parts',
};

const usePostReportPackageContext = (parentId, postType, postId) => {
	const isChild = useMemo(() => parentId !== postId, [parentId, postId]);

	const { record, isResolving } = useEntityRecord(
		'postType',
		postType,
		parentId
	);
	const [meta, setMeta] = useEntityProp(
		'postType',
		postType,
		'meta',
		parentId
	);
	const { canDelete, canUpdate } = useResourcePermissions('posts', parentId);

	// Read directly from meta — no local state copies.
	const materials = meta?.reportMaterials ?? [];
	const chapters = meta?.multiSectionReport ?? [];
	const parts = meta?.package_parts ?? [];
	const enableParts = meta?.package_parts__enabled ?? false;

	// Refs for accessing current values inside stable callbacks.
	// Eagerly updated after each mutation so rapid consecutive calls
	// (e.g. "Change Type" updating type, url, label, icon in one click)
	// each see the previous call's result.
	const metaRef = useRef(meta);
	metaRef.current = meta;
	const materialsRef = useRef(materials);
	materialsRef.current = materials;
	const chaptersRef = useRef(chapters);
	chaptersRef.current = chapters;
	const partsRef = useRef(parts);
	partsRef.current = parts;
	const enablePartsRef = useRef(enableParts);
	enablePartsRef.current = enableParts;

	/**
	 * Write a single meta key and eagerly update refs so
	 * back-to-back calls within the same tick see each other.
	 */
	const writeMeta = useCallback(
		(metaKey, value) => {
			const next = { ...metaRef.current, [metaKey]: value };
			metaRef.current = next;
			setMeta(next);
		},
		[setMeta]
	);

	const parentPost = useMemo(() => {
		if (isResolving || !record) {
			return undefined;
		}
		return record;
	}, [record, isResolving]);

	const parentPostTitle = useMemo(() => {
		if (parentPost) {
			return decodeEntities(parentPost.title.rendered);
		}
		return '';
	}, [parentPost]);

	const allowEditing = useMemo(() => {
		if (isResolving) {
			return false;
		}
		return canDelete && canUpdate;
	}, [isResolving, canDelete, canUpdate]);

	const hasChapters = useMemo(() => {
		return Array.isArray(chapters) && chapters.length > 0;
	}, [chapters]);

	const getRefByItemType = useCallback((itemsType) => {
		if ('materials' === itemsType) return materialsRef;
		if ('chapters' === itemsType) return chaptersRef;
		if ('parts' === itemsType) return partsRef;
		return null;
	}, []);

	const reorder = useCallback(
		(oldIndex, newIndex, itemsType = 'materials') => {
			const ref = getRefByItemType(itemsType);
			const metaKey = META_KEY_MAP[itemsType];
			if (!ref || !metaKey) return;
			const next = Array.isArray(ref.current) ? [...ref.current] : [];
			const [item] = next.splice(oldIndex, 1);
			next.splice(newIndex, 0, item);
			ref.current = next;
			writeMeta(metaKey, next);
		},
		[writeMeta, getRefByItemType]
	);

	const append = useCallback(
		(key, value = {}, itemsType = 'materials') => {
			const ref = getRefByItemType(itemsType);
			const metaKey = META_KEY_MAP[itemsType];
			if (!ref || !metaKey) return;
			const next = [
				...(Array.isArray(ref.current) ? ref.current : []),
				{ key, ...value },
			];
			ref.current = next;
			writeMeta(metaKey, next);
		},
		[writeMeta, getRefByItemType]
	);

	const remove = useCallback(
		(index, itemsType = 'materials') => {
			const ref = getRefByItemType(itemsType);
			const metaKey = META_KEY_MAP[itemsType];
			if (!ref || !metaKey) return;
			const next = Array.isArray(ref.current)
				? ref.current.filter((_, i) => i !== index)
				: [];
			ref.current = next;
			writeMeta(metaKey, next);
		},
		[writeMeta, getRefByItemType]
	);

	const updateItem = useCallback(
		(index, valueKey, value, itemsType = 'materials') => {
			const ref = getRefByItemType(itemsType);
			const metaKey = META_KEY_MAP[itemsType];
			if (!ref || !metaKey) return;
			const next = Array.isArray(ref.current)
				? ref.current.map((item, i) =>
						i === index ? { ...item, [valueKey]: value } : item
				  )
				: [];
			ref.current = next;
			writeMeta(metaKey, next);
		},
		[writeMeta, getRefByItemType]
	);

	const toggleParts = useCallback(() => {
		writeMeta('package_parts__enabled', !enablePartsRef.current);
	}, [writeMeta]);

	return {
		isChild,
		isResolving,
		allowEditing,
		parentId,
		postId,
		postType,
		parentPost,
		parentPostTitle,
		hasChapters,
		enableParts,
		materials,
		chapters,
		parts,
		reorder,
		append,
		remove,
		updateItem,
		toggleParts,
	};
};

const usePostReportPackage = () => useContext(postReportPackageContext);

function ProvidePostReportPackage({ parentId, postType, postId, children }) {
	const provider = usePostReportPackageContext(parentId, postType, postId);
	return (
		<postReportPackageContext.Provider value={provider}>
			{children}
		</postReportPackageContext.Provider>
	);
}

export { ProvidePostReportPackage, usePostReportPackage };
export default ProvidePostReportPackage;
