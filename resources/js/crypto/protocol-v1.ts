import { decryptAesGcm, encryptAesGcm } from './aes-gcm';
import { deriveArgon2idKey, type Argon2idParams } from './argon2id';
import { decodeBase64url, encodeBase64url } from './base64url';
import { CryptoError } from './errors';
import { deriveAesGcmKey } from './hkdf';
import { randomBytes } from './random';
import type { CryptoEnvelopeV1, PasswordKdfConfig } from './types';

/*
 * Crypto protocol v1. Normative description: docs/crypto-protocol-v1.md.
 * Parameters are versioned here on purpose and never configurable at runtime.
 */
export const LINK_KEY_BYTES = 32;
export const IV_BYTES = 12;
export const HKDF_SALT_BYTES = 32;
export const PASSWORD_SALT_BYTES = 16;
export const DELETE_TOKEN_BYTES = 32;
export const TAG_BYTES = 16;
export const MAX_PLAINTEXT_BYTES = 64 * 1024;

export const AAD = 'password-transfer:v1';
export const HKDF_INFO_LINK_ONLY = 'password-transfer:v1:content';
export const HKDF_INFO_PASSWORD = 'password-transfer:v1:content+password';

export const ARGON2ID_DEFAULTS: Argon2idParams = {
    memoryKib: 65536,
    iterations: 3,
    parallelism: 1,
};

/** DoS limits for envelopes received from the server, not target values. */
export const ARGON2ID_HARD_LIMITS: Argon2idParams = {
    memoryKib: 262144,
    iterations: 10,
    parallelism: 4,
};

const MAX_CIPHERTEXT_BYTES = MAX_PLAINTEXT_BYTES + TAG_BYTES;

const encoder = new TextEncoder();

export interface EncryptionMaterial {
    linkKey: Uint8Array<ArrayBuffer>;
    iv: Uint8Array<ArrayBuffer>;
    hkdfSalt: Uint8Array<ArrayBuffer>;
    password?: {
        value: string;
        salt: Uint8Array<ArrayBuffer>;
        params: Argon2idParams;
    };
}

export interface EncryptedSecret {
    envelope: CryptoEnvelopeV1;
    linkKey: Uint8Array<ArrayBuffer>;
}

/**
 * Passwords are NFC-normalized so visually identical input from different
 * keyboards/platforms derives the same key.
 */
function encodePassword(password: string): Uint8Array<ArrayBuffer> {
    return encoder.encode(password.normalize('NFC'));
}

async function deriveContentKey(
    linkKey: Uint8Array<ArrayBuffer>,
    hkdfSalt: Uint8Array<ArrayBuffer>,
    password?: {
        value: string;
        salt: Uint8Array;
        params: Argon2idParams;
    },
): Promise<CryptoKey> {
    if (password === undefined) {
        return deriveAesGcmKey(linkKey, hkdfSalt, HKDF_INFO_LINK_ONLY);
    }

    // Password mode: the Argon2id output is the HKDF salt (protocol v1 §8.3).
    const passwordKey = await deriveArgon2idKey(
        encodePassword(password.value),
        password.salt,
        password.params,
    );

    try {
        return await deriveAesGcmKey(linkKey, passwordKey, HKDF_INFO_PASSWORD);
    } finally {
        passwordKey.fill(0);
    }
}

/**
 * Deterministic encryption from explicit key material. Used by encryptV1 and
 * by the golden test vectors.
 */
export async function encryptWithMaterial(
    plaintext: string,
    material: EncryptionMaterial,
): Promise<CryptoEnvelopeV1> {
    const plaintextBytes = encoder.encode(plaintext);

    if (plaintextBytes.length === 0) {
        throw new CryptoError('The secret must not be empty.');
    }

    if (plaintextBytes.length > MAX_PLAINTEXT_BYTES) {
        throw new CryptoError('The secret is too large.');
    }

    const key = await deriveContentKey(
        material.linkKey,
        material.hkdfSalt,
        material.password,
    );

    const ciphertext = await encryptAesGcm(
        key,
        material.iv,
        plaintextBytes,
        encoder.encode(AAD),
    );

    plaintextBytes.fill(0);

    const password: CryptoEnvelopeV1['password'] =
        material.password === undefined
            ? { enabled: false }
            : {
                  enabled: true,
                  kdf: 'argon2id',
                  salt: encodeBase64url(material.password.salt),
                  memory_kib: material.password.params.memoryKib,
                  iterations: material.password.params.iterations,
                  parallelism: material.password.params.parallelism,
              };

    return {
        v: 1,
        alg: 'A256GCM',
        iv: encodeBase64url(material.iv),
        hkdf_salt: encodeBase64url(material.hkdfSalt),
        password,
        ciphertext: encodeBase64url(ciphertext),
    };
}

/**
 * Encrypt a plaintext with fresh random key material.
 */
export async function encryptV1(
    plaintext: string,
    password?: string,
): Promise<EncryptedSecret> {
    const linkKey = randomBytes(LINK_KEY_BYTES);

    const envelope = await encryptWithMaterial(plaintext, {
        linkKey,
        iv: randomBytes(IV_BYTES),
        hkdfSalt: randomBytes(HKDF_SALT_BYTES),
        password:
            password === undefined || password === ''
                ? undefined
                : {
                      value: password,
                      salt: randomBytes(PASSWORD_SALT_BYTES),
                      params: ARGON2ID_DEFAULTS,
                  },
    });

    return { envelope, linkKey };
}

