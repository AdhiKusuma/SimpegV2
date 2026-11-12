<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'SIMPEG') }}</title>

        <script>
            (() => {
                const storedTheme = window.localStorage.getItem('simpeg-theme');
                const hasUserChoice = window.localStorage.getItem('simpeg-theme-explicit-choice-v1') === '1';
                const theme = hasUserChoice && (storedTheme === 'light' || storedTheme === 'dark') ? storedTheme : 'dark';

                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            })();
        </script>

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
    </body>
</html>
