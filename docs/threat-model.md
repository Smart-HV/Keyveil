# Threat model

Product promise: **the server stores only encrypted data and lifecycle metadata. The key and the plaintext stay in the browser.**

## Assets

| Asset | Where | Seen by the server? |
|---|---|---|
| Plaintext | browser memory | never |
| `linkKey` | URL fragment, browser memory | never (fragments are not transmitted) |
| Additional password / Argon2id output | browser memory | never |
| Delete token | management link fragment | only `SHA-256(token)` |
| Envelope (ciphertext) | PostgreSQL until expiry or last view | yes, opaque |
| Lifecycle (`expires_at`, `max_views`, `view_count`) | PostgreSQL | yes |
| Client IP | cache store (rate limit key, short-lived) | yes, not persisted |

## Protects against

| Threat | Mitigation | Evidence |
|---|---|---|
| Database or backup leak | no key in the database; AES-GCM; delete hash stored as a hash of a hash | `CreateSecretTest`, [crypto protocol](crypto-protocol-v1.md) |
| Honest-but-curious operator | zero-knowledge design, no server-side decryption | code review, `SensitiveLoggingTest` |
| Network attacker | HTTPS only, HSTS, HTTP → HTTPS redirect | `SecurityHeadersTest` |
| Secret ID enumeration | 128-bit IDs, uniform 404 responses, rate limits | `PublicIdTest`, `ClaimSecretTest` |
| Tampered ciphertext / IV / AAD / KDF parameters | AES-GCM authentication fails | `protocol-v1.test.ts` |
| Parallel claims | `SELECT … FOR UPDATE` inside a transaction | `tests/Concurrency` (PostgreSQL, forked processes) |
| Link preview bots / prefetching | `GET` only loads the UI; claims are `POST` after an explicit click | E2E "reloading before the claim consumes nothing" |
| XSS through secret content | rendered via `textContent` only; strict CSP without `unsafe-inline` | E2E, `SecurityHeadersTest` |
| Fragment leakage | the fragment never appears in any request URL, body or header | `fragment-leak.spec.ts` |
| Memory DoS via a manipulated envelope | client hard limits for Argon2id parameters and sizes | `protocol-v1.test.ts` |
| Clickjacking | `frame-ancestors 'none'`, `X-Frame-Options: DENY` | `SecurityHeadersTest` |
| CSRF | `PreventRequestForgery` (Sec-Fetch-Site + token), `SameSite=Strict` | Laravel default |

## Does not (fully) protect against

- **A compromised web server or manipulated JavaScript.** Whoever can change the delivered JavaScript can capture the plaintext or the key before encryption. Mitigations: minimal dependencies (only `hash-wasm` and `uqr` in the bundle), no third-party scripts, CSP, assets built in CI, branch protection, reviews (see [operations](operations.md)).
- **A compromised browser** of the sender or recipient (malware, extensions, clipboard snooping).
- **A stolen full link.** Without an additional password, the link is the proof of possession.
- **A weak additional password.** With the link and the ciphertext, offline guessing remains possible; Argon2id only makes it expensive.
- **Scanners that execute JavaScript** and click buttons can consume a view (the v1 goal covers ordinary link previews only).
- **Browser history** may still contain the link with its fragment, even though it is removed from the address bar after use.
- **Backups** may still contain physically deleted secrets (encrypted).

## Deliberate product decisions

These points are not changed without a new, documented decision:

1. The server never knows a content key; there is no key recovery and no admin decryption.
2. The URL fragment is the only transport for the link key.
3. Password protection works entirely client side, with no server-side password check.
4. A claim consumes a view **before** decryption. A wrong password after the claim can therefore use up a one-time link (the client allows retrying local decryption as long as the page stays open).
5. `GET` on the secret page never consumes a view.
6. No file uploads, no structured secret types, no service worker, no third-party JavaScript or analytics.
7. Physical deletion instead of soft deletes.
