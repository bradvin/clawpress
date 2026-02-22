import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const ToolDialogForm = ( {
	fields,
	initialValues,
	onSubmit,
	onPreview,
	onRun,
	onCancel,
	disabled,
} ) => {
	const [ values, setValues ] = useState( initialValues );

	useEffect( () => {
		setValues( initialValues );
	}, [ initialValues ] );

	const updateValue = ( name, value ) =>
		setValues( ( prev ) => ( {
			...prev,
			[ name ]: value,
		} ) );

	const handleSubmit = ( event ) => {
		event.preventDefault();
		if ( disabled ) {
			return;
		}
		onSubmit( values );
	};

	const handleRun = ( event ) => {
		event.preventDefault();
		if ( disabled ) {
			return;
		}
		if ( onRun ) {
			onRun( values );
		}
	};

	return (
		<form className="clawpress-tool-dialog-form" onSubmit={ handleSubmit }>
			{ fields.map( ( field ) => {
				const value = values[ field.name ];
				const common = {
					id: `clawpress-tool-field-${ field.name }`,
					name: field.name,
					disabled,
				};

				if ( field.type === 'hidden' ) {
					return (
						<input
							key={ field.name }
							{ ...common }
							type="hidden"
							value={ value ?? '' }
						/>
					);
				}

				return (
					<label
						key={ field.name }
						className="clawpress-tool-dialog-field"
						htmlFor={ common.id }
					>
						<span className="clawpress-tool-dialog-label">
							{ field.label }
						</span>
						{ field.type === 'textarea' ? (
							<textarea
								{ ...common }
								className="clawpress-tool-dialog-input"
								rows={ field.rows || 3 }
								value={ value ?? '' }
								onChange={ ( e ) =>
									updateValue( field.name, e.target.value )
								}
							/>
						) : field.type === 'select' ? (
							<select
								{ ...common }
								className="clawpress-tool-dialog-input"
								value={ value ?? '' }
								onChange={ ( e ) =>
									updateValue( field.name, e.target.value )
								}
							>
								{ field.options?.map( ( option ) => (
									<option
										key={ option.value }
										value={ option.value }
									>
										{ option.label }
									</option>
								) ) }
							</select>
						) : field.type === 'checkbox' ? (
							<input
								{ ...common }
								type="checkbox"
								checked={ Boolean( value ) }
								onChange={ ( e ) =>
									updateValue( field.name, e.target.checked )
								}
							/>
						) : (
							<input
								{ ...common }
								className="clawpress-tool-dialog-input"
								type={ field.type || 'text' }
								value={ value ?? '' }
								onChange={ ( e ) =>
									updateValue( field.name, e.target.value )
								}
							/>
						) }
						{ field.help ? (
							<span className="clawpress-tool-dialog-help">
								{ field.help }
							</span>
						) : null }
					</label>
				);
			} ) }
			<div className="clawpress-tool-dialog-form-actions">
				<button
					className="button button-primary"
					type="submit"
					disabled={ disabled }
				>
					{ __( 'Preview', 'clawpress' ) }
				</button>
				{ onRun ? (
					<button
						className="button"
						type="button"
						onClick={ handleRun }
						disabled={ disabled }
					>
						{ __( 'Run', 'clawpress' ) }
					</button>
				) : null }
				{ onCancel ? (
					<button
						className="button"
						type="button"
						onClick={ onCancel }
						disabled={ disabled }
					>
						{ __( 'Cancel', 'clawpress' ) }
					</button>
				) : null }
			</div>
		</form>
	);
};

export default ToolDialogForm;
