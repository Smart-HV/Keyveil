/**
 * Derive a non-extractable AES-256-GCM key via HKDF-SHA-256.
 */
export async function deriveAesGcmKey(
    inputKeyMaterial: Uint8Array<ArrayBuffer>,
    salt: Uint8Array<ArrayBuffer>,
    info: string,
): Promise<CryptoKey> {
    const baseKey = await crypto.subtle.importKey(
        'raw',
        inputKeyMaterial,
        'HKDF',
        false,
        ['deriveKey'],
    );

    return crypto.subtle.deriveKey(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt,
            info: new TextEncoder().encode(info),
        },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}
