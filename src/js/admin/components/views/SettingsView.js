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
	const [ executionUserId, setExecutionUserId ] = useState( 0 );
	const [ memoryEnabled, setMemoryEnabled ] = useState( false );
	const [ onboardingCompleted, setOnboardingCompleted ] = useState( false );

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
				setExecutionUserId(
					Number.isFinite( Number( settings.execution_user_id ) )
						? Number( settings.execution_user_id )
						: 0
				);
				setMemoryEnabled( Boolean( settings.memory_enabled ) );
				setOnboardingCompleted(
					Boolean( settings.onboarding_completed )
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
					execution_user_id:
						Number.isFinite( Number( executionUserId ) ) &&
						Number( executionUserId ) > 0
							? Number( executionUserId )
							: 0,
					memory_enabled: memoryEnabled,
					onboarding_completed: onboardingCompleted,
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
								label={ __(
									'Execution User ID',
									'clawpress'
								) }
								type="number"
								min={ 0 }
								value={ String( executionUserId ) }
								onChange={ ( value ) =>
									setExecutionUserId(
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
									'Onboarding Completed',
									'clawpress'
								) }
								checked={ onboardingCompleted }
								onChange={ setOnboardingCompleted }
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
