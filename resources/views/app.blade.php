<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- PWA / mobile-app friendliness. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="application-name" content="{{ config('app.name', 'Todai') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Todai') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="theme-color" content="#252525">

        {{-- The app is always dark; set the HTML background to match app.css. --}}
        <style>
            html {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{-- Bump ?v= whenever the icon art changes to bust browser/CDN caches. --}}
        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
