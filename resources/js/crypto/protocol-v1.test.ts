import { describe, expect, it } from 'vite-plus/test';
import fixture from '../../../tests/fixtures/crypto-v1-vectors.json';
import { decryptAesGcm } from './aes-gcm';
import { decodeBase64url, encodeBase64url } from './base64url';
import { deriveAesGcmKey } from './hkdf';
import {
    decryptV1,
    encryptV1,
    encryptWithMaterial,
    hashDeleteToken,
    HKDF_INFO_LINK_ONLY,
    MAX_PLAINTEXT_BYTES,
    parseEnvelopeV1,
} from './protocol-v1';
import type { CryptoEnvelopeV1 } from './types';

const linkOnlyVector = fixture.vectors[0]!;
const passwordVector = fixture.vectors[2]!;

function envelopeOf(
    vector: (typeof fixture.vectors)[number],
): CryptoEnvelopeV1 {
    return parseEnvelopeV1(structuredClone(vector.envelope));
}

/** Flip one bit in a base64url encoded field. */
function tamper(value: string): string {
    const bytes = decodeBase64url(value);
    bytes[0] = bytes[0]! ^ 0x01;

    return encodeBase64url(bytes);
}

describe('golden vectors', () => {
    it.each(fixture.vectors)('decrypts "$name"', async (vector) => {
        const plaintext = await decryptV1(
            envelopeOf(vector),
            decodeBase64url(vector.link_key, 32),
            vector.password ?? undefined,
        );

        expect(plaintext).toBe(vector.plaintext);
    });

    it.each(fixture.vectors)(
        're-encrypts "$name" to the identical envelope',
        async (vector) => {
            const envelope = envelopeOf(vector);

            const encrypted = await encryptWithMaterial(vector.plaintext, {
                linkKey: decodeBase64url(vector.link_key, 32),
                iv: decodeBase64url(envelope.iv, 12),
                hkdfSalt: decodeBase64url(envelope.hkdf_salt, 32),
                password: envelope.password.enabled
                    ? {
                          value: vector.password!,
                          salt: decodeBase64url(envelope.password.salt, 16),
                          params: {
                              memoryKib: envelope.password.memory_kib,
                              iterations: envelope.password.iterations,
                              parallelism: envelope.password.parallelism,
                          },
                      }
                    : undefined,
            });

            expect(encrypted).toEqual(vector.envelope);
        },
    );

    it('hashes the delete token vector', async () => {
        expect(
            await hashDeleteToken(
                decodeBase64url(fixture.delete_token.token, 32),
            ),
        ).toBe(fixture.delete_token.sha256);
    });
});

describe('round trip', () => {
    it.each([
        ['ascii', 'hunter2'],
        ['unicode', 'Grüße 🔑 тест ✓ 中文'],
        ['minimal', 'x'],
        ['html-like', '<img src=x onerror=alert(1)>'],
    ])('encrypts and decrypts %s', async (_, plaintext) => {
        const { envelope, linkKey } = await encryptV1(plaintext);

        expect(envelope.password).toEqual({ enabled: false });
        expect(await decryptV1(parseEnvelopeV1(envelope), linkKey)).toBe(
            plaintext,
        );
    });

    it('encrypts and decrypts with a password', async () => {
        const { envelope, linkKey } = await encryptV1(
            'db password',
            'pässword',
        );

        expect(envelope.password).toMatchObject({
            enabled: true,
            kdf: 'argon2id',
            memory_kib: 65536,
            iterations: 3,
            parallelism: 1,
        });
        expect(await decryptV1(envelope, linkKey, 'pässword')).toBe(
            'db password',
        );
    });

    it('treats NFC and NFD passwords as equal', async () => {
        const { envelope, linkKey } = await encryptV1('x', 'Päss');

        expect(await decryptV1(envelope, linkKey, 'Päss')).toBe('x');
    });

    it('uses fresh key material for every secret', async () => {
        const first = await encryptV1('same');
        const second = await encryptV1('same');

        expect(first.envelope.iv).not.toBe(second.envelope.iv);
        expect(first.envelope.hkdf_salt).not.toBe(second.envelope.hkdf_salt);
        expect(first.envelope.ciphertext).not.toBe(second.envelope.ciphertext);
        expect(first.linkKey).not.toEqual(second.linkKey);
    });

    it('accepts the maximum plaintext size', async () => {
        const plaintext = 'a'.repeat(MAX_PLAINTEXT_BYTES);
        const { envelope, linkKey } = await encryptV1(plaintext);

        expect(await decryptV1(parseEnvelopeV1(envelope), linkKey)).toBe(
            plaintext,
        );
    });

    it('rejects empty and oversized plaintexts', async () => {
        await expect(encryptV1('')).rejects.toThrow('must not be empty');
        await expect(
            encryptV1('ä'.repeat(MAX_PLAINTEXT_BYTES / 2 + 1)),
        ).rejects.toThrow('too large');
    });
});

