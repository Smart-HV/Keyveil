import { CryptoError } from './errors';

const BASE64URL_PATTERN = /^[A-Za-z0-9_-]*$/;

/**
 * Encode bytes as base64url without padding (RFC 4648 §5).
 */
export function encodeBase64url(bytes: Uint8Array): string {
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

/**
 * Strictly decode unpadded base64url. Rejects foreign characters, padding,
 * non-canonical encodings and (optionally) unexpected byte lengths.
 */
export function decodeBase64url(
    value: string,
    expectedLength?: number,
): Uint8Array<ArrayBuffer> {
    if (!BASE64URL_PATTERN.test(value) || value.length % 4 === 1) {
        throw new CryptoError('Invalid base64url data.');
    }

    const padded =
        value.replace(/-/g, '+').replace(/_/g, '/') +
        '='.repeat((4 - (value.length % 4)) % 4);

    let binary: string;

    try {
        binary = atob(padded);
    } catch {
        throw new CryptoError('Invalid base64url data.');
    }

    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));

    if (encodeBase64url(bytes) !== value) {
        throw new CryptoError('Invalid base64url data.');
    }

    if (expectedLength !== undefined && bytes.length !== expectedLength) {
        throw new CryptoError('Invalid base64url length.');
    }

    return bytes;
}
