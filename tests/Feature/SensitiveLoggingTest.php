<?php

use App\Models\Secret;
use Database\Factories\SecretFactory;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

/**
 * Collect everything written to the log, including exception context.
 *
 * @return Closure(): string
 */
function captureLogs(): Closure
{
    $logged = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $context = $event->context;
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $logged[] = $event->message.' '.json_encode($context);

        if ($exception instanceof Throwable) {
            $logged[] = (string) $exception;
        }
    });

    return function () use (&$logged): string {
        return implode("\n", $logged);
    };
}

test('the secret lifecycle writes no sensitive data to the log', function () {
    $logs = captureLogs();

    $payload = validSecretPayload(['max_views' => 2]);

    $publicId = $this->postJson('/api/v1/secrets', $payload)->assertCreated()->json('data.public_id');
    $this->postJson("/api/v1/secrets/{$publicId}/claim")->assertOk();
    $this->deleteJson("/api/v1/secrets/{$publicId}", ['delete_token_hash' => $payload['delete_token_hash']])->assertNoContent();
    $this->postJson("/api/v1/secrets/{$publicId}/claim")->assertNotFound();

    expect($logs())
        ->not->toContain(substr($payload['envelope']['ciphertext'], 0, 12))
        ->not->toContain(substr($payload['delete_token_hash'], 0, 12));
});

test('server errors log and render neither the request body nor the envelope', function () {
    config(['app.debug' => false]);
    $logs = captureLogs();

    Secret::creating(fn () => throw new RuntimeException('Simulated storage failure'));

    $payload = validSecretPayload([
        'envelope' => SecretFactory::envelope(passwordProtected: true),
        'password_protected' => true,
    ]);

    $response = $this->postJson('/api/v1/secrets', $payload)->assertServerError();

    foreach ([$payload['envelope']['ciphertext'], $payload['envelope']['iv'], $payload['delete_token_hash']] as $sensitive) {
        expect($logs())->not->toContain(substr($sensitive, 0, 12));
        expect($response->getContent())->not->toContain(substr($sensitive, 0, 12));
    }

    expect($logs())->toContain('Simulated storage failure');
});
