<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="no-referrer">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body data-page="@yield('page')">
        <header class="site-header">
            <a href="{{ route('home') }}" class="brand">{{ config('app.name') }}</a>
        </header>

        <main class="container">
            @yield('content')
        </main>

        <footer class="site-footer">
            <p>Ende-zu-Ende verschlüsselt im Browser. Der Server sieht weder Klartext noch Schlüssel.</p>
        </footer>
    </body>
</html>
