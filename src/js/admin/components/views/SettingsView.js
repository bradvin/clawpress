import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const getAdminConfig = () => {
	if ( typeof window === 'undefined' ) {
		return { restBase: '/wp-json/clawpress/v1', nonce: '' };
	}

	return {
		restBase: window.CLAWPRESS_ADMIN?.restBase || '/wp-json/clawpress/v1',
		nonce: window.CLAWPRESS_ADMIN?.nonce || '',
	};
};

const requestJson = async ( path, { method = 'GET', body } = {} ) => {
	const { restBase, nonce } = getAdminConfig();
	const url = `${ restBase }/${ path.replace( /^\//, '' ) }`;

	const response = await fetch( url, {
		method,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: body ? JSON.stringify( body ) : undefined,
	} );

	const text = await response.text();
	let payload = {};

	if ( text ) {
		try {
			payload = JSON.parse( text );
		} catch {
			payload = { message: text };
		}
	}

	if ( ! response.ok ) {
		throw new Error(
			payload?.message ||
				payload?.error ||
				__( 'Request failed.', 'clawpress' )
		);
	}

	return payload;
};

export default function SettingsView() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ success, setSuccess ] = useState( '' );
	const [ provider, setProvider ] = useState( '' );
	const [ model, setModel ] = useState( '' );
	const [ temperature, setTemperature ] = useState( 0.2 );
	const [ topP, setTopP ] = useState( 0.9 );
	const [ maxOutputTokens, setMaxOutputTokens ] = useState( 1200 );
	const [ frequencyPenalty, setFrequencyPenalty ] = useState( 0.2 );
	const [ presencePenalty, setPresencePenalty ] = useState( 0.0 );
	const [ requestTimeout, setRequestTimeout ] = useState( 45 );
	const [ agentUserId, setAgentUserId ] = useState( 0 );
	const [ memoryEnabled, setMemoryEnabled ] = useState( false );
	const [ setupCompleted, setSetupCompleted ] = useState( false );

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

				const settings = data?.settings || {};
				setProvider(
					typeof settings.provider === 'string'
						? settings.provider
						: ''
				);
				setModel(
					typeof settings.model === 'string'
						? settings.model
						: ''
				);
				setTemperature(
					Number.isFinite( Number( settings.temperature ) )
						? Number( settings.temperature )
						: 0.2
				);
				setTopP(
					Number.isFinite( Number( settings.top_p ) )
						? Number( settings.top_p )
						: 0.9
				);
				setMaxOutputTokens(
					Number.isFinite( Number( settings.max_output_tokens ) ) &&
						Number( settings.max_output_tokens ) > 0
						? Number( settings.max_output_tokens )
						: 1200
				);
				setFrequencyPenalty(
					Number.isFinite( Number( settings.frequency_penalty ) )
						? Number( settings.frequency_penalty )
						: 0.2
				);
				setPresencePenalty(
					Number.isFinite( Number( settings.presence_penalty ) )
						? Number( settings.presence_penalty )
						: 0.0
				);
				setRequestTimeout(
					Number.isFinite( Number( settings.request_timeout ) ) &&
						Number( settings.request_timeout ) > 0
						? Number( settings.request_timeout )
						: 45
				);
				setAgentUserId(
					Number.isFinite( Number( settings.agent_user_id ) )
						? Number( settings.agent_user_id )
						: 0
				);
				setMemoryEnabled( Boolean( settings.memory_enabled ) );
				setSetupCompleted(
					Boolean( settings.setup_completed )
				);
			} catch ( e ) {
				if ( ! mounted ) {
					return;
				}
				setError(
					e?.message ||
						__( 'Unable to load settings.', 'clawpress' )
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
					provider,
					model,
					temperature: Number.isFinite( Number( temperature ) )
						? Number( temperature )
						: 0.2,
					top_p: Number.isFinite( Number( topP ) )
						? Number( topP )
						: 0.9,
					max_output_tokens:
						Number.isFinite( Number( maxOutputTokens ) ) &&
						Number( maxOutputTokens ) > 0
							? Number( maxOutputTokens )
							: 1200,
					frequency_penalty:
						Number.isFinite( Number( frequencyPenalty ) )
							? Number( frequencyPenalty )
							: 0.2,
					presence_penalty:
						Number.isFinite( Number( presencePenalty ) )
							? Number( presencePenalty )
							: 0.0,
					request_timeout:
						Number.isFinite( Number( requestTimeout ) ) &&
						Number( requestTimeout ) > 0
							? Number( requestTimeout )
							: 45,
					agent_user_id:
						Number.isFinite( Number( agentUserId ) ) &&
						Number( agentUserId ) > 0
							? Number( agentUserId )
							: 0,
					memory_enabled: memoryEnabled,
					setup_completed: setupCompleted,
				},
			} );

			setSuccess( __( 'Settings saved.', 'clawpress' ) );
		} catch ( e ) {
			setError(
				e?.message ||
					__( 'Unable to save settings.', 'clawpress' )
			);
		} finally {
			setSaving( false );
		}
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
							<SelectControl
								label={ __( 'Provider', 'clawpress' ) }
								value={ provider }
								options={ [
									{
										label: __(
											'Select a provider',
											'clawpress'
										),
										value: '',
									},
										{
											label: __(
												'OpenAI',
												'clawpress'
											),
											value: 'openai',
										},
										{
											label: __(
												'Anthropic',
												'clawpress'
											),
											value: 'anthropic',
										},
										{
											label: __(
												'Google',
												'clawpress'
											),
											value: 'google',
										},
									] }
								onChange={ setProvider }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Model', 'clawpress' ) }
								value={ model }
								onChange={ setModel }
								help={ __(
									'Example: gpt-4.1-mini',
									'clawpress'
								) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Temperature', 'clawpress' ) }
								type="number"
								step="0.1"
								min={ 0 }
								max={ 2 }
								value={ String( temperature ) }
								onChange={ ( value ) =>
									setTemperature(
										Number.isFinite( Number( value ) )
											? Number( value )
											: 0.2
									)
								}
								help={ __( 'Balanced default: 0.2', 'clawpress' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Top P', 'clawpress' ) }
								type="number"
								step="0.1"
								min={ 0 }
								max={ 1 }
								value={ String( topP ) }
								onChange={ ( value ) =>
									setTopP(
										Number.isFinite( Number( value ) )
											? Number( value )
											: 0.9
									)
								}
								help={ __( 'Balanced default: 0.9 (when supported).', 'clawpress' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Max Output Tokens', 'clawpress' ) }
								type="number"
								min={ 1 }
								value={ String( maxOutputTokens ) }
								onChange={ ( value ) =>
									setMaxOutputTokens(
										Number.isFinite( Number( value ) ) && Number( value ) > 0
											? Number( value )
											: 1200
									)
								}
								help={ __( 'Balanced default: 1200', 'clawpress' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Frequency Penalty', 'clawpress' ) }
								type="number"
								step="0.1"
								min={ -2 }
								max={ 2 }
								value={ String( frequencyPenalty ) }
								onChange={ ( value ) =>
									setFrequencyPenalty(
										Number.isFinite( Number( value ) )
											? Number( value )
											: 0.2
									)
								}
								help={ __( 'Balanced default: 0.2 (when supported).', 'clawpress' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Presence Penalty', 'clawpress' ) }
								type="number"
								step="0.1"
								min={ -2 }
								max={ 2 }
								value={ String( presencePenalty ) }
								onChange={ ( value ) =>
									setPresencePenalty(
										Number.isFinite( Number( value ) )
											? Number( value )
											: 0.0
									)
								}
								help={ __( 'Balanced default: 0.0 (when supported).', 'clawpress' ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __(
									'Request Timeout (seconds)',
									'clawpress'
								) }
								type="number"
								min={ 1 }
								value={ String( requestTimeout ) }
								onChange={ ( value ) =>
									setRequestTimeout(
										Number.isFinite( Number( value ) ) &&
											Number( value ) > 0
											? Number( value )
											: 45
									)
								}
								help={ __(
									'Maximum time to wait for an AI response. Balanced default is 45 seconds.',
									'clawpress'
								) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __(
									'Agent User ID',
									'clawpress'
								) }
								type="number"
								min={ 0 }
								value={ String( agentUserId ) }
								onChange={ ( value ) =>
									setAgentUserId(
										Number.isFinite( Number( value ) )
											? Number( value )
											: 0
									)
								}
								help={ __(
									'Use 0 to mark as not configured.',
									'clawpress'
								) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __(
									'Enable Memory',
									'clawpress'
								) }
								checked={ memoryEnabled }
								onChange={ setMemoryEnabled }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __(
									'Setup Completed',
									'clawpress'
								) }
								checked={ setupCompleted }
								onChange={ setSetupCompleted }
								__nextHasNoMarginBottom
							/>
							<Button
								variant="primary"
								onClick={ saveSettings }
								isBusy={ saving }
								disabled={ saving }
							>
								{ saving
									? __(
											'Saving…',
											'clawpress'
									  )
									: __( 'Save Settings', 'clawpress' ) }
							</Button>
						</div>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
