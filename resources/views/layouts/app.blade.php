<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="no-referrer">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        <title>@yield('title', config('app.name'))</title>

        <link rel="icon" href="{{ config('branding.favicon') }}">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body data-page="@yield('page')">
        <header class="site-header">
            <x-brand />
            <p class="trust-pill"><span class="trust-dot" aria-hidden="true"></span>Lokal verschlüsselt</p>
        </header>

        <main class="page @yield('page-class')">
            @yield('content')
        </main>

        <footer class="site-footer">
            <p>Ende-zu-Ende verschlüsselt in deinem Browser. Der Server speichert nur Unlesbares und löscht es nach Ablauf.</p>
        </footer>
    </body>
</html>
