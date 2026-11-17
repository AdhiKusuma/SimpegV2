<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'SIMPEG') }}</title>

        <link rel="preload" as="image" href="{{ asset('images/logo-simpeg-256.png') }}" type="image/png" fetchpriority="high">
        @if ((request()->routeIs('login') || request()->is('login')) && ! Vite::isRunningHot())
            <link rel="modulepreload" href="{{ Vite::asset('resources/js/Pages/Auth/Login.jsx') }}">
        @endif
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
        <script>
            (() => {
                const root = document.getElementById('app');
                let appConfig = {};

                try {
                    appConfig = JSON.parse(root?.dataset.page || '{}')?.props?.appConfig || {};
                } catch (error) {
                    appConfig = {};
                }

                const storedMode = window.localStorage.getItem('simpeg-theme-mode');
                const storedTheme = window.localStorage.getItem('simpeg-theme');
                const hasUserChoice = window.localStorage.getItem('simpeg-theme-explicit-choice-v1') === '1';
                const isLogin = window.location.pathname === '/login';
                const loginThemeDisabled = isLogin && appConfig.applyLoginTheme === false;
                const allowUserTheme = !loginThemeDisabled && appConfig.allowUserTheme !== false;
                const configuredTheme = appConfig.defaultTheme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : (appConfig.defaultTheme === 'dark' ? 'dark' : 'light');
                const theme = !allowUserTheme
                    ? configuredTheme
                    : storedMode === 'system'
                        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                        : hasUserChoice && (storedTheme === 'light' || storedTheme === 'dark') ? storedTheme : configuredTheme;

                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
                document.documentElement.toggleAttribute('data-simpeg-reduce-motion', Boolean(appConfig.reduceMotion));
                if (appConfig.reduceMotion) {
                    document.documentElement.dataset.simpegReduceMotion = '1';
                }
                document.documentElement.dataset.simpegDensity = ['compact', 'comfortable', 'spacious'].includes(appConfig.density)
                    ? appConfig.density
                    : 'comfortable';
            })();
        </script>
    </body>
</html>
