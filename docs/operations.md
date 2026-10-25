# Operations

## Infrastructure

- PHP 8.3+ with `pdo_pgsql`; `sodium` (tests only) and `pcntl`/`posix` (concurrency tests only)
- PostgreSQL — the source of truth for view consumption. `config/database.php` forces the session time zone to UTC so `TIMESTAMPTZ` values are correct.
- Redis as cache store for rate limits and the scheduler lock (`CACHE_STORE=redis`)
- Scheduler: `* * * * * php artisan schedule:run` → `model:prune` removes expired secrets (`onOneServer`)
- No queue worker required

PostgreSQL and Redis must not be reachable from the public network. Use a least-privilege database user for the app (DML on `secrets` and the cache tables only) and a separate user for migrations.

## Production `.env` (excerpt)

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

For limits (`SECRET_*`) see [`.env.example`](../.env.example) and [`config/secrets.php`](../config/secrets.php). Crypto parameters are **not** configurable through the environment.

## Reverse proxy (nginx example)

```nginx
# Log the path without the query string; browsers never send the fragment anyway.
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

    # Matches SECRET_MAX_REQUEST_BYTES (132 KiB).
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

- Never cache `/api/v1/secrets*`, `/s/*` or `/manage/*` in a CDN or edge cache (Laravel sends `Cache-Control: no-store`).
- No APM body capture, no session replay or marketing scripts.
- PHP: `post_max_size` ≥ `client_max_body_size`, `display_errors=Off`, `zend.exception_ignore_args=On`.
- Only add HSTS `preload` after deliberately reviewing your domain and subdomain setup.
- Keep log retention short (e.g. 7 days) and do not log referrers.

## Backups

- Backups contain ciphertext and lifecycle metadata, but no keys. Encrypt them anyway, restrict access and define a retention period (e.g. ≤ 7 days, the maximum TTL).
- Physically deleted secrets can still exist in older backups. Do not claim otherwise to your users.

## Build and deployment (malicious frontend mitigation)

- Build assets in CI (`npm ci && npm run build`) and deploy that artifact; never build on the production host.
- `package-lock.json` and `composer.lock` are committed. Runtime JavaScript dependencies are pinned exactly: `hash-wasm@4.12.0` and `uqr@0.1.3` (both MIT, zero dependencies). Fonts: `@fontsource-variable/geist` and `geist-mono` `@5.3.0` (OFL, CSS and woff2 only, no JavaScript), bundled same-origin by Vite.
- `.npmrc` sets `ignore-scripts=true` (no install scripts).
- No public source maps (`build.sourcemap: false`).
- Branch protection, mandatory review for everything under `resources/js/crypto/`, separate CI/CD permissions, Dependabot pull requests merged only after review.
- Run `npm audit` and `composer audit` for every release.

## Before your first production deployment

- [ ] **Benchmark Argon2id** on current desktop Chrome, Firefox and Safari, an average Android phone and a current iPhone. Goal: noticeably expensive for attackers, still acceptable for users. Adjust `ARGON2ID_DEFAULTS` in `protocol-v1.ts` if needed — existing secrets stay decryptable because the parameters are stored in the envelope.
- [ ] Load-test and tune the rate limits.
- [ ] Review your deployment against the [threat model](threat-model.md).

## Release checklist

- [ ] `APP_DEBUG=false` and a strong `APP_KEY`
- [ ] Cookies `Secure`, `HttpOnly`, `SameSite=Strict`
- [ ] HTTPS redirect and HSTS active
- [ ] No debug bar or Telescope on secret routes
- [ ] `TRUSTED_PROXIES` limited to your actual proxies
- [ ] Least-privilege database user; PostgreSQL and Redis not publicly reachable
- [ ] Backups encrypted, retention defined
- [ ] CI/CD secrets protected
- [ ] `npm audit` and `composer audit` reviewed
- [ ] CSP and security headers verified in the browser; no external requests on secret pages
- [ ] No public source maps
- [ ] Logs checked for redaction
- [ ] Rate limits load-tested
- [ ] Scheduler (`schedule:run`) running
