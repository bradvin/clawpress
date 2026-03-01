import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { requestJson } from '../../utils/requestJson';

const DEFAULT_STATE = {
	abilities: [],
	categories: [],
	enabled_abilities: [],
	default_abilities: [],
};

const normalizeCategory = ( category = {} ) => {
	const slug =
		typeof category.slug === 'string' && category.slug.trim()
			? category.slug
			: 'uncategorized';
	const label =
		typeof category.label === 'string' && category.label.trim()
			? category.label
			: slug;

	return { slug, label };
};

const normalizeAbility = ( ability = {} ) => ( {
	tool_name: typeof ability.tool_name === 'string' ? ability.tool_name : '',
	ability_name:
		typeof ability.ability_name === 'string' ? ability.ability_name : '',
	label:
		typeof ability.label === 'string' && ability.label.trim()
			? ability.label
			: ability.ability_name || ability.tool_name,
	description:
		typeof ability.description === 'string' ? ability.description : '',
	registered: Boolean( ability.registered ),
	manageable: Boolean( ability.manageable ),
	enabled: Boolean( ability.enabled ),
	category: normalizeCategory( ability.category ),
	annotations: {
		readonly: Boolean( ability?.annotations?.readonly ),
		destructive: Boolean( ability?.annotations?.destructive ),
		idempotent: Boolean( ability?.annotations?.idempotent ),
	},
} );

const normalizeState = ( payload = {} ) => {
	const abilitiesRaw = Array.isArray( payload.abilities )
		? payload.abilities
		: [];
	const categoriesRaw = Array.isArray( payload.categories )
		? payload.categories
		: [];
	const enabledRaw = Array.isArray( payload.enabled_abilities )
		? payload.enabled_abilities
		: [];
	const defaultsRaw = Array.isArray( payload.default_abilities )
		? payload.default_abilities
		: [];

	return {
		abilities: abilitiesRaw.map( normalizeAbility ),
		categories: categoriesRaw.map( normalizeCategory ),
		enabled_abilities: enabledRaw.filter(
			( item ) => typeof item === 'string'
		),
		default_abilities: defaultsRaw.filter(
			( item ) => typeof item === 'string'
		),
	};
};

