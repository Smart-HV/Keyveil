<?php

use App\Models\Secret;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;

test('pruning removes only expired secrets', function () {
    $expired = Secret::factory()->expired()->count(3)->create();
    $active = Secret::factory()->create();

    $this->artisan('model:prune', ['--model' => [Secret::class]])->assertSuccessful();

    expect(Secret::query()->pluck('id')->all())->toBe([$active->id]);
    expect(Secret::query()->whereIn('id', $expired->pluck('id'))->exists())->toBeFalse();
});

test('pruning is scheduled every minute on one server', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'model:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->onOneServer)->toBeTrue();
});

test('the database rejects invalid lifecycle values', function (array $attributes) {
    Secret::factory()->create($attributes);
})->with([
    'zero max views' => [['max_views' => 0]],
    'negative view count' => [['view_count' => -1]],
    'view count reached max views' => [['max_views' => 2, 'view_count' => 2]],
    'zero crypto version' => [['crypto_version' => 0]],
])->throws(QueryException::class);
