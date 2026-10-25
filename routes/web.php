<?php

use App\Http\Controllers\Api\V1\SecretController;
use App\Http\Middleware\LimitRequestBody;
use Illuminate\Support\Facades\Route;

Route::pattern('secret', '[A-Za-z0-9_-]{22}');

Route::view('/', 'secrets.create')->name('home');

// Pages never touch the database: rendering must not reveal or consume anything.
Route::view('/s/{secret}', 'secrets.show')->name('secrets.show');
Route::view('/manage/{secret}', 'secrets.manage')->name('secrets.manage');

Route::prefix('api/v1')
    ->middleware(['throttle:secret-api', LimitRequestBody::class])
    ->name('api.v1.')
    ->group(function () {
        Route::post('secrets', [SecretController::class, 'store'])
            ->middleware('throttle:secret-create')
            ->name('secrets.store');

        Route::post('secrets/{secret}/claim', [SecretController::class, 'claim'])
            ->middleware('throttle:secret-claim')
            ->name('secrets.claim');

        Route::delete('secrets/{secret}', [SecretController::class, 'destroy'])
            ->middleware('throttle:secret-delete')
            ->name('secrets.destroy');
    });
