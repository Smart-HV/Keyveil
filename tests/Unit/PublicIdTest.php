<?php

use App\Models\Secret;

test('public ids are 22 url-safe characters encoding 16 random bytes', function () {
    $publicId = Secret::generatePublicId();

    expect($publicId)->toMatch('/^[A-Za-z0-9_-]{22}$/');

    $bytes = base64_decode(strtr($publicId, '-_', '+/').'==', strict: true);

    expect(strlen($bytes))->toBe(16);
});

test('public ids do not repeat', function () {
    $ids = array_map(fn () => Secret::generatePublicId(), range(1, 1000));

    expect(array_unique($ids))->toHaveCount(1000);
});

test('the stored delete token hash differs from the client supplied hash', function () {
    $clientHash = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    expect(Secret::hashDeleteToken($clientHash))
        ->toBe(hash('sha256', $clientHash))
        ->not->toBe($clientHash);
});
