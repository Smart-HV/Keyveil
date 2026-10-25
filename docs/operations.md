# Betrieb

## Infrastruktur

- PHP 8.4+ mit `pdo_pgsql`, `sodium` (nur Tests), `pcntl`/`posix` (nur Concurrency-Tests)
- PostgreSQL (Source of Truth für View-Verbrauch), Session-Zeitzone wird von Laravel auf UTC gesetzt
- Redis für Cache-Store: Rate Limits und Scheduler-Lock (`CACHE_STORE=redis`)
- Scheduler: `* * * * * php artisan schedule:run` → `model:prune` für abgelaufene Secrets (`onOneServer`)
- Keine Queue nötig

PostgreSQL und Redis dürfen nicht öffentlich erreichbar sein. DB-User mit Least Privilege (nur DML auf `secrets`, `cache*`, `migrations` lesend; Migrationen mit separatem User).

## Produktions-.env (Auszug)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://secrets.example.com
LOG_LEVEL=warning
DB_CONNECTION=pgsql
CACHE_STORE=redis
SESSION_DRIVER=cookie
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
TRUSTED_PROXIES=10.0.0.0/8
```

Grenzen (`SECRET_*`) siehe `.env.example` und `config/secrets.php`. Crypto-Parameter sind **nicht** per ENV konfigurierbar.

## Reverse Proxy (nginx-Beispiel)

```nginx
# Pfad ohne Query loggen; Fragment wird vom Browser ohnehin nie gesendet.
log_format keyveil '$remote_addr [$time_local] "$request_method $uri" $status $body_bytes_sent';
access_log /var/log/nginx/keyveil.log keyveil;

server {
    listen 80;
    server_name secrets.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name secrets.example.com;

    # Kompatibel mit SECRET_MAX_REQUEST_BYTES (132 KiB).
    client_max_body_size 132k;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location /build/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Content-Type-Options nosniff;
    }

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

- `/api/v1/secrets*` und `/s/*`, `/manage/*` niemals per CDN/Edge cachen (Laravel sendet `Cache-Control: no-store`).
- Keine APM-Body-Capture, keine Session-Replay- oder Marketing-Skripte.
- PHP: `post_max_size` ≥ `client_max_body_size`, `display_errors=Off`, `zend.exception_ignore_args=On`.
- HSTS `preload` erst nach bewusster Prüfung des Domain-/Subdomain-Konzepts.
- Log-Retention kurz halten (z. B. 7 Tage), keine Referrer loggen.

## Backups

- Backups enthalten Ciphertext und Lifecycle-Metadaten, aber keine Schlüssel. Trotzdem verschlüsseln, Zugriff minimieren, Retention definieren (z. B. ≤ 7 Tage = max. TTL).
- Physisch gelöschte Secrets können in historischen Backups noch existieren. Produktkommunikation darf nichts anderes behaupten.

## Build & Deployment (Malicious-Frontend-Mitigation)

- Assets in CI bauen (`npm ci && npm run build`), Deployment nutzt das CI-Artefakt; kein Build auf dem Production-Host.
- `package-lock.json` / `composer.lock` committet; Runtime-JS-Abhängigkeiten exakt gepinnt: `hash-wasm@4.12.0`, `uqr@0.1.3` (beide MIT, 0 Dependencies). Schriften: `@fontsource-variable/geist` und `geist-mono` `@5.3.0` (OFL, nur CSS + woff2, kein JS), same-origin über Vite gebündelt.
- `.npmrc`: `ignore-scripts=true` (keine postinstall-Skripte).
- Keine öffentlichen Source Maps (`build.sourcemap: false`).
- Branch Protection, Reviews für alles unter `resources/js/crypto/`, getrennte CI/CD-Rechte, Dependabot-PRs nur mit Review.

## Audit-Stand

| Datum | `npm audit` | `composer audit` |
|---|---|---|
| 2026-10-25 | 0 vulnerabilities | keine Advisories |

## Offene Release-TODOs

- [ ] **Argon2id-Benchmark** (plan.md §8.3) auf Desktop Chrome/Firefox/Safari, durchschnittlichem Android, aktuellem iPhone. Ziel: spürbar teuer, aber akzeptabel. Parameter ggf. in `protocol-v1.ts` (`ARGON2ID_DEFAULTS`) anpassen — alte Secrets bleiben entschlüsselbar, da Parameter im Envelope stehen.
- [ ] Rate-Limit-Load-Test und Tuning.
- [ ] Manueller Security-Review (plan.md Phase 11).
- [ ] Release-Checkliste plan.md §72 abarbeiten.
