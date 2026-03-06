import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { update } from '@wordpress/icons';
import { requestJson } from '../../utils/requestJson';

const DEFAULT_SETTINGS = {
	provider: '',
	model: '',
	temperature: 0.2,
	top_p: 0.9,
	max_output_tokens: 1200,
	frequency_penalty: 0.2,
	presence_penalty: 0.0,
	request_timeout: 45,
	agent_user_id: 0,
	memory_enabled: false,
	setup_completed: false,
};

const PROVIDER_MODEL_CACHE_KEY = 'clawpress_wp_ai_provider_models_v1';

const normalizeProviderId = ( provider ) =>
	typeof provider === 'string' ? provider.trim().toLowerCase() : '';

const normalizeModelKey = ( value ) =>
	typeof value === 'string'
		? value
				.trim()
				.toLowerCase()
				.replace( /[^a-z0-9]/g, '' )
		: '';

const normalizeDiscoveredProviderModelOptions = ( options ) => {
	if ( ! Array.isArray( options ) ) {
		return [];
	}

	return options
		.map( ( option ) => {
			const id = typeof option?.id === 'string' ? option.id.trim() : '';
			let label = '';
			if ( typeof option?.label === 'string' ) {
				label = option.label.trim();
			} else if ( typeof option?.name === 'string' ) {
				label = option.name.trim();
			}
			if ( ! id ) {
				return null;
			}

			return {
				id,
				label: label || id,
			};
		} )
		.filter( Boolean );
};

const normalizeSettings = ( settings = {} ) => ( {
	provider: typeof settings.provider === 'string' ? settings.provider : '',
	model: typeof settings.model === 'string' ? settings.model : '',
	temperature: Number.isFinite( Number( settings.temperature ) )
		? Number( settings.temperature )
		: 0.2,
	top_p: Number.isFinite( Number( settings.top_p ) )
		? Number( settings.top_p )
		: 0.9,
	max_output_tokens:
		Number.isFinite( Number( settings.max_output_tokens ) ) &&
		Number( settings.max_output_tokens ) > 0
			? Number( settings.max_output_tokens )
			: 1200,
	frequency_penalty: Number.isFinite( Number( settings.frequency_penalty ) )
		? Number( settings.frequency_penalty )
		: 0.2,
	presence_penalty: Number.isFinite( Number( settings.presence_penalty ) )
		? Number( settings.presence_penalty )
		: 0.0,
	request_timeout:
		Number.isFinite( Number( settings.request_timeout ) ) &&
		Number( settings.request_timeout ) > 0
			? Number( settings.request_timeout )
			: 45,
	agent_user_id: Number.isFinite( Number( settings.agent_user_id ) )
		? Number( settings.agent_user_id )
		: 0,
	memory_enabled: Boolean( settings.memory_enabled ),
	setup_completed: Boolean( settings.setup_completed ),
} );

const getDefaultProviderOptions = () => [
	{
		value: 'openai',
		label: __( 'OpenAI', 'clawpress' ),
	},
	{
		value: 'anthropic',
		label: __( 'Anthropic', 'clawpress' ),
	},
	{
		value: 'google',
		label: __( 'Google', 'clawpress' ),
	},
];

const requestWpAiJson = async ( path ) => {
	const restBase =
		typeof window !== 'undefined' &&
		typeof window.CLAWPRESS_ADMIN?.restBase === 'string'
			? window.CLAWPRESS_ADMIN.restBase
			: '/wp-json/clawpress/v1';
	const nonce =
		typeof window !== 'undefined' &&
		typeof window.CLAWPRESS_ADMIN?.nonce === 'string'
			? window.CLAWPRESS_ADMIN.nonce
			: '';
	const wpAiBase = restBase.replace( /\/clawpress\/v1\/?$/, '/wp-ai/v1' );
	const url = `${ wpAiBase }/${ path.replace( /^\//, '' ) }`;

	const response = await fetch( url, {
		method: 'GET',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
	} );

	const text = await response.text();
	let payload = [];

	if ( text ) {
		try {
			payload = JSON.parse( text );
		} catch {
			payload = [];
		}
	}

	if ( ! response.ok ) {
		throw new Error( 'wp_ai_request_failed' );
	}

	return payload;
};

