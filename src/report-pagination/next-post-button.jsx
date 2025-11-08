/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

export default function NextPostButton({
	nextPost,
	backgroundColor,
	textColor,
	boxShadowColor,
}) {
	if (!nextPost) {
		return null;
	}
	const { title, link } = nextPost;

	return (
		<a
			href={link}
			className="wp-block-prc-block-report-pagination__next-post-button"
			alt={__(`Go to the next post in this report package: ${title}`)}
		>
			{title}
		</a>
	);
}
