import { render } from '@wordpress/element';
import App from './App';
import '../css/admin.css';

document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('ranksavvy-admin-app');
    if (rootElement) {
        render(<App />, rootElement);
    }
});
