@extends('layouts.app')

@section('page', 'create')

@section('content')
    <div class="intro">
        <h1>Teile ein Secret, das sich selbst zerstört.</h1>
        <p class="lead">Passwörter, Zugangsdaten oder Notizen werden in deinem Browser verschlüsselt, bevor sie ihn verlassen. Niemand außer dem Empfänger kann sie lesen – auch wir nicht.</p>
    </div>

    <noscript>
        <p class="notice notice-danger">JavaScript ist erforderlich, da die Verschlüsselung ausschließlich in deinem Browser stattfindet.</p>
    </noscript>

    {{-- Inputs intentionally have no "name" attribute: a form submitted without JavaScript never transmits the plaintext. --}}
    <form id="create-form" class="sheet composer" autocomplete="off" novalidate>
        <div class="composer-head">
            <label for="secret-text" class="field-label">Secret</label>
            <x-seal state="open" class="seal-small" />
        </div>

        <div class="stamp" aria-hidden="true">
            <x-seal id="create-seal" state="open" class="seal-stamp" />
            <p>Wird in deinem Browser versiegelt …</p>
        </div>

        <textarea id="secret-text" class="composer-text" rows="7" required spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="Passwort, Zugangsdaten oder Notiz einfügen …" aria-describedby="secret-size"></textarea>
        <p id="secret-size" class="composer-size">0 B von 64 KiB</p>

        <div class="composer-options">
            <div class="field">
                <label for="secret-ttl" class="field-label">Ablaufzeit</label>
                <div class="select-wrap">
                    <x-icon name="clock" />
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
            </div>

            <div class="field">
                <label for="secret-max-views" class="field-label">Maximale Abrufe</label>
                <div class="select-wrap">
                    <x-icon name="eye" />
                    <select id="secret-max-views">
                        @foreach (range(1, config('secrets.max_views')) as $views)
                            <option value="{{ $views }}" @selected($views === 1)>{{ $views === 1 ? '1× – Einmal-Link' : $views.'×' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="composer-password">
            <button id="password-toggle" type="button" class="button button-text" aria-expanded="false" aria-controls="password-field">
                <x-icon name="plus" class="when-collapsed" />
                <x-icon name="minus" class="when-expanded" />
                <span>Zusätzliches Passwort</span>
            </button>

            <div id="password-field" class="field" hidden>
                <label for="secret-password" class="visually-hidden">Zusätzliches Passwort (optional)</label>
                <input id="secret-password" type="password" autocomplete="new-password">
                <p class="field-hint">Der Empfänger braucht dann Link <em>und</em> Passwort. Schicke das Passwort über einen anderen Kanal.</p>
            </div>
        </div>

        <p id="create-error" class="notice notice-danger" role="alert" hidden></p>

        <div class="composer-actions">
            <p class="composer-note">Der Server erhält nur verschlüsselte Daten.</p>
            <button id="create-button" type="submit" class="button button-primary" disabled>
                <x-icon name="lock" />
                <span>Verschlüsseln &amp; Link erstellen</span>
            </button>
        </div>
    </form>

    <section id="create-result" class="sheet result" aria-labelledby="result-heading" hidden>
        <div class="result-head">
            <x-seal state="sealed" class="seal-large" />
            <div>
                <h2 id="result-heading">Link erstellt</h2>
                <p class="field-hint">Versiegelt und bereit zum Teilen.</p>
            </div>
        </div>

        <div class="field">
            <label for="share-link" class="field-label">Share-Link</label>
            <div class="copy-row">
                <input id="share-link" class="mono" type="text" readonly spellcheck="false">
                <button id="copy-share-link" type="button" class="button button-secondary copy-button">
                    <x-icon name="copy" class="when-idle" />
                    <x-icon name="check" class="when-copied" />
                    <span>Kopieren</span>
                </button>
            </div>
            <p id="copy-share-link-status" class="visually-hidden" role="status"></p>
        </div>

        <div class="result-details">
            <dl class="meta">
                <div>
                    <dt>Läuft ab</dt>
                    <dd id="result-expires"></dd>
                </div>
                <div>
                    <dt>Maximale Abrufe</dt>
                    <dd id="result-max-views"></dd>
                </div>
                <div>
                    <dt>Passwortschutz</dt>
                    <dd id="result-password"></dd>
                </div>
            </dl>

            <figure class="qr-figure">
                <div id="qr-code" class="qr"></div>
                <figcaption>Auf einem anderen Gerät scannen</figcaption>
            </figure>
        </div>

        <p class="notice notice-caution">
            <x-icon name="alert" />
            <span>Kopiere den Link jetzt. Er enthält den Schlüssel und kann später nicht wiederhergestellt werden.</span>
        </p>

        <details class="disclosure">
            <summary>Lösch-Link (nur für dich)</summary>
            <p class="field-hint">Damit kannst du das Secret vor Ablauf endgültig löschen. Nicht weitergeben.</p>
            <label for="manage-link" class="visually-hidden">Lösch-Link</label>
            <div class="copy-row">
                <input id="manage-link" class="mono" type="text" readonly spellcheck="false">
                <button id="copy-manage-link" type="button" class="button button-secondary copy-button">
                    <x-icon name="copy" class="when-idle" />
                    <x-icon name="check" class="when-copied" />
                    <span>Kopieren</span>
                </button>
            </div>
            <p id="copy-manage-link-status" class="visually-hidden" role="status"></p>
        </details>

        <a href="{{ route('home') }}" class="button button-text">
            <x-icon name="plus" />
            <span>Neues Secret erstellen</span>
        </a>
    </section>

    <section class="steps" aria-labelledby="steps-heading">
        <h2 id="steps-heading">So funktioniert's</h2>
        <ol>
            <li>
                <strong>Im Browser verschlüsseln</strong>
                <span>Dein Text wird mit AES-256 verschlüsselt. Der Schlüssel entsteht nur auf deinem Gerät.</span>
            </li>
            <li>
                <strong>Link teilen</strong>
                <span>Der Schlüssel steckt im Teil des Links nach dem #, den Browser nie an Server senden.</span>
            </li>
            <li>
                <strong>Öffnen, dann gelöscht</strong>
                <span>Nach dem letzten erlaubten Abruf oder nach Ablauf wird das Secret endgültig gelöscht.</span>
            </li>
        </ol>
    </section>
@endsection
