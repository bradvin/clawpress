import { ToolDialogShell } from './toolDialogHelpers';
import { __ } from '@wordpress/i18n';
import {
	toolName as updatePostsFindReplaceName,
	policy as updatePostsFindReplacePolicy,
	Renderer as UpdatePostsFindReplaceRenderer,
} from './tools/updatePostsFindReplace';

const ToolDefaultRenderer = ( {
	toolName,
	args,
	toolDialog,
	runTool,
	onCancel,
	policy,
	isOpen,
} ) => {
	const status = toolDialog.status || 'idle';
	const error = toolDialog.error || null;
	const result = toolDialog.result || null;
	const canRerun = Boolean( policy?.canRerun );

	return (
		<ToolDialogShell
			status={ status }
			title={ toolName || __( 'Unknown tool', 'clawpress' ) }
			canRerun={ canRerun }
			policy={ policy }
			isOpen={ isOpen }
			error={ error }
			onRun={ ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				runTool( toolDialog.id, toolDialog.args || args );
			} }
			onCancel={ ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				onCancel( toolDialog.id );
			} }
		>
			{ status === 'done' ? (
				<syntax-highlight language="json">
					{ JSON.stringify( result, null, 2 ) }
				</syntax-highlight>
			) : (
				<syntax-highlight language="json">
					{ JSON.stringify( args, null, 2 ) }
				</syntax-highlight>
			) }
			{ toolDialog.diff ? (
				<div className="clawpress-tool-dialog-diff">
					<h4>{ __( 'Changes:', 'clawpress' ) }</h4>
					<syntax-highlight language="json">
						{ JSON.stringify( toolDialog.diff, null, 2 ) }
					</syntax-highlight>
				</div>
			) : null }
		</ToolDialogShell>
	);
};

const registry = {
	[ updatePostsFindReplaceName ]: {
		renderer: UpdatePostsFindReplaceRenderer,
		policy: updatePostsFindReplacePolicy,
	},
};

export const getToolRenderer = ( toolName ) =>
	registry[ toolName ]?.renderer || ToolDefaultRenderer;

export const getToolPolicy = ( toolName ) => registry[ toolName ]?.policy || {};
