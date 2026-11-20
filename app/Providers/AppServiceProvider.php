<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Password policy used by registration and password reset.
        // max(72): bcrypt only uses the first 72 bytes of a password.
        // (A breached-password check, ->uncompromised(), would call an external
        // service, so it is not enabled without approval.)
        Password::defaults(fn () => Password::min(8)->max(72));

        // Abuse protection for public account forms (per IP address).
        // Login has its own, friendlier per-email+IP limiter in LoginRequest.
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Cart changes and checkout submissions, per signed-in customer (Phase 7).
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        // Razorpay payment start / verify (per customer) and webhooks (per sender IP) — Phase 8.
        RateLimiter::for('payment', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));

        // Customer API (routes/api.php): 60 requests per minute per signed-in
        // customer (Bearer token), or per IP address for guests.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));
    }
}
