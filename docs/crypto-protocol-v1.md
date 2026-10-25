# Crypto protocol v1 (normative)

Status: v1, implemented in [`resources/js/crypto/protocol-v1.ts`](../resources/js/crypto/protocol-v1.ts).
Test vectors: [`tests/fixtures/crypto-v1-vectors.json`](../tests/fixtures/crypto-v1-vectors.json) — generated with PHP (OpenSSL, `hash_hkdf`, libsodium) and verified by the TypeScript implementation (WebCrypto, hash-wasm) as well as by [`tests/Unit/CryptoVectorsTest.php`](../tests/Unit/CryptoVectorsTest.php).

The implementation and this document must match. Any deviation requires a new protocol version.

## 1. Primitives

| Purpose | Primitive | Parameters |
|---|---|---|
| Content encryption | AES-256-GCM (WebCrypto) | 96-bit IV, 128-bit tag |
| Key derivation | HKDF-SHA-256 (WebCrypto) | 256-bit output |
| Password KDF | Argon2id (hash-wasm 4.12.0) | 32-byte output |
| Delete token hash | SHA-256 (WebCrypto) | – |
| Randomness | `crypto.getRandomValues()` | – |
| Text | UTF-8 (`TextEncoder`, decoding with `fatal: true`) | – |
| Binary → text | Unpadded base64url (RFC 4648 §5), strictly canonical | – |

## 2. Random values per secret

| Value | Bytes | Location |
|---|---|---|
| `linkKey` | 32 | URL fragment only |
| AES-GCM IV | 12 | envelope `iv` |
| HKDF salt | 32 | envelope `hkdf_salt` |
| Argon2id salt | 16 | envelope `password.salt` (password mode only) |
| `deleteToken` | 32 | management link fragment only |
| `public_id` | 16 | server side (PHP `random_bytes`) |

## 3. Key derivation

Constants (UTF-8):

- `AAD = "password-transfer:v1"`
- `INFO_LINK = "password-transfer:v1:content"`
- `INFO_PASSWORD = "password-transfer:v1:content+password"`

**Link only:**

```
aesKey = HKDF-SHA-256(ikm = linkKey, salt = hkdf_salt, info = INFO_LINK, L = 32)
```

**With password:**

```
pw          = UTF-8(NFC(password))
passwordKey = Argon2id(pw, salt = password.salt, m = memory_kib, t = iterations, p = parallelism, L = 32)
aesKey      = HKDF-SHA-256(ikm = linkKey, salt = passwordKey, info = INFO_PASSWORD, L = 32)
```

In password mode, `passwordKey` is the HKDF salt, so the final key binds both factors. The envelope field `hkdf_salt` is still mandatory (uniform schema) but is **not** used in this mode.

Passwords are NFC-normalized before encoding, so visually identical input from different platforms derives the same key.

Argon2id defaults for v1: `memory_kib = 65536`, `iterations = 3`, `parallelism = 1`. The parameters are stored in the envelope; if the defaults change, existing secrets remain decryptable.

## 4. Encryption

```
ciphertext = AES-256-GCM-Encrypt(aesKey, iv, plaintext = UTF-8(text), aad = AAD, tagLength = 128)
           = encrypted_bytes || tag(16)
```

- Plaintext: 1 to 65,536 bytes of UTF-8.
- Every secret uses a fresh `linkKey` and a fresh random IV.

## 5. Envelope

A JSON object with exactly these fields. Unknown fields are rejected by both client **and** server.

```json
{
  "v": 1,
  "alg": "A256GCM",
  "iv": "<base64url, 12 bytes>",
  "hkdf_salt": "<base64url, 32 bytes>",
  "password": { "enabled": false },
  "ciphertext": "<base64url, 17..65552 bytes>"
}
```

Password mode:

```json
"password": {
  "enabled": true,
  "kdf": "argon2id",
  "salt": "<base64url, 16 bytes>",
  "memory_kib": 65536,
  "iterations": 3,
  "parallelism": 1
}
```

The server stores the envelope as JSONB (field order is not significant). The AAD is deliberately only the constant `AAD`; there is no envelope canonicalization.

### Client hard limits when reading (DoS protection, not target values)

- `v === 1`, `alg === "A256GCM"`
- `iv` exactly 12 bytes, `hkdf_salt` exactly 32 bytes, `password.salt` exactly 16 bytes
- Ciphertext > 16 and ≤ 65,552 bytes
- `memory_kib` ≤ 262,144 (256 MiB) and ≥ 8·p, `iterations` 1..10, `parallelism` 1..4, all integers

### Server-side structural validation (`StoreSecretRequest`)

Same shape checks, plus: `memory_kib` 19,456..262,144, `iterations` 2..10, `parallelism` 1..4, envelope JSON ≤ `SECRET_MAX_ENVELOPE_BYTES`. The server never decrypts.

## 6. Fragments

Share link:

```
https://<host>/s/<public_id>#v1.<base64url(linkKey)>[.p]
```

- `.p` is a pure UI hint ("password required"), so the password can be requested **before** the claim without a status endpoint. It is not security relevant: if it is missing, the client asks for the password after the claim.
- The parser rejects unknown versions, wrong lengths, padding and any suffix.

Management link:

```
https://<host>/manage/<public_id>#d=<base64url(deleteToken)>
```

## 7. Delete token

```
client:  delete_token_hash = base64url(SHA-256(deleteToken))   → sent to the server
server:  stored            = hex(SHA-256(delete_token_hash))     → database
verify:  hash_equals(stored, hex(SHA-256(received)))
```

The second hashing step means a database leak does not contain a usable delete credential.

## 8. Memory

Secret material is kept in JavaScript memory only. Byte arrays are overwritten with `fill(0)` after use.

> JavaScript cannot guarantee memory wiping because of garbage collection and runtime behavior. Strings (plaintext, password, links) are immutable and cannot be overwritten.

## 9. Versioning

The API version (`/api/v1`), envelope version (`v`) and fragment version (`#v1.`) are independent. For v2: add `protocol-v2.ts`, keep v1 decryption, create new secrets with v2, and remove v1 no earlier than the maximum TTL plus a safety margin.
