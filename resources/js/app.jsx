import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import '../css/app.css';
import MarketplaceLayout from './Layouts/MarketplaceLayout';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

createInertiaApp({
    resolve: (name) => {
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(`Inertia page "${name}" was not found.`);
        }

        const component = page.default;
        if (/^(Storefront|Auth|Customer)\//.test(name)) {
            component.layout = (page) => <MarketplaceLayout>{page}</MarketplaceLayout>;
        }

        return component;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
    },
});