const loadWpAiProviders = async () => {
	let providers = [];

	try {
		providers = await requestWpAiJson( 'providers' );
	} catch {
		return null;
	}

	if ( ! Array.isArray( providers ) || providers.length === 0 ) {
		return null;
	}

	const providerOptions = providers
		.map( ( provider ) => {
			const providerId = normalizeProviderId( provider?.id );
			const providerLabel =
				typeof provider?.name === 'string'
					? provider.name.trim()
					: providerId;

			if ( ! providerId ) {
				return null;
			}

			return {
				value: providerId,
				label: providerLabel || providerId,
			};
		} )
		.filter( Boolean );

	return providerOptions.length > 0 ? providerOptions : null;
};

const loadWpAiProviderModels = async ( providerId ) => {
	const normalizedProviderId = normalizeProviderId( providerId );

	if ( ! normalizedProviderId ) {
		return [];
	}

	const providerModels = await requestWpAiJson(
		`providers/${ encodeURIComponent( normalizedProviderId ) }/models`
	);

	if ( ! Array.isArray( providerModels ) ) {
		return [];
	}

	return normalizeDiscoveredProviderModelOptions( providerModels );
};

const readProviderModelCache = () => {
	if ( typeof window === 'undefined' || ! window.localStorage ) {
		return {};
	}

	try {
		const rawValue = window.localStorage.getItem(
			PROVIDER_MODEL_CACHE_KEY
		);
		if ( ! rawValue ) {
			return {};
		}

		const parsedValue = JSON.parse( rawValue );
		return parsedValue &&
			typeof parsedValue === 'object' &&
			! Array.isArray( parsedValue )
			? parsedValue
			: {};
	} catch {
		return {};
	}
};

const writeProviderModelCache = ( cache ) => {
	if ( typeof window === 'undefined' || ! window.localStorage ) {
		return;
	}

	try {
		window.localStorage.setItem(
			PROVIDER_MODEL_CACHE_KEY,
			JSON.stringify( cache )
		);
	} catch {
		// Ignore storage write failures.
	}
};

const getCachedProviderModelOptions = ( providerId ) => {
	const normalizedProviderId = normalizeProviderId( providerId );
	if ( ! normalizedProviderId ) {
		return {
			hasValue: false,
			options: [],
		};
	}

	const cache = readProviderModelCache();
	const cachedEntry = cache[ normalizedProviderId ];

	if ( Array.isArray( cachedEntry ) ) {
		return {
			hasValue: true,
			options: normalizeDiscoveredProviderModelOptions( cachedEntry ),
		};
	}

	if (
		! cachedEntry ||
		typeof cachedEntry !== 'object' ||
		! Array.isArray( cachedEntry.models )
	) {
		return {
			hasValue: false,
			options: [],
		};
	}

	return {
		hasValue: true,
		options: normalizeDiscoveredProviderModelOptions( cachedEntry.models ),
	};
};

const setCachedProviderModelOptions = ( providerId, options ) => {
	const normalizedProviderId = normalizeProviderId( providerId );
	if ( ! normalizedProviderId ) {
		return;
	}

	const cache = readProviderModelCache();
	cache[ normalizedProviderId ] = {
		cached_at: Date.now(),
		models: normalizeDiscoveredProviderModelOptions( options ),
	};
	writeProviderModelCache( cache );
};

const clearCachedProviderModelOptions = ( providerId ) => {
	const normalizedProviderId = normalizeProviderId( providerId );
	if ( ! normalizedProviderId ) {
		return;
	}

	const cache = readProviderModelCache();
	delete cache[ normalizedProviderId ];
	writeProviderModelCache( cache );
};

const normalizeProviderOptions = ( providers ) => {
	if ( ! Array.isArray( providers ) ) {
		return getDefaultProviderOptions();
	}

	const options = providers
		.map( ( provider ) => {
			const value = normalizeProviderId( provider?.value );
			const label =
				typeof provider?.label === 'string'
					? provider.label.trim()
					: '';

			if ( ! value || ! label ) {
				return null;
			}

			return { value, label };
		} )
		.filter( Boolean );

	return options.length > 0 ? options : getDefaultProviderOptions();
};

const normalizeModelOptions = ( models ) => {
	if ( ! models || typeof models !== 'object' || Array.isArray( models ) ) {
		return {};
	}

	return Object.entries( models ).reduce(
		( accumulator, [ provider, options ] ) => {
			const providerKey = normalizeProviderId( provider );
			if ( ! providerKey || ! Array.isArray( options ) ) {
				return accumulator;
			}

			accumulator[ providerKey ] =
				normalizeDiscoveredProviderModelOptions( options );

			return accumulator;
		},
		{}
	);
};

