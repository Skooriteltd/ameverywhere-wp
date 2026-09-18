import { createRoot, render } from '@wordpress/element';
import App from './App';
import '../css/admin.css';

document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('ameverywhere-admin-app') || document.getElementById('ranksavvy-admin-app');
    if (rootElement) {
        if (createRoot) {
            createRoot(rootElement).render(<App />);
        } else {
            render(<App />, rootElement);
        }
    }
});
