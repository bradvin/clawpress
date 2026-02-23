import { getParsedArguments } from '../utils/parseArguments';
import { getToolRenderer, getToolPolicy } from '../utils/toolDialogRenderers';

const ToolDialog = ( { toolDialog, isOpen, onRunTool, onCancel } ) => {
	if ( ! toolDialog ) {
		return null;
	}
	const args = getParsedArguments( toolDialog.function );
	const toolName = toolDialog.function?.name || '';
	const Renderer = getToolRenderer( toolName );
	const policy = getToolPolicy( toolName );

	return (
		<Renderer
			toolName={ toolName }
			args={ args }
			toolDialog={ toolDialog }
			runTool={ onRunTool }
			onCancel={ onCancel }
			policy={ policy }
			isOpen={ isOpen }
		/>
	);
};

export default ToolDialog;
