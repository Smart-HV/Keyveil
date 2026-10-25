@extends('layouts.app')

@section('page', 'open')

@section('content')
    <div id="open-secret" class="sheet envelope" data-public-id="{{ $secret }}">
        <x-seal id="open-seal" state="sealed" class="seal-large" />

        <div class="envelope-head">
            <h1 id="open-heading">Jemand hat dir ein Secret geschickt.</h1>
            <p id="open-lead" class="lead">Es wurde vor dem Versand verschlüsselt und wird erst hier, in deinem Browser, entschlüsselt.</p>
        </div>

        <noscript>
            <p class="notice notice-danger">JavaScript ist erforderlich, da die Entschlüsselung ausschließlich in deinem Browser stattfindet.</p>
        </noscript>

        <section id="reveal-step" class="stack">
            <p class="notice notice-caution">
                <x-icon name="alert" />
                <span>Dieses Secret kann nur begrenzt oft geöffnet werden. Durch Anzeigen kann ein verfügbarer Abruf verbraucht werden.</span>
            </p>

            <div id="password-step" class="field" hidden>
                <label for="open-password" class="field-label">Passwort</label>
                <input id="open-password" type="password" autocomplete="off">
                <p class="field-hint">Der Absender hat das Secret zusätzlich mit einem Passwort geschützt.</p>
            </div>

            <button id="reveal-button" type="button" class="button button-primary button-block" disabled>
                <x-icon name="eye" />
                <span>Secret anzeigen</span>
            </button>
        </section>

        <p id="open-error" class="notice notice-danger" role="alert" hidden></p>

        <a id="open-unavailable-cta" href="{{ route('home') }}" class="button button-secondary" hidden>
            <x-icon name="plus" />
            <span>Eigenes Secret erstellen</span>
        </a>

        <section id="secret-result" class="stack" hidden>
            <div class="secret-head">
                <label for="secret-output" class="field-label">Secret</label>
                <button id="copy-secret" type="button" class="button button-secondary copy-button">
                    <x-icon name="copy" class="when-idle" />
                    <x-icon name="check" class="when-copied" />
                    <span>Kopieren</span>
                </button>
            </div>
            <pre id="secret-output" class="secret-output" tabindex="0"></pre>
            <p id="copy-secret-status" class="visually-hidden" role="status"></p>
            <p class="field-hint">Der Abruf wurde verbraucht und der Link aus der Adresszeile entfernt. Neu laden zeigt das Secret nicht erneut an.</p>
        </section>
    </div>
@endsection
