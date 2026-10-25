import { CryptoError } from './errors';

const TAG_LENGTH_BITS = 128;

/**
 * AES-256-GCM encryption with a 128-bit tag and additional authenticated data.
 */
export async function encryptAesGcm(
    key: CryptoKey,
    iv: Uint8Array<ArrayBuffer>,
    plaintext: Uint8Array<ArrayBuffer>,
    additionalData: Uint8Array<ArrayBuffer>,
): Promise<Uint8Array<ArrayBuffer>> {
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv, additionalData, tagLength: TAG_LENGTH_BITS },
        key,
        plaintext,
    );

    return new Uint8Array(ciphertext);
}

/**
 * AES-256-GCM decryption. Any authentication failure (wrong key, wrong
 * password, tampered IV/ciphertext/AAD) surfaces as the same generic error.
 */
export async function decryptAesGcm(
    key: CryptoKey,
    iv: Uint8Array<ArrayBuffer>,
    ciphertext: Uint8Array<ArrayBuffer>,
    additionalData: Uint8Array<ArrayBuffer>,
): Promise<Uint8Array<ArrayBuffer>> {
    try {
        const plaintext = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv, additionalData, tagLength: TAG_LENGTH_BITS },
            key,
            ciphertext,
        );

        return new Uint8Array(plaintext);
    } catch {
        throw new CryptoError('Decryption failed.');
    }
}
