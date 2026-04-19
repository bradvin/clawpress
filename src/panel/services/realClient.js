import { __, sprintf } from '@wordpress/i18n';

const RUN_POLL_INTERVAL_MS = 1000;
const RUN_POLL_MAX_SECONDS = 180;
const RUN_PROGRESS_MESSAGE_STEP_SECONDS = 8;
const RUN_PROGRESS_MESSAGES = [
	__( 'I am still working on this', 'clawpress' ),
	__( 'Yes, still working on it.', 'clawpress' ),
	__( 'Ok, this is taking long now. Still on it.', 'clawpress' ),
	__(
		'Plot twist: still working on it. My keyboard is sweating.',
		'clawpress'
	),
	__(
		'At this point even my coffee is worried, but I am still on it.',
		'clawpress'
	),
];
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

const createRealClient = ( {
	restBase,
	nonce,
	streamNonce,
	onEvent,
	onDone,
	onError,
} ) => {
	const parseSseBlock = ( block ) => {
		const lines = block.split( '\n' );
		let type = 'message';
		const dataLines = [];

		lines.forEach( ( line ) => {
			if ( ! line || line.startsWith( ':' ) ) {
				return;
			}

			if ( line.startsWith( 'event:' ) ) {
				type = line.slice( 6 ).trim() || type;
				return;
			}

			if ( line.startsWith( 'data:' ) ) {
				dataLines.push( line.slice( 5 ).trimStart() );
			}
		} );

		if ( dataLines.length === 0 ) {
			return null;
		}

		try {
			return JSON.parse( dataLines.join( '\n' ) );
		} catch {
			return null;
		}
	};

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

	const getRunProgressMessage = ( elapsedSeconds ) => {
		const normalizedElapsedSeconds = Number.isFinite(
			Number( elapsedSeconds )
		)
			? Math.max( 0, Number( elapsedSeconds ) )
			: 0;
		const index = Math.min(
			Math.floor(
				normalizedElapsedSeconds / RUN_PROGRESS_MESSAGE_STEP_SECONDS
			),
			RUN_PROGRESS_MESSAGES.length - 1
		);
		return RUN_PROGRESS_MESSAGES[ index ] || RUN_PROGRESS_MESSAGES[ 0 ];
	};

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

		const nameCandidates = [
			rawCall.name,
			rawCall.tool_name,
			rawCall.ability_name,
		];
		const name = nameCandidates
			.map( ( value ) =>
				typeof value === 'string' ? value.trim() : ''
			)
			.find( ( value ) => value.length > 0 );
		if ( ! name ) {
			return null;
		}

		let ability = '';
		if ( typeof rawCall.ability === 'string' ) {
			ability = rawCall.ability.trim();
		} else if ( typeof rawCall.ability_name === 'string' ) {
			ability = rawCall.ability_name.trim();
		}
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
				const call = normalizeToolCall( {
					name: toolName,
					tool_name: payload.tool_name,
					ability_name: payload.ability_name,
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
					requires_confirmation: status === 'requires_confirmation',
				} );
				if ( ! call ) {
					return;
				}

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

			const call = normalizeToolCall( {
				name: toolName,
				tool_name: payload.tool_name,
				ability_name: payload.ability_name,
				args: {},
				status: normalizedStatus,
				message: detailMessage || null,
				round: Number.isFinite( Number( payload.round ) )
					? Math.max( 1, Math.round( Number( payload.round ) ) )
					: 1,
				sequence: Number.isFinite( Number( payload.sequence ) )
					? Math.max( 1, Math.round( Number( payload.sequence ) ) )
					: index + 1,
				requires_confirmation: status === 'requires_confirmation',
			} );
			if ( ! call ) {
				return;
			}

			emitToolCallIfNew(
				call,
				index + 1,
				toolEvents.length,
				seenToolCallKeys
			);
		} );
	};

	const emitRuntimeInProgressSignals = ( runPayload, seenToolCallKeys ) => {
		const runMeta = isObjectRecord( runPayload?.meta )
			? runPayload.meta
			: {};
		let runtimeResult = null;
		if ( isObjectRecord( runMeta.last_result ) ) {
			runtimeResult = runMeta.last_result;
		} else if ( isObjectRecord( runMeta.result ) ) {
			runtimeResult = runMeta.result;
		}
		if ( ! runtimeResult ) {
			return;
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
				text: __(
					'I finished the background steps, but I did not receive a final text response. Please tell me to continue and I will pick up from here.',
					'clawpress'
				),
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
		let lastProgressMessage = '';

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
			emitRuntimeInProgressSignals( runPayload, seenToolCallKeys );
			const status = normalizeRunStatus( runPayload?.status );
			if ( TERMINAL_RUN_STATUSES.has( status ) ) {
				emitRunTerminalOutcome(
					runPayload,
					initialReply,
					seenToolCallKeys
				);
				return;
			}

			const progressMessage = getRunProgressMessage( elapsedSeconds );
			if ( progressMessage && progressMessage !== lastProgressMessage ) {
				onEvent( 'run_progress', { text: progressMessage } );
				lastProgressMessage = progressMessage;
			}

			await waitForNextPoll( signal );
		}
	};

	const streamMessage = async ( message, signal, seenToolCallKeys ) => {
		const response = await fetch( `${ restBase }/chat/stream`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': streamNonce || nonce,
				Accept: 'text/event-stream',
			},
			body: JSON.stringify( { message } ),
			signal,
		} );

		if ( ! response.ok ) {
			const rawText = await response.text();
			let messageText = rawText;

			if ( rawText ) {
				try {
					const parsed = JSON.parse( rawText );
					messageText = parsed?.message || parsed?.error || rawText;
				} catch {
					messageText = rawText;
				}
			}

			throw new Error(
				messageText || __( 'Streaming request failed.', 'clawpress' )
			);
		}

		const contentType = response.headers.get( 'content-type' ) || '';
		if (
			! response.body ||
			! contentType.toLowerCase().includes( 'text/event-stream' )
		) {
			throw new Error(
				__(
					'Streaming endpoint returned a non-streaming response.',
					'clawpress'
				)
			);
		}

		const reader = response.body.getReader();
		const decoder = new TextDecoder();
		let buffer = '';
		let streamedText = '';
		let continuation = null;

		const handleParsedFrame = ( frame ) => {
			const type =
				frame && typeof frame.type === 'string' ? frame.type : '';
			const payload =
				frame?.payload && typeof frame.payload === 'object'
					? frame.payload
					: {};

			switch ( type ) {
				case 'delta':
					if (
						typeof payload.text === 'string' &&
						payload.text.length > 0
					) {
						streamedText += payload.text;
						onEvent( 'delta', { text: payload.text } );
					}
					break;
				case 'tool_call': {
					const call = normalizeToolCall( payload.call );
					if ( call ) {
						emitToolCallIfNew(
							call,
							Number.isFinite( Number( payload.index ) )
								? Number( payload.index )
								: undefined,
							Number.isFinite( Number( payload.total ) )
								? Number( payload.total )
								: undefined,
							seenToolCallKeys
						);
					}
					break;
				}
				case 'history_reset':
					onEvent( 'history_reset', {} );
					break;
				case 'suggestions':
					if ( Array.isArray( payload.items ) ) {
						onEvent( 'suggestions', {
							items: payload.items,
						} );
					}
					break;
				case 'context_usage': {
					const context = normalizeContextUsage( payload.context );
					if ( context ) {
						onEvent( 'context_usage', { context } );
					}
					break;
				}
				case 'response_card': {
					const card = normalizeCard( payload.card );
					if ( card ) {
						onEvent( 'response_card', {
							card,
							text:
								typeof payload.text === 'string'
									? payload.text
									: '',
							role:
								payload.role === 'system'
									? 'system'
									: 'assistant',
						} );
					}
					break;
				}
				case 'response_message':
					if (
						typeof payload.text === 'string' &&
						payload.text.trim()
					) {
						onEvent( 'response_message', {
							text: payload.text.trim(),
							role:
								payload.role === 'system'
									? 'system'
									: 'assistant',
						} );
					}
					break;
				case 'error':
					onEvent( 'error', {
						error:
							typeof payload.error === 'string' &&
							payload.error.trim()
								? payload.error.trim()
								: __( 'Chat request failed.', 'clawpress' ),
						type:
							typeof payload.type === 'string' &&
							payload.type.trim()
								? payload.type.trim()
								: 'request',
						card: normalizeCard( payload.card ),
					} );
					break;
				case 'in_progress': {
					const runId = Number( payload.run_id );
					if ( Number.isFinite( runId ) && runId > 0 ) {
						continuation = {
							runId,
							initialEventsCursor: Number(
								payload.events_cursor
							),
							initialReply:
								streamedText ||
								( typeof payload.initial_reply === 'string'
									? payload.initial_reply.trim()
									: '' ),
						};
					}
					break;
				}
			}
		};

		while ( true ) {
			const { done, value } = await reader.read();

			buffer += decoder.decode( value || new Uint8Array(), {
				stream: ! done,
			} );

			const blocks = buffer.split( '\n\n' );
			buffer = blocks.pop() || '';

			blocks.forEach( ( block ) => {
				const frame = parseSseBlock( block );
				if ( frame ) {
					handleParsedFrame( frame );
				}
			} );

			if ( done ) {
				if ( buffer.trim() ) {
					const frame = parseSseBlock( buffer );
					if ( frame ) {
						handleParsedFrame( frame );
					}
				}

				break;
			}
		}

		return {
			initialReply: streamedText,
			continuation,
		};
	};

	const stream = ( prompt ) => {
		const controller = new AbortController();
		const seenToolCallKeys = new Set();

		( async () => {
			try {
				const streamed = await streamMessage(
					prompt,
					controller.signal,
					seenToolCallKeys
				);

				if ( streamed?.continuation ) {
					await pollRunUntilTerminal( {
						runId: streamed.continuation.runId,
						initialReply: streamed.continuation.initialReply || '',
						signal: controller.signal,
						initialEventsCursor:
							streamed.continuation.initialEventsCursor,
						seenToolCallKeys,
					} );
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