const normalizeModelCatalog = ( modelCatalog ) => {
	if (
		! modelCatalog ||
		typeof modelCatalog !== 'object' ||
		Array.isArray( modelCatalog )
	) {
		return {};
	}

	return Object.entries( modelCatalog ).reduce(
		( accumulator, [ provider, entries ] ) => {
			const providerKey = normalizeProviderId( provider );
			if ( ! providerKey || ! Array.isArray( entries ) ) {
				return accumulator;
			}

			const normalizedEntries = entries
				.map( ( entry ) => {
					const id =
						typeof entry?.id === 'string' ? entry.id.trim() : '';
					const label =
						typeof entry?.label === 'string'
							? entry.label.trim()
							: '';
					const context =
						typeof entry?.context === 'string'
							? entry.context.trim()
							: '';
					const cost =
						typeof entry?.cost === 'string'
							? entry.cost.trim()
							: '';
					if ( ! id ) {
						return null;
					}

					return {
						id,
						label: label || id,
						context,
						cost,
					};
				} )
				.filter( Boolean );

			if ( normalizedEntries.length > 0 ) {
				accumulator[ providerKey ] = normalizedEntries;
			}

			return accumulator;
		},
		{}
	);
};

const getLongestModelMatch = ( entries, modelKey, keySelector ) =>
	entries
		.filter( ( entry ) => {
			const entryKey = normalizeModelKey( keySelector( entry ) );
			return (
				entryKey &&
				modelKey &&
				( modelKey.startsWith( entryKey ) ||
					entryKey.startsWith( modelKey ) )
			);
		} )
		.sort(
			( a, b ) =>
				normalizeModelKey( keySelector( b ) ).length -
				normalizeModelKey( keySelector( a ) ).length
		)[ 0 ] || null;

const findCatalogModelEntry = (
	catalogEntries,
	modelId,
	selectedOptionLabel = ''
) => {
	if ( ! Array.isArray( catalogEntries ) || catalogEntries.length === 0 ) {
		return null;
	}

	const normalizedModelId = typeof modelId === 'string' ? modelId.trim() : '';
	const exactIdMatch =
		catalogEntries.find( ( entry ) => entry.id === normalizedModelId ) ||
		null;
	if ( exactIdMatch ) {
		return exactIdMatch;
	}

	const normalizedModelKey = normalizeModelKey( normalizedModelId );
	const normalizedIdMatch =
		catalogEntries.find(
			( entry ) => normalizeModelKey( entry.id ) === normalizedModelKey
		) || null;
	if ( normalizedIdMatch ) {
		return normalizedIdMatch;
	}

	const prefixedIdMatch = getLongestModelMatch(
		catalogEntries,
		normalizedModelKey,
		( entry ) => entry.id
	);
	if ( prefixedIdMatch ) {
		return prefixedIdMatch;
	}

	const normalizedLabelKey = normalizeModelKey( selectedOptionLabel );
	if ( ! normalizedLabelKey ) {
		return null;
	}

	const exactLabelMatch =
		catalogEntries.find(
			( entry ) => normalizeModelKey( entry.label ) === normalizedLabelKey
		) || null;
	if ( exactLabelMatch ) {
		return exactLabelMatch;
	}

	return getLongestModelMatch(
		catalogEntries,
		normalizedLabelKey,
		( entry ) => entry.label
	);
};

const getModelDescriptionState = (
	provider,
	model,
	discoveredModelOptionsByProvider,
	modelCatalogByProvider
) => {
	const modelId = typeof model === 'string' ? model.trim() : '';

	if ( ! modelId ) {
		return {
			selectedEntry: null,
			modelId,
		};
	}

	const providerId = normalizeProviderId( provider );

	const discoveredOptions =
		providerId &&
		Array.isArray( discoveredModelOptionsByProvider?.[ providerId ] )
			? discoveredModelOptionsByProvider[ providerId ]
			: [];

	const catalogEntries = Array.isArray(
		modelCatalogByProvider?.[ providerId ]
	)
		? modelCatalogByProvider[ providerId ]
		: [];
	const selectedOption =
		discoveredOptions.find( ( option ) => option.id === modelId ) || null;
	const selectedEntry = findCatalogModelEntry(
		catalogEntries,
		modelId,
		selectedOption?.label || ''
	);

	return {
		selectedEntry: selectedEntry || null,
		modelId,
	};
};

