import { __ } from '@wordpress/i18n';

const getRunLabel = ( status, canRerun ) => {
	if ( status === 'running' ) {
		return __( 'Running…', 'clawpress' );
	}
	if ( status === 'blocked' ) {
		return __( 'Blocked', 'clawpress' );
	}
	if ( status === 'done' && canRerun ) {
		return __( 'Re-run', 'clawpress' );
	}
	if ( status === 'error' ) {
		return __( 'Retry', 'clawpress' );
	}
	return __( 'Run', 'clawpress' );
};

const getStatusMessage = ( status, policy ) => {
	if ( status === 'running' ) {
		return __( 'Running…', 'clawpress' );
	}
	if ( status === 'blocked' ) {
		return policy?.concurrency === 'serial'
			? __( 'Blocked (waiting for earlier tool).', 'clawpress' )
			: __( 'Blocked.', 'clawpress' );
	}
	return null;
};

export const ToolDialogShell = ( {
	status,
	title,
	canRerun,
	policy,
	isOpen,
	error,
	children,
	onRun,
	onCancel,
	showActions: showActionsOverride,
	runLabelOverride,
} ) => {
	const runLabel = runLabelOverride || getRunLabel( status, canRerun );
	const isCancelled = status === 'cancelled';
	const showErrorIcon = status === 'error';
	const showActions =
		showActionsOverride !== undefined
			? showActionsOverride
			: status !== 'running' &&
			  ! isCancelled &&
			  ( status !== 'done' || canRerun );
	const isBlocked = status === 'blocked';
	const statusMessage = getStatusMessage( status, policy );

	return (
		<details
			className="clawpress-tool-dialog"
			open={ isCancelled ? false : isOpen }
		>
			<summary className="clawpress-tool-dialog-summary">
				<span className="clawpress-tool-dialog-heading">
					<span
						className={ `clawpress-tool-dialog-status clawpress-tool-dialog-status-${ status }` }
						aria-hidden="true"
					/>
					<span className="clawpress-tool-dialog-title">
						{ title }
					</span>
				</span>
				{ showActions ? (
					<span className="clawpress-tool-dialog-actions">
						{ showErrorIcon ? (
							<span
								className="clawpress-tool-dialog-error-icon"
								aria-hidden="true"
							>
								<svg viewBox="0 0 24 24" role="img">
									<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 6a1 1 0 0 1 1 1v5a1 1 0 1 1-2 0V9a1 1 0 0 1 1-1Zm0 10a1.25 1.25 0 1 1 0-2.5A1.25 1.25 0 0 1 12 18Z" />
								</svg>
							</span>
						) : null }
						<button
							className="button button-primary"
							type="button"
							onClick={ onRun }
							disabled={
								status === 'running' || isBlocked || isCancelled
							}
						>
							{ runLabel }
						</button>
						<button
							className="button"
							type="button"
							onClick={ onCancel }
						>
							{ __( 'Cancel', 'clawpress' ) }
						</button>
					</span>
				) : null }
			</summary>
			<div className="clawpress-tool-dialog-body">
				{ isCancelled ? (
					<p>{ __( 'Cancelled.', 'clawpress' ) }</p>
				) : null }
				{ statusMessage ? <p>{ statusMessage }</p> : null }
				{ status === 'error' ? <p>{ error }</p> : null }
				{ children }
			</div>
		</details>
	);
};
