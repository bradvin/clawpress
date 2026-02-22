( () => {
	'use strict';
	const e = window.wp.element,
		s = window.wp.i18n,
		t = window.ReactJSXRuntime,
		r = ( e ) =>
			'online' === e
				? ( 0, s.__ )( 'Online', 'clawpress' )
				: 'offline' === e
				? ( 0, s.__ )( 'Offline', 'clawpress' )
				: e,
		a = ( {
			onClose: e,
			onToggleTheme: a,
			statusMode: n,
			statusLabel: l,
			statusLoading: o,
		} ) => {
			const c = n || 'offline';
			return ( 0, t.jsxs )( 'div', {
				className: 'clawpress-header',
				children: [
					( 0, t.jsxs )( 'div', {
						className: 'clawpress-header-meta',
						children: [
							( 0, t.jsx )( 'div', {
								className: 'clawpress-title',
								children: ( 0, s.__ )(
									'ClawPress Agent',
									'clawpress'
								),
							} ),
							( 0, t.jsxs )( 'div', {
								className: `clawpress-status clawpress-status-${ c }`,
								children: [
									( 0, t.jsx )( 'span', {
										className: 'clawpress-status-dot',
									} ),
									( 0, t.jsx )( 'span', {
										className: 'clawpress-status-mode',
										children: o
											? ( 0, s.__ )(
													'Checking…',
													'clawpress'
											  )
											: r( c ),
									} ),
									l
										? ( 0, t.jsx )( 'span', {
												className:
													'clawpress-status-label',
												children: l,
										  } )
										: null,
								],
							} ),
						],
					} ),
					( 0, t.jsx )( 'button', {
						className: 'clawpress-theme-toggle',
						type: 'button',
						onClick: a,
						'aria-label': ( 0, s.__ )(
							'Toggle theme',
							'clawpress'
						),
						children: ( 0, t.jsx )( 'svg', {
							viewBox: '0 0 24 24',
							'aria-hidden': 'true',
							focusable: 'false',
							children: ( 0, t.jsx )( 'path', {
								d: 'M12 18q2.484 0 4.242-1.758t1.758-4.242-1.758-4.242-4.242-1.758q-1.219 0-2.484 0.563 1.547 0.703 2.508 2.18t0.961 3.258-0.961 3.258-2.508 2.18q1.266 0.563 2.484 0.563zM20.016 8.672l3.281 3.328-3.281 3.328v4.688h-4.688l-3.328 3.281-3.328-3.281h-4.688v-4.688l-3.281-3.328 3.281-3.328v-4.688h4.688l3.328-3.281 3.328 3.281h4.688v4.688z',
							} ),
						} ),
					} ),
					( 0, t.jsx )( 'button', {
						className: 'clawpress-close',
						onClick: e,
						type: 'button',
						'aria-label': ( 0, s.__ )( 'Close panel', 'clawpress' ),
						children: ( 0, t.jsx )( 'svg', {
							viewBox: '0 0 24 24',
							'aria-hidden': 'true',
							focusable: 'false',
							children: ( 0, t.jsx )( 'path', {
								d: 'M18.984 6.422l-5.578 5.578 5.578 5.578-1.406 1.406-5.578-5.578-5.578 5.578-1.406-1.406 5.578-5.578-5.578-5.578 1.406-1.406 5.578 5.578 5.578-5.578z',
							} ),
						} ),
					} ),
				],
			} );
		},
		n = ( e ) => {
			const s = Number( e );
			if ( ! Number.isFinite( s ) || s <= 0 ) {
				return '0';
			}
			if ( s < 1e3 ) {
				return String( Math.round( s ) );
			}
			const t = [
				{ threshold: 1e9, suffix: 'b' },
				{ threshold: 1e6, suffix: 'm' },
				{ threshold: 1e3, suffix: 'k' },
			];
			for ( const e of t ) {
				if ( s >= e.threshold ) {
					const t = s / e.threshold,
						r = t >= 100 ? 0 : 1;
					return `${ t.toFixed( r ).replace( /\.0$/, '' ) }${
						e.suffix
					}`;
				}
			}
			return String( Math.round( s ) );
		},
		l = ( {
			input: r,
			onInputChange: a,
			onSend: l,
			onStop: o,
			streaming: c,
			suggestions: i,
			contextUsage: d,
			onSendSuggestion: u,
			onHistoryUp: p,
			onHistoryDown: m,
		} ) => {
			const h = ( 0, e.useRef )( null ),
				g = Number( d?.usedTokens ),
				w = Number( d?.contextWindowTokens ),
				y = Number( d?.percentUsed ),
				f = Number.isFinite( g ) && g >= 0 ? Math.round( g ) : 0,
				_ = Number.isFinite( w ) && w > 0 ? Math.round( w ) : 0,
				b =
					Number.isFinite( y ) && y >= 0
						? Math.max( 0, Math.min( 100, Math.round( y ) ) )
						: _ > 0
						? Math.max(
								0,
								Math.min( 100, Math.round( ( f / _ ) * 100 ) )
						  )
						: null,
				x = null === b ? null : Math.max( 0, 100 - b ),
				j = _ > 0 && null !== b && f >= 0,
				N =
					j && null !== x
						? ( 0, s.sprintf )(
								/* translators: 1: percentage used, 2: percentage left */ /* translators: 1: percentage used, 2: percentage left */
								( 0, s.__ )(
									'%1$d%% used (%2$d%% left)',
									'clawpress'
								),
								b,
								x
						  )
						: '',
				v = j
					? ( 0, s.sprintf )(
							/* translators: 1: used tokens, 2: available context-window tokens */ /* translators: 1: used tokens, 2: available context-window tokens */
							( 0, s.__ )(
								'%1$s / %2$s tokens used',
								'clawpress'
							),
							n( f ),
							n( _ )
					  )
					: '';
			return ( 0, t.jsxs )( 'div', {
				className: 'clawpress-input',
				children: [
					Array.isArray( i ) && i.length > 0
						? ( 0, t.jsxs )( 'div', {
								className: 'clawpress-suggestions',
								'aria-label': ( 0, s.__ )(
									'Suggestions',
									'clawpress'
								),
								children: [
									( 0, t.jsx )( 'div', {
										className:
											'clawpress-suggestions-label',
										children: ( 0, s.__ )(
											'Suggestions',
											'clawpress'
										),
									} ),
									( 0, t.jsx )( 'div', {
										className: 'clawpress-suggestions-list',
										children: i.map( ( e ) =>
											( 0, t.jsx )(
												'button',
												{
													className:
														'clawpress-suggestion button button-secondary',
													onClick: () => u?.( e ),
													type: 'button',
													disabled: c,
													children: e,
												},
												e
											)
										),
									} ),
								],
						  } )
						: null,
					( 0, t.jsx )( 'textarea', {
						ref: h,
						value: r,
						onChange: a,
						onKeyDown: ( e ) => {
							if (
								( 'Enter' !== e.key ||
									e.shiftKey ||
									( e.preventDefault(), l() ),
								'ArrowUp' === e.key )
							) {
								const s = h.current,
									t = s?.selectionStart ?? 0,
									n = ! r.slice( 0, t ).includes( '\n' );
								if ( ( '' === r.trim() || n ) && p ) {
									e.preventDefault();
									const s = p( r );
									if ( null == s ) {
										return;
									}
									a( { target: { value: s } } ),
										setTimeout( () => {
											if ( ! h.current ) {
												return;
											}
											const e = s.length;
											h.current.setSelectionRange( e, e );
										}, 0 );
								}
							}
							if ( 'ArrowDown' === e.key ) {
								const s = h.current,
									t = s?.selectionStart ?? 0,
									n = ! r.slice( t ).includes( '\n' );
								if ( ( '' === r.trim() || n ) && m ) {
									e.preventDefault();
									const s = m();
									if ( null == s ) {
										return;
									}
									a( { target: { value: s } } ),
										setTimeout( () => {
											if ( ! h.current ) {
												return;
											}
											const e = s.length;
											h.current.setSelectionRange( e, e );
										}, 0 );
								}
							}
						},
						placeholder: ( 0, s.__ )(
							'Ask me anything…',
							'clawpress'
						),
						disabled: c,
					} ),
					( 0, t.jsxs )( 'div', {
						className: 'clawpress-input-footer',
						children: [
							( 0, t.jsx )( 'div', {
								className: 'clawpress-context-slot',
								children: j
									? ( 0, t.jsxs )( 'div', {
											className:
												'clawpress-context-indicator',
											role: 'img',
											tabIndex: 0,
											'aria-label': ( 0, s.sprintf )(
												/* translators: 1: context usage summary, 2: token usage summary */ /* translators: 1: context usage summary, 2: token usage summary */
												( 0, s.__ )(
													'Context window: %1$s. %2$s.',
													'clawpress'
												),
												N,
												v
											),
											children: [
												( 0, t.jsx )( 'span', {
													className:
														'clawpress-context-pie',
													style: {
														'--clawpress-context-used': `${ b }%`,
													},
													'aria-hidden': 'true',
												} ),
												( 0, t.jsxs )( 'div', {
													className:
														'clawpress-context-tooltip',
													role: 'tooltip',
													children: [
														( 0, t.jsx )( 'div', {
															className:
																'clawpress-context-tooltip-title',
															children: ( 0,
															s.__ )(
																'Context window:',
																'clawpress'
															),
														} ),
														( 0, t.jsx )( 'div', {
															className:
																'clawpress-context-tooltip-line',
															children: N,
														} ),
														( 0, t.jsx )( 'div', {
															className:
																'clawpress-context-tooltip-line',
															children: v,
														} ),
														( 0, t.jsx )( 'div', {
															className:
																'clawpress-context-tooltip-note',
															children: ( 0,
															s.__ )(
																'Codex automatically compacts its context',
																'clawpress'
															),
														} ),
													],
												} ),
											],
									  } )
									: null,
							} ),
							c
								? ( 0, t.jsx )( 'button', {
										className: 'button',
										onClick: o,
										type: 'button',
										children: ( 0, s.__ )(
											'Stop',
											'clawpress'
										),
								  } )
								: ( 0, t.jsx )( 'button', {
										className: 'button button-primary',
										onClick: l,
										type: 'button',
										children: ( 0, s.__ )(
											'Send',
											'clawpress'
										),
								  } ),
						],
					} ),
				],
			} );
		},
		o = ( e ) => {
			if ( ! e || void 0 === e.arguments || null === e.arguments ) {
				return {};
			}
			if ( 'string' === typeof e.arguments ) {
				if ( '' === e.arguments.trim() ) {
					return {};
				}
				try {
					return JSON.parse( e.arguments );
				} catch {
					return { raw: e.arguments };
				}
			}
			return e.arguments;
		},
		c = ( {
			status: e,
			title: r,
			canRerun: a,
			policy: n,
			isOpen: l,
			error: o,
			children: c,
			onRun: i,
			onCancel: d,
			showActions: u,
			runLabelOverride: p,
		} ) => {
			const m =
					p ||
					( ( e, t ) =>
						'running' === e
							? ( 0, s.__ )( 'Running…', 'clawpress' )
							: 'blocked' === e
							? ( 0, s.__ )( 'Blocked', 'clawpress' )
							: 'done' === e && t
							? ( 0, s.__ )( 'Re-run', 'clawpress' )
							: 'error' === e
							? ( 0, s.__ )( 'Retry', 'clawpress' )
							: ( 0, s.__ )( 'Run', 'clawpress' ) )( e, a ),
				h = 'cancelled' === e,
				g = 'error' === e,
				w =
					void 0 !== u
						? u
						: 'running' !== e && ! h && ( 'done' !== e || a ),
				y = 'blocked' === e,
				f = ( ( e, t ) =>
					'running' === e
						? ( 0, s.__ )( 'Running…', 'clawpress' )
						: 'blocked' === e
						? 'serial' === t?.concurrency
							? ( 0, s.__ )(
									'Blocked (waiting for earlier tool).',
									'clawpress'
							  )
							: ( 0, s.__ )( 'Blocked.', 'clawpress' )
						: null )( e, n );
			return ( 0, t.jsxs )( 'details', {
				className: 'clawpress-tool-dialog',
				open: ! h && l,
				children: [
					( 0, t.jsxs )( 'summary', {
						className: 'clawpress-tool-dialog-summary',
						children: [
							( 0, t.jsxs )( 'span', {
								className: 'clawpress-tool-dialog-heading',
								children: [
									( 0, t.jsx )( 'span', {
										className: `clawpress-tool-dialog-status clawpress-tool-dialog-status-${ e }`,
										'aria-hidden': 'true',
									} ),
									( 0, t.jsx )( 'span', {
										className:
											'clawpress-tool-dialog-title',
										children: r,
									} ),
								],
							} ),
							w
								? ( 0, t.jsxs )( 'span', {
										className:
											'clawpress-tool-dialog-actions',
										children: [
											g
												? ( 0, t.jsx )( 'span', {
														className:
															'clawpress-tool-dialog-error-icon',
														'aria-hidden': 'true',
														children: ( 0, t.jsx )(
															'svg',
															{
																viewBox:
																	'0 0 24 24',
																role: 'img',
																children: ( 0,
																t.jsx )(
																	'path',
																	{
																		d: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 6a1 1 0 0 1 1 1v5a1 1 0 1 1-2 0V9a1 1 0 0 1 1-1Zm0 10a1.25 1.25 0 1 1 0-2.5A1.25 1.25 0 0 1 12 18Z',
																	}
																),
															}
														),
												  } )
												: null,
											( 0, t.jsx )( 'button', {
												className:
													'button button-primary',
												type: 'button',
												onClick: i,
												disabled:
													'running' === e || y || h,
												children: m,
											} ),
											( 0, t.jsx )( 'button', {
												className: 'button',
												type: 'button',
												onClick: d,
												children: ( 0, s.__ )(
													'Cancel',
													'clawpress'
												),
											} ),
										],
								  } )
								: null,
						],
					} ),
					( 0, t.jsxs )( 'div', {
						className: 'clawpress-tool-dialog-body',
						children: [
							h
								? ( 0, t.jsx )( 'p', {
										children: ( 0, s.__ )(
											'Cancelled.',
											'clawpress'
										),
								  } )
								: null,
							f ? ( 0, t.jsx )( 'p', { children: f } ) : null,
							'error' === e
								? ( 0, t.jsx )( 'p', { children: o } )
								: null,
							c,
						],
					} ),
				],
			} );
		},
		i = ( {
			fields: r,
			initialValues: a,
			onSubmit: n,
			onPreview: l,
			onRun: o,
			onCancel: c,
			disabled: i,
		} ) => {
			const [ d, u ] = ( 0, e.useState )( a );
			( 0, e.useEffect )( () => {
				u( a );
			}, [ a ] );
			const p = ( e, s ) => u( ( t ) => ( { ...t, [ e ]: s } ) );
			return ( 0, t.jsxs )( 'form', {
				className: 'clawpress-tool-dialog-form',
				onSubmit: ( e ) => {
					e.preventDefault(), i || n( d );
				},
				children: [
					r.map( ( e ) => {
						const s = d[ e.name ],
							r = {
								id: `clawpress-tool-field-${ e.name }`,
								name: e.name,
								disabled: i,
							};
						return 'hidden' === e.type
							? ( 0, t.jsx )(
									'input',
									{ ...r, type: 'hidden', value: s ?? '' },
									e.name
							  )
							: ( 0, t.jsxs )(
									'label',
									{
										className:
											'clawpress-tool-dialog-field',
										htmlFor: r.id,
										children: [
											( 0, t.jsx )( 'span', {
												className:
													'clawpress-tool-dialog-label',
												children: e.label,
											} ),
											'textarea' === e.type
												? ( 0, t.jsx )( 'textarea', {
														...r,
														className:
															'clawpress-tool-dialog-input',
														rows: e.rows || 3,
														value: s ?? '',
														onChange: ( s ) =>
															p(
																e.name,
																s.target.value
															),
												  } )
												: 'select' === e.type
												? ( 0, t.jsx )( 'select', {
														...r,
														className:
															'clawpress-tool-dialog-input',
														value: s ?? '',
														onChange: ( s ) =>
															p(
																e.name,
																s.target.value
															),
														children:
															e.options?.map(
																( e ) =>
																	( 0,
																	t.jsx )(
																		'option',
																		{
																			value: e.value,
																			children:
																				e.label,
																		},
																		e.value
																	)
															),
												  } )
												: 'checkbox' === e.type
												? ( 0, t.jsx )( 'input', {
														...r,
														type: 'checkbox',
														checked: Boolean( s ),
														onChange: ( s ) =>
															p(
																e.name,
																s.target.checked
															),
												  } )
												: ( 0, t.jsx )( 'input', {
														...r,
														className:
															'clawpress-tool-dialog-input',
														type: e.type || 'text',
														value: s ?? '',
														onChange: ( s ) =>
															p(
																e.name,
																s.target.value
															),
												  } ),
											e.help
												? ( 0, t.jsx )( 'span', {
														className:
															'clawpress-tool-dialog-help',
														children: e.help,
												  } )
												: null,
										],
									},
									e.name
							  );
					} ),
					( 0, t.jsxs )( 'div', {
						className: 'clawpress-tool-dialog-form-actions',
						children: [
							( 0, t.jsx )( 'button', {
								className: 'button button-primary',
								type: 'submit',
								disabled: i,
								children: ( 0, s.__ )( 'Preview', 'clawpress' ),
							} ),
							o
								? ( 0, t.jsx )( 'button', {
										className: 'button',
										type: 'button',
										onClick: ( e ) => {
											e.preventDefault(),
												i || ( o && o( d ) );
										},
										disabled: i,
										children: ( 0, s.__ )(
											'Run',
											'clawpress'
										),
								  } )
								: null,
							c
								? ( 0, t.jsx )( 'button', {
										className: 'button',
										type: 'button',
										onClick: c,
										disabled: i,
										children: ( 0, s.__ )(
											'Cancel',
											'clawpress'
										),
								  } )
								: null,
						],
					} ),
				],
			} );
		},
		d = 'update_posts_find_replace',
		u = ( {
			toolName: e,
			args: r,
			toolDialog: a,
			runTool: n,
			onCancel: l,
			policy: o,
			isOpen: i,
		} ) => {
			const d = a.status || 'idle',
				u = a.error || null,
				p = a.result || null,
				m = Boolean( o?.canRerun );
			return ( 0, t.jsxs )( c, {
				status: d,
				title: e || ( 0, s.__ )( 'Unknown tool', 'clawpress' ),
				canRerun: m,
				policy: o,
				isOpen: i,
				error: u,
				onRun: ( e ) => {
					e.preventDefault(),
						e.stopPropagation(),
						n( a.id, a.args || r );
				},
				onCancel: ( e ) => {
					e.preventDefault(), e.stopPropagation(), l( a.id );
				},
				children: [
					'done' === d
						? ( 0, t.jsx )( 'syntax-highlight', {
								language: 'json',
								children: JSON.stringify( p, null, 2 ),
						  } )
						: ( 0, t.jsx )( 'syntax-highlight', {
								language: 'json',
								children: JSON.stringify( r, null, 2 ),
						  } ),
					a.diff
						? ( 0, t.jsxs )( 'div', {
								className: 'clawpress-tool-dialog-diff',
								children: [
									( 0, t.jsx )( 'h4', {
										children: ( 0, s.__ )(
											'Changes:',
											'clawpress'
										),
									} ),
									( 0, t.jsx )( 'syntax-highlight', {
										language: 'json',
										children: JSON.stringify(
											a.diff,
											null,
											2
										),
									} ),
								],
						  } )
						: null,
				],
			} );
		},
		p = {
			[ d ]: {
				renderer: ( {
					args: e,
					toolDialog: r,
					runTool: a,
					onCancel: n,
					policy: l,
					isOpen: o,
				} ) => {
					const d = e.search ?? '',
						u = e.replace ?? '',
						p = e.post_status ?? 'any',
						m =
							( void 0 === e.dry_run || e.dry_run,
							r.status || 'idle' ),
						h = r.error || null,
						g = r.result || null,
						w =
							'done' === m
								? Boolean( g?.dry_run )
								: Boolean( l?.canRerun ),
						y = 'done' === m && g?.dry_run,
						f = 'error' === m,
						_ = 'blocked' === m,
						b = g?.total ?? 0,
						x = Array.isArray( g?.changed ) ? g.changed : [],
						j = ( e ) => {
							a( r.id, e );
						},
						N = [
							{
								name: 'search',
								label: ( 0, s.__ )( 'Find', 'clawpress' ),
								type: 'text',
								help: ( 0, s.__ )(
									'The text to search for.',
									'clawpress'
								),
							},
							{
								name: 'replace',
								label: ( 0, s.__ )( 'Replace', 'clawpress' ),
								type: 'text',
								help: ( 0, s.__ )(
									'The text to replace with.',
									'clawpress'
								),
							},
							{
								name: 'post_status',
								label: ( 0, s.__ )(
									'Post status',
									'clawpress'
								),
								type: 'select',
								options: [
									{
										value: 'any',
										label: ( 0, s.__ )(
											'Any',
											'clawpress'
										),
									},
									{
										value: 'publish',
										label: ( 0, s.__ )(
											'Published',
											'clawpress'
										),
									},
									{
										value: 'draft',
										label: ( 0, s.__ )(
											'Draft',
											'clawpress'
										),
									},
								],
							},
							{
								name: 'dry_run',
								label: ( 0, s.__ )( 'Dry run', 'clawpress' ),
								type: 'hidden',
							},
						],
						v =
							'cancelled' === m
								? ( 0, s.__ )(
										'Update Posts - Find and Replace (Cancelled)',
										'clawpress'
								  )
								: ( 0, s.__ )(
										'Update Posts - Find and Replace',
										'clawpress'
								  );
					return ( 0, t.jsx )( c, {
						status: m,
						title: v,
						canRerun: w,
						policy: l,
						isOpen: o,
						error: h,
						showActions: ! 1,
						onRun: ( e ) => {
							e.preventDefault(),
								e.stopPropagation(),
								j( {
									search: d,
									replace: u,
									post_status: p,
									dry_run: ! 1,
								} );
						},
						onCancel: ( e ) => {
							e.preventDefault(), e.stopPropagation(), n( r.id );
						},
						children:
							'cancelled' === m
								? null
								: f
								? ( 0, t.jsxs )( 'div', {
										className:
											'clawpress-tool-result-actions',
										children: [
											( 0, t.jsx )( 'button', {
												className:
													'button button-primary',
												type: 'button',
												onClick: () =>
													j( {
														...( r.args || e ),
														dry_run:
															! 1 !==
															r.args?.dry_run,
													} ),
												children: ( 0, s.__ )(
													'Retry',
													'clawpress'
												),
											} ),
											( 0, t.jsx )( 'button', {
												className: 'button',
												type: 'button',
												onClick: () => n( r.id ),
												children: ( 0, s.__ )(
													'Cancel',
													'clawpress'
												),
											} ),
										],
								  } )
								: 'done' !== m
								? ( 0, t.jsx )( i, {
										fields: N,
										initialValues: {
											search: d,
											replace: u,
											post_status: p,
											dry_run: ! 0,
										},
										disabled: 'running' === m || _,
										onSubmit: ( e ) =>
											j( { ...e, dry_run: ! 0 } ),
										onRun: ( e ) =>
											j( { ...e, dry_run: ! 1 } ),
										onCancel: () => n( r.id ),
								  } )
								: ( 0, t.jsxs )( 'div', {
										className: 'clawpress-tool-result',
										children: [
											( 0, t.jsx )( 'div', {
												className:
													'clawpress-tool-result-summary',
												children: ( 0, t.jsx )(
													'span',
													{
														children: y
															? ( 0, s.__ )(
																	'Preview Results',
																	'clawpress'
															  )
															: ( 0, s.__ )(
																	'Results',
																	'clawpress'
															  ),
													}
												),
											} ),
											0 === b
												? ( 0, t.jsx )( 'p', {
														children: ( 0, s.__ )(
															'No matches found.',
															'clawpress'
														),
												  } )
												: ( 0, t.jsxs )( 'details', {
														className:
															'clawpress-tool-result-list',
														open: ! 0,
														children: [
															( 0, t.jsx )(
																'summary',
																{
																	children:
																		( 0,
																		s.sprintf )(
																			/* translators: %d: number of changed posts */ /* translators: %d: number of changed posts */
																			( 0,
																			s.__ )(
																				'Changed posts (%d)',
																				'clawpress'
																			),
																			b
																		),
																}
															),
															( 0, t.jsx )(
																'ul',
																{
																	children:
																		x.map(
																			(
																				e
																			) =>
																				( 0,
																				t.jsxs )(
																					'li',
																					{
																						children:
																							[
																								( 0,
																								t.jsx )(
																									'span',
																									{
																										className:
																											'clawpress-tool-result-title',
																										children:
																											e.title ||
																											( 0,
																											s.__ )(
																												'Untitled',
																												'clawpress'
																											),
																									}
																								),
																								( 0,
																								t.jsx )(
																									'span',
																									{
																										className:
																											'clawpress-tool-result-meta',
																										children:
																											( 0,
																											s.sprintf )(
																												/* translators: %d: post ID */ /* translators: %d: post ID */
																												( 0,
																												s.__ )(
																													'ID %d',
																													'clawpress'
																												),
																												e.id
																											),
																									}
																								),
																								( 0,
																								t.jsx )(
																									'span',
																									{
																										className:
																											'clawpress-tool-result-meta',
																										children:
																											( 0,
																											s.sprintf )(
																												/* translators: %d: number of replacements in a post */ /* translators: %d: number of replacements in a post */
																												( 0,
																												s._n )(
																													'%d change',
																													'%d changes',
																													e.count,
																													'clawpress'
																												),
																												e.count
																											),
																									}
																								),
																							],
																					},
																					e.id
																				)
																		),
																}
															),
														],
												  } ),
											y
												? ( 0, t.jsxs )( 'div', {
														className:
															'clawpress-tool-result-actions',
														children: [
															( 0, t.jsx )(
																'button',
																{
																	className:
																		'button button-primary',
																	type: 'button',
																	onClick:
																		() =>
																			j( {
																				search: d,
																				replace:
																					u,
																				post_status:
																					p,
																				dry_run:
																					! 1,
																			} ),
																	children:
																		( 0,
																		s.__ )(
																			'Run',
																			'clawpress'
																		),
																}
															),
															( 0, t.jsx )(
																'button',
																{
																	className:
																		'button',
																	type: 'button',
																	onClick:
																		() =>
																			n(
																				r.id
																			),
																	children:
																		( 0,
																		s.__ )(
																			'Cancel',
																			'clawpress'
																		),
																}
															),
														],
												  } )
												: null,
										],
								  } ),
					} );
				},
				policy: { concurrency: 'serial', canRerun: ! 1 },
			},
		},
		m = ( e ) => p[ e ]?.policy || {},
		h = ( { toolDialog: e, isOpen: s, onRunTool: r, onCancel: a } ) => {
			if ( ! e ) {
				return null;
			}
			const n = o( e.function ),
				l = e.function?.name || '',
				c = ( ( e ) => p[ e ]?.renderer || u )( l ),
				i = m( l );
			return ( 0, t.jsx )( c, {
				toolName: l,
				args: n,
				toolDialog: e,
				runTool: r,
				onCancel: a,
				policy: i,
				isOpen: s,
			} );
		},
		g = ( e ) =>
			Array.isArray( e?.data?.actions )
				? e.data.actions
						.filter( ( e ) => e && 'object' === typeof e )
						.map( ( e, s ) => {
							const t =
									'string' === typeof e.label
										? e.label.trim()
										: '',
								r =
									'string' === typeof e.type && e.type.trim()
										? e.type.trim().toLowerCase()
										: 'send_prompt';
							if ( ! t ) {
								return null;
							}
							if ( 'open_url' === r ) {
								const r = [ e.url, e.href ]
									.map( ( e ) =>
										'string' === typeof e ? e.trim() : ''
									)
									.find( ( e ) => e.length > 0 );
								return r
									? {
											id:
												'string' === typeof e.id &&
												e.id.trim()
													? e.id.trim()
													: `action-${ s }`,
											label: t,
											type: 'open_url',
											url: r,
									  }
									: null;
							}
							if ( 'run_tool' === r ) {
								const r = [ e.tool, e.tool_name, e.name ]
									.map( ( e ) =>
										'string' === typeof e ? e.trim() : ''
									)
									.find( ( e ) => e.length > 0 );
								if ( ! r ) {
									return null;
								}
								let a = {};
								if (
									e.args &&
									'object' === typeof e.args &&
									! Array.isArray( e.args )
								) {
									a = e.args;
								} else if ( 'string' === typeof e.arguments ) {
									try {
										const s = JSON.parse( e.arguments );
										s &&
											'object' === typeof s &&
											! Array.isArray( s ) &&
											( a = s );
									} catch {
										a = {};
									}
								} else {
									e.arguments &&
										'object' === typeof e.arguments &&
										! Array.isArray( e.arguments ) &&
										( a = e.arguments );
								}
								return {
									id:
										'string' === typeof e.id && e.id.trim()
											? e.id.trim()
											: `action-${ s }`,
									label: t,
									type: 'run_tool',
									tool: r,
									args: a,
								};
							}
							const a = [ e.prompt, e.message, e.command ]
								.map( ( e ) =>
									'string' === typeof e ? e.trim() : ''
								)
								.find( ( e ) => e.length > 0 );
							return a
								? {
										id:
											'string' === typeof e.id &&
											e.id.trim()
												? e.id.trim()
												: `action-${ s }`,
										label: t,
										type: 'send_prompt',
										prompt: a,
								  }
								: null;
						} )
						.filter( ( e ) => Boolean( e ) )
				: [],
		w = ( { card: e, onSendAction: r, isBusy: a = ! 1 } ) => {
			const n =
					'string' === typeof e?.data?.title && e.data.title.trim()
						? e.data.title
						: ( 0, s.__ )( 'Welcome to ClawPress', 'clawpress' ),
				l =
					'string' === typeof e?.data?.message &&
					e.data.message.trim()
						? e.data.message
						: ( 0, s.__ )(
								'Hello! I am ready to help with your WordPress tasks.',
								'clawpress'
						  ),
				o =
					'string' === typeof e?.data?.subtitle &&
					e.data.subtitle.trim()
						? e.data.subtitle
						: '',
				c =
					'string' === typeof e?.data?.emoji && e.data.emoji.trim()
						? e.data.emoji
						: '👋',
				i = g( e );
			return ( 0, t.jsx )( 'div', {
				className: 'clawpress-card clawpress-card-welcome',
				children: ( 0, t.jsxs )( 'div', {
					className: 'clawpress-card-body',
					children: [
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-title',
							children: ( 0, t.jsxs )( 'div', {
								className: 'clawpress-card-welcome-title-line',
								children: [
									( 0, t.jsx )( 'span', {
										className: 'clawpress-card-emoji',
										'aria-hidden': 'true',
										children: c,
									} ),
									( 0, t.jsx )( 'div', {
										className: 'clawpress-card-title',
										children: n,
									} ),
								],
							} ),
						} ),
						o
							? ( 0, t.jsx )( 'div', {
									className:
										'clawpress-card-section-subtitle',
									children: ( 0, t.jsx )( 'div', {
										className: 'clawpress-card-subtitle',
										children: o,
									} ),
							  } )
							: null,
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-content',
							children: ( 0, t.jsx )( 'div', {
								className: 'clawpress-card-text',
								children: l,
							} ),
						} ),
						i.length > 0
							? ( 0, t.jsx )( 'div', {
									className: 'clawpress-card-section-buttons',
									children: ( 0, t.jsx )( 'div', {
										className: 'clawpress-card-actions',
										children: i.map( ( e ) =>
											( 0, t.jsx )(
												'button',
												{
													type: 'button',
													className:
														'button button-secondary button-small',
													onClick: () => r?.( e ),
													disabled: a,
													children: e.label,
												},
												e.id
											)
										),
									} ),
							  } )
							: null,
					],
				} ),
			} );
		},
		y = '__clawpress_custom_model__',
		f = ( e, s ) =>
			e
				.filter(
					( e ) =>
						'send_prompt' === e?.type &&
						'string' === typeof e.prompt &&
						e.prompt.startsWith( s )
				)
				.map( ( e ) => ( {
					...e,
					value: e.prompt.slice( s.length ).trim(),
				} ) )
				.filter( ( e ) => e.value.length > 0 ),
		_ = ( e ) =>
			'string' !== typeof e
				? ''
				: e.replace( /\r\n/g, '\n' ).replace( /<br\s*\/?>/gi, '\n' ),
		b = ( { card: r, onSendAction: a, isBusy: n = ! 1 } ) => {
			const l =
					r?.data &&
					'object' === typeof r.data &&
					! Array.isArray( r.data )
						? r.data
						: {},
				o =
					'string' === typeof l.title && l.title.trim()
						? l.title
						: ( 0, s.__ )( 'Setup Wizard', 'clawpress' ),
				c =
					'string' === typeof l.emoji && l.emoji.trim()
						? l.emoji
						: '🧙',
				i =
					'string' === typeof l.message && l.message.trim()
						? _( l.message )
						: ( 0, s.__ )(
								'Follow these steps to finish setup.',
								'clawpress'
						  ),
				d =
					'string' === typeof l.detail && l.detail.trim()
						? l.detail
						: '',
				u =
					'string' === typeof l.error && l.error.trim()
						? l.error
						: '',
				p = _( d ),
				m = _( u ),
				h = 'string' === typeof l.step ? l.step.trim() : '',
				w =
					'string' === typeof l.step_label && l.step_label.trim()
						? l.step_label
						: h,
				b = Number.isFinite( Number( l.step_index ) )
					? Number( l.step_index )
					: null,
				x = Number.isFinite( Number( l.step_total ) )
					? Number( l.step_total )
					: null,
				j = Array.isArray( l.steps ) ? l.steps : [],
				N = g( r ),
				v = ( 0, e.useMemo )( () => f( N, '/setup provider ' ), [ N ] ),
				k = ( 0, e.useMemo )( () => f( N, '/setup model ' ), [ N ] ),
				S =
					'string' === typeof l.selected_model &&
					l.selected_model.trim()
						? l.selected_model.trim()
						: '',
				A = ( 0, e.useMemo )(
					() =>
						'model' !== h
							? k
							: [
									...k,
									{
										id: y,
										label: ( 0, s.__ )(
											'Use Custom Model ID',
											'clawpress'
										),
										type: 'send_prompt',
										prompt: '',
									},
							  ],
					[ k, h ]
				),
				C = ( 0, e.useMemo )( () => {
					const e = new Set( [
						...v.map( ( e ) => e.id ),
						...k.map( ( e ) => e.id ),
					] );
					return N.filter( ( s ) => ! e.has( s.id ) );
				}, [ N, v, k ] ),
				E = ( 0, e.useMemo )( () => {
					const e = C.some(
						( e ) =>
							'send_prompt' === e?.type &&
							'string' === typeof e.prompt &&
							'/setup back' === e.prompt.trim()
					);
					return 'provider' === h || e
						? C
						: [
								{
									id: 'wizard-back-fallback',
									label: ( 0, s.__ )( 'Back', 'clawpress' ),
									type: 'send_prompt',
									prompt: '/setup back',
								},
								...C,
						  ];
				}, [ C, h ] ),
				[ R, T ] = ( 0, e.useState )( v[ 0 ]?.id || '' ),
				[ $, P ] = ( 0, e.useState )( A[ 0 ]?.id || '' ),
				[ D, M ] = ( 0, e.useState )( '' );
			( 0, e.useEffect )( () => {
				const e = v.map( ( e ) => e.id );
				0 !== e.length ? e.includes( R ) || T( e[ 0 ] ) : T( '' );
			}, [ v, R ] ),
				( 0, e.useEffect )( () => {
					const e = A.map( ( e ) => e.id );
					0 !== e.length ? e.includes( $ ) || P( e[ 0 ] ) : P( '' );
				}, [ A, $ ] ),
				( 0, e.useEffect )( () => {
					if ( 'model' !== h || ! S ) {
						return;
					}
					const e = k.find( ( e ) => e.value === S );
					e ? P( e.id ) : ( P( y ), M( S ) );
				}, [ k, S, h ] );
			const L = v.find( ( e ) => e.id === R ) || v[ 0 ] || null,
				I = k.find( ( e ) => e.id === $ ) || k[ 0 ] || null,
				O = 'model' === h && $ === y,
				B = D.trim(),
				q =
					'string' === typeof l.settings_url && l.settings_url.trim()
						? l.settings_url.trim()
						: '',
				F = ( 0, e.useMemo )( () => {
					if ( ! q || 'undefined' === typeof window ) {
						return ! 1;
					}
					try {
						const e = new URL( window.location.href ),
							s = new URL( q, window.location.origin );
						return (
							e.pathname === s.pathname && e.search === s.search
						);
					} catch {
						return ! 1;
					}
				}, [ q ] ),
				U =
					b && x
						? ( 0, s.sprintf )(
								/* translators: 1: current step number, 2: total step count, 3: step label. */ /* translators: 1: current step number, 2: total step count, 3: step label. */
								( 0, s.__ )(
									'Step %1$d OF %2$d : %3$s',
									'clawpress'
								),
								b,
								x,
								w || ''
						  )
						: '',
				W =
					'workspace' === h &&
					'string' === typeof l.workspace_path &&
					l.workspace_path.trim()
						? ( ( e ) => {
								if ( 'string' !== typeof e ) {
									return '';
								}
								const s = e.trim().replace( /\\/g, '/' );
								if ( ! s ) {
									return '';
								}
								const t = s.indexOf( '/wp-content' );
								return -1 === t ? s : s.slice( t );
						  } )( l.workspace_path )
						: '',
				H =
					'workspace' !== h
						? ''
						: 'string' === typeof l.workspace_exists &&
						  l.workspace_exists.trim()
						? l.workspace_exists.trim()
						: 'string' === typeof l.workspace_exists_line &&
						  l.workspace_exists_line.trim()
						? l.workspace_exists_line
								.replace( /^Exists\s*:\s*/i, '' )
								.trim()
						: '',
				J = 'provider' === h && v.length > 1,
				z = 'model' === h && A.length > 0,
				G = 'provider' === h && 1 === v.length,
				K = 'model' === h && ! z && 1 === k.length,
				X = G || K || E.length > 0,
				V = J || z || X,
				Z = O ? ( B ? `/setup model ${ B }` : '' ) : I?.prompt || '',
				Q = 'provider' === h ? L?.prompt || '' : Z,
				Y =
					n ||
					( 'provider' === h && ! L ) ||
					( 'model' === h && ( O ? 0 === B.length : ! I ) );
			return ( 0, t.jsx )( 'div', {
				className: 'clawpress-card clawpress-card-setup',
				children: ( 0, t.jsxs )( 'div', {
					className: 'clawpress-card-body',
					children: [
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-title',
							children: ( 0, t.jsxs )( 'div', {
								className: 'clawpress-card-setup-title-line',
								children: [
									( 0, t.jsx )( 'span', {
										className: 'clawpress-card-setup-emoji',
										'aria-hidden': 'true',
										children: c,
									} ),
									( 0, t.jsx )( 'div', {
										className: 'clawpress-card-title',
										children: o,
									} ),
								],
							} ),
						} ),
						U || j.length > 0
							? ( 0, t.jsxs )( 'div', {
									className:
										'clawpress-card-section-subtitle',
									children: [
										U
											? ( 0, t.jsx )( 'div', {
													className:
														'clawpress-card-subtitle clawpress-card-setup-step-line',
													children: U,
											  } )
											: null,
										j.length > 0
											? ( 0, t.jsx )( 'div', {
													className:
														'clawpress-card-setup-progress',
													'aria-hidden': 'true',
													children: j.map(
														( e, s ) => {
															const r =
																	'string' ===
																	typeof e?.status
																		? e.status
																		: 'pending',
																a = [
																	'pending',
																	'done',
																	'current',
																	'completed',
																].includes( r )
																	? r
																	: 'pending';
															return ( 0,
															t.jsxs )(
																'div',
																{
																	className: `clawpress-card-setup-progress-item clawpress-card-setup-progress-${ a }`,
																	children: [
																		( 0,
																		t.jsx )(
																			'span',
																			{
																				className:
																					'clawpress-card-setup-progress-dot',
																				children:
																					s +
																					1,
																			}
																		),
																		s <
																		j.length -
																			1
																			? ( 0,
																			  t.jsx )(
																					'span',
																					{
																						className:
																							'clawpress-card-setup-progress-line',
																					}
																			  )
																			: null,
																	],
																},
																`${
																	e?.id || s
																}`
															);
														}
													),
											  } )
											: null,
									],
							  } )
							: null,
						( 0, t.jsxs )( 'div', {
							className: 'clawpress-card-section-content',
							children: [
								( 0, t.jsx )( 'div', {
									className: 'clawpress-card-text',
									children: i,
								} ),
								W || H
									? ( 0, t.jsxs )( 'div', {
											className:
												'clawpress-card-setup-field-list',
											children: [
												W
													? ( 0, t.jsxs )( 'div', {
															className:
																'clawpress-card-setup-field-row',
															children: [
																( 0, t.jsxs )(
																	'span',
																	{
																		className:
																			'clawpress-card-setup-field-label',
																		children:
																			[
																				( 0,
																				s.__ )(
																					'Path',
																					'clawpress'
																				),
																				' :',
																			],
																	}
																),
																( 0, t.jsx )(
																	'span',
																	{
																		className:
																			'clawpress-card-setup-field-value',
																		children:
																			W,
																	}
																),
															],
													  } )
													: null,
												H
													? ( 0, t.jsxs )( 'div', {
															className:
																'clawpress-card-setup-field-row',
															children: [
																( 0, t.jsxs )(
																	'span',
																	{
																		className:
																			'clawpress-card-setup-field-label',
																		children:
																			[
																				( 0,
																				s.__ )(
																					'Exists',
																					'clawpress'
																				),
																				' :',
																			],
																	}
																),
																( 0, t.jsx )(
																	'span',
																	{
																		className:
																			'clawpress-card-setup-field-value',
																		children:
																			H,
																	}
																),
															],
													  } )
													: null,
											],
									  } )
									: null,
								p
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-setup-detail',
											children: p,
									  } )
									: null,
								F && 'provider' === h
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-setup-detail',
											children: ( 0, s.__ )(
												'Provider settings page detected. After saving credentials, click Refresh Providers here.',
												'clawpress'
											),
									  } )
									: null,
								m
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-setup-error',
											children: m,
									  } )
									: null,
							],
						} ),
						V
							? ( 0, t.jsxs )( 'div', {
									className:
										'clawpress-card-section-buttons clawpress-card-setup-buttons',
									children: [
										J || z
											? ( 0, t.jsxs )( 'div', {
													className:
														'clawpress-card-setup-inline-controls',
													children: [
														J
															? ( 0, t.jsx )(
																	'select',
																	{
																		className:
																			'clawpress-card-setup-select',
																		value: R,
																		onChange:
																			(
																				e
																			) =>
																				T(
																					e
																						.target
																						.value
																				),
																		disabled:
																			n,
																		children:
																			v.map(
																				(
																					e
																				) =>
																					( 0,
																					t.jsx )(
																						'option',
																						{
																							value: e.id,
																							children:
																								e.label,
																						},
																						e.id
																					)
																			),
																	}
															  )
															: null,
														z
															? ( 0, t.jsx )(
																	'select',
																	{
																		className:
																			'clawpress-card-setup-select',
																		value: $,
																		onChange:
																			(
																				e
																			) =>
																				P(
																					e
																						.target
																						.value
																				),
																		disabled:
																			n,
																		children:
																			A.map(
																				(
																					e
																				) =>
																					( 0,
																					t.jsx )(
																						'option',
																						{
																							value: e.id,
																							children:
																								e.label,
																						},
																						e.id
																					)
																			),
																	}
															  )
															: null,
														O
															? ( 0, t.jsx )(
																	'input',
																	{
																		type: 'text',
																		className:
																			'clawpress-card-setup-input',
																		value: D,
																		onChange:
																			(
																				e
																			) =>
																				M(
																					e
																						.target
																						.value
																				),
																		placeholder:
																			( 0,
																			s.__ )(
																				'Enter custom model ID',
																				'clawpress'
																			),
																		disabled:
																			n,
																	}
															  )
															: null,
														( 0, t.jsx )(
															'button',
															{
																type: 'button',
																className:
																	'button button-primary button-small',
																disabled: Y,
																onClick: () =>
																	a?.( Q ),
																children:
																	'provider' ===
																	h
																		? ( 0,
																		  s.__ )(
																				'Use Selected Provider',
																				'clawpress'
																		  )
																		: ( 0,
																		  s.__ )(
																				'Use Selected Model',
																				'clawpress'
																		  ),
															}
														),
													],
											  } )
											: null,
										X
											? ( 0, t.jsxs )( 'div', {
													className:
														'clawpress-card-actions',
													children: [
														G
															? ( 0, t.jsx )(
																	'button',
																	{
																		type: 'button',
																		className:
																			'button button-primary button-small',
																		onClick:
																			() =>
																				a?.(
																					v[ 0 ]
																				),
																		disabled:
																			n,
																		children:
																			v[ 0 ]
																				.label,
																	}
															  )
															: null,
														K
															? ( 0, t.jsx )(
																	'button',
																	{
																		type: 'button',
																		className:
																			'button button-primary button-small',
																		onClick:
																			() =>
																				a?.(
																					k[ 0 ]
																				),
																		disabled:
																			n,
																		children:
																			k[ 0 ]
																				.label,
																	}
															  )
															: null,
														E.map( ( e ) =>
															( 0, t.jsx )(
																'button',
																{
																	type: 'button',
																	className:
																		'button button-secondary button-small',
																	onClick:
																		() =>
																			a?.(
																				e
																			),
																	disabled: n,
																	children:
																		e.label,
																},
																e.id
															)
														),
													],
											  } )
											: null,
									],
							  } )
							: null,
					],
				} ),
			} );
		},
		x = ( { card: e } ) => {
			const r =
					'string' === typeof e?.data?.title && e.data.title.trim()
						? e.data.title
						: ( 0, s.__ )( 'Request Error', 'clawpress' ),
				a =
					'string' === typeof e?.data?.message &&
					e.data.message.trim()
						? e.data.message
						: ( 0, s.__ )(
								'An unknown error occurred.',
								'clawpress'
						  ),
				n =
					'string' === typeof e?.data?.subtitle &&
					e.data.subtitle.trim()
						? e.data.subtitle
						: '';
			return ( 0, t.jsx )( 'div', {
				className: 'clawpress-card clawpress-card-error',
				role: 'alert',
				'aria-live': 'polite',
				children: ( 0, t.jsxs )( 'div', {
					className: 'clawpress-card-body',
					children: [
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-title',
							children: ( 0, t.jsx )( 'div', {
								className: 'clawpress-card-title',
								children: r,
							} ),
						} ),
						n
							? ( 0, t.jsx )( 'div', {
									className:
										'clawpress-card-section-subtitle',
									children: ( 0, t.jsx )( 'div', {
										className: 'clawpress-card-subtitle',
										children: n,
									} ),
							  } )
							: null,
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-content',
							children: ( 0, t.jsx )( 'div', {
								className: 'clawpress-card-text',
								children: a,
							} ),
						} ),
					],
				} ),
			} );
		},
		j = ( { card: e, onSendAction: r, isBusy: a = ! 1 } ) => {
			const n =
					'string' === typeof e?.data?.title && e.data.title.trim()
						? e.data.title
						: ( 0, s.__ )(
								'User Confirmation Required',
								'clawpress'
						  ),
				l =
					'string' === typeof e?.data?.subtitle &&
					e.data.subtitle.trim()
						? e.data.subtitle
						: ( 0, s.__ )(
								'Destructive action pending',
								'clawpress'
						  ),
				o =
					'string' === typeof e?.data?.message &&
					e.data.message.trim()
						? e.data.message
						: ( 0, s.__ )(
								'Please confirm or decline this action.',
								'clawpress'
						  ),
				c = g( e );
			return ( 0, t.jsx )( 'div', {
				className: 'clawpress-card clawpress-card-user-confirmation',
				role: 'alert',
				'aria-live': 'polite',
				children: ( 0, t.jsxs )( 'div', {
					className: 'clawpress-card-body',
					children: [
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-title',
							children: ( 0, t.jsx )( 'div', {
								className: 'clawpress-card-title',
								children: n,
							} ),
						} ),
						l
							? ( 0, t.jsx )( 'div', {
									className:
										'clawpress-card-section-subtitle',
									children: ( 0, t.jsx )( 'div', {
										className: 'clawpress-card-subtitle',
										children: l,
									} ),
							  } )
							: null,
						( 0, t.jsx )( 'div', {
							className: 'clawpress-card-section-content',
							children: ( 0, t.jsx )( 'div', {
								className: 'clawpress-card-text',
								children: o,
							} ),
						} ),
						c.length > 0
							? ( 0, t.jsx )( 'div', {
									className: 'clawpress-card-section-buttons',
									children: ( 0, t.jsx )( 'div', {
										className: 'clawpress-card-actions',
										children: c.map( ( e, s ) =>
											( 0, t.jsx )(
												'button',
												{
													type: 'button',
													className:
														'button button-small ' +
														( 0 === s
															? 'button-primary'
															: 'button-secondary' ),
													onClick: () => r?.( e ),
													disabled: a,
													children: e.label,
												},
												e.id
											)
										),
									} ),
							  } )
							: null,
					],
				} ),
			} );
		},
		N = ( {
			card: e,
			fallbackText: r,
			onSendAction: a,
			isBusy: n = ! 1,
		} ) => {
			if ( ! e || 'object' !== typeof e ) {
				return ( 0, t.jsx )( 'div', {
					className: 'clawpress-msg-content',
					children: r || '',
				} );
			}
			switch ( e.type ) {
				case 'welcome':
					return ( 0, t.jsx )( w, {
						card: e,
						onSendAction: a,
						isBusy: n,
					} );
				case 'setup':
					return ( 0, t.jsx )( b, {
						card: e,
						onSendAction: a,
						isBusy: n,
					} );
				case 'error':
					return ( 0, t.jsx )( x, { card: e } );
				case 'user_confirmation':
					return ( 0, t.jsx )( j, {
						card: e,
						onSendAction: a,
						isBusy: n,
					} );
			}
			const l =
					'string' === typeof e?.data?.title && e.data.title.trim()
						? e.data.title
						: ( 0, s.__ )( 'Card', 'clawpress' ),
				o =
					'string' === typeof e?.data?.message &&
					e.data.message.trim()
						? e.data.message
						: r || '',
				c =
					'string' === typeof e?.data?.subtitle &&
					e.data.subtitle.trim()
						? e.data.subtitle
						: '',
				i = g( e );
			return o || 0 !== i.length
				? ( 0, t.jsx )( 'div', {
						className: 'clawpress-card clawpress-card-generic',
						children: ( 0, t.jsxs )( 'div', {
							className: 'clawpress-card-body',
							children: [
								l
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-section-title',
											children: ( 0, t.jsx )( 'div', {
												className:
													'clawpress-card-title',
												children: l,
											} ),
									  } )
									: null,
								c
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-section-subtitle',
											children: ( 0, t.jsx )( 'div', {
												className:
													'clawpress-card-subtitle',
												children: c,
											} ),
									  } )
									: null,
								o
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-section-content',
											children: ( 0, t.jsx )( 'div', {
												className:
													'clawpress-card-text',
												children: o,
											} ),
									  } )
									: null,
								i.length > 0
									? ( 0, t.jsx )( 'div', {
											className:
												'clawpress-card-section-buttons',
											children: ( 0, t.jsx )( 'div', {
												className:
													'clawpress-card-actions',
												children: i.map( ( e ) =>
													( 0, t.jsx )(
														'button',
														{
															type: 'button',
															className:
																'button button-secondary button-small',
															onClick: () =>
																a?.( e ),
															disabled: n,
															children: e.label,
														},
														e.id
													)
												),
											} ),
									  } )
									: null,
							],
						} ),
				  } )
				: ( 0, t.jsx )( 'div', {
						className: 'clawpress-msg-content',
						children: r || '',
				  } );
		},
		v = ( e ) =>
			'string' === typeof e && e
				? e.split( /(`[^`\n]+`|\*\*[^*\n]+?\*\*)/g ).map( ( e, s ) => {
						const r = e.match( /^\*\*([^*\n]+?)\*\*$/ );
						if ( r ) {
							return ( 0, t.jsx )(
								'strong',
								{ children: r[ 1 ] },
								`strong-${ s }`
							);
						}
						const a = e.match( /^`([^`\n]+)`$/ );
						return a
							? ( 0, t.jsx )(
									'code',
									{ children: a[ 1 ] },
									`code-${ s }`
							  )
							: e;
				  } )
				: e || '',
		k = ( {
			messages: r,
			streaming: a,
			currentStreamText: n,
			waitingForResponse: l,
			toolDialogs: o,
			onRunToolDialog: c,
			onCancelToolDialog: i,
			onSendCardAction: d,
		} ) => {
			const u = ( 0, e.useRef )( null );
			( 0, e.useEffect )( () => {
				const e = u.current;
				e && ( e.scrollTop = e.scrollHeight );
			}, [ r, o, a, n, l ] );
			const p = o
					.filter(
						( e ) =>
							'serial' ===
								m( e.function?.name || '' ).concurrency &&
							'done' !== e.status &&
							'cancelled' !== e.status
					)
					.sort( ( e, s ) => e.createdAt - s.createdAt ),
				g = p[ 0 ]?.id || o[ o.length - 1 ]?.id || null,
				w = r.length - 1,
				y = [
					...r.map( ( e, s ) => ( {
						type: 'message',
						createdAt: e.createdAt ?? 0,
						messageIndex: s,
						data: e,
					} ) ),
					...o.map( ( e ) => ( {
						type: 'tool',
						createdAt: e.createdAt ?? 0,
						data: e,
					} ) ),
				].sort( ( e, s ) => {
					const t = e.createdAt - s.createdAt;
					return 0 !== t
						? t
						: 'message' === e.type && 'message' === s.type
						? ( e.messageIndex ?? 0 ) - ( s.messageIndex ?? 0 )
						: 'message' === e.type
						? -1
						: 'message' === s.type
						? 1
						: 0;
				} );
			return ( 0, t.jsxs )( 'div', {
				className: 'clawpress-messages',
				ref: u,
				children: [
					y.map( ( e ) =>
						'message' === e.type
							? ( () => {
									const r = 'system' === e.data.role,
										n = 'assistant' === e.data.role,
										o =
											e.data.card &&
											'object' === typeof e.data.card,
										c = o && e.messageIndex === w,
										i = e.data.content || '',
										u = /(\.\.\.|…)\s*$/.test( i ),
										p = r && u,
										m = p
											? i.replace(
													/\s*(\.\.\.|…)\s*$/,
													''
											  )
											: i;
									return ( 0, t.jsxs )(
										'div',
										{
											className: `clawpress-msg clawpress-${ e.data.role }`,
											children: [
												r || n
													? ( 0, t.jsx )( 'div', {
															className:
																'clawpress-msg-label',
															children: r
																? ( 0, s.__ )(
																		'System',
																		'clawpress'
																  )
																: ( 0, s.__ )(
																		'AGENT',
																		'clawpress'
																  ),
													  } )
													: null,
												o
													? ( 0, t.jsx )( N, {
															card: e.data.card,
															fallbackText: m,
															onSendAction: d,
															isBusy:
																a || l || ! c,
													  } )
													: ( 0, t.jsx )( 'div', {
															className:
																'clawpress-msg-content' +
																( p
																	? ' clawpress-thinking'
																	: '' ),
															children: v( m ),
													  } ),
											],
										},
										e.data.id || e.data.content
									);
							  } )()
							: ( 0, t.jsx )(
									h,
									{
										toolDialog: e.data,
										isOpen: e.data.id === g,
										onRunTool: c,
										onCancel: i,
									},
									e.data.id
							  )
					),
					l && ! n
						? ( 0, t.jsx )( 'div', {
								className: 'clawpress-msg clawpress-system',
								children: ( 0, t.jsx )( 'div', {
									className:
										'clawpress-msg-content clawpress-thinking',
									children: ( 0, s.__ )(
										'Thinking',
										'clawpress'
									),
								} ),
						  } )
						: null,
					a && n
						? ( 0, t.jsxs )( 'div', {
								className: 'clawpress-msg clawpress-assistant',
								children: [
									( 0, t.jsx )( 'div', {
										className: 'clawpress-msg-label',
										children: ( 0, s.__ )(
											'AGENT',
											'clawpress'
										),
									} ),
									( 0, t.jsx )( 'div', {
										className: 'clawpress-msg-content',
										children: v( n || '...' ),
									} ),
								],
						  } )
						: null,
				],
			} );
		},
		S = ( { onToggle: e } ) =>
			( 0, t.jsx )( 'button', {
				className: 'button button-primary clawpress-toggle',
				onClick: e,
				type: 'button',
				children: ( 0, s.__ )( 'ClawPress', 'clawpress' ),
			} ),
		A = async ( {
			url: e,
			method: t = 'GET',
			nonce: r,
			body: a,
			signal: n,
		} ) => {
			const l = await fetch( e, {
					method: t,
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': r,
					},
					body: a ? JSON.stringify( a ) : void 0,
					signal: n,
				} ),
				o = await l.text();
			let c = {};
			if ( o ) {
				try {
					c = JSON.parse( o );
				} catch {
					c = { message: o };
				}
			}
			if ( ! l.ok ) {
				const e =
					c?.message ||
					c?.error ||
					( 0, s.sprintf )(
						/* translators: %d: HTTP status code */ /* translators: %d: HTTP status code */
						( 0, s.__ )( 'Request failed (%d)', 'clawpress' ),
						l.status
					);
				throw new Error( e );
			}
			return c;
		},
		C = ( {
			mockEnabled: e,
			mockScenario: t,
			mockDelay: r,
			restBase: a,
			streamNonce: n,
			nonce: l,
			onEvent: o,
			onDone: c,
			onError: i,
		} ) =>
			e
				? ( ( {
						mockScenario: e,
						mockDelay: t,
						onEvent: r,
						onDone: a,
						onError: n,
				  } ) => ( {
						stream: ( l ) =>
							( ( {
								prompt: e,
								mode: t = 'normal',
								delayMode: r = 'normal',
								onEvent: a,
								onDone: n,
								onError: l,
							} ) => {
								const o = [];
								let c = ! 1;
								const i = 'slow' === r ? 3e3 : 0,
									d = ( e, s ) => {
										const t = setTimeout( () => {
											c || e();
										}, s + i );
										o.push( t );
									},
									u = ( e, s ) => {
										c || a( { type: e, payload: s } );
									},
									p = () => {
										( c = ! 0 ),
											o.forEach( clearTimeout ),
											n?.( { aborted: ! 0 } );
									};
								if ( 'infinite' === r ) {
									return { stop: p };
								}
								if ( 'error' === t ) {
									return (
										d(
											() =>
												l?.( {
													error: ( 0, s.__ )(
														'Mock error: something went wrong.',
														'clawpress'
													),
												} ),
											300
										),
										d( () => n?.( { aborted: ! 1 } ), 600 ),
										{ stop: p }
									);
								}
								( 'tool' !== t && 'tool_error' !== t ) ||
									( d(
										() =>
											u( 'tool_call', {
												function: {
													name: 'update_posts_find_replace',
													arguments: '{}',
												},
											} ),
										200
									),
									d(
										() =>
											u( 'tool_plan', {
												function: {
													name: 'update_posts_find_replace',
													arguments: {
														search:
															'tool_error' === t
																? ( 0,
																  s.sprintf )(
																		/* translators: %s: mock text prefixed with ERROR to trigger an error path */ /* translators: %s: mock text prefixed with ERROR to trigger an error path */
																		( 0,
																		s.__ )(
																			'ERROR: %s',
																			'clawpress'
																		),
																		( 0,
																		s.__ )(
																			'Old Phrase',
																			'clawpress'
																		)
																  )
																: ( 0, s.__ )(
																		'Old Phrase',
																		'clawpress'
																  ),
														replace:
															'tool_error' === t
																? ( 0,
																  s.sprintf )(
																		/* translators: %s: mock text prefixed with ERROR to trigger an error path */ /* translators: %s: mock text prefixed with ERROR to trigger an error path */
																		( 0,
																		s.__ )(
																			'ERROR: %s',
																			'clawpress'
																		),
																		( 0,
																		s.__ )(
																			'New Phrase',
																			'clawpress'
																		)
																  )
																: ( 0, s.__ )(
																		'New Phrase',
																		'clawpress'
																  ),
														post_status:
															'tool_error' === t
																? 'draft'
																: 'publish',
														dry_run: ! 0,
													},
												},
											} ),
										700
									) );
								const m =
										( 0, s.__ )(
											'Here is a longer mock response to help you test streaming behavior. ',
											'clawpress'
										) +
										( 0, s.__ )(
											'It should keep streaming long enough for you to press Stop and see the UI react. ',
											'clawpress'
										) +
										( 0, s.__ )(
											'We can include multiple sentences, line breaks, and a bit of variety.\n\n',
											'clawpress'
										) +
										( 0, s.__ )(
											'Chunk one: The quick brown fox jumps over the lazy dog. ',
											'clawpress'
										) +
										( 0, s.__ )(
											'Chunk two: Sphinx of black quartz, judge my vow. ',
											'clawpress'
										) +
										( 0, s.__ )(
											'Chunk three: Pack my box with five dozen liquor jugs.\n\n',
											'clawpress'
										) +
										( 0, s.__ )(
											'Final chunk: This should be enough to test canceling a long stream.',
											'clawpress'
										),
									h = (
										'tool' === t || 'tool_error' === t
											? ( 0, s.__ )(
													'I found a tool that can update posts. Here is the proposed change.',
													'clawpress'
											  )
											: 'long' === t
											? m
											: ( 0, s.sprintf )(
													/* translators: %s: user prompt */ /* translators: %s: user prompt */
													( 0, s.__ )(
														'Here is a mock response for: "%s"',
														'clawpress'
													),
													e
											  )
									).split( '' );
								let g = 150;
								return (
									h.forEach( ( e ) => {
										d( () => u( 'delta', { text: e } ), g ),
											( g += 12 );
									} ),
									d( () => n?.( { aborted: ! 1 } ), g + 200 ),
									{ stop: p }
								);
							} )( {
								prompt: l,
								mode: e,
								delayMode: t,
								onEvent: ( { type: e, payload: s } ) =>
									r( e, s ),
								onDone: () => a?.( { aborted: ! 1 } ),
								onError: n,
							} ),
						runTool: ( e, r ) =>
							( async (
								e,
								t,
								{ mockDelay: r = 'normal' } = {}
							) => {
								if ( 'infinite' === r ) {
									await new Promise( () => {} );
								} else {
									const e = 'slow' === r ? 3e3 : 300;
									await new Promise( ( s ) =>
										setTimeout( s, e )
									);
								}
								if (
									( 'string' === typeof t?.search
										? t.search
										: ''
									).includes( 'ERROR' )
								) {
									throw new Error(
										( 0, s.__ )(
											'Mock tool error: execution failed.',
											'clawpress'
										)
									);
								}
								return {
									step: {
										data: {
											result: {
												dry_run: ! 0,
												total: 2,
												changed: [
													{
														id: 123,
														title: ( 0, s.__ )(
															'Hello World',
															'clawpress'
														),
														count: 1,
													},
													{
														id: 456,
														title: ( 0, s.__ )(
															'Sample Post',
															'clawpress'
														),
														count: 2,
													},
												],
												tool: e,
												args: t,
											},
										},
									},
								};
							} )( e, r, { mockDelay: t } ),
						getHistory: async () => ( { items: [] } ),
						getStatus: async () => ( {
							mode: 'offline',
							provider: { id: null, configured: ! 1 },
							model: { id: null, configured: ! 1 },
							setup: { completed: ! 1 },
							memory: { enabled: ! 1 },
							agent_user: { id: null, configured: ! 1 },
							suggestions: [
								'/help',
								'/clear',
								'/status',
								'/setup resume',
								'/memory list',
								'/site info',
								'/tools list',
							],
						} ),
						getPanelState: async () => ( {
							open: ! 1,
							width: 420,
							last_history_id: '',
						} ),
						setPanelState: async ( e ) => ( {
							open: Boolean( e?.open ),
							width: Number( e?.width ) || 420,
							last_history_id:
								'string' === typeof e?.last_history_id
									? e.last_history_id
									: '',
						} ),
				  } ) )( {
						mockScenario: t,
						mockDelay: r,
						onEvent: o,
						onDone: c,
						onError: i,
				  } )
				: ( ( {
						restBase: e,
						nonce: t,
						onEvent: r,
						onDone: a,
						onError: n,
				  } ) => {
						const l = ( e ) => {
							if ( ! e || 'object' !== typeof e ) {
								return null;
							}
							const s =
								'string' === typeof e.type ? e.type.trim() : '';
							return s
								? {
										type: s,
										data:
											e.data &&
											'object' === typeof e.data &&
											! Array.isArray( e.data )
												? e.data
												: {},
								  }
								: null;
						};
						return {
							stream: ( o ) => {
								const c = new AbortController();
								return (
									( async () => {
										try {
											const n = await ( ( i = o ),
											( d = c.signal ),
											A( {
												url: `${ e }/chat/message`,
												method: 'POST',
												nonce: t,
												body: { message: i },
												signal: d,
											} ) );
											n?.meta?.command?.effects &&
												! 0 ===
													n.meta.command.effects
														.clear_history &&
												r( 'history_reset', {} ),
												Array.isArray(
													n?.meta?.suggestions
												) &&
													r( 'suggestions', {
														items: n.meta
															.suggestions,
													} );
											const u = ( ( e ) => {
												if (
													! e ||
													'object' !== typeof e
												) {
													return null;
												}
												const s = ( e ) => {
														const s = Number( e );
														return ! Number.isFinite(
															s
														) || s < 0
															? null
															: Math.round( s );
													},
													t = ( e ) => {
														if ( null == e ) {
															return null;
														}
														const s = Number( e );
														return Number.isFinite(
															s
														)
															? Math.max(
																	0,
																	Math.min(
																		100,
																		Math.round(
																			s
																		)
																	)
															  )
															: null;
													},
													r =
														s( e.prompt_tokens ) ??
														0,
													a =
														s(
															e.completion_tokens
														) ?? 0,
													n =
														s( e.total_tokens ) ??
														0,
													l =
														s( e.used_tokens ) ??
														( r > 0 ? r : n ),
													o =
														s(
															e.context_window_tokens
														) ?? null,
													c = t( e.percent_used ),
													i = t( e.percent_left ),
													d =
														'boolean' ===
														typeof e.window_is_estimated
															? e.window_is_estimated
															: null;
												return 0 === r &&
													0 === a &&
													0 === n &&
													0 === l &&
													null === o
													? null
													: {
															promptTokens: r,
															completionTokens: a,
															totalTokens: n,
															usedTokens: l,
															contextWindowTokens:
																o,
															percentUsed: c,
															percentLeft: i,
															windowIsEstimated:
																d,
													  };
											} )( n?.meta?.context );
											u &&
												r( 'context_usage', {
													context: u,
												} );
											const p = Array.isArray(
												n?.meta?.tool_calls
											)
												? n.meta.tool_calls
														.map( ( e ) =>
															( ( e ) => {
																if (
																	! e ||
																	'object' !==
																		typeof e
																) {
																	return null;
																}
																const s =
																	'string' ===
																	typeof e.name
																		? e.name.trim()
																		: '';
																if ( ! s ) {
																	return null;
																}
																const t =
																		'string' ===
																		typeof e.ability
																			? e.ability.trim()
																			: '',
																	r =
																		e.args &&
																		'object' ===
																			typeof e.args &&
																		! Array.isArray(
																			e.args
																		)
																			? e.args
																			: {},
																	a = ( (
																		e
																	) => {
																		const s =
																			'string' ===
																			typeof e
																				? e
																						.trim()
																						.toLowerCase()
																				: '';
																		return 'success' ===
																			s ||
																			'error' ===
																				s ||
																			'requires_confirmation' ===
																				s
																			? s
																			: 'success';
																	} )(
																		e.status
																	);
																return {
																	name: s,
																	ability:
																		t ||
																		null,
																	args: r,
																	status: a,
																	message:
																		( 'string' ===
																			typeof e.message &&
																		e.message.trim()
																			? e.message.trim()
																			: '' ) ||
																		null,
																	round: Number.isFinite(
																		Number(
																			e.round
																		)
																	)
																		? Math.max(
																				1,
																				Math.round(
																					Number(
																						e.round
																					)
																				)
																		  )
																		: 1,
																	sequence:
																		Number.isFinite(
																			Number(
																				e.sequence
																			)
																		)
																			? Math.max(
																					1,
																					Math.round(
																						Number(
																							e.sequence
																						)
																					)
																			  )
																			: 1,
																	requiresConfirmation:
																		'boolean' ===
																		typeof e.requires_confirmation
																			? e.requires_confirmation
																			: 'requires_confirmation' ===
																			  a,
																};
															} )( e )
														)
														.filter( Boolean )
												: [];
											p.forEach( ( e, s ) => {
												r( 'tool_call', {
													call: e,
													index: s + 1,
													total: p.length,
												} );
											} );
											const m =
													n?.meta?.error &&
													'object' ===
														typeof n.meta.error
														? n.meta.error
														: null,
												h =
													n?.meta?.card &&
													'object' ===
														typeof n.meta.card
														? l( n.meta.card )
														: null;
											if ( m ) {
												const e =
													'string' ===
														typeof m.message &&
													m.message.trim()
														? m.message.trim()
														: ( 0, s.__ )(
																'Chat request failed.',
																'clawpress'
														  );
												return (
													r( 'error', {
														error: e,
														type:
															'string' ===
																typeof m.type &&
															m.type.trim()
																? m.type.trim()
																: 'provider',
														card: h,
													} ),
													void a?.( { aborted: ! 1 } )
												);
											}
											const g =
													'string' === typeof n?.reply
														? n.reply.trim()
														: '',
												w = Boolean(
													n?.meta?.command?.name
												),
												y = l( n?.meta?.card );
											y
												? r( 'response_card', {
														card: y,
														text: g,
														role: w
															? 'system'
															: 'assistant',
												  } )
												: g &&
												  r( 'response_message', {
														text: g,
														role: w
															? 'system'
															: 'assistant',
												  } ),
												a?.( { aborted: ! 1 } );
										} catch ( e ) {
											if ( 'AbortError' === e?.name ) {
												return void a?.( {
													aborted: ! 0,
												} );
											}
											n?.( {
												error:
													e?.message ||
													( 0, s.__ )(
														'Chat request failed.',
														'clawpress'
													),
												type: 'request',
											} ),
												a?.( { aborted: ! 1 } );
										}
										let i, d;
									} )(),
									{ stop: () => c.abort() }
								);
							},
							runTool: async () => {
								throw new Error(
									( 0, s.__ )(
										'Tool execution is not available in chat mode.',
										'clawpress'
									)
								);
							},
							getHistory: () =>
								A( {
									url: `${ e }/chat/history`,
									method: 'GET',
									nonce: t,
								} ),
							getStatus: () =>
								A( {
									url: `${ e }/status`,
									method: 'GET',
									nonce: t,
								} ),
							getPanelState: () =>
								A( {
									url: `${ e }/panel/state`,
									method: 'GET',
									nonce: t,
								} ),
							setPanelState: ( s ) =>
								A( {
									url: `${ e }/panel/state`,
									method: 'POST',
									nonce: t,
									body: s,
								} ),
						};
				  } )( {
						restBase: a,
						streamNonce: n,
						nonce: l,
						onEvent: o,
						onDone: c,
						onError: i,
				  } ),
		E = ( {
			mockEnabled: e,
			mockScenario: r,
			mockDelay: a,
			onSelectScenario: n,
			onSelectDelay: l,
			onRunScenario: o,
			themeMode: c,
		} ) => {
			if ( ! e ) {
				return null;
			}
			const i = [
					{
						key: 'normal',
						label: ( 0, s.__ )( 'Normal', 'clawpress' ),
					},
					{ key: 'long', label: ( 0, s.__ )( 'Long', 'clawpress' ) },
					{ key: 'tool', label: ( 0, s.__ )( 'Tool', 'clawpress' ) },
					{
						key: 'tool_error',
						label: ( 0, s.__ )( 'Tool Error', 'clawpress' ),
					},
					{
						key: 'error',
						label: ( 0, s.__ )( 'Error', 'clawpress' ),
					},
				],
				d = [
					{
						key: 'normal',
						label: ( 0, s.__ )( 'Normal', 'clawpress' ),
					},
					{
						key: 'slow',
						label: ( 0, s.__ )( 'Slow (3s)', 'clawpress' ),
					},
					{
						key: 'infinite',
						label: ( 0, s.__ )( 'Infinite', 'clawpress' ),
					},
				];
			return ( 0, t.jsxs )( 'div', {
				className: 'clawpress-mock-panel',
				'data-theme': c,
				children: [
					( 0, t.jsx )( 'div', {
						className: 'clawpress-mock-badge',
						children: ( 0, s.__ )( 'Mock', 'clawpress' ),
					} ),
					( 0, t.jsxs )( 'fieldset', {
						className: 'clawpress-mock-fieldset',
						children: [
							( 0, t.jsx )( 'legend', {
								children: ( 0, s.__ )(
									'Scenario',
									'clawpress'
								),
							} ),
							( 0, t.jsx )( 'div', {
								className: 'clawpress-mock-buttons',
								children: i.map( ( e ) =>
									( 0, t.jsx )(
										'button',
										{
											className:
												'button ' +
												( r === e.key
													? 'button-primary'
													: '' ),
											type: 'button',
											onClick: () => n( e.key ),
											children: e.label,
										},
										e.key
									)
								),
							} ),
						],
					} ),
					( 0, t.jsxs )( 'fieldset', {
						className: 'clawpress-mock-fieldset',
						children: [
							( 0, t.jsx )( 'legend', {
								children: ( 0, s.__ )(
									'Response delay',
									'clawpress'
								),
							} ),
							( 0, t.jsx )( 'div', {
								className: 'clawpress-mock-buttons',
								children: d.map( ( e ) =>
									( 0, t.jsx )(
										'button',
										{
											className:
												'button ' +
												( a === e.key
													? 'button-primary'
													: '' ),
											type: 'button',
											onClick: () => l( e.key ),
											children: e.label,
										},
										e.key
									)
								),
							} ),
						],
					} ),
					( 0, t.jsx )( 'button', {
						className: 'button',
						type: 'button',
						onClick: o,
						children: ( 0, s.__ )( 'Run', 'clawpress' ),
					} ),
				],
			} );
		},
		R = () => {
			const [ r, n ] = ( 0, e.useState )(
					JSON.parse(
						localStorage.getItem( 'clawpress_open' ) || 'false'
					)
				),
				[ c, i ] = ( 0, e.useState )(
					Number(
						localStorage.getItem( 'clawpress_width' ) ||
							CLAWPRESS_PANEL.defaultWidth
					)
				),
				[ d, u ] = ( 0, e.useState )( [] ),
				[ p, h ] = ( 0, e.useState )( '' ),
				[ g, w ] = ( 0, e.useState )( () => {
					const e = localStorage.getItem( 'clawpress_input_history' );
					if ( ! e ) {
						return [];
					}
					try {
						const s = JSON.parse( e );
						return Array.isArray( s )
							? s.filter( ( e ) => 'string' === typeof e )
							: [];
					} catch {
						return [];
					}
				} ),
				[ y, f ] = ( 0, e.useState )( -1 ),
				[ _, b ] = ( 0, e.useState )( '' ),
				[ x, j ] = ( 0, e.useState )( ! 1 ),
				[ N, v ] = ( 0, e.useState )( '' ),
				[ A, R ] = ( 0, e.useState )( ! 1 ),
				[ T, $ ] = ( 0, e.useState )( [] ),
				[ P, D ] = ( 0, e.useState )( [] ),
				[ M, L ] = ( 0, e.useState )( ! 1 ),
				[ I, O ] = ( 0, e.useState )( null ),
				[ B, q ] = ( 0, e.useState )( ! 0 ),
				[ F, U ] = ( 0, e.useState )( [] ),
				[ W, H ] = ( 0, e.useState )( null ),
				[ J, z ] = ( 0, e.useState )( ! 1 ),
				G = Boolean( CLAWPRESS_PANEL.mockEnabled ),
				[ K, X ] = ( 0, e.useState )(
					localStorage.getItem( 'clawpress_theme' ) || 'light'
				),
				[ V, Z ] = ( 0, e.useState )( 'normal' ),
				[ Q, Y ] = ( 0, e.useState )( 'normal' ),
				[ ee, se ] = ( 0, e.useState )( ! 1 ),
				te = ( 0, e.useRef )( null ),
				re = ( 0, e.useRef )( 0 ),
				ae = ( 0, e.useRef )( [] ),
				ne = ( 0, e.useRef )( ! 1 ),
				le = ( 0, e.useRef )( null ),
				oe = ( 0, e.useRef )( null ),
				ce = ( 0, e.useRef )( N ),
				ie = ( 0, e.useRef )( T );
			( 0, e.useEffect )( () => {
				ce.current = N;
			}, [ N ] ),
				( 0, e.useEffect )( () => {}, [] ),
				( 0, e.useEffect )( () => {
					ie.current = T;
				}, [ T ] );
			const de = ( 0, e.useRef )( ! 1 ),
				ue = ( 0, e.useRef )( 0 ),
				pe = ( 0, e.useRef )( c ),
				me = ( e ) => {
					if ( ! e || 'object' !== typeof e ) {
						return null;
					}
					const s = 'string' === typeof e.type ? e.type.trim() : '';
					return s
						? {
								type: s,
								data:
									e.data &&
									'object' === typeof e.data &&
									! Array.isArray( e.data )
										? e.data
										: {},
						  }
						: null;
				},
				he = ( e, s, t = null ) =>
					u( ( r ) => [
						...r,
						{
							id: `msg-${ Date.now() }-${ Math.random() }`,
							role: e,
							content: s,
							card: me( t ),
							createdAt: ++re.current,
						},
					] ),
				ge = ( e, t = null, r = null ) => {
					if ( ! e || 'object' !== typeof e ) {
						return '';
					}
					const a = 'string' === typeof e.name ? e.name.trim() : '';
					if ( ! a ) {
						return '';
					}
					const n =
							'string' === typeof e.status
								? e.status.trim().toLowerCase()
								: 'success',
						l =
							'string' === typeof e.message
								? e.message.trim()
								: '';
					let o = ( 0, s.__ )( 'success', 'clawpress' );
					'error' === n
						? ( o = ( 0, s.__ )( 'error', 'clawpress' ) )
						: 'requires_confirmation' === n &&
						  ( o = ( 0, s.__ )(
								'confirmation required',
								'clawpress'
						  ) );
					let c = ( 0, s.sprintf )(
						/* translators: 1: tool name, 2: tool call status */ /* translators: 1: tool name, 2: tool call status */
						( 0, s.__ )( 'Tool call `%1$s` (%2$s)', 'clawpress' ),
						a,
						o
					);
					return (
						Number.isFinite( Number( t ) ) &&
							Number.isFinite( Number( r ) ) &&
							( c = ( 0, s.sprintf )(
								/* translators: 1: tool call position, 2: total tool calls, 3: tool summary text */ /* translators: 1: tool call position, 2: total tool calls, 3: tool summary text */
								( 0, s.__ )( '[%1$d/%2$d] %3$s', 'clawpress' ),
								Math.max( 1, Math.round( Number( t ) ) ),
								Math.max( 1, Math.round( Number( r ) ) ),
								c
							) ),
						l ? `${ c }\n${ l }` : c
					);
				},
				we = ( e ) =>
					e && 'object' === typeof e
						? {
								open:
									'boolean' === typeof e.open ? e.open : null,
								width: Number.isFinite( Number( e.width ) )
									? Number( e.width )
									: null,
								lastHistoryId:
									'string' === typeof e.last_history_id
										? e.last_history_id
										: '',
								welcomeCardSeen:
									'boolean' === typeof e.welcome_card_seen &&
									e.welcome_card_seen,
						  }
						: {
								open: null,
								width: null,
								lastHistoryId: '',
								welcomeCardSeen: ! 1,
						  },
				ye = ( e ) => {
					if ( ! Array.isArray( e ) ) {
						return [];
					}
					const s = new Set();
					return e
						.map( ( e ) =>
							'string' === typeof e ? e.trim() : ''
						)
						.filter( ( e ) => e.length > 0 )
						.filter( ( e ) => ! s.has( e ) && ( s.add( e ), ! 0 ) )
						.slice( 0, 8 );
				},
				fe = ( 0, e.useRef )( null ),
				_e = () => {
					const e = fe.current;
					e &&
						( u( ( s ) => s.filter( ( s ) => s.id !== e ) ),
						( fe.current = null ) );
				};
			( 0, e.useEffect )(
				() =>
					localStorage.setItem(
						'clawpress_open',
						JSON.stringify( r )
					),
				[ r ]
			),
				( 0, e.useEffect )(
					() =>
						localStorage.setItem( 'clawpress_width', String( c ) ),
					[ c ]
				),
				( 0, e.useEffect )( () => {
					document.body.classList.toggle( 'clawpress-panel-open', r ),
						document.body.style.setProperty(
							'--clawpress-panel-width',
							`${ c }px`
						),
						( () => {
							const e = document.querySelector(
								'#wp-admin-bar-clawpress-toggle > a'
							);
							e &&
								e.setAttribute(
									'aria-expanded',
									r ? 'true' : 'false'
								);
						} )();
				}, [ r, c ] ),
				( 0, e.useEffect )( () => {
					localStorage.setItem( 'clawpress_theme', K );
				}, [ K ] ),
				( 0, e.useEffect )( () => {
					localStorage.setItem(
						'clawpress_input_history',
						JSON.stringify( g )
					);
				}, [ g ] ),
				( 0, e.useEffect )( () => {
					const e = ( e ) => {
						if (
							( ! e.metaKey && ! e.ctrlKey ) ||
							e.altKey ||
							'k' !== e.key.toLowerCase()
						) {
							return;
						}
						const s =
							document.activeElement?.tagName?.toLowerCase();
						'input' === s ||
							'textarea' === s ||
							'select' === s ||
							document.activeElement?.isContentEditable ||
							( e.preventDefault(), n( ( e ) => ! e ) );
					};
					return (
						window.addEventListener( 'keydown', e ),
						() => window.removeEventListener( 'keydown', e )
					);
				}, [] ),
				( 0, e.useEffect )( () => {
					const e = ( e ) => {
							if ( ! de.current ) {
								return;
							}
							const s = ue.current - e.clientX,
								t = Math.max( 320, pe.current + s );
							i( t );
						},
						s = () => {
							de.current &&
								( ( de.current = ! 1 ),
								document.body.classList.remove(
									'clawpress-resizing'
								) );
						};
					return (
						window.addEventListener( 'mousemove', e ),
						window.addEventListener( 'mouseup', s ),
						() => {
							window.removeEventListener( 'mousemove', e ),
								window.removeEventListener( 'mouseup', s );
						}
					);
				}, [] );
			const be = ( e = null ) => {
					const s = 'string' === typeof e ? e.trim() : p.trim();
					s &&
						( h( '' ),
						w( ( e ) => {
							const t = [ ...e, s ],
								r = CLAWPRESS_PANEL.historyLimit ?? 20;
							return t.length > r ? t.slice( t.length - r ) : t;
						} ),
						f( -1 ),
						b( '' ),
						he( 'user', s ),
						R( ! 0 ),
						Ae( s ) );
				},
				xe = ( e ) => m( e ).concurrency || 'parallel',
				je = ( e, s = [] ) => {
					const t = e?.name || '',
						r =
							'serial' === xe( t ) &&
							s.some(
								( e ) =>
									e.function?.name === t &&
									'done' !== e.status &&
									'error' !== e.status &&
									'cancelled' !== e.status
							);
					return {
						id: `tool-${ Date.now() }-${ Math.random() }`,
						function: e,
						args: o( e ),
						status: r ? 'blocked' : 'idle',
						result: null,
						error: null,
						diff: null,
						createdAt: ++re.current,
					};
				},
				Ne = () => {
					if ( ne.current ) {
						return;
					}
					const e = ae.current;
					if ( ! e.length ) {
						return;
					}
					const { type: t, payload: r } = e.shift();
					'assistant_message' === t && r?.content
						? ( ( e ) => {
								if ( ! e ) {
									return;
								}
								( ne.current = ! 0 ),
									v( '' ),
									( ce.current = '' );
								let s = 0;
								const t = () => {
									if ( s >= e.length ) {
										return (
											he( 'assistant', e ),
											v( '' ),
											( ce.current = '' ),
											( ne.current = ! 1 ),
											( le.current = null ),
											void Ne()
										);
									}
									const r = e.slice( s, s + 2 );
									( s += 2 ),
										v( ( e ) => {
											const s = e + r;
											return ( ce.current = s ), s;
										} ),
										( le.current = setTimeout( t, 30 ) );
								};
								t();
						  } )( r.content )
						: ( ( ( e, t ) => {
								switch ( ( _e(), e ) ) {
									case 'delta':
										if ( t.text ) {
											const e = `${ ce.current || '' }${
												t.text
											}`;
											( ce.current = e ), v( e );
										}
										break;
									case 'response_message':
										t?.text &&
											he(
												'system' === t?.role
													? 'system'
													: 'assistant',
												t.text
											);
										break;
									case 'response_card':
										t?.card &&
											he(
												'system' === t?.role
													? 'system'
													: 'assistant',
												'string' === typeof t?.text
													? t.text
													: '',
												t.card
											);
										break;
									case 'history_reset':
										u( [] ),
											D( [] ),
											$( [] ),
											H( null ),
											( re.current = 0 );
										break;
									case 'suggestions': {
										const e = ye( t?.items );
										U( e );
										break;
									}
									case 'context_usage':
										t?.context && H( t.context );
										break;
									case 'tool_call':
										if (
											t?.call &&
											'object' === typeof t.call
										) {
											const e = ge(
												t.call,
												t?.index,
												t?.total
											);
											e && he( 'system', e );
											break;
										}
										M ||
											( ( ( e ) => {
												const s = `status-${ Date.now() }-${ Math.random() }`;
												( fe.current = s ),
													u( ( t ) => [
														...t,
														{
															id: s,
															role: 'system',
															content: e,
															createdAt:
																++re.current,
														},
													] );
											} )(
												( 0, s.__ )(
													'Preparing tool plan...',
													'clawpress'
												)
											),
											L( ! 0 ) );
										break;
									case 'tool_plan':
										t?.function &&
											'object' === typeof t.function &&
											$( ( e ) => [ ...e, t ] ),
											L( ! 1 );
										break;
									case 'error':
										he(
											'system',
											t?.error ||
												( 0, s.__ )(
													'Stream error.',
													'clawpress'
												),
											t?.card ||
												( ( e, t = '' ) => {
													const r = {
															timeout: ( 0,
															s.__ )(
																'Request timed out',
																'clawpress'
															),
															request: ( 0,
															s.__ )(
																'Network or API request error',
																'clawpress'
															),
															provider: ( 0,
															s.__ )(
																'Provider error',
																'clawpress'
															),
														},
														a =
															'string' ===
																typeof t &&
															r[ t ]
																? r[ t ]
																: ( 0, s.__ )(
																		'Error',
																		'clawpress'
																  );
													return {
														type: 'error',
														data: {
															title: ( 0, s.__ )(
																'Request Error',
																'clawpress'
															),
															subtitle: a,
															message:
																'string' ===
																	typeof e &&
																e.trim()
																	? e
																	: ( 0,
																	  s.__ )(
																			'Chat request failed.',
																			'clawpress'
																	  ),
														},
													};
												} )( t?.error, t?.type )
										);
										break;
									case 'done':
										( () => {
											j( ! 1 ),
												( te.current = null ),
												R( ! 1 ),
												_e();
											const e = ce.current;
											e && e.trim()
												? ( he( 'assistant', e ),
												  v( '' ),
												  ( ce.current = '' ) )
												: ( v( '' ),
												  ( ce.current = '' ) );
											const s = Array.isArray(
												ie.current
											)
												? ie.current
												: [];
											s.length > 0 &&
												D( ( e ) => {
													const t = [ ...e ];
													return (
														s.forEach( ( e ) => {
															e?.function &&
																'object' ===
																	typeof e.function &&
																t.push(
																	je(
																		e.function,
																		t
																	)
																);
														} ),
														t
													);
												} ),
												$( [] ),
												Se( ! 0 );
										} )();
								}
						  } )( t, r ),
						  Ne() );
				},
				ve = ( e, s ) => {
					R( ! 1 ),
						_e(),
						ae.current.push( { type: e, payload: s } ),
						Ne();
				},
				ke = () =>
					C( {
						mockEnabled: G,
						mockScenario: V,
						mockDelay: Q,
						restBase: CLAWPRESS_PANEL.restBase,
						streamNonce: CLAWPRESS_PANEL.streamNonce,
						nonce: CLAWPRESS_PANEL.nonce,
						onEvent: ve,
						onDone: () => ve( 'done', {} ),
						onError: ( e ) => ve( 'error', e ),
					} ),
				Se = async ( e = ! 1 ) => {
					e || q( ! 0 );
					try {
						const e = await ke().getStatus?.();
						e &&
							( O( e ),
							U( ( s ) =>
								s.length > 0 ? s : ye( e?.suggestions )
							) );
					} catch {
						O( null );
					} finally {
						q( ! 1 );
					}
				};
			( 0, e.useEffect )( () => {
				let e = ! 0;
				return (
					( async () => {
						const t = ke();
						let r = we( null );
						try {
							const s = await t.getPanelState?.();
							if ( ! e ) {
								return;
							}
							( r = we( s ) ),
								'boolean' === typeof r.open && n( r.open ),
								Number.isFinite( r.width ) &&
									r.width > 0 &&
									i( r.width );
						} catch {}
						try {
							const s = await t.getStatus?.();
							if ( ! e ) {
								return;
							}
							O( s || null ),
								U( ( e ) =>
									e.length > 0 ? e : ye( s?.suggestions )
								);
						} catch {
							if ( ! e ) {
								return;
							}
							O( null );
						} finally {
							e && q( ! 1 );
						}
						try {
							const n = await t.getHistory?.();
							if ( ! e ) {
								return;
							}
							const l =
								( ( a = n?.items || [] ),
								Array.isArray( a )
									? a
											.filter(
												( e ) =>
													e && 'object' === typeof e
											)
											.reduce( ( e, s, t ) => {
												const r =
														'user' === s.role ||
														'assistant' ===
															s.role ||
														'system' === s.role
															? s.role
															: 'system',
													a =
														'string' ===
														typeof s.content
															? s.content
															: '',
													n = Number.isFinite(
														Number( s.createdAt )
													)
														? Number( s.createdAt )
														: t + 1,
													l =
														'string' ===
															typeof s.id && s.id
															? s.id
															: `history-${ n }-${ t }`,
													o = me( s.card ),
													c = Array.isArray(
														s.tool_calls
													)
														? s.tool_calls.filter(
																( e ) =>
																	e &&
																	'object' ===
																		typeof e
														  )
														: [];
												return (
													c.forEach( ( s, t ) => {
														const r = ge(
															s,
															t + 1,
															c.length
														);
														r &&
															e.push( {
																id: `${ l }-tool-${
																	t + 1
																}`,
																role: 'system',
																content: r,
																card: null,
																createdAt: n,
															} );
													} ),
													e.push( {
														id: l,
														role: r,
														content: a,
														card: o,
														createdAt: n,
													} ),
													e
												);
											}, [] )
									: [] );
							if ( 0 === l.length && ! 0 !== r.welcomeCardSeen ) {
								const e = Date.now(),
									r = {
										id: `welcome-${ e }`,
										role: 'assistant',
										content: '',
										card: {
											type: 'welcome',
											data: {
												title: ( 0, s.__ )(
													'Welcome to ClawPress',
													'clawpress'
												),
												message: ( 0, s.__ )(
													'Hello! I am ready to help with your WordPress tasks.',
													'clawpress'
												),
												emoji: '👋',
												actions: [
													{
														id: 'start-setup',
														label: ( 0, s.__ )(
															'Start Setup',
															'clawpress'
														),
														prompt: '/setup start',
													},
												],
											},
										},
										createdAt: e,
									};
								u( [ r ] ),
									( re.current = e ),
									t
										.setPanelState?.( {
											welcome_card_seen: ! 0,
										} )
										.catch( () => {} );
							} else {
								u( l ),
									( re.current = l.reduce(
										( e, s ) =>
											Math.max(
												e,
												Number( s.createdAt ) || 0
											),
										0
									) );
							}
						} catch {
							if ( ! e ) {
								return;
							}
							he(
								'system',
								( 0, s.__ )(
									'Unable to load chat history.',
									'clawpress'
								)
							);
						}
						let a;
						e && z( ! 0 );
					} )(),
					() => {
						e = ! 1;
					}
				);
			}, [] ),
				( 0, e.useEffect )( () => {
					if ( ! r ) {
						return;
					}
					Se( ! 0 );
					const e = setInterval( () => {
						Se( ! 0 );
					}, 15e3 );
					return () => clearInterval( e );
				}, [ r ] ),
				( 0, e.useEffect )( () => {
					if ( ! J ) {
						return;
					}
					const e = d[ d.length - 1 ],
						s = {
							open: r,
							width: c,
							last_history_id:
								'string' === typeof e?.id ? e.id : '',
						};
					return (
						oe.current && clearTimeout( oe.current ),
						( oe.current = setTimeout( () => {
							ke()
								.setPanelState?.( s )
								.catch( () => {} );
						}, 350 ) ),
						() => {
							oe.current && clearTimeout( oe.current );
						}
					);
				}, [ J, r, c, d ] );
			const Ae = async ( e ) => {
					j( ! 0 ), v( '' ), ( ce.current = '' ), $( [] );
					const s = ke();
					te.current = s.stream( e );
				},
				Ce = ( e, s ) => {
					D( ( t ) =>
						t.map( ( t ) => ( t.id === e ? { ...t, ...s } : t ) )
					);
				};
			return (
				( 0, e.useEffect )( () => {
					D( ( e ) => {
						let s = ! 1;
						const t = e.map( ( t ) => {
							const r = t.function?.name || '';
							if ( 'serial' !== xe( r ) ) {
								return t;
							}
							if (
								'running' === t.status ||
								'error' === t.status ||
								'done' === t.status ||
								'cancelled' === t.status
							) {
								return t;
							}
							const a = e.some(
								( e ) =>
									e.function?.name === r &&
									e.createdAt < t.createdAt &&
									'done' !== e.status &&
									'cancelled' !== e.status
							)
								? 'blocked'
								: 'idle';
							return t.status !== a
								? ( ( s = ! 0 ), { ...t, status: a } )
								: t;
						} );
						return s ? t : e;
					} );
				}, [ P ] ),
				( 0, e.useEffect )( () => {
					const e = document.querySelector(
						'#wp-admin-bar-clawpress-toggle > a'
					);
					if ( ! e ) {
						return void se( ! 0 );
					}
					se( ! 1 );
					const s = ( e ) => {
						e.preventDefault(), n( ( e ) => ! e );
					};
					return (
						e.addEventListener( 'click', s ),
						() => e.removeEventListener( 'click', s )
					);
				}, [] ),
				( 0, t.jsxs )( e.Fragment, {
					children: [
						ee
							? ( 0, t.jsx )( S, {
									onToggle: () => n( ( e ) => ! e ),
							  } )
							: null,
						( 0, t.jsxs )( 'div', {
							className: 'clawpress-panel',
							'data-theme': K,
							style: { width: `${ c }px` },
							children: [
								( 0, t.jsx )( a, {
									onClose: () => n( ! 1 ),
									onToggleTheme: () =>
										X( ( e ) =>
											'light' === e ? 'dark' : 'light'
										),
									statusMode: I?.mode || 'offline',
									statusLabel: ( ( e ) => {
										if ( ! e || 'object' !== typeof e ) {
											return '';
										}
										const s = e?.provider?.id,
											t = e?.model?.id;
										return s && t
											? `${ s } · ${ t }`
											: s || '';
									} )( I ),
									statusLoading: B,
								} ),
								( 0, t.jsx )( 'div', {
									className: 'clawpress-drag-handle',
									onMouseDown: ( e ) => {
										( de.current = ! 0 ),
											( ue.current = e.clientX ),
											( pe.current = c ),
											document.body.classList.add(
												'clawpress-resizing'
											);
									},
								} ),
								( 0, t.jsx )( k, {
									messages: d,
									streaming: x,
									currentStreamText: N,
									waitingForResponse: A,
									toolDialogs: P,
									onRunToolDialog: ( e, t ) => {
										const r = P.find( ( s ) => s.id === e );
										if ( ! r ) {
											return;
										}
										const a = r.function?.name || '';
										if ( 'serial' === xe( a ) ) {
											const e = P.filter(
													( e ) =>
														e.function?.name === a
												).sort(
													( e, s ) =>
														e.createdAt -
														s.createdAt
												),
												s = e.some(
													( e ) =>
														'running' === e.status
												),
												n = e.some(
													( e ) =>
														e.id !== r.id &&
														e.createdAt <
															r.createdAt &&
														( 'idle' === e.status ||
															'running' ===
																e.status ||
															'error' ===
																e.status ||
															'blocked' ===
																e.status )
												);
											if ( s || n ) {
												return void Ce( r.id, {
													status: 'blocked',
													args: t ?? r.args,
												} );
											}
										}
										t && Ce( r.id, { args: t } );
										( async ( e ) => {
											const t = e.function?.name || '',
												r = e.args || o( e.function );
											Ce( e.id, {
												status: 'running',
												error: null,
											} );
											try {
												const s = await ( async (
														e,
														s
													) => {
														try {
															return await ke().runTool(
																e,
																s
															);
														} catch ( e ) {
															throw e;
														}
													} )( t, r ),
													a =
														s.step?.data?.result ??
														null,
													n = Array.isArray(
														a?.changed
													)
														? a.changed
														: null;
												Ce( e.id, {
													status: 'done',
													result: a,
													diff: n,
												} );
											} catch ( t ) {
												Ce( e.id, {
													status: 'error',
													error:
														t?.message ||
														( 0, s.__ )(
															'Tool execution failed.',
															'clawpress'
														),
												} );
											}
										} )( t ? { ...r, args: t } : r );
									},
									onCancelToolDialog: ( e ) => {
										Ce( e, { status: 'cancelled' } );
									},
									onSendCardAction: ( e ) => {
										if ( 'string' === typeof e ) {
											return void be( e );
										}
										if ( ! e || 'object' !== typeof e ) {
											return;
										}
										if ( 'open_url' === e.type ) {
											const t =
												'string' === typeof e.url
													? e.url.trim()
													: '';
											if ( ! t ) {
												return void he(
													'system',
													( 0, s.__ )(
														'Invalid card action.',
														'clawpress'
													)
												);
											}
											try {
												const e = new URL(
													t,
													window.location.origin
												);
												window.location.assign(
													e.toString()
												);
											} catch {
												he(
													'system',
													( 0, s.__ )(
														'Invalid card URL.',
														'clawpress'
													)
												);
											}
											return;
										}
										if ( 'run_tool' === e.type ) {
											const t =
												'string' === typeof e.tool
													? e.tool.trim()
													: '';
											if ( ! t ) {
												return void he(
													'system',
													( 0, s.__ )(
														'Invalid card action.',
														'clawpress'
													)
												);
											}
											const r =
												e.args &&
												'object' === typeof e.args &&
												! Array.isArray( e.args )
													? e.args
													: {};
											return void D( ( e ) => {
												const s = [ ...e ];
												return (
													s.push(
														je(
															{
																name: t,
																arguments: r,
															},
															s
														)
													),
													s
												);
											} );
										}
										const t =
											'string' === typeof e.prompt
												? e.prompt.trim()
												: '';
										t && be( t );
									},
								} ),
								( 0, t.jsx )( l, {
									input: p,
									onInputChange: ( e ) => h( e.target.value ),
									onSend: be,
									suggestions: F,
									contextUsage: W,
									onSendSuggestion: ( e ) => be( e ),
									onStop: () => {
										te.current?.stop?.(),
											he(
												'system',
												( 0, s.__ )(
													'Stream stopped.',
													'clawpress'
												)
											);
									},
									streaming: x,
									onHistoryUp: ( e ) => {
										if ( 0 === g.length ) {
											return null;
										}
										if ( -1 === y ) {
											b( e );
											const s = g.length - 1;
											return f( s ), g[ s ];
										}
										const s = Math.max( 0, y - 1 );
										return f( s ), g[ s ];
									},
									onHistoryDown: () => {
										if ( -1 === y ) {
											return null;
										}
										const e = y + 1;
										if ( e >= g.length ) {
											f( -1 );
											const e = _;
											return b( '' ), e;
										}
										return f( e ), g[ e ];
									},
								} ),
							],
						} ),
						( 0, t.jsx )( E, {
							mockEnabled: G,
							mockScenario: V,
							mockDelay: Q,
							onSelectScenario: Z,
							onSelectDelay: Y,
							onRunScenario: () => {
								if ( ! G ) {
									return;
								}
								h( '' );
								const e = ( 0, s.sprintf )(
									/* translators: %s: selected mock scenario */ /* translators: %s: selected mock scenario */
									( 0, s.__ )( 'Mock: %s', 'clawpress' ),
									V
								);
								he( 'user', e ), Ae( e );
							},
							themeMode: K,
						} ),
					],
				} )
			);
		};
	let T = document.getElementById( 'clawpress-floating-panel-root' );
	T ||
		( ( T = document.createElement( 'div' ) ),
		( T.id = 'clawpress-floating-panel-root' ),
		document.body.appendChild( T ) ),
		( 0, e.createRoot )( T ).render( ( 0, t.jsx )( R, {} ) );
} )();
