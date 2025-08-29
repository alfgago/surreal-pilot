import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route } from 'ziggy-js';
// @ts-ignore
import { Ziggy } from './ziggy.js';

const appName = import.meta.env.VITE_APP_NAME || 'SurrealPilot';

// Make route function globally available
window.route = route;

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4F46E5',
    },
}).then(() => {
    // Initialize Ziggy with the routes
    if (typeof window !== 'undefined') {
        window.Ziggy = Ziggy;
    }
});