describe('authentication failures', () => {
    const linkKey = () => decodeBase64url(linkOnlyVector.link_key, 32);

    it('fails with a wrong link key', async () => {
        const wrongKey = linkKey();
        wrongKey[0] = wrongKey[0]! ^ 0x01;

        await expect(
            decryptV1(envelopeOf(linkOnlyVector), wrongKey),
        ).rejects.toThrow('Decryption failed.');
    });

    it('fails with a wrong password', async () => {
        await expect(
            decryptV1(
                envelopeOf(passwordVector),
                decodeBase64url(passwordVector.link_key, 32),
                'wrong password',
            ),
        ).rejects.toThrow('Decryption failed.');
    });

    it('requires a password for password protected envelopes', async () => {
        await expect(
            decryptV1(
                envelopeOf(passwordVector),
                decodeBase64url(passwordVector.link_key, 32),
            ),
        ).rejects.toThrow('A password is required.');
    });

    it.each(['ciphertext', 'iv', 'hkdf_salt'] as const)(
        'fails with a tampered %s',
        async (field) => {
            const envelope = envelopeOf(linkOnlyVector);
            envelope[field] = tamper(envelope[field]);

            await expect(decryptV1(envelope, linkKey())).rejects.toThrow(
                'Decryption failed.',
            );
        },
    );

    it('fails with tampered Argon2id parameters', async () => {
        const envelope = envelopeOf(passwordVector);

        if (!envelope.password.enabled) {
            throw new Error('Expected a password vector.');
        }

        envelope.password.iterations = 2;

        await expect(
            decryptV1(
                envelope,
                decodeBase64url(passwordVector.link_key, 32),
                passwordVector.password!,
            ),
        ).rejects.toThrow('Decryption failed.');
    });

    it('fails with different additional authenticated data', async () => {
        const envelope = envelopeOf(linkOnlyVector);
        const key = await deriveAesGcmKey(
            linkKey(),
            decodeBase64url(envelope.hkdf_salt, 32),
            HKDF_INFO_LINK_ONLY,
        );

        await expect(
            decryptAesGcm(
                key,
                decodeBase64url(envelope.iv, 12),
                decodeBase64url(envelope.ciphertext),
                new TextEncoder().encode('password-transfer:v2'),
            ),
        ).rejects.toThrow('Decryption failed.');
    });
});

describe('envelope validation', () => {
    const valid = () =>
        structuredClone(passwordVector.envelope) as Record<string, unknown> & {
            password: Record<string, unknown>;
        };

    it('accepts all golden vectors', () => {
        for (const vector of fixture.vectors) {
            expect(parseEnvelopeV1(structuredClone(vector.envelope))).toEqual(
                vector.envelope,
            );
        }
    });

    it('rejects unknown protocol versions', () => {
        expect(() => parseEnvelopeV1({ ...valid(), v: 2 })).toThrow(
            'Unsupported protocol version.',
        );
    });

    it.each<[string, (envelope: ReturnType<typeof valid>) => unknown]>([
        ['non-object', () => 'opaque'],
        ['array', () => []],
        ['unknown algorithm', (e) => ({ ...e, alg: 'A128GCM' })],
        ['unknown top-level field', (e) => ({ ...e, extra: true })],
        [
            'missing field',
            (e) => {
                delete e.ciphertext;

                return e;
            },
        ],
        ['short iv', (e) => ({ ...e, iv: encodeBase64url(new Uint8Array(8)) })],
        ['invalid base64url iv', (e) => ({ ...e, iv: 'QEFCQ0RFRkdISUp+' })],
        [
            'padded ciphertext',
            (e) => ({ ...e, ciphertext: `${e.ciphertext as string}=` }),
        ],
        [
            'ciphertext without tag',
            (e) => ({ ...e, ciphertext: encodeBase64url(new Uint8Array(16)) }),
        ],
        [
            'oversized ciphertext',
            (e) => ({
                ...e,
                ciphertext: encodeBase64url(
                    new Uint8Array(MAX_PLAINTEXT_BYTES + 17),
                ),
            }),
        ],
        [
            'unknown kdf',
            (e) => ({ ...e, password: { ...e.password, kdf: 'scrypt' } }),
        ],
        [
            'argon2 memory DoS',
            (e) => ({
                ...e,
                password: { ...e.password, memory_kib: 4 * 1024 * 1024 },
            }),
        ],
        [
            'argon2 iterations DoS',
            (e) => ({ ...e, password: { ...e.password, iterations: 100 } }),
        ],
        [
            'argon2 parallelism DoS',
            (e) => ({ ...e, password: { ...e.password, parallelism: 16 } }),
        ],
        [
            'fractional iterations',
            (e) => ({ ...e, password: { ...e.password, iterations: 2.5 } }),
        ],
        [
            'string memory',
            (e) => ({ ...e, password: { ...e.password, memory_kib: '65536' } }),
        ],
        [
            'short password salt',
            (e) => ({
                ...e,
                password: {
                    ...e.password,
                    salt: encodeBase64url(new Uint8Array(8)),
                },
            }),
        ],
        [
            'unknown password field',
            (e) => ({ ...e, password: { ...e.password, pepper: 'x' } }),
        ],
        [
            'disabled password with params',
            (e) => ({ ...e, password: { ...e.password, enabled: false } }),
        ],
    ])('rejects %s', (_, mutate) => {
        expect(() => parseEnvelopeV1(mutate(valid()))).toThrow();
    });
});
