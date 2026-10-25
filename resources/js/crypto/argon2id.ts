/**
 * The only module importing the Argon2id dependency (hash-wasm), so it can be
 * swapped later. Loaded lazily: link-only secrets never fetch the WASM chunk.
 */
export interface Argon2idParams {
    memoryKib: number;
    iterations: number;
    parallelism: number;
}

/**
 * Derive a 32-byte password key with Argon2id.
 */
export async function deriveArgon2idKey(
    password: Uint8Array,
    salt: Uint8Array,
    params: Argon2idParams,
): Promise<Uint8Array<ArrayBuffer>> {
    const { argon2id } = await import('hash-wasm');

    const output = await argon2id({
        password,
        salt,
        parallelism: params.parallelism,
        iterations: params.iterations,
        memorySize: params.memoryKib,
        hashLength: 32,
        outputType: 'binary',
    });

    return new Uint8Array(output);
}
