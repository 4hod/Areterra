import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} — Areterra Hub` : 'Areterra Hub'),
    resolve: (name) => pages[`./Pages/${name}.tsx`] as never,
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
