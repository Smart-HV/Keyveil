import { describe, expect, it } from 'vite-plus/test';
import { decodeBase64url, encodeBase64url } from './base64url';

describe('base64url', () => {
    it('round-trips arbitrary bytes without padding', () => {
        for (let length = 0; length < 70; length++) {
            const bytes = crypto.getRandomValues(new Uint8Array(length));
            const encoded = encodeBase64url(bytes);

            expect(encoded).toMatch(/^[A-Za-z0-9_-]*$/);
            expect(decodeBase64url(encoded)).toEqual(bytes);
        }
    });

    it.each([
        ['padding', 'AAAAAAAAAAAAAAA='],
        ['standard alphabet plus', 'AAAA+AAA'],
        ['standard alphabet slash', 'AAAA/AAA'],
        ['whitespace', 'AAAA AAA'],
        ['impossible length', 'AAAAA'],
        ['non-canonical trailing bits', 'AB'],
    ])('rejects %s', (_, value) => {
        expect(() => decodeBase64url(value)).toThrow('Invalid base64url');
    });

    it('rejects unexpected byte lengths', () => {
        expect(() =>
            decodeBase64url(encodeBase64url(new Uint8Array(31)), 32),
        ).toThrow('Invalid base64url length.');
    });
});
