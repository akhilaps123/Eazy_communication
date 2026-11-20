<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\CustomerEsimController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer API — Bearer token authentication (Laravel Sanctum)
|--------------------------------------------------------------------------
| All URLs start with /api. Uses the "api" middleware group: no session,
| no cookies, no CSRF. Send "Authorization: Bearer <token>" from /api/login.
| The website's session login (routes/web.php) is separate and unchanged.
|
| Future customer endpoints (orders, eSIMs) must load data only through
| $request->user() and the existing policies (OrderPolicy, EsimPolicy).
*/

Route::middleware('throttle:api')->name('api.')->group(function () {
    // Public catalogue (Phase 5) — no login needed; sellable plans only, customer-safe fields only.
    Route::get('/countries', [CatalogueController::class, 'countries'])->name('countries.index');
    Route::get('/countries/{slug}/plans', [CatalogueController::class, 'countryPlans'])->name('countries.plans');
    Route::get('/plans/{slug}', [CatalogueController::class, 'plan'])->name('plans.show');

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');   // lockout in LoginRequest

    Route::middleware(['auth:sanctum', 'abilities:'.AuthController::TOKEN_ABILITY])->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Phase 9E: the token owner's orders and eSIMs (same rules as the website portal).
        Route::get('/my-orders', [CustomerEsimController::class, 'orders'])->name('my-orders');
        Route::get('/my-esims', [CustomerEsimController::class, 'esims'])->name('my-esims');
        Route::get('/my-esims/{esim}', [CustomerEsimController::class, 'esim'])->whereNumber('esim')->name('my-esims.show');
    });
});
