# Theming & Branding

Name, Logo, Farben und Schriften lassen sich ohne Eingriff in die App-Logik anpassen.

| Was | Wo | Rebuild nötig? |
|---|---|---|
| Name | `APP_NAME` in `.env` | nein |
| Logo | `BRAND_LOGO` in `.env` + Datei in `public/` | nein |
| Favicon | `BRAND_FAVICON` in `.env` + Datei in `public/` | nein |
| Farben, Radien, Schatten | `resources/css/theme.css` | ja (`npm run build`) |
| Schriften | `resources/css/theme.css` | ja (`npm run build`) |

## Name und Logo (`.env`)

```dotenv
APP_NAME="Acme Secrets"
BRAND_LOGO=/branding/logo.svg
BRAND_FAVICON=/branding/favicon.svg
BRAND_SHOW_NAME=true
```

- Dateien nach `public/branding/` legen (der Ordner ist frei wählbar, muss aber unter `public/` liegen).
- **Nur same-origin:** Die Content Security Policy erlaubt keine externen Bilder. URLs wie `https://cdn.example.com/logo.svg` werden blockiert.
- Das Logo wird 28 px hoch dargestellt (SVG empfohlen). Ohne `BRAND_LOGO` erscheint das eingebaute Zeichen, dessen Farben über `--brand-mark` und `--brand-mark-accent` in `theme.css` steuerbar sind.
- Enthält das Logo bereits den Schriftzug, blendet `BRAND_SHOW_NAME=false` den Text daneben aus. Für Screenreader bleibt der Name als Link-Beschriftung erhalten.
- Nach Änderungen an `.env` in Produktion: `php artisan config:cache`.

## Farben

Alle Farben stehen als CSS-Variablen in `resources/css/theme.css`, jeweils für hell und dunkel (`prefers-color-scheme: dark`). `app.css` enthält keine eigenen Markenfarben.

| Token | Rolle |
|---|---|
| `--paper` | Seitenhintergrund |
| `--sheet` | Karten, Dialoge |
| `--field` | Eingabefelder |
| `--rule`, `--rule-strong` | Rahmen und Linien |
| `--ink`, `--ink-muted`, `--ink-faint` | Text; `--ink` ist auch die Farbe des Primärbuttons |
| `--on-ink` | Text auf dem Primärbutton |
| `--seal`, `--seal-tint` | Akzentfarbe: „verschlüsselt“, Erfolg, Fokusring, Siegel-Animation |
| `--caution`, `--caution-tint` | Hinweise zum Verbrauch von Abrufen |
| `--danger`, `--danger-tint`, `--on-danger` | Fehler und Löschen |
| `--brand-mark`, `--brand-mark-accent` | eingebautes Logo-Zeichen |

Beispiel für eine violette Marke:

```css
:root {
    --ink: #2e1065;
    --seal: #7c3aed;
    --seal-tint: #ede9fe;
}

@media (prefers-color-scheme: dark) {
    :root {
        --ink: #ede9fe;
        --seal: #a78bfa;
        --seal-tint: #2e1a5c;
    }
}
```

Kontrast prüfen: Text gegen `--paper`/`--sheet` und `--on-ink` gegen `--ink` mindestens 4.5:1.

Der QR-Code bleibt bewusst schwarz auf weiß, damit er zuverlässig scannbar ist.

## Schriften

Schriften müssen selbst gehostet sein (CSP `font-src 'self'`); Google Fonts o. Ä. werden blockiert.

**Andere Fontsource-Schrift:**

```bash
npm i -E @fontsource-variable/inter
```

```css
/* resources/css/theme.css */
@import '@fontsource-variable/inter';

:root {
    --font-sans: 'Inter Variable', system-ui, sans-serif;
}
```

Den nicht mehr genutzten Import (`@fontsource-variable/geist`) entfernen und das Paket deinstallieren.

**Eigene Schriftdateien:** `.woff2` nach `resources/fonts/` legen und in `theme.css` einbinden — Vite bündelt sie same-origin:

```css
@font-face {
    font-family: 'Brand Sans';
    src: url('../fonts/brand-sans.woff2') format('woff2');
    font-weight: 100 900;
    font-display: swap;
}

:root {
    --font-sans: 'Brand Sans', system-ui, sans-serif;
}
```

**Nur Systemschriften:** Imports entfernen und `--font-sans: system-ui, sans-serif;` setzen.

`--font-mono` wird nur für Links und den entschlüsselten Inhalt verwendet.

## Bauen

```bash
npm run build
```

Danach mit `npm run e2e` prüfen: Der E2E-Test schlägt fehl, falls ein Theme-Asset die CSP verletzt (z. B. ein externer Font).
