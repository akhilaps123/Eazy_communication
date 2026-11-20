<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Customer API with Sanctum Bearer tokens (prefix /api, "api" middleware
        // group: no session, no CSRF). Separate from the website's session login.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // 'admin' = signed-in user whose role is admin. Not used by any route yet
            // (admin panel is Phase 9); being signed in alone never grants admin access.
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,

            // Same as Laravel's "guest" middleware for browsers; JSON requests from a
            // signed-in user get a 409 JSON message instead of a redirect.
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

            // Sanctum token abilities, e.g. 'abilities:customer' (routes/api.php).
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);

        // Guests hitting protected pages go to the login page;
        // signed-in customers opening login/register pages go to My Account.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('my-account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON for every /api request, and for website requests sending
        // "Accept: application/json" (e.g. Postman).
        $wantsJson = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($wantsJson);

        /*
        | JSON error format: { "success": false, "message": "...", "errors"?: {...} }.
        | Each callback returns null for normal browser requests, so the
        | website's redirects and error pages are unchanged.
        */

        // 422 validation errors (and the 429 login lockout, which uses the same exception).
        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        // 401 instead of a redirect to the login page.
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $request->is('api/*')
                    ? 'Missing, invalid, revoked or expired access token. Log in again to get a new token.'
                    : 'Please sign in to continue.',
            ], 401);
        });

        // 403 for API tokens without the required ability. (Laravel turns the
        // AuthorizationException into a 403 HTTP exception before this runs.)
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') || $e->getStatusCode() !== 403) {
                return null;
            }

            return response()->json(['success' => false, 'message' => 'This access token is not allowed to do that.'], 403);
        });

        // 419 (missing/expired CSRF token) and 429 (rate limits) only.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request) || ! in_array($e->getStatusCode(), [419, 429], true)) {
                return null;
            }

            $message = $e->getStatusCode() === 419
                ? 'CSRF token missing or expired. Request a page (e.g. GET /login) and send its XSRF-TOKEN cookie value in the X-XSRF-TOKEN header.'
                : 'Too many requests. Please try again in '.($e->getHeaders()['Retry-After'] ?? 60).' seconds.';

            return response()->json(['success' => false, 'message' => $message], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();
