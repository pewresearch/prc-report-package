/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { Modal, ButtonGroup, Button } from '@wordpress/components';

export default function AddChildModal({
	parentTitle,
	childTitle,
	onDeny,
	onConfirm,
}) {
	return (
		<Modal
			title={__(
				'Confirm Linking Child Post',
				'prc-platform-post-report-package'
			)}
			onRequestClose={onDeny}
		>
			<p>
				Link <strong>{decodeEntities(childTitle)}</strong> post to{' '}
				<strong>{decodeEntities(parentTitle)}</strong>?
			</p>
			<ButtonGroup>
				<Button
					variant="secondary"
					__next40pxDefaultSize
					style={{ width: '100%', justifyContent: 'center' }}
					onClick={onDeny}
				>
					No
				</Button>
				<Button
					variant="primary"
					__next40pxDefaultSize
					style={{ width: '100%', justifyContent: 'center' }}
					onClick={onConfirm}
				>
					Yes
				</Button>
			</ButtonGroup>
		</Modal>
	);
}
