import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { applyConfiguredTheme, resolveTheme } from './Components/ThemeToggle';

const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => `${title} | SIMPEG`,
    resolve: (name) => pages[`./Pages/${name}.jsx`](),
    progress: {
        color: resolveTheme() === 'dark' ? '#7DB3FF' : '#0D5FE0',
    },
    setup({ el, App, props }) {
        const initialPage = props?.initialPage;
        const appConfig = initialPage?.props?.appConfig || {};
        applyConfiguredTheme(appConfig, { isLogin: window.location.pathname === '/login' });
        createRoot(el).render(createElement(App, props));
    },
});