/**
 * Decrypt a validated envelope. Wrong key, wrong password and tampering are
 * deliberately indistinguishable.
 */
export async function decryptV1(
    envelope: CryptoEnvelopeV1,
    linkKey: Uint8Array<ArrayBuffer>,
    password?: string,
): Promise<string> {
    if (linkKey.length !== LINK_KEY_BYTES) {
        throw new CryptoError('Invalid key.');
    }

    if (
        envelope.password.enabled &&
        (password === undefined || password === '')
    ) {
        throw new CryptoError('A password is required.');
    }

    const key = await deriveContentKey(
        linkKey,
        decodeBase64url(envelope.hkdf_salt, HKDF_SALT_BYTES),
        envelope.password.enabled
            ? {
                  value: password ?? '',
                  salt: decodeBase64url(
                      envelope.password.salt,
                      PASSWORD_SALT_BYTES,
                  ),
                  params: {
                      memoryKib: envelope.password.memory_kib,
                      iterations: envelope.password.iterations,
                      parallelism: envelope.password.parallelism,
                  },
              }
            : undefined,
    );

    const plaintextBytes = await decryptAesGcm(
        key,
        decodeBase64url(envelope.iv, IV_BYTES),
        decodeBase64url(envelope.ciphertext),
        encoder.encode(AAD),
    );

    try {
        return new TextDecoder('utf-8', { fatal: true }).decode(plaintextBytes);
    } catch {
        throw new CryptoError('Decryption failed.');
    } finally {
        plaintextBytes.fill(0);
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function hasExactKeys(value: Record<string, unknown>, keys: string[]): boolean {
    const actual = Object.keys(value).sort();
    const expected = [...keys].sort();

    return (
        actual.length === expected.length &&
        actual.every((key, index) => key === expected[index])
    );
}

function isBoundedInteger(value: unknown, min: number, max: number): boolean {
    return (
        Number.isInteger(value) &&
        (value as number) >= min &&
        (value as number) <= max
    );
}

function parsePasswordConfig(value: unknown): CryptoEnvelopeV1['password'] {
    if (!isRecord(value)) {
        throw new CryptoError('Invalid envelope.');
    }

    if (value.enabled === false && hasExactKeys(value, ['enabled'])) {
        return { enabled: false };
    }

    if (
        value.enabled !== true ||
        !hasExactKeys(value, [
            'enabled',
            'kdf',
            'salt',
            'memory_kib',
            'iterations',
            'parallelism',
        ]) ||
        value.kdf !== 'argon2id' ||
        typeof value.salt !== 'string' ||
        !isBoundedInteger(
            value.memory_kib,
            8 * (value.parallelism as number),
            ARGON2ID_HARD_LIMITS.memoryKib,
        ) ||
        !isBoundedInteger(
            value.iterations,
            1,
            ARGON2ID_HARD_LIMITS.iterations,
        ) ||
        !isBoundedInteger(
            value.parallelism,
            1,
            ARGON2ID_HARD_LIMITS.parallelism,
        )
    ) {
        throw new CryptoError('Invalid envelope.');
    }

    decodeBase64url(value.salt, PASSWORD_SALT_BYTES);

    return value as unknown as PasswordKdfConfig;
}

/**
 * Runtime validation of an envelope received from the network. TypeScript
 * types alone do not validate untrusted data.
 */
export function parseEnvelopeV1(value: unknown): CryptoEnvelopeV1 {
    if (
        !isRecord(value) ||
        !hasExactKeys(value, [
            'v',
            'alg',
            'iv',
            'hkdf_salt',
            'password',
            'ciphertext',
        ])
    ) {
        throw new CryptoError('Invalid envelope.');
    }

    if (value.v !== 1) {
        throw new CryptoError('Unsupported protocol version.');
    }

    if (
        value.alg !== 'A256GCM' ||
        typeof value.iv !== 'string' ||
        typeof value.hkdf_salt !== 'string' ||
        typeof value.ciphertext !== 'string'
    ) {
        throw new CryptoError('Invalid envelope.');
    }

    decodeBase64url(value.iv, IV_BYTES);
    decodeBase64url(value.hkdf_salt, HKDF_SALT_BYTES);

    const ciphertextLength = decodeBase64url(value.ciphertext).length;

    if (
        ciphertextLength <= TAG_BYTES ||
        ciphertextLength > MAX_CIPHERTEXT_BYTES
    ) {
        throw new CryptoError('Invalid envelope.');
    }

    return {
        v: 1,
        alg: 'A256GCM',
        iv: value.iv,
        hkdf_salt: value.hkdf_salt,
        password: parsePasswordConfig(value.password),
        ciphertext: value.ciphertext,
    };
}

/**
 * SHA-256 of the raw delete token. Only this hash is sent to the server.
 */
export async function hashDeleteToken(
    deleteToken: Uint8Array<ArrayBuffer>,
): Promise<string> {
    if (deleteToken.length !== DELETE_TOKEN_BYTES) {
        throw new CryptoError('Invalid delete token.');
    }

    return encodeBase64url(
        new Uint8Array(await crypto.subtle.digest('SHA-256', deleteToken)),
    );
}
