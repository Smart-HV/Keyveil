# Threat Model

Produktversprechen: **Laravel speichert nur verschlüsselte Daten und Lifecycle-Metadaten. Schlüssel und Klartext bleiben im Browser.**

## Assets

| Asset | Wo | Server sieht? |
|---|---|---|
| Klartext | Browser-Speicher | nie |
| `linkKey` | URL-Fragment, Browser-Speicher | nie (Fragmente werden nicht übertragen) |
| Zusatzpasswort / Argon2id-Output | Browser-Speicher | nie |
| Delete-Token | Management-Fragment | nur `SHA-256(token)` |
| Envelope (Ciphertext) | PostgreSQL bis Ablauf/letzter View | ja, opaque |
| Lifecycle (`expires_at`, `max_views`, `view_count`) | PostgreSQL | ja |
| Client-IP | Redis (Rate-Limit-Key, kurzlebig) | ja, nicht persistiert |

## Schützt gegen

| Bedrohung | Maßnahme | Nachweis |
|---|---|---|
| Datenbank-/Backup-Leak | Kein Key in DB; AES-GCM; Delete-Hash doppelt gehasht | `CreateSecretTest`, `docs/crypto-protocol-v1.md` |
| Neugieriger Serverbetrieb | Zero-Knowledge-Design, keine Server-Entschlüsselung | Code-Review, `SensitiveLoggingTest` |
| Netzwerkangreifer | HTTPS-only, HSTS, HTTP→HTTPS-Redirect | `SecurityHeadersTest` |
| Secret-ID-Enumeration | 128-Bit-IDs, einheitliche 404, Rate Limits | `PublicIdTest`, `ClaimSecretTest` |
| Manipulierter Ciphertext/IV/AAD/KDF-Parameter | GCM-Authentisierung schlägt fehl | `protocol-v1.test.ts` |
| Parallele Claims | `SELECT … FOR UPDATE` in Transaktion | `tests/Concurrency` (PostgreSQL, fork) |
| Link-Preview-Bots / Prefetch | GET lädt nur UI; Claim nur per POST nach Klick | E2E „reloading before the claim…“ |
| XSS über Secret-Inhalt | nur `textContent`, strikte CSP ohne `unsafe-inline` | E2E, `SecurityHeadersTest` |
| Fragment-Leak | Fragment nie in URL/Body/Header | `fragment-leak.spec.ts` |
| Memory-DoS durch manipuliertes Envelope | Client-Hard-Limits für Argon2id / Größen | `protocol-v1.test.ts` |
| Clickjacking | `frame-ancestors 'none'`, `X-Frame-Options: DENY` | `SecurityHeadersTest` |
| CSRF | `PreventRequestForgery` (Sec-Fetch-Site + Token), SameSite=strict | Laravel-Standard |

## Schützt nicht (vollständig) gegen

- **Kompromittierter Webserver / manipuliertes JavaScript.** Wer das ausgelieferte JS ändern kann, kann Klartext oder Key vor der Verschlüsselung abgreifen. Mitigation: minimale Dependencies (nur `hash-wasm`, `uqr` im Bundle), keine Third-Party-Skripte, CSP, CI-gebaute Assets, Branch Protection, Reviews (siehe `operations.md`).
- **Kompromittierter Browser des Senders/Empfängers** (Malware, Extensions, Clipboard-Mitleser).
- **Gestohlener vollständiger Link.** Ohne Zusatzpasswort ist der Link der Besitznachweis.
- **Schwaches Zusatzpasswort.** Mit Link + Ciphertext bleibt Offline-Guessing möglich; Argon2id verteuert es nur.
- **JS-fähige Scanner**, die Buttons klicken, können einen View verbrauchen (v1-Ziel: nur gewöhnliche Link-Previews).
- **Browser-History** kann den Link mit Fragment enthalten, obwohl er nach Nutzung aus der Adresszeile entfernt wird.
- **Backups** können physisch gelöschte Secrets (verschlüsselt) noch enthalten.

## Bewusste Produktentscheidungen

Siehe plan.md §66. Insbesondere: Claim verbraucht den View **vor** der Entschlüsselung; ein falsches Passwort nach dem Claim kann daher einen Einmal-Link verbrauchen (der Client erlaubt erneutes lokales Entschlüsseln, solange die Seite offen ist).
