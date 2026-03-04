import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
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

const loadWpAiProviderModelCatalog = async () => {
	let providers = [];

	try {
		providers = await requestWpAiJson( 'providers' );
	} catch {
		return null;
	}

	if ( ! Array.isArray( providers ) || providers.length === 0 ) {
		return null;
	}

	const providerOptions = [];
	const discoveredModelOptions = {};

	await Promise.all(
		providers.map( async ( provider ) => {
			const providerId =
				typeof provider?.id === 'string'
					? provider.id.trim().toLowerCase()
					: '';
			const providerLabel =
				typeof provider?.name === 'string'
					? provider.name.trim()
					: providerId;

			if ( ! providerId ) {
				return;
			}

			providerOptions.push( {
				value: providerId,
				label: providerLabel || providerId,
			} );

			try {
				const providerModels = await requestWpAiJson(
					`providers/${ encodeURIComponent( providerId ) }/models`
				);
				if ( ! Array.isArray( providerModels ) ) {
					discoveredModelOptions[ providerId ] = [];
					return;
				}

				discoveredModelOptions[ providerId ] = providerModels
					.map( ( model ) => {
						const id =
							typeof model?.id === 'string'
								? model.id.trim()
								: '';
						const label =
							typeof model?.name === 'string'
								? model.name.trim()
								: '';
						if ( ! id ) {
							return null;
						}

						return {
							id,
							label: label || id,
						};
					} )
					.filter( Boolean );
			} catch {
				discoveredModelOptions[ providerId ] = [];
			}
		} )
	);

	if ( providerOptions.length === 0 ) {
		return null;
	}

	return {
		providers: providerOptions,
		models: discoveredModelOptions,
	};
};

const normalizeProviderOptions = ( providers ) => {
	if ( ! Array.isArray( providers ) ) {
		return getDefaultProviderOptions();
	}

	const options = providers
		.map( ( provider ) => {
			const value =
				typeof provider?.value === 'string'
					? provider.value.trim().toLowerCase()
					: '';
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
			const providerKey =
				typeof provider === 'string'
					? provider.trim().toLowerCase()
					: '';
			if ( ! providerKey || ! Array.isArray( options ) ) {
				return accumulator;
			}

			const normalizedOptions = options
				.map( ( option ) => {
					const id =
						typeof option?.id === 'string' ? option.id.trim() : '';
					const label =
						typeof option?.label === 'string'
							? option.label.trim()
							: '';
					if ( ! id ) {
						return null;
					}

					return {
						id,
						label: label || id,
					};
				} )
				.filter( Boolean );

			if ( normalizedOptions.length > 0 ) {
				accumulator[ providerKey ] = normalizedOptions;
			}

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
			const providerKey =
				typeof provider === 'string'
					? provider.trim().toLowerCase()
					: '';
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

const buildModelDescription = (
	provider,
	model,
	discoveredModelOptionsByProvider,
	modelCatalogByProvider
) => {
	const providerId =
		typeof provider === 'string' ? provider.trim().toLowerCase() : '';
	const modelId = typeof model === 'string' ? model.trim() : '';

	if ( ! providerId ) {
		return __( 'Select a provider first.', 'clawpress' );
	}

	const discoveredOptions = Array.isArray(
		discoveredModelOptionsByProvider?.[ providerId ]
	)
		? discoveredModelOptionsByProvider[ providerId ]
		: [];

	if ( discoveredOptions.length === 0 ) {
		return __(
			'No models were discovered for this provider. Configure it in Connectors, then refresh this page.',
			'clawpress'
		);
	}

	if ( ! modelId ) {
		return __(
			'Select a model to view context and cost details.',
			'clawpress'
		);
	}

	const catalogEntries = Array.isArray(
		modelCatalogByProvider?.[ providerId ]
	)
		? modelCatalogByProvider[ providerId ]
		: [];
	const selectedEntry =
		catalogEntries.find( ( entry ) => entry.id === modelId ) || null;

	if ( ! selectedEntry ) {
		return __(
			'Context and cost are not available for this model in the curated catalog.',
			'clawpress'
		);
	}

	return sprintf(
		/* translators: 1: model context window, 2: model cost information */
		__( 'Context window: %1$s | Cost: %2$s', 'clawpress' ),
		selectedEntry.context || __( 'Unknown', 'clawpress' ),
		selectedEntry.cost || __( 'Unknown', 'clawpress' )
	);
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

				const wpAiCatalog = await loadWpAiProviderModelCatalog();
				if ( ! mounted || ! wpAiCatalog ) {
					return;
				}

				setProviderOptions(
					normalizeProviderOptions( wpAiCatalog.providers )
				);
				setDiscoveredModelOptionsByProvider(
					normalizeModelOptions( wpAiCatalog.models )
				);
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

	const selectedProviderId =
		typeof settings.provider === 'string'
			? settings.provider.trim().toLowerCase()
			: '';
	const providerModelOptions = Array.isArray(
		discoveredModelOptionsByProvider?.[ selectedProviderId ]
	)
		? discoveredModelOptionsByProvider[ selectedProviderId ]
		: [];
	const modelSelectElements = [
		{
			label: __( 'Select a model', 'clawpress' ),
			value: '',
		},
		...providerModelOptions.map( ( option ) => ( {
			label: option.label,
			value: option.id,
		} ) ),
	];

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
			Edit: 'select',
			description: buildModelDescription(
				settings.provider,
				settings.model,
				discoveredModelOptionsByProvider,
				modelCatalogByProvider
			),
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
												typeof next.provider ===
												'string'
													? next.provider
															.trim()
															.toLowerCase()
													: '';
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
						</div>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
