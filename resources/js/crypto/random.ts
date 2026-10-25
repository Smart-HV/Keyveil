/**
 * Cryptographically secure random bytes from the browser CSPRNG.
 */
export function randomBytes(length: number): Uint8Array<ArrayBuffer> {
    return crypto.getRandomValues(new Uint8Array(length));
}
