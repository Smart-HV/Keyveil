import type { CryptoEnvelopeV1 } from '../crypto/types';

/*
 * Same-origin API client. Requests carry only ciphertext, lifecycle options
 * and the delete token hash — never the fragment, key or password.
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
    ) {
        super(code);
        this.name = 'ApiError';
    }
}

export interface CreateSecretPayload {
    crypto_version: 1;
    envelope: CryptoEnvelopeV1;
    password_protected: boolean;
    expires_in: number;
    max_views: number;
    delete_token_hash: string;
}

export interface CreatedSecret {
    public_id: string;
    expires_at: string;
    max_views: number;
}

export interface ClaimedSecret {
    crypto_version: number;
    envelope: unknown;
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function request(
    method: 'POST' | 'DELETE',
    path: string,
    body?: unknown,
): Promise<Response> {
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        cache: 'no-store',
        referrerPolicy: 'no-referrer',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        let code = 'request_failed';

        try {
            const json = (await response.json()) as {
                error?: { code?: unknown };
            };

            if (typeof json.error?.code === 'string') {
                code = json.error.code;
            }
        } catch {
            // Non-JSON error bodies are reported by status only.
        }

        throw new ApiError(response.status, code);
    }

    return response;
}

export async function createSecret(
    payload: CreateSecretPayload,
): Promise<CreatedSecret> {
    const response = await request('POST', '/api/v1/secrets', payload);
    const json = (await response.json()) as { data: CreatedSecret };

    return json.data;
}

export async function claimSecret(publicId: string): Promise<ClaimedSecret> {
    const response = await request(
        'POST',
        `/api/v1/secrets/${encodeURIComponent(publicId)}/claim`,
    );
    const json = (await response.json()) as { data: ClaimedSecret };

    return json.data;
}

export async function deleteSecret(
    publicId: string,
    deleteTokenHash: string,
): Promise<void> {
    await request('DELETE', `/api/v1/secrets/${encodeURIComponent(publicId)}`, {
        delete_token_hash: deleteTokenHash,
    });
}
