<?php

use App\Models\Secret;

const UNAVAILABLE = ['error' => ['code' => 'secret_unavailable', 'message' => 'This secret is not available.']];

test('claiming an active secret returns the envelope and consumes one view', function () {
    $secret = Secret::factory()->views(3)->create();

    $response = $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")
        ->assertOk()
        ->assertExactJsonStructure(['data' => ['crypto_version', 'envelope']])
        ->assertJsonPath('data.crypto_version', 1);

    expect($response->json('data.envelope'))->toEqual($secret->envelope);

    expect($secret->fresh()->view_count)->toBe(1);
});

test('the last allowed view physically deletes the secret', function () {
    $secret = Secret::factory()->views(2)->create();

    $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")->assertOk();
    $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")->assertOk();

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();

    $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")
        ->assertNotFound()
        ->assertExactJson(UNAVAILABLE);
});

test('a one-time secret is deleted by its first claim', function () {
    $secret = Secret::factory()->create();

    $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")->assertOk();

    expect(Secret::query()->count())->toBe(0);
});

test('an expired secret is unavailable and deleted on claim', function () {
    $secret = Secret::factory()->views(5)->create(['expires_at' => now()->addMinute()]);

    $this->travel(61)->seconds();

    $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")
        ->assertNotFound()
        ->assertExactJson(UNAVAILABLE);

    expect(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();
});

test('unknown, malformed and exhausted secrets are indistinguishable', function (Closure $publicId) {
    $response = $this->postJson('/api/v1/secrets/'.$publicId().'/claim');

    $response->assertNotFound()->assertExactJson(UNAVAILABLE);
})->with([
    'unknown' => fn () => Secret::generatePublicId(),
    'too short' => fn () => 'abc',
    'invalid characters' => fn () => str_repeat('%', 22),
    'deleted after use' => function () {
        $secret = Secret::factory()->create();
        test()->postJson("/api/v1/secrets/{$secret->public_id}/claim");

        return $secret->public_id;
    },
]);

test('claim responses are not cacheable', function () {
    $secret = Secret::factory()->create();

    $response = $this->postJson("/api/v1/secrets/{$secret->public_id}/claim")->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('claims cannot be triggered with GET', function () {
    $secret = Secret::factory()->create();

    $this->getJson("/api/v1/secrets/{$secret->public_id}/claim")->assertMethodNotAllowed();

    expect($secret->fresh()->view_count)->toBe(0);
});

test('claiming is rate limited per IP', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/api/v1/secrets/'.Secret::generatePublicId().'/claim')->assertNotFound();
    }

    $this->postJson('/api/v1/secrets/'.Secret::generatePublicId().'/claim')->assertTooManyRequests();
});
