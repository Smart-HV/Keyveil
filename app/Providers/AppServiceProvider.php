<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        URL::forceHttps(app()->isProduction());

        TrustProxies::at(config('secrets.trusted_proxies'));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Per-IP rate limiters for the secret API. Keys contain only the limiter
     * name and the client IP, never secret IDs or payload data.
     */
    protected function configureRateLimiting(): void
    {
        $limits = config('secrets.rate_limits');

        RateLimiter::for('secret-api', fn (Request $request) => Limit::perMinute($limits['api_per_minute'])->by($request->ip()));

        RateLimiter::for('secret-create', fn (Request $request) => [
            Limit::perMinute($limits['create_per_minute'])->by('minute:'.$request->ip()),
            Limit::perHour($limits['create_per_hour'])->by('hour:'.$request->ip()),
        ]);

        RateLimiter::for('secret-claim', fn (Request $request) => Limit::perMinute($limits['claim_per_minute'])->by($request->ip()));

        RateLimiter::for('secret-delete', fn (Request $request) => Limit::perMinute($limits['delete_per_minute'])->by($request->ip()));
    }
}
