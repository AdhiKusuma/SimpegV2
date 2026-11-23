import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { applyConfiguredTheme, resolveTheme } from './Components/ThemeToggle';

const pages = import.meta.glob('./Pages/**/*.jsx');

function requestBrowserLocation(initialPage) {
    const userId = initialPage?.props?.auth?.user?.id_user;
    if (!userId || window.location.pathname === '/login' || !navigator.geolocation) {
        return;
    }

    const requestKey = 'simpeg-audit-location-requested-v1';
    if (window.sessionStorage.getItem(requestKey) === '1') {
        return;
    }

    window.sessionStorage.setItem(requestKey, '1');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    navigator.geolocation.getCurrentPosition(({ coords }) => {
        fetch('/session/location', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                latitude: coords.latitude,
                longitude: coords.longitude,
                accuracy: coords.accuracy,
            }),
        }).catch(() => undefined);
    }, () => undefined, {
        enableHighAccuracy: false,
        maximumAge: 300000,
        timeout: 5000,
    });
}

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
        requestBrowserLocation(initialPage);
    },
});
