<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'SIMPEG') }}</title>

        <link rel="preload" as="image" href="{{ asset('images/logo-simpeg-256.png') }}" type="image/png" fetchpriority="high">
        @if ((request()->routeIs('login') || request()->is('login')) && ! Vite::isRunningHot())
            <link rel="modulepreload" href="{{ Vite::asset('src/Pages/Auth/Login.jsx') }}">
        @endif
        @viteReactRefresh
        @vite(['src/css/app.css', 'src/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
