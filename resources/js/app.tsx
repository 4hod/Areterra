import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const pages = import.meta.glob('./Pages/**/*.tsx');

createInertiaApp({
    defaults: {
        future: {
            useScriptElementForInitialPage: true,
        },
    },
    title: (title) => (title ? `${title} — Areterra Hub` : 'Areterra Hub'),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, pages),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#009DE6' },
});

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/ah-sw.js').catch(() => {
        // Service worker is progressive enhancement only.
    });
}
