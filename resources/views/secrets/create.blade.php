@extends('layouts.app')

@section('page', 'create')

@section('content')
    <h1>Secret sicher teilen</h1>

    <noscript>
        <p class="notice notice-error">JavaScript ist erforderlich, da die Verschlüsselung ausschließlich in deinem Browser stattfindet.</p>
    </noscript>

    {{-- Inputs intentionally have no "name" attribute: a form submitted without JavaScript never transmits the plaintext. --}}
    <form id="create-form" class="stack" autocomplete="off" novalidate>
        <label for="secret-text">Secret</label>
        <textarea id="secret-text" rows="8" required spellcheck="false" autocapitalize="off" autocorrect="off"></textarea>
        <p class="hint">Maximal 64 KiB Text.</p>

        <div class="grid">
            <div class="stack">
                <label for="secret-ttl">Ablaufzeit</label>
                <select id="secret-ttl">
                    <option value="300">5 Minuten</option>
                    <option value="900">15 Minuten</option>
                    <option value="3600" selected>1 Stunde</option>
                    <option value="21600">6 Stunden</option>
                    <option value="86400">24 Stunden</option>
                    <option value="259200">3 Tage</option>
                    <option value="604800">7 Tage</option>
                </select>
            </div>

            <div class="stack">
                <label for="secret-max-views">Maximale Abrufe</label>
                <input id="secret-max-views" type="number" min="1" max="{{ config('secrets.max_views') }}" value="1" required>
                <p class="hint"><strong>1 = Einmal-Link</strong> (empfohlen)</p>
            </div>
        </div>

        <label for="secret-password">Zusätzliches Passwort (optional)</label>
        <input id="secret-password" type="password" autocomplete="new-password">
        <p class="hint">Bei Passwortschutz benötigt der Empfänger den Link <em>und</em> das Passwort. Übermittle das Passwort über einen anderen Kanal.</p>

        <p id="create-error" class="notice notice-error" role="alert" hidden></p>

        <button id="create-button" type="submit" class="button-primary" disabled>Verschlüsseln &amp; Link erstellen</button>
    </form>

    <ul class="hints">
        <li>Die Verschlüsselung findet lokal in deinem Browser statt.</li>
        <li>Der Server erhält keinen Klartext und keinen Schlüssel.</li>
        <li>Der Link selbst ist sensibel: Wer ihn besitzt, kann das Secret öffnen.</li>
    </ul>

    <section id="create-result" class="stack" hidden>
        <h2>Link erstellt</h2>

        <label for="share-link">Share-Link</label>
        <div class="row">
            <input id="share-link" type="text" readonly spellcheck="false">
            <button id="copy-share-link" type="button">Kopieren</button>
        </div>
        <p id="copy-share-link-status" class="hint" role="status"></p>

        <div id="qr-code" class="qr" aria-label="QR-Code des Share-Links"></div>

        <dl class="meta">
            <dt>Läuft ab</dt>
            <dd id="result-expires"></dd>
            <dt>Maximale Abrufe</dt>
            <dd id="result-max-views"></dd>
            <dt>Passwortschutz</dt>
            <dd id="result-password"></dd>
        </dl>

        <label for="manage-link">Lösch-Link (nur für dich)</label>
        <div class="row">
            <input id="manage-link" type="text" readonly spellcheck="false">
            <button id="copy-manage-link" type="button">Kopieren</button>
        </div>
        <p id="copy-manage-link-status" class="hint" role="status"></p>

        <p class="notice">Bewahre die Links jetzt auf. Ein verlorener Link oder Schlüssel ist nicht wiederherstellbar.</p>

        <a href="{{ route('home') }}" class="button">Neues Secret</a>
    </section>
@endsection
