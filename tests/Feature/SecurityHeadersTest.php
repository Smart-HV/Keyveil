<?php

use App\Models\Secret;

dataset('responses', [
    'create page' => fn () => test()->get('/'),
    'secret page' => fn () => test()->get('/s/'.Secret::generatePublicId()),
    'manage page' => fn () => test()->get('/manage/'.Secret::generatePublicId()),
    'claim api' => fn () => test()->postJson('/api/v1/secrets/'.Secret::generatePublicId().'/claim'),
    'create api' => fn () => test()->postJson('/api/v1/secrets', validSecretPayload()),
    'not found page' => fn () => test()->get('/does-not-exist'),
]);

test('every response carries the security headers', function ($response) {
    $response
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'none'")
        ->toContain("script-src 'self' 'wasm-unsafe-eval'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'none'")
        ->not->toContain('unsafe-inline')
        ->not->toContain("'unsafe-eval'");
})->with('responses');

test('HSTS is not sent outside production', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});

test('production redirects HTTP to HTTPS and sends HSTS', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://localhost/s/'.Secret::generatePublicId())
        ->assertRedirect()
        ->assertStatus(301);

    $this->get('https://localhost/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('pages contain no external assets or inline scripts', function (string $uri) {
    $html = $this->get($uri)->assertOk()->getContent();

    expect($html)
        ->not->toMatch('/<(script|link)[^>]+(src|href)="https?:\/\/(?!localhost)/i')
        ->not->toMatch('/<script(?![^>]*\bsrc=)[^>]*>/i')
        ->not->toMatch('/\son[a-z]+="/i')
        ->not->toContain('style=');
})->with([
    '/',
    '/s/AAAAAAAAAAAAAAAAAAAAAA',
    '/manage/AAAAAAAAAAAAAAAAAAAAAA',
]);

test('secret pages render without touching the database', function () {
    $secret = Secret::factory()->create();

    $this->get('/s/'.$secret->public_id)->assertOk();
    $this->get('/s/'.Secret::generatePublicId())->assertOk();

    expect($secret->fresh()->view_count)->toBe(0);
});
