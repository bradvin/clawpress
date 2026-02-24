import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ToolDialog from './ToolDialog';
import PanelCard from './PanelCard';
import { getToolPolicy } from '../utils/toolDialogRenderers';

const renderSimpleMarkdown = ( text ) => {
	if ( typeof text !== 'string' || ! text ) {
		return text || '';
	}

	const tokens = text.split( /(`[^`\n]+`|\*\*[^*\n]+?\*\*)/g );

	return tokens.map( ( token, index ) => {
		const strongMatch = token.match( /^\*\*([^*\n]+?)\*\*$/ );
		if ( strongMatch ) {
			return (
				<strong key={ `strong-${ index }` }>
					{ strongMatch[ 1 ] }
				</strong>
			);
		}

		const codeMatch = token.match( /^`([^`\n]+)`$/ );
		if ( codeMatch ) {
			return <code key={ `code-${ index }` }>{ codeMatch[ 1 ] }</code>;
		}

		return token;
	} );
};

const PanelMessages = ( {
	messages,
	streaming,
	currentStreamText,
	waitingForResponse,
	toolDialogs,
	onRunToolDialog,
	onCancelToolDialog,
	onSendCardAction,
} ) => {
	const containerRef = useRef( null );
	const inProgressStatusText = __(
		'I am still working on this',
		'clawpress'
	);
	const isInProgressStatusMessage = ( content ) =>
		typeof content === 'string' && content.trim() === inProgressStatusText;

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container ) {
			return;
		}
		container.scrollTop = container.scrollHeight;
	}, [
		messages,
		toolDialogs,
		streaming,
		currentStreamText,
		waitingForResponse,
	] );

	const serialCandidates = toolDialogs
		.filter(
			( dialog ) =>
				getToolPolicy( dialog.function?.name || '' ).concurrency ===
					'serial' &&
				dialog.status !== 'done' &&
				dialog.status !== 'cancelled'
		)
		.sort( ( a, b ) => a.createdAt - b.createdAt );

	const openId =
		serialCandidates[ 0 ]?.id ||
		toolDialogs[ toolDialogs.length - 1 ]?.id ||
		null;

	const latestMessageIndex = messages.length - 1;
	const latestMessage =
		latestMessageIndex >= 0 ? messages[ latestMessageIndex ] : null;
	const latestInProgressStatusVisible = isInProgressStatusMessage(
		latestMessage?.content || ''
	);

	const items = [
		...messages.map( ( message, index ) => ( {
			type: 'message',
			createdAt: message.createdAt ?? 0,
			messageIndex: index,
			data: message,
		} ) ),
		...toolDialogs.map( ( dialog ) => ( {
			type: 'tool',
			createdAt: dialog.createdAt ?? 0,
			data: dialog,
		} ) ),
	].sort( ( a, b ) => {
		const createdAtDiff = a.createdAt - b.createdAt;
		if ( createdAtDiff !== 0 ) {
			return createdAtDiff;
		}

		if ( a.type === 'message' && b.type === 'message' ) {
			return ( a.messageIndex ?? 0 ) - ( b.messageIndex ?? 0 );
		}

		if ( a.type === 'message' ) {
			return -1;
		}

		if ( b.type === 'message' ) {
			return 1;
		}

		return 0;
	} );

	return (
		<div className="clawpress-messages" ref={ containerRef }>
			{ items.map( ( item ) =>
				item.type === 'message' ? (
					( () => {
						const content = item.data.content || '';
						const isInProgressStatus =
							isInProgressStatusMessage( content );
						const messageRole = isInProgressStatus
							? 'system'
							: item.data.role;
						const isSystem = messageRole === 'system';
						const isAssistant = messageRole === 'assistant';
						const hasCard =
							item.data.card &&
							typeof item.data.card === 'object';
						const isLatestMessageCard =
							hasCard && item.messageIndex === latestMessageIndex;
						const hasEllipsis = /(\.\.\.|…)\s*$/.test( content );
						const showThinking =
							( isSystem && hasEllipsis ) || isInProgressStatus;
						const displayContent = showThinking
							? content.replace( /\s*(\.\.\.|…)\s*$/, '' )
							: content;

						return (
							<div
								key={ item.data.id || item.data.content }
								className={ `clawpress-msg clawpress-${ messageRole }` }
							>
								{ ( isSystem || isAssistant ) &&
								! isInProgressStatus ? (
									<div className="clawpress-msg-label">
										{ isSystem
											? __( 'System', 'clawpress' )
											: __( 'AGENT', 'clawpress' ) }
									</div>
								) : null }
								{ hasCard ? (
									<PanelCard
										card={ item.data.card }
										fallbackText={ displayContent }
										onSendAction={ onSendCardAction }
										isBusy={
											streaming ||
											waitingForResponse ||
											! isLatestMessageCard
										}
									/>
								) : (
									<div
										className={ `clawpress-msg-content${
											showThinking
												? ' clawpress-thinking'
												: ''
										}` }
									>
										{ renderSimpleMarkdown(
											displayContent
										) }
									</div>
								) }
							</div>
						);
					} )()
				) : (
					<ToolDialog
						key={ item.data.id }
						toolDialog={ item.data }
						isOpen={ item.data.id === openId }
						onRunTool={ onRunToolDialog }
						onCancel={ onCancelToolDialog }
					/>
				)
			) }
			{ waitingForResponse &&
			! currentStreamText &&
			! latestInProgressStatusVisible ? (
				<div className="clawpress-msg clawpress-system">
					<div className="clawpress-msg-content clawpress-thinking">
						{ __( 'Thinking', 'clawpress' ) }
					</div>
				</div>
			) : null }
			{ streaming && currentStreamText ? (
				<div className="clawpress-msg clawpress-assistant">
					<div className="clawpress-msg-label">
						{ __( 'AGENT', 'clawpress' ) }
					</div>
					<div className="clawpress-msg-content">
						{ renderSimpleMarkdown( currentStreamText || '...' ) }
					</div>
				</div>
			) : null }
		</div>
	);
};

export default PanelMessages;