export default function SettingsView() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ success, setSuccess ] = useState( '' );
	const [ settings, setSettings ] = useState( DEFAULT_SETTINGS );
	const [ providerOptions, setProviderOptions ] = useState(
		getDefaultProviderOptions()
	);
	const [
		discoveredModelOptionsByProvider,
		setDiscoveredModelOptionsByProvider,
	] = useState( {} );
	const [ modelCatalogByProvider, setModelCatalogByProvider ] = useState(
		{}
	);
	const [ modelsLoadingByProvider, setModelsLoadingByProvider ] = useState(
		{}
	);

	useEffect( () => {
		let mounted = true;

		const load = async () => {
			setLoading( true );
			setError( '' );
			try {
				const data = await requestJson( 'settings' );
				if ( ! mounted ) {
					return;
				}

				setSettings( normalizeSettings( data?.settings || {} ) );
				setProviderOptions(
					normalizeProviderOptions( data?.providers || [] )
				);
				setDiscoveredModelOptionsByProvider(
					normalizeModelOptions( data?.models || {} )
				);
				setModelCatalogByProvider(
					normalizeModelCatalog( data?.model_catalog || {} )
				);

				const wpAiProviders = await loadWpAiProviders();
				if ( ! mounted || ! wpAiProviders ) {
					return;
				}

				setProviderOptions( normalizeProviderOptions( wpAiProviders ) );
			} catch ( e ) {
				if ( ! mounted ) {
					return;
				}
				setError(
					e?.message || __( 'Unable to load settings.', 'clawpress' )
				);
			} finally {
				if ( mounted ) {
					setLoading( false );
				}
			}
		};

		load();

		return () => {
			mounted = false;
		};
	}, [] );

	const saveSettings = async () => {
		setSaving( true );
		setError( '' );
		setSuccess( '' );

		try {
			await requestJson( 'settings', {
				method: 'POST',
				body: {
					provider: settings.provider,
					model: settings.model,
					temperature: Number.isFinite(
						Number( settings.temperature )
					)
						? Number( settings.temperature )
						: 0.2,
					top_p: Number.isFinite( Number( settings.top_p ) )
						? Number( settings.top_p )
						: 0.9,
					max_output_tokens:
						Number.isFinite(
							Number( settings.max_output_tokens )
						) && Number( settings.max_output_tokens ) > 0
							? Number( settings.max_output_tokens )
							: 1200,
					frequency_penalty: Number.isFinite(
						Number( settings.frequency_penalty )
					)
						? Number( settings.frequency_penalty )
						: 0.2,
					presence_penalty: Number.isFinite(
						Number( settings.presence_penalty )
					)
						? Number( settings.presence_penalty )
						: 0.0,
					request_timeout:
						Number.isFinite( Number( settings.request_timeout ) ) &&
						Number( settings.request_timeout ) > 0
							? Number( settings.request_timeout )
							: 45,
					agent_user_id:
						Number.isFinite( Number( settings.agent_user_id ) ) &&
						Number( settings.agent_user_id ) > 0
							? Number( settings.agent_user_id )
							: 0,
					memory_enabled: Boolean( settings.memory_enabled ),
					setup_completed: Boolean( settings.setup_completed ),
				},
			} );

			setSuccess( __( 'Settings saved.', 'clawpress' ) );
		} catch ( e ) {
			setError(
				e?.message || __( 'Unable to save settings.', 'clawpress' )
			);
		} finally {
			setSaving( false );
		}
	};

	const loadProviderModels = useCallback(
		async ( providerId, { forceRefresh = false } = {} ) => {
			const normalizedProviderId = normalizeProviderId( providerId );
			if ( ! normalizedProviderId ) {
				return [];
			}

			if ( ! forceRefresh ) {
				const cached =
					getCachedProviderModelOptions( normalizedProviderId );
				if ( cached.hasValue ) {
					setDiscoveredModelOptionsByProvider( ( current ) => ( {
						...current,
						[ normalizedProviderId ]: cached.options,
					} ) );
					return cached.options;
				}
			}

			setModelsLoadingByProvider( ( current ) => ( {
				...current,
				[ normalizedProviderId ]: true,
			} ) );

			try {
				const refreshedOptions =
					await loadWpAiProviderModels( normalizedProviderId );
				setDiscoveredModelOptionsByProvider( ( current ) => ( {
					...current,
					[ normalizedProviderId ]: refreshedOptions,
				} ) );
				setCachedProviderModelOptions(
					normalizedProviderId,
					refreshedOptions
				);

				return refreshedOptions;
			} catch {
				setError(
					__(
						'Unable to refresh models for the selected provider.',
						'clawpress'
					)
				);
				return [];
			} finally {
				setModelsLoadingByProvider( ( current ) => ( {
					...current,
					[ normalizedProviderId ]: false,
				} ) );
			}
		},
		[]
	);

	const selectedProviderId = normalizeProviderId( settings.provider );
	const isSelectedProviderLoading = Boolean(
		modelsLoadingByProvider?.[ selectedProviderId ]
	);
	const providerModelOptions = useMemo( () => {
		if (
			! Array.isArray(
				discoveredModelOptionsByProvider?.[ selectedProviderId ]
			)
		) {
			return [];
		}

		return discoveredModelOptionsByProvider[ selectedProviderId ];
	}, [ selectedProviderId, discoveredModelOptionsByProvider ] );
	const selectedModelOption = useMemo( () => {
		const selectedModelId =
			typeof settings.model === 'string' ? settings.model.trim() : '';

		if ( ! selectedModelId ) {
			return null;
		}

		if (
			providerModelOptions.some(
				( option ) => option.id === selectedModelId
			)
		) {
			return null;
		}

		return {
			id: selectedModelId,
			label: selectedModelId,
		};
	}, [ providerModelOptions, settings.model ] );

	useEffect( () => {
		if ( ! selectedProviderId ) {
			return;
		}

		loadProviderModels( selectedProviderId );
	}, [ selectedProviderId, loadProviderModels ] );

	const refreshSelectedProviderModels = async () => {
		if ( ! selectedProviderId ) {
			return;
		}

		setError( '' );
		clearCachedProviderModelOptions( selectedProviderId );
		await loadProviderModels( selectedProviderId, { forceRefresh: true } );
	};
	const modelSelectElements = useMemo(
		() => [
			{
				label: __( 'Select a model', 'clawpress' ),
				value: '',
			},
			...( selectedModelOption
				? [
						{
							label: selectedModelOption.label,
							value: selectedModelOption.id,
						},
				  ]
				: [] ),
			...providerModelOptions.map( ( option ) => ( {
				label: option.label,
				value: option.id,
			} ) ),
		],
		[ providerModelOptions, selectedModelOption ]
	);
	const modelDescriptionState = getModelDescriptionState(
		settings.provider,
		settings.model,
		discoveredModelOptionsByProvider,
		modelCatalogByProvider
	);
	let modelDescription = null;

	if ( modelDescriptionState.modelId ) {
		const modelContext = modelDescriptionState.selectedEntry?.context || '';
		const modelCost = modelDescriptionState.selectedEntry?.cost || '';

		modelDescription = (
			<span className="clawpress-settings__model-meta">
				<code>{ modelDescriptionState.modelId }</code>
				{ modelContext ? (
					<>
						{ ' ' }
						{ __( 'Context Size :', 'clawpress' ) }{ ' ' }
						<code>{ modelContext }</code>
					</>
				) : null }
				{ modelCost ? (
					<>
						{ ' ' }
						{ __( 'Cost :', 'clawpress' ) }{ ' ' }
						<code>{ modelCost }</code>
					</>
				) : null }
			</span>
		);
	}

	const fields = [
		{
			id: 'provider',
			type: 'text',
			label: __( 'Provider', 'clawpress' ),
			description: __(
				'Choose which AI service ClawPress should use.',
				'clawpress'
			),
			Edit: 'select',
			elements: [
				{
					label: __( 'Select a provider', 'clawpress' ),
					value: '',
				},
				...providerOptions,
			],
		},
		{
			id: 'model',
			type: 'text',
			label: __( 'Model', 'clawpress' ),
			description: modelDescription,
			Edit: 'select',
			elements: modelSelectElements,
		},
		{
			id: 'temperature',
			type: 'number',
			label: __( 'Temperature', 'clawpress' ),
			description: __(
				'Lower values make replies more focused and consistent. Higher values make replies more creative and varied.',
				'clawpress'
			),
		},
		{
			id: 'top_p',
			type: 'number',
			label: __( 'Top P', 'clawpress' ),
			description: __(
				'Lower values keep replies more predictable. Higher values allow more variety in wording and ideas.',
				'clawpress'
			),
		},
		{
			id: 'max_output_tokens',
			type: 'integer',
			label: __( 'Max Output Tokens', 'clawpress' ),
			description: __(
				'Sets the maximum reply length. Higher values allow longer answers; lower values keep answers shorter.',
				'clawpress'
			),
		},
		{
			id: 'frequency_penalty',
			type: 'number',
			label: __( 'Frequency Penalty', 'clawpress' ),
			description: __(
				'Higher values reduce repeated words and phrases. Lower values allow more repetition.',
				'clawpress'
			),
		},
		{
			id: 'presence_penalty',
			type: 'number',
			label: __( 'Presence Penalty', 'clawpress' ),
			description: __(
				'Higher values encourage fresh ideas. Lower values keep replies closer to what has already been said.',
				'clawpress'
			),
		},
		{
			id: 'request_timeout',
			type: 'integer',
			label: __( 'Request Timeout (seconds)', 'clawpress' ),
			description: __(
				'How long ClawPress waits for a reply before stopping the request.',
				'clawpress'
			),
		},
		{
			id: 'agent_user_id',
			type: 'integer',
			label: __( 'Agent User ID', 'clawpress' ),
			description: __(
				'Use 0 if this is not configured yet.',
				'clawpress'
			),
		},
		{
			id: 'memory_enabled',
			type: 'boolean',
			label: __( 'Enable Memory', 'clawpress' ),
			description: __(
				'When enabled, ClawPress can remember useful details from earlier chats.',
				'clawpress'
			),
			Edit: 'toggle',
		},
		{
			id: 'setup_completed',
			type: 'boolean',
			label: __( 'Setup Completed', 'clawpress' ),
			description: __(
				'Turn this on after you finish the initial setup.',
				'clawpress'
			),
			Edit: 'toggle',
		},
	];

	const form = {
		layout: {
			type: 'regular',
			labelPosition: 'top',
		},
		fields: [
			'provider',
			'model',
			'request_timeout',
			'agent_user_id',
			'memory_enabled',
			'setup_completed',
			{
				id: 'generation_settings',
				label: __( 'LLM Reply Settings', 'clawpress' ),
				description: __(
					'Not all settings are supported by every provider or model. Unsupported settings are skipped automatically.',
					'clawpress'
				),
				layout: {
					type: 'card',
					isOpened: true,
					withHeader: true,
				},
				children: [
					'temperature',
					'top_p',
					'max_output_tokens',
					'frequency_penalty',
					'presence_penalty',
				],
			},
		],
	};

	return (
		<div className="clawpress-settings">
			<Card>
				<CardHeader>
					<h3>{ __( 'Settings', 'clawpress' ) }</h3>
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
					{ loading ? (
						<Spinner />
					) : (
						<div>
							<DataForm
								data={ settings }
								fields={ fields }
								form={ form }
								onChange={ ( edits ) =>
									setSettings( ( current ) => {
										const next = {
											...current,
											...edits,
										};

										if (
											Object.prototype.hasOwnProperty.call(
												edits,
												'provider'
											)
										) {
											const nextProviderId =
												normalizeProviderId(
													next.provider
												);
											const nextOptions = Array.isArray(
												discoveredModelOptionsByProvider?.[
													nextProviderId
												]
											)
												? discoveredModelOptionsByProvider[
														nextProviderId
												  ]
												: [];
											const hasSelectedModel =
												nextOptions.some(
													( option ) =>
														option.id === next.model
												);

											if ( ! hasSelectedModel ) {
												next.model = '';
											}
										}

										return next;
									} )
								}
							/>
							<div className="clawpress-settings__actions">
								<Button
									className="clawpress-settings__save-button"
									variant="primary"
									onClick={ saveSettings }
									isBusy={ saving }
									disabled={ saving }
								>
									{ saving
										? __( 'Saving…', 'clawpress' )
										: __( 'Save Settings', 'clawpress' ) }
								</Button>
								<Button
									className="clawpress-settings__refresh-models-button"
									variant="secondary"
									icon={ update }
									onClick={ refreshSelectedProviderModels }
									isBusy={ isSelectedProviderLoading }
									disabled={
										! selectedProviderId ||
										isSelectedProviderLoading ||
										saving
									}
								>
									{ __( 'Refresh Models', 'clawpress' ) }
								</Button>
							</div>
						</div>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
