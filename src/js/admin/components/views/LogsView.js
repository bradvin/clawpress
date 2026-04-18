import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Modal,
	Notice,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import {
	Fragment,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { requestJson } from '../../utils/requestJson';

const PAGE_SIZE = 50;
const LINKED_EVENTS_PAGE_SIZE = 100;

const STANDARD_TABS = [
	{
		name: 'all',
		label: __( 'All', 'clawpress' ),
	},
	{
		name: 'tool_call',
		label: __( 'Tool Calls', 'clawpress' ),
	},
	{
		name: 'command',
		label: __( 'Commands', 'clawpress' ),
	},
	{
		name: 'message',
		label: __( 'Messages', 'clawpress' ),
	},
];

const EVENT_TYPE_LABELS = {
	tool_call: __( 'Tool Calls', 'clawpress' ),
	command: __( 'Commands', 'clawpress' ),
	message: __( 'Messages', 'clawpress' ),
	event: __( 'Events', 'clawpress' ),
};

const normalizeCountsByType = ( countsByType ) => {
	if (
		! countsByType ||
		typeof countsByType !== 'object' ||
		Array.isArray( countsByType )
	) {
		return {};
	}

	return Object.entries( countsByType ).reduce( ( acc, [ key, value ] ) => {
		const normalizedKey =
			typeof key === 'string' ? key.trim().toLowerCase() : '';
		if ( ! normalizedKey ) {
			return acc;
		}

		acc[ normalizedKey ] = Math.max( 0, Number( value ) || 0 );
		return acc;
	}, {} );
};

const normalizeLogItem = ( item = {} ) => {
	let context = {};
	if (
		item?.context &&
		typeof item.context === 'object' &&
		item.context !== null
	) {
		context = item.context;
	}

	return {
		id: Number( item?.id ) || 0,
		event_type:
			typeof item?.event_type === 'string' ? item.event_type : 'event',
		action_name:
			typeof item?.action_name === 'string' ? item.action_name : '',
		status: typeof item?.status === 'string' ? item.status : 'info',
		message: typeof item?.message === 'string' ? item.message : '',
		requesting_user_id: Number( item?.requesting_user_id ) || 0,
		execution_user_id: Number( item?.execution_user_id ) || 0,
		context,
		created_at: typeof item?.created_at === 'string' ? item.created_at : '',
	};
};

const normalizePayload = ( payload = {} ) => {
	const rawItems = Array.isArray( payload?.items ) ? payload.items : [];

	return {
		items: rawItems.map( normalizeLogItem ),
		counts_by_type: normalizeCountsByType( payload?.counts_by_type ),
		total: Math.max( 0, Number( payload?.total ) || 0 ),
		limit: Math.max( 1, Number( payload?.limit ) || PAGE_SIZE ),
		offset: Math.max( 0, Number( payload?.offset ) || 0 ),
		has_more: Boolean( payload?.has_more ),
	};
};

const buildLogsPath = ( eventType, limit, offset ) => {
	const params = new URLSearchParams();
	params.set( 'limit', String( limit ) );
	params.set( 'offset', String( offset ) );

	if ( eventType && 'all' !== eventType ) {
		params.set( 'event_type', eventType );
	}

	return `logs?${ params.toString() }`;
};

const formatEventTypeLabel = ( eventType ) => {
	if ( EVENT_TYPE_LABELS[ eventType ] ) {
		return EVENT_TYPE_LABELS[ eventType ];
	}

	if ( typeof eventType !== 'string' || ! eventType.trim() ) {
		return __( 'Unknown', 'clawpress' );
	}

	return eventType
		.trim()
		.replace( /[_:-]+/g, ' ' )
		.replace( /\b\w/g, ( character ) => character.toUpperCase() );
};

const hasContextDetails = ( context ) => {
	if ( Array.isArray( context ) ) {
		return context.length > 0;
	}

	if ( context && typeof context === 'object' ) {
		return Object.keys( context ).length > 0;
	}

	return false;
};

const stringifyContext = ( context ) => {
	try {
		return JSON.stringify( context, null, 2 );
	} catch {
		return __( 'Unable to render log details.', 'clawpress' );
	}
};

const getContextWithoutToolPayload = ( context ) => {
	if (
		! context ||
		typeof context !== 'object' ||
		Array.isArray( context )
	) {
		return context;
	}

	const { request, response, ...remainingContext } = context;

	return remainingContext;
};

const getStatusClassName = ( status ) => {
	const normalizedStatus =
		typeof status === 'string' ? status.trim().toLowerCase() : 'info';

	if ( ! normalizedStatus ) {
		return 'is-info';
	}

	return `is-${ normalizedStatus.replace( /[^a-z0-9_-]/g, '-' ) }`;
};

const getLinkedEventTarget = ( context ) => {
	const runId = Number( context?.run_id ) || 0;
	const sessionId = Number( context?.session_id ) || 0;

	if ( runId > 0 ) {
		return {
			scope: 'run',
			runId,
			sessionId: sessionId > 0 ? sessionId : 0,
		};
	}

	if ( sessionId > 0 ) {
		return {
			scope: 'session',
			runId: 0,
			sessionId,
		};
	}

	return null;
};

const buildLinkedEventsPath = ( target, after = 0 ) => {
	const params = new URLSearchParams();
	params.set( 'limit', String( LINKED_EVENTS_PAGE_SIZE ) );
	params.set( 'after', String( after ) );

	if ( target?.runId ) {
		params.set( 'run_id', String( target.runId ) );
	}

	if ( target?.sessionId ) {
		params.set( 'session_id', String( target.sessionId ) );
	}

	return `logs/linked-events?${ params.toString() }`;
};

const formatLinkedEventTitle = ( target ) => {
	if ( ! target ) {
		return __( 'Linked Events', 'clawpress' );
	}

	if ( 'run' === target.scope && target.runId ) {
		return sprintf(
			/* translators: %d: run ID */
			__( 'Linked Events for Run %d', 'clawpress' ),
			target.runId
		);
	}

	return sprintf(
		/* translators: %d: session ID */
		__( 'Linked Events for Session %d', 'clawpress' ),
		target.sessionId
	);
};

const renderLogDetails = ( item ) => {
	const isToolCall = 'tool_call' === item?.event_type;
	const request = item?.context?.request;
	const response = item?.context?.response;
	const remainingContext = getContextWithoutToolPayload( item?.context );
	const hasRemainingContext = hasContextDetails( remainingContext );

	if ( ! isToolCall ) {
		return (
			<pre className="clawpress-logs__details">
				{ stringifyContext( item?.context || {} ) }
			</pre>
		);
	}

	return (
		<div className="clawpress-logs__detail-sections">
			<div className="clawpress-logs__detail-section">
				<h4>{ __( 'Request', 'clawpress' ) }</h4>
				<pre className="clawpress-logs__details">
					{ stringifyContext( request || {} ) }
				</pre>
			</div>
			<div className="clawpress-logs__detail-section">
				<h4>{ __( 'Response', 'clawpress' ) }</h4>
				<pre className="clawpress-logs__details">
					{ stringifyContext( response || {} ) }
				</pre>
			</div>
			{ hasRemainingContext ? (
				<div className="clawpress-logs__detail-section">
					<h4>{ __( 'Metadata', 'clawpress' ) }</h4>
					<pre className="clawpress-logs__details">
						{ stringifyContext( remainingContext ) }
					</pre>
				</div>
			) : null }
		</div>
	);
};

export default function LogsView() {
	const [ currentType, setCurrentType ] = useState( 'all' );
	const [ items, setItems ] = useState( [] );
	const [ countsByType, setCountsByType ] = useState( {} );
	const [ total, setTotal ] = useState( 0 );
	const [ hasMore, setHasMore ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ clearing, setClearing ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ success, setSuccess ] = useState( '' );
	const [ expandedRows, setExpandedRows ] = useState( {} );
	const [ isConfirmOpen, setIsConfirmOpen ] = useState( false );
	const [ linkedLog, setLinkedLog ] = useState( null );
	const [ linkedEvents, setLinkedEvents ] = useState( [] );
	const [ linkedEventsError, setLinkedEventsError ] = useState( '' );
	const [ linkedEventsLoading, setLinkedEventsLoading ] = useState( false );
	const [ linkedEventsLoadingMore, setLinkedEventsLoadingMore ] =
		useState( false );
	const [ linkedEventsHasMore, setLinkedEventsHasMore ] = useState( false );
	const [ linkedEventsNextCursor, setLinkedEventsNextCursor ] = useState( 0 );

	const tabs = useMemo( () => {
		const discoveredTypes = Object.keys( countsByType )
			.filter(
				( eventType ) =>
					! STANDARD_TABS.some( ( tab ) => tab.name === eventType )
			)
			.sort( ( first, second ) => first.localeCompare( second ) );

		const standardTabs = STANDARD_TABS.map( ( tab ) => {
			const count =
				'all' === tab.name ? total : countsByType[ tab.name ] || 0;

			return {
				name: tab.name,
				title: sprintf(
					/* translators: 1: log tab label, 2: log count */
					__( '%1$s (%2$d)', 'clawpress' ),
					tab.label,
					count
				),
			};
		} );

		const dynamicTabs = discoveredTypes.map( ( eventType ) => ( {
			name: eventType,
			title: sprintf(
				/* translators: 1: log tab label, 2: log count */
				__( '%1$s (%2$d)', 'clawpress' ),
				formatEventTypeLabel( eventType ),
				countsByType[ eventType ] || 0
			),
		} ) );

		return [ ...standardTabs, ...dynamicTabs ];
	}, [ countsByType, total ] );

	const loadLogs = useCallback(
		async ( {
			append = false,
			eventType = currentType,
			offset = 0,
		} = {} ) => {
			if ( append ) {
				setLoadingMore( true );
			} else {
				setLoading( true );
			}

			setError( '' );

			try {
				const payload = normalizePayload(
					await requestJson(
						buildLogsPath( eventType, PAGE_SIZE, offset )
					)
				);

				setCountsByType( payload.counts_by_type );
				setTotal( payload.total );
				setHasMore( payload.has_more );
				setItems( ( currentItems ) =>
					append
						? [ ...currentItems, ...payload.items ]
						: payload.items
				);
			} catch ( requestError ) {
				setError(
					requestError?.message ||
						__( 'Unable to load logs.', 'clawpress' )
				);
			} finally {
				setLoading( false );
				setLoadingMore( false );
			}
		},
		[ currentType ]
	);

	useEffect( () => {
		setExpandedRows( {} );
		loadLogs( { eventType: currentType } );
	}, [ currentType, loadLogs ] );

	useEffect( () => {
		const validTabNames = tabs.map( ( tab ) => tab.name );
		if ( ! validTabNames.includes( currentType ) ) {
			setCurrentType( 'all' );
		}
	}, [ currentType, tabs ] );

	const refreshLogs = () => {
		setSuccess( '' );
		loadLogs( { eventType: currentType } );
	};

	const loadMoreLogs = () => {
		loadLogs( {
			append: true,
			eventType: currentType,
			offset: items.length,
		} );
	};

	const toggleRowDetails = ( logId ) => {
		setExpandedRows( ( current ) => ( {
			...current,
			[ logId ]: ! current[ logId ],
		} ) );
	};

	const clearLogs = async () => {
		setClearing( true );
		setError( '' );
		setSuccess( '' );

		try {
			const payload = await requestJson( 'logs', {
				method: 'DELETE',
			} );
			const deletedCount = Math.max( 0, Number( payload?.deleted ) || 0 );

			setIsConfirmOpen( false );
			setExpandedRows( {} );
			setItems( [] );
			setCountsByType( {} );
			setTotal( 0 );
			setHasMore( false );
			if ( deletedCount > 0 ) {
				setSuccess(
					sprintf(
						/* translators: %d: number of cleared logs */
						_n(
							'%d log cleared.',
							'%d logs cleared.',
							deletedCount,
							'clawpress'
						),
						deletedCount
					)
				);
			} else {
				setSuccess( __( 'Logs cleared.', 'clawpress' ) );
			}

			if ( 'all' === currentType ) {
				loadLogs( { eventType: 'all' } );
			} else {
				setCurrentType( 'all' );
			}
		} catch ( requestError ) {
			setError(
				requestError?.message ||
					__( 'Unable to clear logs.', 'clawpress' )
			);
		} finally {
			setClearing( false );
		}
	};

	const loadLinkedEvents = useCallback(
		async ( logItem, { append = false, after = 0 } = {} ) => {
			const target = getLinkedEventTarget( logItem?.context );
			if ( ! target ) {
				return;
			}

			if ( append ) {
				setLinkedEventsLoadingMore( true );
			} else {
				setLinkedEventsLoading( true );
				setLinkedEventsError( '' );
				setLinkedEvents( [] );
				setLinkedEventsHasMore( false );
				setLinkedEventsNextCursor( 0 );
				setLinkedLog( logItem );
			}

			try {
				const payload = await requestJson(
					buildLinkedEventsPath( target, after )
				);
				const nextEvents = Array.isArray( payload?.events )
					? payload.events
					: [];

				setLinkedEvents( ( currentEvents ) =>
					append ? [ ...currentEvents, ...nextEvents ] : nextEvents
				);
				setLinkedEventsHasMore( Boolean( payload?.has_more ) );
				setLinkedEventsNextCursor(
					Math.max( 0, Number( payload?.next_cursor ) || 0 )
				);
			} catch ( requestError ) {
				setLinkedEventsError(
					requestError?.message ||
						__( 'Unable to load linked events.', 'clawpress' )
				);
			} finally {
				setLinkedEventsLoading( false );
				setLinkedEventsLoadingMore( false );
			}
		},
		[]
	);

	const closeLinkedEvents = () => {
		setLinkedLog( null );
		setLinkedEvents( [] );
		setLinkedEventsError( '' );
		setLinkedEventsLoading( false );
		setLinkedEventsLoadingMore( false );
		setLinkedEventsHasMore( false );
		setLinkedEventsNextCursor( 0 );
	};

	return (
		<div className="clawpress-logs">
			<Card>
				<CardHeader>
					<div className="clawpress-logs__header">
						<div className="clawpress-logs__header-copy">
							<h3>{ __( 'Logs', 'clawpress' ) }</h3>
							<p>
								{ __(
									'Review ClawPress activity, including tool calls, commands, and chat log entries.',
									'clawpress'
								) }
							</p>
						</div>
						<div className="clawpress-logs__actions">
							<Button
								variant="secondary"
								onClick={ refreshLogs }
								isBusy={ loading && items.length > 0 }
								disabled={ clearing || loadingMore }
							>
								{ __( 'Refresh', 'clawpress' ) }
							</Button>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => setIsConfirmOpen( true ) }
								disabled={ clearing || loading || loadingMore }
							>
								{ __( 'Clear All Logs', 'clawpress' ) }
							</Button>
						</div>
					</div>
				</CardHeader>
				<CardBody>
					{ error ? (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) : null }
					{ success ? (
						<Notice status="success" isDismissible={ false }>
							{ success }
						</Notice>
					) : null }
					{ loading && 0 === items.length ? (
						<div className="clawpress-logs__loading">
							<Spinner />
						</div>
					) : (
						<TabPanel
							className="clawpress-logs__tabs"
							tabs={ tabs }
							initialTabName={ currentType }
							onSelect={ ( tabName ) => {
								setSuccess( '' );
								setCurrentType( tabName );
							} }
						>
							{ () => (
								<div className="clawpress-logs__panel">
									<p className="clawpress-logs__summary">
										{ sprintf(
											/* translators: %d: total logs in current tab */
											__(
												'%d logs in this view.',
												'clawpress'
											),
											total
										) }
									</p>
									<div className="clawpress-logs__table-wrap">
										<table className="widefat fixed striped clawpress-logs__table">
											<thead>
												<tr>
													<th>
														{ __(
															'Timestamp',
															'clawpress'
														) }
													</th>
													<th>
														{ __(
															'Type',
															'clawpress'
														) }
													</th>
													<th>
														{ __(
															'Action',
															'clawpress'
														) }
													</th>
													<th>
														{ __(
															'Status',
															'clawpress'
														) }
													</th>
													<th>
														{ __(
															'Message',
															'clawpress'
														) }
													</th>
													<th>
														{ __(
															'Details',
															'clawpress'
														) }
													</th>
												</tr>
											</thead>
											<tbody>
												{ 0 === items.length ? (
													<tr>
														<td colSpan="6">
															<p className="clawpress-logs__empty">
																{ __(
																	'No logs found for this tab.',
																	'clawpress'
																) }
															</p>
														</td>
													</tr>
												) : (
													items.map( ( item ) => {
														const hasDetails =
															hasContextDetails(
																item.context
															);
														const linkedEventTarget =
															getLinkedEventTarget(
																item.context
															);
														const isExpanded =
															Boolean(
																expandedRows[
																	item.id
																]
															);

														return (
															<Fragment
																key={ item.id }
															>
																<tr>
																	<td>
																		{ item.created_at ||
																			'--' }
																	</td>
																	<td>
																		{ formatEventTypeLabel(
																			item.event_type
																		) }
																	</td>
																	<td>
																		<code>
																			{ item.action_name ||
																				'--' }
																		</code>
																	</td>
																	<td>
																		<span
																			className={ `clawpress-logs__status ${ getStatusClassName(
																				item.status
																			) }` }
																		>
																			{ item.status ||
																				__(
																					'info',
																					'clawpress'
																				) }
																		</span>
																	</td>
																	<td>
																		{ item.message ||
																			'--' }
																	</td>
																	<td>
																		{ hasDetails ||
																		linkedEventTarget ? (
																			<div className="clawpress-logs__row-actions">
																				{ hasDetails ? (
																					<Button
																						variant="tertiary"
																						onClick={ () =>
																							toggleRowDetails(
																								item.id
																							)
																						}
																					>
																						{ isExpanded
																							? __(
																									'Hide Details',
																									'clawpress'
																							  )
																							: __(
																									'View Details',
																									'clawpress'
																							  ) }
																					</Button>
																				) : null }
																				{ linkedEventTarget ? (
																					<Button
																						variant="tertiary"
																						onClick={ () =>
																							loadLinkedEvents(
																								item
																							)
																						}
																					>
																						{ __(
																							'View Events',
																							'clawpress'
																						) }
																					</Button>
																				) : null }
																			</div>
																		) : (
																			'--'
																		) }
																	</td>
																</tr>
																{ isExpanded ? (
																	<tr className="clawpress-logs__details-row">
																		<td colSpan="6">
																			{ renderLogDetails(
																				item
																			) }
																		</td>
																	</tr>
																) : null }
															</Fragment>
														);
													} )
												) }
											</tbody>
										</table>
									</div>
									{ hasMore ? (
										<div className="clawpress-logs__load-more">
											<Button
												variant="secondary"
												onClick={ loadMoreLogs }
												isBusy={ loadingMore }
												disabled={
													loadingMore || loading
												}
											>
												{ __(
													'Load More',
													'clawpress'
												) }
											</Button>
										</div>
									) : null }
								</div>
							) }
						</TabPanel>
					) }
				</CardBody>
			</Card>
			{ isConfirmOpen ? (
				<Modal
					title={ __( 'Clear all logs?', 'clawpress' ) }
					onRequestClose={ () => {
						if ( ! clearing ) {
							setIsConfirmOpen( false );
						}
					} }
				>
					<p>
						{ __(
							'This removes every action log entry, including tool calls, commands, and messages.',
							'clawpress'
						) }
					</p>
					<div className="clawpress-logs__confirm-actions">
						<Button
							variant="secondary"
							onClick={ () => setIsConfirmOpen( false ) }
							disabled={ clearing }
						>
							{ __( 'Cancel', 'clawpress' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ clearLogs }
							isBusy={ clearing }
							disabled={ clearing }
						>
							{ __( 'Clear Logs', 'clawpress' ) }
						</Button>
					</div>
				</Modal>
			) : null }
			{ linkedLog ? (
				<Modal
					title={ formatLinkedEventTitle(
						getLinkedEventTarget( linkedLog.context )
					) }
					onRequestClose={ closeLinkedEvents }
				>
					{ linkedEventsError ? (
						<Notice status="error" isDismissible={ false }>
							{ linkedEventsError }
						</Notice>
					) : null }
					{ linkedEventsLoading && 0 === linkedEvents.length ? (
						<div className="clawpress-logs__loading">
							<Spinner />
						</div>
					) : (
						<div className="clawpress-logs__linked-events">
							{ 0 === linkedEvents.length ? (
								<p className="clawpress-logs__empty">
									{ __(
										'No linked events were found for this log entry.',
										'clawpress'
									) }
								</p>
							) : (
								linkedEvents.map( ( event ) => (
									<div
										key={ event.event_id }
										className="clawpress-logs__linked-event"
									>
										<div className="clawpress-logs__linked-event-header">
											<strong>
												{ event.event_type || '--' }
											</strong>
											<span>
												{ event.created_at_gmt || '--' }
											</span>
										</div>
										<pre className="clawpress-logs__details">
											{ stringifyContext(
												event.payload || {}
											) }
										</pre>
									</div>
								) )
							) }
							{ linkedEventsHasMore ? (
								<div className="clawpress-logs__load-more">
									<Button
										variant="secondary"
										onClick={ () =>
											loadLinkedEvents( linkedLog, {
												append: true,
												after: linkedEventsNextCursor,
											} )
										}
										isBusy={ linkedEventsLoadingMore }
										disabled={ linkedEventsLoadingMore }
									>
										{ __(
											'Load More Events',
											'clawpress'
										) }
									</Button>
								</div>
							) : null }
						</div>
					) }
				</Modal>
			) : null }
		</div>
	);
}
