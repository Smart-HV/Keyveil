export type ProtocolVersion = 1;

export interface PasswordKdfConfig {
    enabled: true;
    kdf: 'argon2id';
    salt: string;
    memory_kib: number;
    iterations: number;
    parallelism: number;
}

export interface NoPasswordConfig {
    enabled: false;
}

export interface CryptoEnvelopeV1 {
    v: 1;
    alg: 'A256GCM';
    iv: string;
    hkdf_salt: string;
    password: PasswordKdfConfig | NoPasswordConfig;
    ciphertext: string;
}
