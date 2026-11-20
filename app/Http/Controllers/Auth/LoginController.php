<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Customer sign-in and sign-out (pages/login.blade.php).
 *
 * Browser form posts get redirects, as before. Requests sending
 * "Accept: application/json" (e.g. Postman) get JSON instead; both use the
 * same session cookie and CSRF protection.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('pages.login');
    }

    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        // New session ID after login prevents session-fixation attacks.
        $request->session()->regenerate();

        // last_login_at is not mass-assignable; set it directly.
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                // Only these three fields — never the password hash, role or tokens.
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return redirect()->intended(route('my-account'));
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            // The new XSRF-TOKEN cookie is attached to this response automatically.
            return response()->json([
                'success' => true,
                'message' => 'Logout successful.',
            ]);
        }

        return redirect()->route('home');
    }
}
