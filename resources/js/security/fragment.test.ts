import { describe, expect, it } from 'vite-plus/test';
import fixture from '../../../tests/fixtures/crypto-v1-vectors.json';
import { encodeBase64url } from '../crypto/base64url';
import {
    buildDeleteFragment,
    buildShareFragment,
    parseDeleteFragment,
    parseShareFragment,
} from './fragment';

const key = encodeBase64url(new Uint8Array(32).fill(7));

describe('share fragment', () => {
    it.each(fixture.vectors)('parses the "$name" vector fragment', (vector) => {
        const parsed = parseShareFragment(vector.fragment);

        expect(encodeBase64url(parsed.linkKey)).toBe(vector.link_key);
        expect(parsed.passwordRequired).toBe(vector.password !== null);
        expect(
            buildShareFragment(parsed.linkKey, parsed.passwordRequired),
        ).toBe(vector.fragment);
    });

    it.each([
        ['empty', ''],
        ['missing hash', `v1.${key}`],
        ['unknown version', `#v2.${key}`],
        ['short key', `#v1.${key.slice(1)}`],
        ['long key', `#v1.${key}A`],
        ['unknown suffix', `#v1.${key}.x`],
        ['query-like suffix', `#v1.${key}&a=b`],
        ['json wrapper', `#{"v":1,"k":"${key}"}`],
    ])('rejects %s', (_, hash) => {
        expect(() => parseShareFragment(hash)).toThrow(
            'Invalid or unsupported link.',
        );
    });

    it('never echoes fragment contents in errors', () => {
        try {
            parseShareFragment(`#v9.${key}`);
        } catch (error) {
            expect(String(error)).not.toContain(key);
        }
    });
});

describe('delete fragment', () => {
    it('parses and builds the delete token fragment', () => {
        const token = parseDeleteFragment(fixture.delete_token.fragment);

        expect(encodeBase64url(token)).toBe(fixture.delete_token.token);
        expect(buildDeleteFragment(token)).toBe(fixture.delete_token.fragment);
    });

    it.each([`#d=${key.slice(2)}`, `#t=${key}`, `#v1.${key}`, '#d='])(
        'rejects %s',
        (hash) => {
            expect(() => parseDeleteFragment(hash)).toThrow(
                'Invalid management link.',
            );
        },
    );
});
