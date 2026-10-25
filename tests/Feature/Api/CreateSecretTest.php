<?php

use App\Models\Secret;
use App\Providers\AppServiceProvider;
use Database\Factories\SecretFactory;

test('a valid envelope is stored and only lifecycle data is returned', function () {
    $this->freezeTime();

    $payload = validSecretPayload(['expires_in' => 86400, 'max_views' => 3]);

    $response = $this->postJson('/api/v1/secrets', $payload)
        ->assertCreated()
        ->assertExactJsonStructure(['data' => ['public_id', 'expires_at', 'max_views']])
        ->assertJsonPath('data.max_views', 3)
        ->assertJsonPath('data.expires_at', now()->addDay()->utc()->toIso8601ZuluString());

    expect($response->getContent())
        ->not->toContain($payload['envelope']['ciphertext'])
        ->not->toContain($payload['delete_token_hash']);

    $secret = Secret::query()->sole();

    expect($secret->public_id)->toBe($response->json('data.public_id'))
        ->and($secret->envelope)->toEqual($payload['envelope'])
        ->and($secret->view_count)->toBe(0)
        ->and($secret->password_protected)->toBeFalse()
        ->and($secret->delete_token_hash)->toBe(hash('sha256', $payload['delete_token_hash']))
        ->and($secret->expires_at->timestamp)->toBe(now()->addDay()->timestamp);
});

test('a password protected envelope is accepted', function () {
    $this->postJson('/api/v1/secrets', validSecretPayload([
        'envelope' => SecretFactory::envelope(passwordProtected: true),
        'password_protected' => true,
    ]))->assertCreated();

    expect(Secret::query()->sole()->password_protected)->toBeTrue();
});

test('server controlled attributes cannot be set by the client', function () {
    $this->postJson('/api/v1/secrets', validSecretPayload([
        'public_id' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'view_count' => 5,
        'expires_at' => '2099-01-01T00:00:00Z',
    ]))->assertCreated();

    $secret = Secret::query()->sole();

    expect($secret->public_id)->not->toBe('AAAAAAAAAAAAAAAAAAAAAA')
        ->and($secret->view_count)->toBe(0)
        ->and($secret->expires_at->year)->not->toBe(2099);
});

dataset('invalid payloads', function () {
    $envelope = SecretFactory::envelope();
    $passwordEnvelope = SecretFactory::envelope(passwordProtected: true);

    return [
        'missing envelope' => [['envelope' => null]],
        'envelope is a string' => [['envelope' => 'opaque']],
        'envelope is a list' => [['envelope' => [1, 2, 3]]],
        'unknown crypto version' => [['crypto_version' => 2]],
        'crypto version as string' => [['crypto_version' => '1']],
        'envelope version mismatch' => [['envelope' => [...$envelope, 'v' => 2]]],
        'unknown envelope key' => [['envelope' => [...$envelope, 'key' => 'leak']]],
        'unknown algorithm' => [['envelope' => [...$envelope, 'alg' => 'A128GCM']]],
        'iv too short' => [['envelope' => [...$envelope, 'iv' => SecretFactory::base64url(8)]]],
        'iv with padding' => [['envelope' => [...$envelope, 'iv' => 'AAAAAAAAAAAAAAA=']]],
        'iv standard base64' => [['envelope' => [...$envelope, 'iv' => 'AAAAAAAAAAAAAA+/']]],
        'hkdf salt too short' => [['envelope' => [...$envelope, 'hkdf_salt' => SecretFactory::base64url(16)]]],
        'empty ciphertext' => [['envelope' => [...$envelope, 'ciphertext' => '']]],
        'ciphertext shorter than tag' => [['envelope' => [...$envelope, 'ciphertext' => SecretFactory::base64url(8)]]],
        'ciphertext with invalid characters' => [['envelope' => [...$envelope, 'ciphertext' => str_repeat('<script>', 4)]]],
        'missing password config' => [['envelope' => array_diff_key($envelope, ['password' => true])]],
        'kdf params without password' => [['envelope' => [...$envelope, 'password' => ['enabled' => false, 'kdf' => 'argon2id']]]],
        'password flag mismatch' => [['envelope' => $passwordEnvelope, 'password_protected' => false]],
        'password flag mismatch reversed' => [['password_protected' => true]],
        'unknown kdf' => [['envelope' => [...$passwordEnvelope, 'password' => [...$passwordEnvelope['password'], 'kdf' => 'pbkdf2']], 'password_protected' => true]],
        'argon2 memory too high' => [['envelope' => [...$passwordEnvelope, 'password' => [...$passwordEnvelope['password'], 'memory_kib' => 4194304]], 'password_protected' => true]],
        'argon2 memory too low' => [['envelope' => [...$passwordEnvelope, 'password' => [...$passwordEnvelope['password'], 'memory_kib' => 1024]], 'password_protected' => true]],
        'argon2 iterations too high' => [['envelope' => [...$passwordEnvelope, 'password' => [...$passwordEnvelope['password'], 'iterations' => 1000]], 'password_protected' => true]],
        'argon2 parallelism too high' => [['envelope' => [...$passwordEnvelope, 'password' => [...$passwordEnvelope['password'], 'parallelism' => 64]], 'password_protected' => true]],
        'password salt too short' => [['envelope' => [...$passwordEnvelope, 'password' => [...$passwordEnvelope['password'], 'salt' => SecretFactory::base64url(8)]], 'password_protected' => true]],
        'password flag as string' => [['password_protected' => 'false']],
        'ttl below minimum' => [['expires_in' => 59]],
        'ttl above maximum' => [['expires_in' => 604801]],
        'ttl as string' => [['expires_in' => '3600']],
        'ttl as float' => [['expires_in' => 3600.5]],
        'zero max views' => [['max_views' => 0]],
        'max views above maximum' => [['max_views' => 11]],
        'delete token hash too short' => [['delete_token_hash' => SecretFactory::base64url(16)]],
        'delete token hash as hex' => [['delete_token_hash' => hash('sha256', 'x')]],
    ];
});