export default function AbilitiesView() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ success, setSuccess ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( 'all' );
	const [ state, setState ] = useState( DEFAULT_STATE );

	const loadAbilities = async () => {
		setLoading( true );
		setError( '' );

		try {
			const payload = await requestJson( 'settings/abilites' );
			setState( normalizeState( payload ) );
		} catch ( e ) {
			setError(
				e?.message || __( 'Unable to load abilities.', 'clawpress' )
			);
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		loadAbilities();
	}, [] );

	const categoryOptions = useMemo( () => {
		const options = [
			{ value: 'all', label: __( 'All Categories', 'clawpress' ) },
		];

		state.categories.forEach( ( category ) => {
			options.push( {
				value: category.slug,
				label: category.label,
			} );
		} );

		return options;
	}, [ state.categories ] );

	const filteredAbilities = useMemo(
		() =>
			state.abilities.filter(
				( ability ) =>
					'all' === categoryFilter ||
					ability.category.slug === categoryFilter
			),
		[ state.abilities, categoryFilter ]
	);

	const toggleAbility = ( abilityName, enabled ) => {
		setError( '' );
		setSuccess( '' );
		setState( ( current ) => ( {
			...current,
			abilities: current.abilities.map( ( ability ) =>
				ability.ability_name === abilityName
					? {
							...ability,
							enabled,
					  }
					: ability
			),
		} ) );
	};

	const saveAbilities = async () => {
		setSaving( true );
		setError( '' );
		setSuccess( '' );

		try {
			const selected = state.abilities
				.filter( ( ability ) => ability.enabled )
				.map( ( ability ) => ability.ability_name );
			const response = await requestJson( 'settings/abilites', {
				method: 'POST',
				body: {
					abilities: selected,
				},
			} );

			setState( normalizeState( response?.state || {} ) );
			setSuccess( __( 'Abilities saved.', 'clawpress' ) );
		} catch ( e ) {
			setError(
				e?.message || __( 'Unable to save abilities.', 'clawpress' )
			);
		} finally {
			setSaving( false );
		}
	};

	const resetAbilities = async () => {
		setResetting( true );
		setError( '' );
		setSuccess( '' );

		try {
			const response = await requestJson( 'settings/abilites', {
				method: 'POST',
				body: {
					reset: true,
				},
			} );

			setState( normalizeState( response?.state || {} ) );
			setSuccess( __( 'Abilities reset to defaults.', 'clawpress' ) );
		} catch ( e ) {
			setError(
				e?.message || __( 'Unable to reset abilities.', 'clawpress' )
			);
		} finally {
			setResetting( false );
		}
	};

	const enabledCount = state.abilities.filter(
		( ability ) => ability.enabled
	).length;

	return (
		<div className="clawpress-abilities">
			<Card>
				<CardHeader>
					<div className="clawpress-abilities__header">
						<h3>{ __( 'Abilities', 'clawpress' ) }</h3>
						<p>
							{ sprintf(
								/* translators: %d: Number of enabled abilities. */
								__( '%d abilities enabled', 'clawpress' ),
								enabledCount
							) }
						</p>
					</div>
				</CardHeader>
				<CardBody>
					{ error ? (
						<Notice
							status="error"
							onRemove={ () => setError( '' ) }
						>
							{ error }
						</Notice>
					) : null }
					{ success ? (
						<Notice
							status="success"
							onRemove={ () => setSuccess( '' ) }
						>
							{ success }
						</Notice>
					) : null }

					{ loading ? (
						<Spinner />
					) : (
						<div>
							<div className="clawpress-abilities__toolbar">
								<SelectControl
									label={ __(
										'Filter by category',
										'clawpress'
									) }
									value={ categoryFilter }
									options={ categoryOptions }
									onChange={ setCategoryFilter }
								/>
								<div className="clawpress-abilities__actions">
									<Button
										variant="secondary"
										onClick={ resetAbilities }
										isBusy={ resetting }
										disabled={ resetting || saving }
									>
										{ resetting
											? __( 'Resetting…', 'clawpress' )
											: __(
													'Reset to Defaults',
													'clawpress'
											  ) }
									</Button>
									<Button
										variant="primary"
										onClick={ saveAbilities }
										isBusy={ saving }
										disabled={ saving || resetting }
									>
										{ saving
											? __( 'Saving…', 'clawpress' )
											: __(
													'Save Abilities',
													'clawpress'
											  ) }
									</Button>
								</div>
							</div>

							{ filteredAbilities.length === 0 ? (
								<p className="clawpress-abilities__empty">
									{ __(
										'No abilities found for this category.',
										'clawpress'
									) }
								</p>
							) : (
								<div className="clawpress-abilities__grid">
									{ filteredAbilities.map( ( ability ) => (
										<article
											key={ ability.ability_name }
											className="clawpress-abilities__item"
										>
											<div className="clawpress-abilities__item-header">
												<div>
													<h4>{ ability.label }</h4>
													<p className="clawpress-abilities__meta">
														{
															ability.category
																.label
														}
													</p>
												</div>
											<ToggleControl
												checked={ ability.enabled }
													onChange={ ( enabled ) =>
														toggleAbility(
															ability.ability_name,
															enabled
														)
													}
												label={ __(
													'Enabled',
													'clawpress'
												) }
												disabled={
													! ability.registered ||
													! ability.manageable
												}
											/>
											</div>

											{ ability.description ? (
												<p className="clawpress-abilities__description">
													{ ability.description }
												</p>
											) : null }

											<div className="clawpress-abilities__annotations">
												{ ability.annotations
													.readonly ? (
													<span className="clawpress-abilities__annotation is-readonly">
														{ _x(
															'readonly',
															'Ability safety annotation pill label',
															'clawpress'
														) }
													</span>
												) : null }
												{ ability.annotations
													.destructive ? (
													<span className="clawpress-abilities__annotation is-destructive">
														{ _x(
															'destructive',
															'Ability safety annotation pill label',
															'clawpress'
														) }
													</span>
												) : null }
											</div>
											{ ! ability.registered ? (
												<p className="clawpress-abilities__warning">
													{ __(
														'This ability is not currently registered.',
														'clawpress'
													) }
												</p>
											) : null }
										</article>
									) ) }
								</div>
							) }
						</div>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
