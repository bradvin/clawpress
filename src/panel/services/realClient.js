import { __, sprintf } from '@wordpress/i18n';

const RUN_POLL_INTERVAL_MS = 500;
const RUN_POLL_MAX_SECONDS = 180;
const TERMINAL_RUN_STATUSES = new Set( [
	'done',
	'success',
	'error',
	'timeout',
	'requires_confirmation',
	'failed',
	'cancelled',
	'canceled',
] );

const requestJson = async ( { url, method = 'GET', nonce, body, signal } ) => {
	const res = await fetch( url, {
		method,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: body ? JSON.stringify( body ) : undefined,
		signal,
	} );

	const text = await res.text();
	let payload = {};

	if ( text ) {
		try {
			payload = JSON.parse( text );
		} catch {
			payload = { message: text };
		}
	}

	if ( ! res.ok ) {
		const message =
			payload?.message ||
			payload?.error ||
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Request failed (%d)', 'clawpress' ),
				res.status
			);
		throw new Error( message );
	}

	return payload;
};

const isObjectRecord = ( value ) =>
	Boolean( value ) && typeof value === 'object' && ! Array.isArray( value );

const createRealClient = ( { restBase, nonce, onEvent, onDone, onError } ) => {
	const sendMessage = ( message, signal ) =>
		requestJson( {
			url: `${ restBase }/chat/message`,
			method: 'POST',
			nonce,
			body: { message },
			signal,
		} );

	const getRunStatus = ( runId, signal ) =>
		requestJson( {
			url: `${ restBase }/agent/runs/${ runId }`,
			method: 'GET',
			nonce,
			signal,
		} );

	const getRunEvents = ( runId, after, signal ) =>
		requestJson( {
			url: `${ restBase }/agent/runs/${ runId }/events?after=${ after }&limit=100`,
			method: 'GET',
			nonce,
			signal,
		} );

	const getHistory = () =>
		requestJson( {
			url: `${ restBase }/chat/history`,
			method: 'GET',
			nonce,
		} );

	const getStatus = () =>
		requestJson( {
			url: `${ restBase }/status`,
			method: 'GET',
			nonce,
		} );

	const getPanelState = () =>
		requestJson( {
			url: `${ restBase }/panel/state`,
			method: 'GET',
			nonce,
		} );

	const setPanelState = ( state ) =>
		requestJson( {
			url: `${ restBase }/panel/state`,
			method: 'POST',
			nonce,
			body: state,
		} );

	const waitForNextPoll = ( signal ) =>
		new Promise( ( resolve, reject ) => {
			const timerId = setTimeout( () => {
				signal?.removeEventListener( 'abort', onAbort );
				resolve();
			}, RUN_POLL_INTERVAL_MS );

			const onAbort = () => {
				clearTimeout( timerId );
				signal?.removeEventListener( 'abort', onAbort );
				const abortError = new Error( 'Aborted' );
				abortError.name = 'AbortError';
				reject( abortError );
			};

			signal?.addEventListener( 'abort', onAbort, { once: true } );
		} );

	const normalizeCard = ( rawCard ) => {
		if ( ! rawCard || typeof rawCard !== 'object' ) {
			return null;
		}

		const type =
			typeof rawCard.type === 'string' ? rawCard.type.trim() : '';
		if ( ! type ) {
			return null;
		}

		const data =
			rawCard.data &&
			typeof rawCard.data === 'object' &&
			! Array.isArray( rawCard.data )
				? rawCard.data
				: {};

		return { type, data };
	};

	const normalizeContextUsage = ( rawContext ) => {
		if ( ! rawContext || typeof rawContext !== 'object' ) {
			return null;
		}

		const toPositiveNumber = ( value ) => {
			const numeric = Number( value );
			if ( ! Number.isFinite( numeric ) || numeric < 0 ) {
				return null;
			}
			return Math.round( numeric );
		};

		const toNullablePercent = ( value ) => {
			if ( value === null || value === undefined ) {
				return null;
			}
			const numeric = Number( value );
			if ( ! Number.isFinite( numeric ) ) {
				return null;
			}
			return Math.max( 0, Math.min( 100, Math.round( numeric ) ) );
		};

		const promptTokens = toPositiveNumber( rawContext.prompt_tokens ) ?? 0;
		const completionTokens =
			toPositiveNumber( rawContext.completion_tokens ) ?? 0;
		const totalTokens = toPositiveNumber( rawContext.total_tokens ) ?? 0;
		const usedTokens =
			toPositiveNumber( rawContext.used_tokens ) ??
			( promptTokens > 0 ? promptTokens : totalTokens );
		const contextWindowTokens =
			toPositiveNumber( rawContext.context_window_tokens ) ?? null;

		if (
			promptTokens === 0 &&
			completionTokens === 0 &&
			totalTokens === 0 &&
			usedTokens === 0 &&
			contextWindowTokens === null
		) {
			return null;
		}

		const percentUsed = toNullablePercent( rawContext.percent_used );
		const percentLeft = toNullablePercent( rawContext.percent_left );
		const windowIsEstimated =
			typeof rawContext.window_is_estimated === 'boolean'
				? rawContext.window_is_estimated
				: null;

		return {
			promptTokens,
			completionTokens,
			totalTokens,
			usedTokens,
			contextWindowTokens,
			percentUsed,
			percentLeft,
			windowIsEstimated,
		};
	};

	const normalizeToolCall = ( rawCall ) => {
		if ( ! rawCall || typeof rawCall !== 'object' ) {
			return null;
		}

		const normalizeStatus = ( value ) => {
			const normalized =
				typeof value === 'string' ? value.trim().toLowerCase() : '';
			if (
				normalized === 'success' ||
				normalized === 'error' ||
				normalized === 'requires_confirmation'
			) {
				return normalized;
			}
			return 'success';
		};

		const name =
			typeof rawCall.name === 'string' ? rawCall.name.trim() : '';
		if ( ! name ) {
			return null;
		}

		const ability =
			typeof rawCall.ability === 'string' ? rawCall.ability.trim() : '';
		const args =
			rawCall.args &&
			typeof rawCall.args === 'object' &&
			! Array.isArray( rawCall.args )
				? rawCall.args
				: {};
		const status = normalizeStatus( rawCall.status );
		const message =
			typeof rawCall.message === 'string' && rawCall.message.trim()
				? rawCall.message.trim()
				: '';
		const round = Number.isFinite( Number( rawCall.round ) )
			? Math.max( 1, Math.round( Number( rawCall.round ) ) )
			: 1;
		const sequence = Number.isFinite( Number( rawCall.sequence ) )
			? Math.max( 1, Math.round( Number( rawCall.sequence ) ) )
			: 1;
		const requiresConfirmation =
			typeof rawCall.requires_confirmation === 'boolean'
				? rawCall.requires_confirmation
				: status === 'requires_confirmation';

		return {
			name,
			ability: ability || null,
			args,
			status,
			message: message || null,
			round,
			sequence,
			requiresConfirmation,
		};
	};

	const normalizeRunStatus = ( value ) =>
		typeof value === 'string' ? value.trim().toLowerCase() : '';

	const buildToolCallDedupKey = ( call ) => {
		if ( ! call || typeof call !== 'object' ) {
			return '';
		}

		const name = typeof call.name === 'string' ? call.name.trim() : '';
		if ( ! name ) {
			return '';
		}

		const status =
			typeof call.status === 'string'
				? call.status.trim().toLowerCase()
				: '';
		const message =
			typeof call.message === 'string' ? call.message.trim() : '';
		const round = Number.isFinite( Number( call.round ) )
			? Math.max( 1, Math.round( Number( call.round ) ) )
			: 1;
		const sequence = Number.isFinite( Number( call.sequence ) )
			? Math.max( 1, Math.round( Number( call.sequence ) ) )
			: 1;

		return `${ name }|${ status }|${ round }|${ sequence }|${ message }`;
	};

	const emitToolCallIfNew = ( call, index, total, seenToolCallKeys ) => {
		const dedupKey = buildToolCallDedupKey( call );
		if ( dedupKey && seenToolCallKeys instanceof Set ) {
			if ( seenToolCallKeys.has( dedupKey ) ) {
				return;
			}
			seenToolCallKeys.add( dedupKey );
		}

		onEvent( 'tool_call', {
			call,
			index,
			total,
		} );
	};

	const emitToolCallEvents = ( events, seenToolCallKeys ) => {
		if ( ! Array.isArray( events ) || events.length === 0 ) {
			return;
		}

		const canonicalToolEvents = events.filter(
			( event ) =>
				isObjectRecord( event ) &&
				typeof event.event_type === 'string' &&
				event.event_type === 'agent.tool_call'
		);
		if ( canonicalToolEvents.length > 0 ) {
			canonicalToolEvents.forEach( ( event, index ) => {
				const payload = isObjectRecord( event.payload )
					? event.payload
					: {};
				const status = normalizeRunStatus( payload.status );

				let toolName = 'tool_call';
				if (
					typeof payload.tool_name === 'string' &&
					payload.tool_name.trim()
				) {
					toolName = payload.tool_name.trim();
				} else if (
					typeof payload.ability_name === 'string' &&
					payload.ability_name.trim()
				) {
					toolName = payload.ability_name.trim();
				}

				let normalizedStatus = 'success';
				if (
					status === 'error' ||
					status === 'requires_confirmation'
				) {
					normalizedStatus = status;
				}

				const detailMessage =
					typeof payload.message === 'string' &&
					payload.message.trim()
						? payload.message.trim()
						: '';

				const call = {
					name: toolName,
					ability:
						typeof payload.ability_name === 'string' &&
						payload.ability_name.trim()
							? payload.ability_name.trim()
							: null,
					args: {},
					status: normalizedStatus,
					message: detailMessage || null,
					round: Number.isFinite( Number( payload.round ) )
						? Math.max( 1, Math.round( Number( payload.round ) ) )
						: 1,
					sequence: Number.isFinite( Number( payload.sequence ) )
						? Math.max(
								1,
								Math.round( Number( payload.sequence ) )
						  )
						: index + 1,
					requiresConfirmation: status === 'requires_confirmation',
				};

				emitToolCallIfNew(
					call,
					index + 1,
					canonicalToolEvents.length,
					seenToolCallKeys
				);
			} );
			return;
		}

		// Fallback for older backends that only emit `tool_call` events.
		const toolEvents = events.filter(
			( event ) =>
				isObjectRecord( event ) &&
				typeof event.event_type === 'string' &&
				event.event_type === 'tool_call'
		);
		if ( toolEvents.length === 0 ) {
			return;
		}

		toolEvents.forEach( ( event, index ) => {
			const payload = isObjectRecord( event.payload )
				? event.payload
				: {};
			const status = normalizeRunStatus( payload.status );
			const result = isObjectRecord( payload.result )
				? payload.result
				: {};
			const error = isObjectRecord( payload.error ) ? payload.error : {};
			let detailMessage = '';
			if ( typeof error.message === 'string' && error.message.trim() ) {
				detailMessage = error.message.trim();
			} else if (
				typeof result.message === 'string' &&
				result.message.trim()
			) {
				detailMessage = result.message.trim();
			}

			let toolName = 'tool_call';
			if (
				typeof payload.tool_name === 'string' &&
				payload.tool_name.trim()
			) {
				toolName = payload.tool_name.trim();
			} else if (
				typeof payload.ability_name === 'string' &&
				payload.ability_name.trim()
			) {
				toolName = payload.ability_name.trim();
			}

			let normalizedStatus = 'success';
			if ( status === 'error' || status === 'requires_confirmation' ) {
				normalizedStatus = status;
			}

			const call = {
				name: toolName,
				ability:
					typeof payload.ability_name === 'string' &&
					payload.ability_name.trim()
						? payload.ability_name.trim()
						: null,
				args: {},
				status: normalizedStatus,
				message: detailMessage || null,
				round: Number.isFinite( Number( payload.round ) )
					? Math.max( 1, Math.round( Number( payload.round ) ) )
					: 1,
				sequence: Number.isFinite( Number( payload.sequence ) )
					? Math.max( 1, Math.round( Number( payload.sequence ) ) )
					: index + 1,
				requiresConfirmation: status === 'requires_confirmation',
			};

			emitToolCallIfNew(
				call,
				index + 1,
				toolEvents.length,
				seenToolCallKeys
			);
		} );
	};

	const emitRuntimeResult = (
		runtimeResult,
		initialReply,
		seenToolCallKeys
	) => {
		if ( ! isObjectRecord( runtimeResult ) ) {
			return false;
		}

		const contextUsage = normalizeContextUsage( runtimeResult.context );
		if ( contextUsage ) {
			onEvent( 'context_usage', { context: contextUsage } );
		}

		const toolCalls = Array.isArray( runtimeResult.tool_calls )
			? runtimeResult.tool_calls
					.map( ( rawCall ) => normalizeToolCall( rawCall ) )
					.filter( Boolean )
			: [];
		toolCalls.forEach( ( call, index ) => {
			emitToolCallIfNew(
				call,
				index + 1,
				toolCalls.length,
				seenToolCallKeys
			);
		} );

		if ( isObjectRecord( runtimeResult.error ) ) {
			const runtimeError = runtimeResult.error;
			const errorMessage =
				typeof runtimeError.message === 'string' &&
				runtimeError.message.trim()
					? runtimeError.message.trim()
					: __( 'Run failed.', 'clawpress' );
			onEvent( 'error', {
				error: errorMessage,
				type:
					typeof runtimeError.type === 'string' &&
					runtimeError.type.trim()
						? runtimeError.type.trim()
						: 'provider',
				card: normalizeCard( runtimeResult.card ),
			} );
			return true;
		}

		const reply =
			typeof runtimeResult.assistant_text === 'string'
				? runtimeResult.assistant_text.trim()
				: '';
		const card = normalizeCard( runtimeResult.card );
		if ( card ) {
			onEvent( 'response_card', {
				card,
				text: reply,
				role: 'assistant',
			} );
			return true;
		}

		if ( reply && reply !== initialReply ) {
			onEvent( 'response_message', {
				text: reply,
				role: 'assistant',
			} );
			return true;
		}

		return false;
	};

	const emitRunTerminalOutcome = (
		runPayload,
		initialReply,
		seenToolCallKeys
	) => {
		const runMeta = isObjectRecord( runPayload?.meta )
			? runPayload.meta
			: {};
		let runtimeResult = null;
		if ( isObjectRecord( runMeta.result ) ) {
			runtimeResult = runMeta.result;
		} else if ( isObjectRecord( runMeta.last_result ) ) {
			runtimeResult = runMeta.last_result;
		}

		if (
			emitRuntimeResult( runtimeResult, initialReply, seenToolCallKeys )
		) {
			return;
		}

		const runStatus = normalizeRunStatus( runPayload?.status );

		if ( runStatus === 'requires_confirmation' ) {
			onEvent( 'response_message', {
				text: __(
					'Action requires confirmation before continuing.',
					'clawpress'
				),
				role: 'assistant',
			} );
			return;
		}

		if ( runStatus === 'done' || runStatus === 'success' ) {
			onEvent( 'response_message', {
				text: __( 'Run completed.', 'clawpress' ),
				role: 'assistant',
			} );
			return;
		}

		const errorMessage =
			typeof runPayload?.error_message === 'string' &&
			runPayload.error_message.trim()
				? runPayload.error_message.trim()
				: __( 'Run ended with an error.', 'clawpress' );

		onEvent( 'error', {
			error: errorMessage,
			type: runStatus === 'timeout' ? 'timeout' : 'provider',
		} );
	};

	const pollRunUntilTerminal = async ( {
		runId,
		initialReply,
		signal,
		initialEventsCursor = 0,
		seenToolCallKeys = null,
	} ) => {
		let afterEventId = Number.isFinite( Number( initialEventsCursor ) )
			? Math.max( 0, Math.round( Number( initialEventsCursor ) ) )
			: 0;
		const startedAt = Date.now();

		while ( true ) {
			if ( signal?.aborted ) {
				const abortError = new Error( 'Aborted' );
				abortError.name = 'AbortError';
				throw abortError;
			}

			const elapsedSeconds = ( Date.now() - startedAt ) / 1000;
			if ( elapsedSeconds >= RUN_POLL_MAX_SECONDS ) {
				onEvent( 'error', {
					error: __(
						'Run polling timed out before a terminal status was received.',
						'clawpress'
					),
					type: 'timeout',
				} );
				return;
			}

			try {
				const eventPayload = await getRunEvents(
					runId,
					afterEventId,
					signal
				);
				const events = Array.isArray( eventPayload?.events )
					? eventPayload.events
					: [];
				emitToolCallEvents( events, seenToolCallKeys );

				const nextCursor = Number( eventPayload?.next_cursor );
				if ( Number.isFinite( nextCursor ) && nextCursor > 0 ) {
					afterEventId = Math.max( afterEventId, nextCursor );
				}
			} catch ( err ) {
				if ( err?.name === 'AbortError' ) {
					throw err;
				}
			}

			const runPayload = await getRunStatus( runId, signal );
			const status = normalizeRunStatus( runPayload?.status );
			if ( TERMINAL_RUN_STATUSES.has( status ) ) {
				emitRunTerminalOutcome(
					runPayload,
					initialReply,
					seenToolCallKeys
				);
				return;
			}

			await waitForNextPoll( signal );
		}
	};

	// Keep a stream-compatible interface for the existing panel flow.
	const stream = ( prompt ) => {
		const controller = new AbortController();
		const seenToolCallKeys = new Set();

		( async () => {
			try {
				const response = await sendMessage( prompt, controller.signal );
				const clearHistory =
					response?.meta?.command?.effects &&
					response.meta.command.effects.clear_history === true;

				if ( clearHistory ) {
					onEvent( 'history_reset', {} );
				}

				if ( Array.isArray( response?.meta?.suggestions ) ) {
					onEvent( 'suggestions', {
						items: response.meta.suggestions,
					} );
				}

				const contextUsage = normalizeContextUsage(
					response?.meta?.context
				);
				if ( contextUsage ) {
					onEvent( 'context_usage', { context: contextUsage } );
				}

				const toolCalls = Array.isArray( response?.meta?.tool_calls )
					? response.meta.tool_calls
							.map( ( rawCall ) => normalizeToolCall( rawCall ) )
							.filter( Boolean )
					: [];

				toolCalls.forEach( ( call, index ) => {
					emitToolCallIfNew(
						call,
						index + 1,
						toolCalls.length,
						seenToolCallKeys
					);
				} );

				const responseError =
					response?.meta?.error &&
					typeof response.meta.error === 'object'
						? response.meta.error
						: null;
				const responseCard =
					response?.meta?.card &&
					typeof response.meta.card === 'object'
						? normalizeCard( response.meta.card )
						: null;

				if ( responseError ) {
					const errorMessage =
						typeof responseError.message === 'string' &&
						responseError.message.trim()
							? responseError.message.trim()
							: __( 'Chat request failed.', 'clawpress' );
					onEvent( 'error', {
						error: errorMessage,
						type:
							typeof responseError.type === 'string' &&
							responseError.type.trim()
								? responseError.type.trim()
								: 'provider',
						card: responseCard,
					} );
					onDone?.( { aborted: false } );
					return;
				}

				const reply =
					typeof response?.reply === 'string'
						? response.reply.trim()
						: '';

				const isCommandResponse = Boolean(
					response?.meta?.command?.name
				);
				const card = normalizeCard( response?.meta?.card );

				if ( card ) {
					onEvent( 'response_card', {
						card,
						text: reply,
						role: isCommandResponse ? 'system' : 'assistant',
					} );
				} else if ( reply ) {
					onEvent( 'response_message', {
						text: reply,
						role: isCommandResponse ? 'system' : 'assistant',
					} );
				}

				const runStatus = normalizeRunStatus( response?.meta?.status );
				const runId = Number( response?.meta?.run_id );
				const initialEventsCursor = Number(
					response?.meta?.events_cursor
				);
				if ( runStatus === 'in_progress' ) {
					if ( Number.isFinite( runId ) && runId > 0 ) {
						await pollRunUntilTerminal( {
							runId,
							initialReply: reply,
							signal: controller.signal,
							initialEventsCursor,
							seenToolCallKeys,
						} );
					} else {
						onEvent( 'error', {
							error: __(
								'Run entered progress mode without a valid run ID.',
								'clawpress'
							),
							type: 'request',
						} );
					}
				}

				onDone?.( { aborted: false } );
			} catch ( err ) {
				if ( err?.name === 'AbortError' ) {
					onDone?.( { aborted: true } );
					return;
				}
				onError?.( {
					error:
						err?.message ||
						__( 'Chat request failed.', 'clawpress' ),
					type: 'request',
				} );
				onDone?.( { aborted: false } );
			}
		} )();

		return { stop: () => controller.abort() };
	};

	const runTool = async () => {
		throw new Error(
			__( 'Tool execution is not available in chat mode.', 'clawpress' )
		);
	};

	return {
		stream,
		runTool,
		getHistory,
		getStatus,
		getPanelState,
		setPanelState,
	};
};

export default createRealClient;