test('invalid payloads are rejected with 422', function (array $overrides) {
    $this->postJson('/api/v1/secrets', validSecretPayload($overrides))
        ->assertUnprocessable();

    expect(Secret::query()->count())->toBe(0);
})->with('invalid payloads');

test('validation errors never echo submitted values', function () {
    $ciphertext = SecretFactory::base64url(48);
    $envelope = [...SecretFactory::envelope(), 'ciphertext' => $ciphertext, 'unknown' => 'x'];

    $content = $this->postJson('/api/v1/secrets', validSecretPayload(['envelope' => $envelope]))
        ->assertUnprocessable()
        ->getContent();

    expect($content)->not->toContain($ciphertext);
});

test('the ttl and max views boundaries are accepted', function (int $expiresIn, int $maxViews) {
    $this->postJson('/api/v1/secrets', validSecretPayload([
        'expires_in' => $expiresIn,
        'max_views' => $maxViews,
    ]))->assertCreated();
})->with([
    'minimum' => [60, 1],
    'maximum' => [604800, 10],
]);

test('oversized request bodies are rejected with 413 before validation', function () {
    $payload = validSecretPayload();
    $payload['envelope']['ciphertext'] = str_repeat('A', 140 * 1024);

    $this->postJson('/api/v1/secrets', $payload)
        ->assertStatus(413)
        ->assertExactJson(['error' => ['code' => 'payload_too_large', 'message' => 'The request body is too large.']]);
});

test('an envelope above the envelope limit is rejected', function () {
    config(['secrets.max_envelope_bytes' => 1024]);

    $payload = validSecretPayload();
    $payload['envelope']['ciphertext'] = str_repeat('A', 2048);

    $this->postJson('/api/v1/secrets', $payload)->assertUnprocessable();
});

test('creating secrets is rate limited per minute', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/secrets', validSecretPayload())->assertCreated();
    }

    $this->postJson('/api/v1/secrets', validSecretPayload())->assertTooManyRequests();
});

test('creating secrets is rate limited per hour', function () {
    config(['secrets.rate_limits.create_per_minute' => 100]);
    $this->app->getProvider(AppServiceProvider::class)->boot();

    foreach (range(1, 20) as $attempt) {
        $this->postJson('/api/v1/secrets', validSecretPayload())->assertCreated();
    }

    $this->postJson('/api/v1/secrets', validSecretPayload())->assertTooManyRequests();
});
