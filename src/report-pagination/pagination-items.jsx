/**
 * External Dependencies
 */
import classNames from 'classnames';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

export default function PaginationItems({
	paginationItems,
	backgroundColor,
	textColor,
	borderColor,
	hoverBackgroundColor,
	activeBackgroundColor,
}) {
	if (!paginationItems) {
		return null;
	}

	return (
		<div className="common-block-style__pagination__pagination-numbers">
			{paginationItems.map((item, index) => {
				const { title, link, isActive } = item;
				const className = classNames(
					'common-block-style__pagination__page-numbers',
					{
						'is-active': isActive,
					}
				);
				return (
					<a
						key={index}
						href={link}
						className={className}
						alt={`Go to page ${index + 1} in report package`}
					>
						{index + 1}
					</a>
				);
			})}
		</div>
	);
}
