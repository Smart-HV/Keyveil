import { decodeBase64url, encodeBase64url } from '../crypto/base64url';
import { CryptoError } from '../crypto/errors';
import { DELETE_TOKEN_BYTES, LINK_KEY_BYTES } from '../crypto/protocol-v1';

/*
 * URL fragments carry key material and never reach the server. Parsers reject
 * anything unexpected and never include fragment contents in errors or logs.
 *
 *   Share:  #v1.<base64url 32-byte link key>[.p]   (.p = password required)
 *   Manage: #d=<base64url 32-byte delete token>
 */
const SHARE_PATTERN = /^#v1\.([A-Za-z0-9_-]{43})(\.p)?$/;
const DELETE_PATTERN = /^#d=([A-Za-z0-9_-]{43})$/;

export interface ShareFragment {
    version: 1;
    linkKey: Uint8Array<ArrayBuffer>;
    passwordRequired: boolean;
}

export function buildShareFragment(
    linkKey: Uint8Array,
    passwordRequired: boolean,
): string {
    return `#v1.${encodeBase64url(linkKey)}${passwordRequired ? '.p' : ''}`;
}

export function parseShareFragment(hash: string): ShareFragment {
    const match = SHARE_PATTERN.exec(hash);

    if (match === null || match[1] === undefined) {
        throw new CryptoError('Invalid or unsupported link.');
    }

    return {
        version: 1,
        linkKey: decodeBase64url(match[1], LINK_KEY_BYTES),
        passwordRequired: match[2] !== undefined,
    };
}

export function buildDeleteFragment(deleteToken: Uint8Array): string {
    return `#d=${encodeBase64url(deleteToken)}`;
}

export function parseDeleteFragment(hash: string): Uint8Array<ArrayBuffer> {
    const match = DELETE_PATTERN.exec(hash);

    if (match === null || match[1] === undefined) {
        throw new CryptoError('Invalid management link.');
    }

    return decodeBase64url(match[1], DELETE_TOKEN_BYTES);
}
