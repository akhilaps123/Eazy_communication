<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * "Forgot password" — emails a reset link (pages/forgot-password.blade.php).
 *
 * The form field is named "identity" (label: "User name or email"). Customers
 * have no user names, so an email address is required (report decision J16).
 *
 * The response is the same whether or not an account exists, so the form
 * cannot be used to discover registered email addresses. Laravel's password
 * broker also limits one email per address per 60 seconds (config/auth.php).
 */
class ForgotPasswordController extends Controller
{
    public const STATUS_MESSAGE = 'If an account exists for that email address, we have sent a password reset link to it.';

    public function create(): View
    {
        return view('pages.forgot-password');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge(['identity' => Str::lower(trim((string) $request->input('identity')))]);

        $request->validate(
            ['identity' => ['required', 'string', 'email', 'max:255']],
            [
                'identity.required' => 'Please enter the email address you registered with.',
                'identity.email' => 'Please enter the email address you registered with.',
            ],
        );

        try {
            // Result deliberately ignored (sent / unknown user / throttled all look the same).
            Password::sendResetLink(['email' => $request->input('identity')]);
        } catch (Throwable $e) {
            report($e);   // e.g. mail transport failure — logged, not shown to the visitor
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => self::STATUS_MESSAGE]);
        }

        return back()->with('status', self::STATUS_MESSAGE);
    }
}
