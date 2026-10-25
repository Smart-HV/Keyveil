<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="color-scheme" content="light dark">
        <title>@yield('code') – {{ config('app.name') }}</title>
        <link rel="icon" href="{{ config('branding.favicon') }}">
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <header class="site-header">
            <x-brand />
        </header>
        <main class="page">
            <div class="sheet envelope">
                @php
                    $code = (int) trim($__env->yieldContent('code'));
                    $message = match (true) {
                        $code === 404 => 'Diese Seite gibt es nicht.',
                        $code === 419 => 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu.',
                        $code === 429 => 'Zu viele Anfragen. Bitte warte einen Moment.',
                        $code >= 500 => 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.',
                        default => 'Diese Seite ist nicht verfügbar.',
                    };
                @endphp
                <p class="error-code">Fehler {{ $code }}</p>
                <h1>{{ $message }}</h1>
                <a href="{{ route('home') }}" class="button button-secondary">Zur Startseite</a>
            </div>
        </main>
    </body>
</html>
