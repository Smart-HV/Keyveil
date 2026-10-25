# Theming and branding

Name, logo, colors and fonts can be changed without touching application logic.

| What | Where | Rebuild needed? |
|---|---|---|
| Name | `APP_NAME` in `.env` | no |
| Logo | `BRAND_LOGO` in `.env` + a file in `public/` | no |
| Favicon | `BRAND_FAVICON` in `.env` + a file in `public/` | no |
| Colors, radii, shadows | [`resources/css/theme.css`](../resources/css/theme.css) | yes (`npm run build`) |
| Fonts | [`resources/css/theme.css`](../resources/css/theme.css) | yes (`npm run build`) |

> The user interface text is currently German. Changing the language is not covered by theming.

## Name and logo (`.env`)

```dotenv
APP_NAME="Acme Secrets"
BRAND_LOGO=/branding/logo.svg
BRAND_FAVICON=/branding/favicon.svg
BRAND_SHOW_NAME=true
```

- Put the files in `public/branding/` (any folder below `public/` works).
- **Same-origin only:** the Content Security Policy blocks external images, so URLs such as `https://cdn.example.com/logo.svg` will not load.
- The logo is rendered 28 px high; SVG is recommended. Without `BRAND_LOGO`, the built-in mark is shown; its colors are controlled by `--brand-mark` and `--brand-mark-accent` in `theme.css`.
- If your logo already contains the wordmark, set `BRAND_SHOW_NAME=false` to hide the text next to it. Screen readers still get the name as the link label.
- After changing `.env` in production, run `php artisan config:cache`.

## Colors

All colors are CSS custom properties in `resources/css/theme.css`, each with a light and a dark variant (`prefers-color-scheme: dark`). `app.css` contains no brand colors of its own.

| Token | Role |
|---|---|
| `--paper` | page background |
| `--sheet` | cards, dialogs |
| `--field` | input fields |
| `--rule`, `--rule-strong` | borders and dividers |
| `--ink`, `--ink-muted`, `--ink-faint` | text; `--ink` is also the primary button color |
| `--on-ink` | text on the primary button |
| `--seal`, `--seal-tint` | accent: "encrypted", success, focus ring, seal animation |
| `--caution`, `--caution-tint` | notices about consuming views |
| `--danger`, `--danger-tint`, `--on-danger` | errors and deletion |
| `--brand-mark`, `--brand-mark-accent` | built-in logo mark |

Example of a violet brand:

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

Check contrast: text against `--paper` and `--sheet`, and `--on-ink` against `--ink`, should be at least 4.5:1.

The QR code intentionally stays black on white so it scans reliably.

## Fonts

Fonts must be self-hosted (CSP `font-src 'self'`); Google Fonts and similar services are blocked.

**Another Fontsource font:**

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

Remove the unused import (`@fontsource-variable/geist`) and uninstall the package.

**Your own font files:** put the `.woff2` files in `resources/fonts/` and declare them in `theme.css`; Vite bundles them same-origin:

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

**System fonts only:** remove the imports and set `--font-sans: system-ui, sans-serif;`.

`--font-mono` is only used for links and the decrypted secret.

## Build

```bash
npm run build
```

Then run `npm run e2e`: the end-to-end tests fail if a theme asset violates the CSP (for example an external font).
