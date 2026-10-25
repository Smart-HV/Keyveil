<?php

use App\Models\Secret;
use Database\Factories\SecretFactory;

test('the correct delete token hash physically deletes the secret', function () {
    $deleteTokenHash = SecretFactory::base64url(32);
    $secret = Secret::factory()->withDeleteTokenHash($deleteTokenHash)->create();

    $this->deleteJson("/api/v1/secrets/{$secret->public_id}", ['delete_token_hash' => $deleteTokenHash])
        ->assertNoContent();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();
});

test('a wrong token and an unknown secret produce the same response', function () {
    $secret = Secret::factory()->withDeleteTokenHash(SecretFactory::base64url(32))->create();
    $wrongHash = SecretFactory::base64url(32);

    $wrongToken = $this->deleteJson("/api/v1/secrets/{$secret->public_id}", ['delete_token_hash' => $wrongHash]);
    $unknownSecret = $this->deleteJson('/api/v1/secrets/'.Secret::generatePublicId(), ['delete_token_hash' => $wrongHash]);

    $wrongToken->assertNotFound();
    $unknownSecret->assertNotFound();

    expect($wrongToken->getContent())->toBe($unknownSecret->getContent());
    expect(Secret::query()->whereKey($secret->id)->exists())->toBeTrue();
});

test('the stored hash itself is not accepted as delete credential', function () {
    $deleteTokenHash = SecretFactory::base64url(32);
    $secret = Secret::factory()->withDeleteTokenHash($deleteTokenHash)->create();

    $this->deleteJson("/api/v1/secrets/{$secret->public_id}", ['delete_token_hash' => $secret->delete_token_hash])
        ->assertUnprocessable();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeTrue();
});

test('a malformed delete token hash is rejected', function () {
    $secret = Secret::factory()->create();

    $this->deleteJson("/api/v1/secrets/{$secret->public_id}", ['delete_token_hash' => 'short'])
        ->assertUnprocessable();
});

test('deleting is rate limited per IP', function () {
    foreach (range(1, 10) as $attempt) {
        $this->deleteJson('/api/v1/secrets/'.Secret::generatePublicId(), ['delete_token_hash' => SecretFactory::base64url(32)])
            ->assertNotFound();
    }

    $this->deleteJson('/api/v1/secrets/'.Secret::generatePublicId(), ['delete_token_hash' => SecretFactory::base64url(32)])
        ->assertTooManyRequests();
});
