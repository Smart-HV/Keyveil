# Crypto-Protokoll v1 (normativ)

Status: v1, implementiert in `resources/js/crypto/protocol-v1.ts`.
Test-Vektoren: `tests/fixtures/crypto-v1-vectors.json` (erzeugt mit PHP openssl/hash_hkdf/libsodium, verifiziert durch TypeScript/WebCrypto/hash-wasm und durch `tests/Unit/CryptoVectorsTest.php`).

Implementierung und Dokument müssen übereinstimmen. Jede Abweichung erfordert eine neue Protokollversion.

## 1. Primitive

| Zweck | Primitive | Parameter |
|---|---|---|
| Content Encryption | AES-256-GCM (WebCrypto) | 96-Bit-IV, 128-Bit-Tag |
| Key Derivation | HKDF-SHA-256 (WebCrypto) | Output 256 Bit |
| Password KDF | Argon2id (hash-wasm 4.12.0) | Output 32 Bytes |
| Delete-Token-Hash | SHA-256 (WebCrypto) | – |
| Zufall | `crypto.getRandomValues()` | – |
| Text | UTF-8 (`TextEncoder`, Decoding mit `fatal: true`) | – |
| Binär → Text | Base64url ohne Padding (RFC 4648 §5), strikt kanonisch | – |

## 2. Zufallswerte pro Secret

| Wert | Bytes | Ort |
|---|---|---|
| `linkKey` | 32 | nur URL-Fragment |
| AES-GCM-IV | 12 | Envelope `iv` |
| HKDF-Salt | 32 | Envelope `hkdf_salt` |
| Argon2id-Salt | 16 | Envelope `password.salt` (nur Passwortmodus) |
| `deleteToken` | 32 | nur Management-Fragment |
| `public_id` | 16 | serverseitig (PHP `random_bytes`) |

## 3. Schlüsselableitung

Konstanten (UTF-8):

- `AAD = "password-transfer:v1"`
- `INFO_LINK = "password-transfer:v1:content"`
- `INFO_PASSWORD = "password-transfer:v1:content+password"`

**Link-only:**

```
aesKey = HKDF-SHA-256(ikm = linkKey, salt = hkdf_salt, info = INFO_LINK, L = 32)
```

**Mit Passwort:**

```
pw          = UTF-8(NFC(password))
passwordKey = Argon2id(pw, salt = password.salt, m = memory_kib, t = iterations, p = parallelism, L = 32)
aesKey      = HKDF-SHA-256(ikm = linkKey, salt = passwordKey, info = INFO_PASSWORD, L = 32)
```

Im Passwortmodus ist `passwordKey` das HKDF-Salt (plan.md §8.3). Das Envelope-Feld `hkdf_salt` ist trotzdem Pflicht (einheitliches Schema), wird in diesem Modus aber **nicht** verwendet.

Passwörter werden vor dem Encoding NFC-normalisiert, damit identisch aussehende Eingaben auf unterschiedlichen Plattformen denselben Schlüssel ergeben.

Argon2id-Zielparameter v1: `memory_kib = 65536`, `iterations = 3`, `parallelism = 1`. Die Parameter stehen im Envelope; ändern sich die Defaults, bleiben alte Secrets entschlüsselbar.

## 4. Verschlüsselung

```
ciphertext = AES-256-GCM-Encrypt(aesKey, iv, plaintext = UTF-8(text), aad = AAD, tagLength = 128)
           = encrypted_bytes || tag(16)
```

- Klartext: 1 bis 65 536 Bytes UTF-8.
- Pro Secret neuer `linkKey` und neuer zufälliger IV.

## 5. Envelope

JSON-Objekt, exakt diese Felder (unbekannte Felder werden von Client **und** Server abgelehnt):

```json
{
  "v": 1,
  "alg": "A256GCM",
  "iv": "<base64url, 12 Bytes>",
  "hkdf_salt": "<base64url, 32 Bytes>",
  "password": { "enabled": false },
  "ciphertext": "<base64url, 17..65552 Bytes>"
}
```

Passwortmodus:

```json
"password": {
  "enabled": true,
  "kdf": "argon2id",
  "salt": "<base64url, 16 Bytes>",
  "memory_kib": 65536,
  "iterations": 3,
  "parallelism": 1
}
```

Der Server speichert das Envelope als JSONB (Feldreihenfolge nicht signifikant). AAD ist bewusst nur die Konstante `AAD`, keine Envelope-Kanonisierung.

### Client-Hard-Limits beim Lesen (DoS-Schutz, keine Zielwerte)

- `v === 1`, `alg === "A256GCM"`
- `iv` exakt 12 Bytes, `hkdf_salt` exakt 32 Bytes, `password.salt` exakt 16 Bytes
- Ciphertext > 16 und ≤ 65 552 Bytes
- `memory_kib` ≤ 262 144 (256 MiB) und ≥ 8·p, `iterations` 1..10, `parallelism` 1..4, jeweils Integer

### Serverseitige Strukturprüfung (`StoreSecretRequest`)

Gleiche Formprüfung plus: `memory_kib` 19 456..262 144, `iterations` 2..10, `parallelism` 1..4, Envelope-JSON ≤ `SECRET_MAX_ENVELOPE_BYTES`. Der Server entschlüsselt nie.

## 6. Fragmente

Share-Link:

```
https://<host>/s/<public_id>#v1.<base64url(linkKey)>[.p]
```

- `.p` ist ein reiner UI-Hinweis „Passwort erforderlich“, damit das Passwort **vor** dem Claim abgefragt wird (kein Status-Endpoint). Er ist nicht sicherheitsrelevant: Fehlt er, fragt der Client nach dem Claim nach dem Passwort.
- Parser lehnt unbekannte Versionen, falsche Längen, Padding und jeden Zusatz ab.

Management-Link:

```
https://<host>/manage/<public_id>#d=<base64url(deleteToken)>
```

## 7. Delete-Token

```
client:  delete_token_hash = base64url(SHA-256(deleteToken))   → an Server
server:  stored            = hex(SHA-256(delete_token_hash))     → DB
verify:  hash_equals(stored, hex(SHA-256(received)))
```

Die zweite Hash-Stufe bewirkt, dass ein DB-Leak kein verwendbares Delete-Credential enthält.

## 8. Speicher

Secret-Material liegt nur im JavaScript-Speicher. Verwendete Byte-Arrays werden nach Gebrauch mit `fill(0)` überschrieben.

> In JavaScript kann garantiertes Memory Wiping aufgrund GC / Runtime nicht zugesichert werden. Strings (Klartext, Passwort, Links) sind unveränderlich und können nicht überschrieben werden.

## 9. Versionierung

API-Version (`/api/v1`), Envelope-Version (`v`) und Fragment-Version (`#v1.`) sind getrennt. Für v2: `protocol-v2.ts` hinzufügen, v1-Decryption bis max. TTL + Marge behalten (plan.md §54).
