# Privacy & Datenfluss

## Erstellen

```
Browser                                     Server (Laravel)            PostgreSQL
───────                                     ────────────────            ──────────
Klartext, Passwort ─┐
linkKey, deleteToken (CSPRNG)
encrypt → Envelope ─┼─ POST /api/v1/secrets ──▶ Validierung ──────────▶ envelope, expires_at,
SHA-256(deleteToken)┘   {envelope, ttl,        expires_at = now+ttl      max_views, view_count=0,
                         max_views, hash}       public_id (CSPRNG)        sha256(hash)
Share-/Manage-Link ◀── {public_id, expires_at, max_views}
(lokal gebaut, Fragment nie gesendet)
```

## Öffnen

```
GET /s/{id}            → nur HTML/JS, kein DB-Zugriff, kein View-Verbrauch
Klick "Secret anzeigen"
POST …/{id}/claim      → Row-Lock, view_count+1 bzw. DELETE beim letzten View → Envelope
Browser: decrypt lokal (Fragment + optional Passwort) → textContent
```

## Löschen

```
DELETE /api/v1/secrets/{id} {delete_token_hash} → hash_equals → physisches DELETE
```

## Gespeicherte Daten

`secrets`: `id`, `public_id`, `crypto_version`, `envelope`, `password_protected`, `expires_at`, `max_views`, `view_count`, `delete_token_hash` (Hash vom Hash), `created_at`, `updated_at`.

**Nicht** gespeichert: IP, User-Agent, Referrer, Geo-Daten, Empfängerinformationen, Klartext, Schlüssel, Passwort.

## Sessions & Cookies

`SESSION_DRIVER=cookie`: Die (verschlüsselte) Session enthält nur das CSRF-Token. Es gibt keine serverseitige Session-Tabelle mit IP/User-Agent. Cookies: `HttpOnly`, `SameSite=Strict`, in Produktion `Secure`.

## Rate Limiting

Schlüssel: Limiter-Name + Client-IP, im Cache-Store (Produktion Redis) mit Ablauf nach dem Zeitfenster. Keine Secret-IDs oder Payload-Daten in Keys.

## Logs

Laravel loggt keine Request-Bodies. Exceptions für unverfügbare Secrets werden nicht gemeldet. Tests stellen sicher, dass Ciphertext, IV und Delete-Hash weder im Log noch in 500-Antworten erscheinen (`SensitiveLoggingTest`). Kein Analytics, kein Error-Tracking, keine Session-Replay-Tools.

## Löschung

- Letzter View, Ablauf (lazy beim Claim + `model:prune` jede Minute) und manuelles Löschen entfernen die Zeile physisch (keine Soft Deletes).
- Historische DB-Backups können verschlüsselte Secrets weiter enthalten (siehe `operations.md`).
