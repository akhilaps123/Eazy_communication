<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Sets a new password from an emailed reset link (pages/reset-password.blade.php).
 *
 * Uses Laravel's password broker: the token is stored hashed in
 * password_reset_tokens, expires after 60 minutes (config/auth.php) and is
 * deleted once used, so it cannot be reused.
 */
class ResetPasswordController extends Controller
{
    public const INVALID_MESSAGE = 'This password reset link is invalid or has expired. Please request a new one.';

    public const SUCCESS_MESSAGE = 'Your password has been reset. You can now sign in.';

    public function create(Request $request, string $token): View
    {
        return view('pages.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'Password must be at least :min characters.',
            'password.max' => 'Password must not be longer than :max characters.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,               // hashed by the model's "hashed" cast
                    'remember_token' => Str::random(60),   // signs out "remember me" sessions
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => self::SUCCESS_MESSAGE]);
            }

            return redirect()->route('login')->with('status', self::SUCCESS_MESSAGE);
        }

        // Invalid token, expired token and unknown email all get the same message.
        if ($request->expectsJson()) {
            // Rendered as a 422 JSON error by bootstrap/app.php.
            throw ValidationException::withMessages(['email' => self::INVALID_MESSAGE]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::INVALID_MESSAGE]);
    }
}
