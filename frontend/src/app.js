import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { applyTheme, resolveTheme } from './Components/ThemeToggle';

applyTheme(resolveTheme());

const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => `${title} | SIMPEG`,
    resolve: (name) => pages[`./Pages/${name}.jsx`](),
    progress: {
        color: resolveTheme() === 'corporate' ? '#263285' : '#F5CE59',
    },
    setup({ el, App, props }) {
        createRoot(el).render(createElement(App, props));
    },
});
