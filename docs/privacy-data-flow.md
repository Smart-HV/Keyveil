# Privacy and data flow

## Creating a secret

```
Browser                                       Server (Laravel)             PostgreSQL
───────                                       ────────────────             ──────────
plaintext, password ─┐
linkKey, deleteToken (CSPRNG)
encrypt → envelope ──┼─ POST /api/v1/secrets ──▶ validation ───────────▶ envelope, expires_at,
SHA-256(deleteToken) ┘   {envelope, ttl,        expires_at = now + ttl    max_views, view_count = 0,
                          max_views, hash}       public_id (CSPRNG)        sha256(hash)
share / manage link ◀── {public_id, expires_at, max_views}
(built locally, the fragment is never sent)
```

## Opening a secret

```
GET /s/{id}          → HTML/JS only, no database access, no view consumed
click "Secret anzeigen"
POST …/{id}/claim    → row lock, view_count + 1, or DELETE on the last view → envelope
browser: decrypts locally (fragment + optional password) → textContent
```

## Deleting a secret

```
DELETE /api/v1/secrets/{id} {delete_token_hash} → hash_equals → physical DELETE
```

## Stored data

`secrets`: `id`, `public_id`, `crypto_version`, `envelope`, `password_protected`, `expires_at`, `max_views`, `view_count`, `delete_token_hash` (hash of the client hash), `created_at`, `updated_at`.

**Not** stored: IP address, user agent, referrer, location, recipient information, plaintext, keys, passwords.

## Sessions and cookies

`SESSION_DRIVER=cookie`: the (encrypted) session holds only the CSRF token. There is no server-side session table with IP addresses or user agents. Cookies are `HttpOnly`, `SameSite=Strict` and, in production, `Secure`.

## Rate limiting

Keys consist of the limiter name and the client IP, stored in the cache (Redis in production) and expiring with the time window. Keys never contain secret IDs or payload data.

## Logs

Laravel does not log request bodies. Exceptions for unavailable secrets are not reported. Tests ensure that ciphertext, IVs and delete hashes appear neither in logs nor in 500 responses (`SensitiveLoggingTest`). There is no analytics, no error tracking and no session replay.

## Deletion

- The last allowed view, expiry (lazily on claim and via `model:prune` every minute) and manual deletion physically remove the row (no soft deletes).
- Historical database backups may still contain encrypted secrets (see [operations](operations.md)).
