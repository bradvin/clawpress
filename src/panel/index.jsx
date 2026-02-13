import { createRoot } from '@wordpress/element';
import './index.scss';
import Panel from './Panel';

let root = document.getElementById('clawpress-floating-panel-root');
if (!root) {
  root = document.createElement('div');
  root.id = 'clawpress-floating-panel-root';
  document.body.appendChild(root);
}

createRoot(root).render(<Panel />);
