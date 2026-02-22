import { ToolDialogShell } from '../toolDialogHelpers';
import { __, _n, sprintf } from '@wordpress/i18n';
import ToolDialogForm from '../../components/ToolDialogForm';

export const toolName = 'update_posts_find_replace';

export const policy = {
	concurrency: 'serial',
	canRerun: false,
};

export const Renderer = ( {
	args,
	toolDialog,
	runTool,
	onCancel,
	policy: toolPolicy,
	isOpen,
} ) => {
	const search = args.search ?? '';
	const replace = args.replace ?? '';
	const postStatus = args.post_status ?? 'any';
	const status = toolDialog.status || 'idle';
	const error = toolDialog.error || null;
	const result = toolDialog.result || null;
	const canRerun =
		status === 'done'
			? Boolean( result?.dry_run )
			: Boolean( toolPolicy?.canRerun );
	const isPreviewResult = status === 'done' && result?.dry_run;
	const isError = status === 'error';
	const isBlocked = status === 'blocked';
	const total = result?.total ?? 0;
	const changed = Array.isArray( result?.changed ) ? result.changed : [];

	const handleRun = ( values ) => {
		runTool( toolDialog.id, values );
	};

	const fields = [
		{
			name: 'search',
			label: __( 'Find', 'clawpress' ),
			type: 'text',
			help: __( 'The text to search for.', 'clawpress' ),
		},
		{
			name: 'replace',
			label: __( 'Replace', 'clawpress' ),
			type: 'text',
			help: __( 'The text to replace with.', 'clawpress' ),
		},
		{
			name: 'post_status',
			label: __( 'Post status', 'clawpress' ),
			type: 'select',
			options: [
				{ value: 'any', label: __( 'Any', 'clawpress' ) },
				{ value: 'publish', label: __( 'Published', 'clawpress' ) },
				{ value: 'draft', label: __( 'Draft', 'clawpress' ) },
			],
		},
		{
			name: 'dry_run',
			label: __( 'Dry run', 'clawpress' ),
			type: 'hidden',
		},
	];

	const title =
		status === 'cancelled'
			? __( 'Update Posts - Find and Replace (Cancelled)', 'clawpress' )
			: __( 'Update Posts - Find and Replace', 'clawpress' );
	let dialogContent = null;

	if ( status !== 'cancelled' ) {
		if ( isError ) {
			dialogContent = (
				<div className="clawpress-tool-result-actions">
					<button
						className="button button-primary"
						type="button"
						onClick={ () =>
							handleRun( {
								...( toolDialog.args || args ),
								dry_run: toolDialog.args?.dry_run !== false,
							} )
						}
					>
						{ __( 'Retry', 'clawpress' ) }
					</button>
					<button
						className="button"
						type="button"
						onClick={ () => onCancel( toolDialog.id ) }
					>
						{ __( 'Cancel', 'clawpress' ) }
					</button>
				</div>
			);
		} else if ( status !== 'done' ) {
			dialogContent = (
				<ToolDialogForm
					fields={ fields }
					initialValues={ {
						search,
						replace,
						post_status: postStatus,
						dry_run: true,
					} }
					disabled={ status === 'running' || isBlocked }
					onSubmit={ ( values ) =>
						handleRun( {
							...values,
							dry_run: true,
						} )
					}
					onRun={ ( values ) =>
						handleRun( {
							...values,
							dry_run: false,
						} )
					}
					onCancel={ () => onCancel( toolDialog.id ) }
				/>
			);
		} else {
			dialogContent = (
				<div className="clawpress-tool-result">
					<div className="clawpress-tool-result-summary">
						<span>
							{ isPreviewResult
								? __( 'Preview Results', 'clawpress' )
								: __( 'Results', 'clawpress' ) }
						</span>
					</div>
					{ total === 0 ? (
						<p>{ __( 'No matches found.', 'clawpress' ) }</p>
					) : (
						<details className="clawpress-tool-result-list" open>
							<summary>
								{ sprintf(
									/* translators: %d: number of changed posts */
									__( 'Changed posts (%d)', 'clawpress' ),
									total
								) }
							</summary>
							<ul>
								{ changed.map( ( item ) => (
									<li key={ item.id }>
										<span className="clawpress-tool-result-title">
											{ item.title ||
												__( 'Untitled', 'clawpress' ) }
										</span>
										<span className="clawpress-tool-result-meta">
											{ sprintf(
												/* translators: %d: post ID */
												__( 'ID %d', 'clawpress' ),
												item.id
											) }
										</span>
										<span className="clawpress-tool-result-meta">
											{ sprintf(
												/* translators: %d: number of replacements in a post */
												_n(
													'%d change',
													'%d changes',
													item.count,
													'clawpress'
												),
												item.count
											) }
										</span>
									</li>
								) ) }
							</ul>
						</details>
					) }
					{ isPreviewResult ? (
						<div className="clawpress-tool-result-actions">
							<button
								className="button button-primary"
								type="button"
								onClick={ () =>
									handleRun( {
										search,
										replace,
										post_status: postStatus,
										dry_run: false,
									} )
								}
							>
								{ __( 'Run', 'clawpress' ) }
							</button>
							<button
								className="button"
								type="button"
								onClick={ () => onCancel( toolDialog.id ) }
							>
								{ __( 'Cancel', 'clawpress' ) }
							</button>
						</div>
					) : null }
				</div>
			);
		}
	}

	return (
		<ToolDialogShell
			status={ status }
			title={ title }
			canRerun={ canRerun }
			policy={ toolPolicy }
			isOpen={ isOpen }
			error={ error }
			showActions={ false }
			onRun={ ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				handleRun( {
					search,
					replace,
					post_status: postStatus,
					dry_run: false,
				} );
			} }
			onCancel={ ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				onCancel( toolDialog.id );
			} }
		>
			{ dialogContent }
		</ToolDialogShell>
	);
};
