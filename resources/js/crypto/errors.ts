/**
 * Generic crypto/protocol error. Messages never contain key material,
 * fragment contents or plaintext.
 */
export class CryptoError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'CryptoError';
    }
}
