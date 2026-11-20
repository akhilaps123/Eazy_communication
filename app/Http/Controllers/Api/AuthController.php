<?php

namespace App\Http\Controllers\Api;

use App\Actions\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer API authentication with Sanctum Bearer tokens (routes/api.php).
 *
 * Completely separate from the website's session login: no cookies, no CSRF,
 * no session. Only tokens are accepted (config/sanctum.php "guard" => []).
 *
 * - Tokens are stored as SHA-256 hashes; the plain token is returned once, by login.
 * - Tokens expire after 7 days and carry only the "customer" ability.
 * - Only customer accounts can get tokens (admin API is Phase 9).
 */
class AuthController extends Controller
{
    public const TOKEN_ABILITY = 'customer';

    public const TOKEN_LIFETIME_DAYS = 7;

    public const NOT_A_CUSTOMER_MESSAGE = 'This account cannot sign in to the customer API.';

    /** POST /api/register — creates the account; no token (customers log in separately). */
    public function register(RegisterRequest $request, RegisterCustomer $registerCustomer): JsonResponse
    {
        $registerCustomer->handle($request->validated(), signingIn: false);

        return response()->json([
            'success' => true,
            'message' => 'Customer registered successfully.',
        ], 201);
    }

    /** POST /api/login — returns a new Bearer token. */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->validate(['device_name' => ['nullable', 'string', 'max:100']]);

        $user = $request->validateCredentials();   // 422 / 429 lockout; never a token

        if ($user->role !== User::ROLE_CUSTOMER) {
            return response()->json(['success' => false, 'message' => self::NOT_A_CUSTOMER_MESSAGE], 403);
        }

        $token = $user->createToken(
            $request->input('device_name') ?: 'api',
            [self::TOKEN_ABILITY],
            now()->addDays(self::TOKEN_LIFETIME_DAYS),
        );

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => self::publicUser($user),
        ]);
    }

    /** GET /api/user — the signed-in customer. */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => self::publicUser($request->user()),
        ]);
    }

    /** POST /api/logout — revokes only the token used for this request. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    /** Only these three fields — never the password hash, role, phone, GSTIN or tokens. */
    private static function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
