<?php

test('the default brand shows the built-in mark and the app name', function () {
    config(['app.name' => 'Acme Secrets']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Acme Secrets</title>', false)
        ->assertSee('class="brand-mark"', false)
        ->assertSee('<span>Acme Secrets</span>', false)
        ->assertSee('<link rel="icon" href="/favicon.svg">', false);
});

test('a custom logo and favicon replace the built-in mark', function () {
    config([
        'branding.logo' => '/branding/logo.svg',
        'branding.favicon' => '/branding/favicon.png',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<img class="brand-logo" src="/branding/logo.svg"', false)
        ->assertDontSee('class="brand-mark"', false)
        ->assertSee('<link rel="icon" href="/branding/favicon.png">', false);
});

test('the name next to the logo can be hidden', function () {
    config(['app.name' => 'Acme Secrets', 'branding.show_name' => false]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('<span>Acme Secrets</span>', false)
        ->assertSee('aria-label="Acme Secrets – Startseite"', false);
});
