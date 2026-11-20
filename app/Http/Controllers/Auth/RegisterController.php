<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Customer registration (pages/registration.blade.php).
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('pages.registration');
    }

    public function store(RegisterRequest $request, RegisterCustomer $registerCustomer): RedirectResponse|JsonResponse
    {
        $user = $registerCustomer->handle($request->validated(), signingIn: true);

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            // The new customer is also signed in (same session cookie as the browser flow).
            return response()->json([
                'success' => true,
                'message' => 'Customer registered successfully.',
            ], 201);
        }

        return redirect()->route('my-account');
    }
}
