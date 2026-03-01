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
import { __ } from '@wordpress/i18n';
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

export default function SettingsView() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ success, setSuccess ] = useState( '' );
	const [ settings, setSettings ] = useState( DEFAULT_SETTINGS );

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
				{
					label: __( 'OpenAI', 'clawpress' ),
					value: 'openai',
				},
				{
					label: __( 'Anthropic', 'clawpress' ),
					value: 'anthropic',
				},
				{
					label: __( 'Google', 'clawpress' ),
					value: 'google',
				},
			],
		},
		{
			id: 'model',
			type: 'text',
			label: __( 'Model', 'clawpress' ),
			description: __(
				'Enter the model name you want to use for replies.',
				'clawpress'
			),
			placeholder: 'gpt-4.1-mini',
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
									setSettings( ( current ) => ( {
										...current,
										...edits,
									} ) )
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
