@extends('layouts.app')

@section('page', 'open')

@section('content')
    <div id="open-secret" class="stack" data-public-id="{{ $secret }}">
        <h1>Secret öffnen</h1>

        <noscript>
            <p class="notice notice-error">JavaScript ist erforderlich, da die Entschlüsselung ausschließlich in deinem Browser stattfindet.</p>
        </noscript>

        <section id="reveal-step" class="stack">
            <p class="notice">Dieses Secret kann nur begrenzt oft geöffnet werden. Durch Anzeigen kann ein verfügbarer Abruf verbraucht werden.</p>

            <div id="password-step" class="stack" hidden>
                <label for="open-password">Passwort</label>
                <input id="open-password" type="password" autocomplete="off">
                <p class="hint">Der Absender hat das Secret zusätzlich mit einem Passwort geschützt.</p>
            </div>

            <button id="reveal-button" type="button" class="button-primary" disabled>Secret anzeigen</button>
        </section>

        <p id="open-error" class="notice notice-error" role="alert" hidden></p>

        <section id="secret-result" class="stack" hidden>
            <label for="secret-output">Secret</label>
            <pre id="secret-output" class="secret-output" tabindex="0"></pre>
            <div class="row">
                <button id="copy-secret" type="button">Kopieren</button>
                <p id="copy-secret-status" class="hint" role="status"></p>
            </div>
            <p class="hint">Der Link wurde aus der Adresszeile entfernt. Ein Neuladen der Seite zeigt das Secret nicht erneut an.</p>
        </section>
    </div>
@endsection
