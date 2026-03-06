import { useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const formatCompactTokens = ( rawValue ) => {
	const value = Number( rawValue );
	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return '0';
	}

	if ( value < 1000 ) {
		return String( Math.round( value ) );
	}

	const units = [
		{ threshold: 1000000000, suffix: 'b' },
		{ threshold: 1000000, suffix: 'm' },
		{ threshold: 1000, suffix: 'k' },
	];

	for ( const unit of units ) {
		if ( value >= unit.threshold ) {
			const scaled = value / unit.threshold;
			const precision = scaled >= 100 ? 0 : 1;
			return `${ scaled.toFixed( precision ).replace( /\.0$/, '' ) }${
				unit.suffix
			}`;
		}
	}

	return String( Math.round( value ) );
};

const PanelInput = ( {
	input,
	onInputChange,
	onSend,
	onStop,
	streaming,
	panelOpen,
	suggestions,
	contextUsage,
	onSendSuggestion,
	onHistoryUp,
	onHistoryDown,
} ) => {
	const textareaRef = useRef( null );
	const wasStreamingRef = useRef( streaming );
	const normalizedUsedTokens = Number( contextUsage?.usedTokens );
	const normalizedContextWindowTokens = Number(
		contextUsage?.contextWindowTokens
	);
	const normalizedPercentUsed = Number( contextUsage?.percentUsed );

	const usedTokens =
		Number.isFinite( normalizedUsedTokens ) && normalizedUsedTokens >= 0
			? Math.round( normalizedUsedTokens )
			: 0;
	const contextWindowTokens =
		Number.isFinite( normalizedContextWindowTokens ) &&
		normalizedContextWindowTokens > 0
			? Math.round( normalizedContextWindowTokens )
			: 0;
	let resolvedPercentUsed = null;
	if (
		Number.isFinite( normalizedPercentUsed ) &&
		normalizedPercentUsed >= 0
	) {
		resolvedPercentUsed = Math.max(
			0,
			Math.min( 100, Math.round( normalizedPercentUsed ) )
		);
	} else if ( contextWindowTokens > 0 ) {
		resolvedPercentUsed = Math.max(
			0,
			Math.min(
				100,
				Math.round( ( usedTokens / contextWindowTokens ) * 100 )
			)
		);
	}
	const resolvedPercentLeft =
		resolvedPercentUsed === null
			? null
			: Math.max( 0, 100 - resolvedPercentUsed );
	const hasContextUsage =
		contextWindowTokens > 0 &&
		resolvedPercentUsed !== null &&
		usedTokens >= 0;
	const contextUsageSummary =
		hasContextUsage && resolvedPercentLeft !== null
			? sprintf(
					/* translators: 1: percentage used, 2: percentage left */
					__( '%1$d%% used (%2$d%% left)', 'clawpress' ),
					resolvedPercentUsed,
					resolvedPercentLeft
			  )
			: '';
	const contextTokensSummary = hasContextUsage
		? sprintf(
				/* translators: 1: used tokens, 2: available context-window tokens */
				__( '%1$s / %2$s tokens used', 'clawpress' ),
				formatCompactTokens( usedTokens ),
				formatCompactTokens( contextWindowTokens )
		  )
		: '';

	useEffect( () => {
		const wasStreaming = wasStreamingRef.current;
		wasStreamingRef.current = streaming;

		if ( ! wasStreaming || streaming || ! panelOpen ) {
			return;
		}

		const textarea = textareaRef.current;
		if ( ! textarea ) {
			return;
		}

		textarea.focus();
		const end = textarea.value.length;
		textarea.setSelectionRange( end, end );
	}, [ streaming, panelOpen ] );

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			onSend();
		}

		if ( e.key === 'ArrowUp' ) {
			const el = textareaRef.current;
			const cursor = el?.selectionStart ?? 0;
			const before = input.slice( 0, cursor );
			const isFirstLine = ! before.includes( '\n' );
			const isEmpty = input.trim() === '';

			if ( ( isEmpty || isFirstLine ) && onHistoryUp ) {
				e.preventDefault();
				const value = onHistoryUp( input );
				if ( value === null || value === undefined ) {
					return;
				}
				onInputChange( { target: { value } } );
				setTimeout( () => {
					if ( ! textareaRef.current ) {
						return;
					}
					const end = value.length;
					textareaRef.current.setSelectionRange( end, end );
				}, 0 );
			}
		}

		if ( e.key === 'ArrowDown' ) {
			const el = textareaRef.current;
			const cursor = el?.selectionStart ?? 0;
			const after = input.slice( cursor );
			const isLastLine = ! after.includes( '\n' );
			const isEmpty = input.trim() === '';

			if ( ( isEmpty || isLastLine ) && onHistoryDown ) {
				e.preventDefault();
				const value = onHistoryDown();
				if ( value === null || value === undefined ) {
					return;
				}
				onInputChange( { target: { value } } );
				setTimeout( () => {
					if ( ! textareaRef.current ) {
						return;
					}
					const end = value.length;
					textareaRef.current.setSelectionRange( end, end );
				}, 0 );
			}
		}
	};

	return (
		<div className="clawpress-input">
			{ Array.isArray( suggestions ) && suggestions.length > 0 ? (
				<div
					className="clawpress-suggestions"
					aria-label={ __( 'Suggestions', 'clawpress' ) }
				>
					<div className="clawpress-suggestions-label">
						{ __( 'Suggestions', 'clawpress' ) }
					</div>
					<div className="clawpress-suggestions-list">
						{ suggestions.map( ( command ) => (
							<button
								key={ command }
								className="clawpress-suggestion button button-secondary button-small"
								onClick={ () => onSendSuggestion?.( command ) }
								type="button"
								disabled={ streaming }
							>
								{ command }
							</button>
						) ) }
					</div>
				</div>
			) : null }
			<textarea
				ref={ textareaRef }
				value={ input }
				onChange={ onInputChange }
				onKeyDown={ handleKeyDown }
				placeholder={ __( 'Ask me anything…', 'clawpress' ) }
				disabled={ streaming }
			/>
			<div className="clawpress-input-footer">
				<div className="clawpress-context-slot">
					{ hasContextUsage ? (
						<div
							className="clawpress-context-indicator"
							role="img"
							tabIndex={ 0 }
							aria-label={ sprintf(
								/* translators: 1: context usage summary, 2: token usage summary */
								__(
									'Context window: %1$s. %2$s.',
									'clawpress'
								),
								contextUsageSummary,
								contextTokensSummary
							) }
						>
							<span
								className="clawpress-context-pie"
								style={ {
									'--clawpress-context-used': `${ resolvedPercentUsed }%`,
								} }
								aria-hidden="true"
							/>
							<div
								className="clawpress-context-tooltip"
								role="tooltip"
							>
								<div className="clawpress-context-tooltip-title">
									{ __( 'Context window:', 'clawpress' ) }
								</div>
								<div className="clawpress-context-tooltip-line">
									{ contextUsageSummary }
								</div>
								<div className="clawpress-context-tooltip-line">
									{ contextTokensSummary }
								</div>
								<div className="clawpress-context-tooltip-note">
									{ __(
										'Codex automatically compacts its context',
										'clawpress'
									) }
								</div>
							</div>
						</div>
					) : null }
				</div>
				{ streaming ? (
					<button className="button" onClick={ onStop } type="button">
						{ __( 'Stop', 'clawpress' ) }
					</button>
				) : (
					<button
						className="button button-primary"
						onClick={ onSend }
						type="button"
					>
						{ __( 'Send', 'clawpress' ) }
					</button>
				) }
			</div>
		</div>
	);
};

export default PanelInput;
