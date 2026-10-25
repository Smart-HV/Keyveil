<?php

/*
 * Cross-implementation check of crypto protocol v1: the golden vectors are
 * decrypted here with PHP primitives (openssl, hash_hkdf, libsodium) and in
 * resources/js/crypto/protocol-v1.test.ts with WebCrypto/hash-wasm.
 * Test code only: the application itself never decrypts anything.
 */

function base64urlDecode(string $value): string
{
    return base64_decode(strtr($value, '-_', '+/'), strict: true);
}

/**
 * @return array<string, mixed>
 */
function cryptoVectors(): array
{
    return json_decode(file_get_contents(__DIR__.'/../fixtures/crypto-v1-vectors.json'), true, flags: JSON_THROW_ON_ERROR);
}

dataset('vectors', fn () => collect(cryptoVectors()['vectors'])->keyBy('name')->map(fn ($vector) => [$vector])->all());

test('golden vectors decrypt with independent PHP primitives', function (array $vector) {
    $envelope = $vector['envelope'];
    $linkKey = base64urlDecode($vector['link_key']);

    if ($envelope['password']['enabled']) {
        $passwordKey = sodium_crypto_pwhash(
            32,
            Normalizer::normalize($vector['password'], Normalizer::FORM_C),
            base64urlDecode($envelope['password']['salt']),
            $envelope['password']['iterations'],
            $envelope['password']['memory_kib'] * 1024,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
        $key = hash_hkdf('sha256', $linkKey, 32, 'password-transfer:v1:content+password', $passwordKey);
    } else {
        $key = hash_hkdf('sha256', $linkKey, 32, 'password-transfer:v1:content', base64urlDecode($envelope['hkdf_salt']));
    }

    $sealed = base64urlDecode($envelope['ciphertext']);

    $plaintext = openssl_decrypt(
        substr($sealed, 0, -16),
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        base64urlDecode($envelope['iv']),
        substr($sealed, -16),
        cryptoVectors()['aad'],
    );

    expect($plaintext)->toBe($vector['plaintext'])
        ->and($vector['fragment'])->toBe('#v1.'.$vector['link_key'].($envelope['password']['enabled'] ? '.p' : ''));
})->with('vectors');

test('the delete token vector hashes with SHA-256', function () {
    $deleteToken = cryptoVectors()['delete_token'];

    expect(rtrim(strtr(base64_encode(hash('sha256', base64urlDecode($deleteToken['token']), true)), '+/', '-_'), '='))
        ->toBe($deleteToken['sha256']);
});
