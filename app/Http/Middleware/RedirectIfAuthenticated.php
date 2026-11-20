<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "guest" middleware (login, register, password pages).
 *
 * Browsers: unchanged — a signed-in customer is redirected to My Account.
 * JSON requests (e.g. Postman): a 409 JSON message instead of a redirect
 * to an HTML page.
 */
class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->expectsJson()) {
            foreach (empty($guards) ? [null] : $guards as $guard) {
                if (Auth::guard($guard)->check()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are already signed in. Log out first to use this page.',
                    ], 409);
                }
            }
        }

        return parent::handle($request, $next, ...$guards);
    }
}
