<?php

use App\Http\Controllers\Account\MyEsimsController;
use App\Http\Controllers\Account\MyOrdersController;
use App\Http\Controllers\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PlanBundleController as AdminPlanBundleController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PlanPriceController as AdminPlanPriceController;
use App\Http\Controllers\Admin\ProviderBundleController as AdminProviderBundleController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages (no login needed)
|--------------------------------------------------------------------------
| Static Blade views for now. When a page needs data (plans, blog posts...),
| swap Route::view() for a controller without changing the URL or name.
*/

// Home, New eSIM and country pages use the catalogue controller: the existing
// static design while the live catalogue is off (default), database content when on.
Route::get('/', [CatalogueController::class, 'home'])->name('home');
Route::view('/about', 'pages.about')->name('about');

// Blog & guides
Route::view('/blog', 'pages.blog')->name('blog');
Route::view('/blog-detail', 'pages.blog-detail')->name('blog-detail');
Route::view('/guides', 'pages.guides')->name('guides');

// eSIM catalogue
Route::get('/new-esim', [CatalogueController::class, 'newEsim'])->name('new-esim');
Route::get('/japan-esim', [CatalogueController::class, 'japanEsim'])->name('japan-esim');   // redirects to /esim/japan when live
Route::get('/esim/{country:slug}', [CatalogueController::class, 'country'])->name('esim.country');   // 404 while the live catalogue is off

// Support & usage (Check Usage stays public until decision J13 is made)
Route::view('/esim-support', 'pages.esim-support')->name('esim-support');
Route::view('/check-usage', 'pages.check-usage')->name('check-usage');
Route::post('/check-usage', [UsageController::class, 'store'])->name('check-usage.submit');   // not implemented yet (501)

/*
|--------------------------------------------------------------------------
| Authentication pages — guests only
|--------------------------------------------------------------------------
| Signed-in customers are redirected to My Account (bootstrap/app.php).
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.submit');   // throttled in LoginRequest

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.submit');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    // "password.reset" is the name Laravel's reset email links to.
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Customer pages — login required
|--------------------------------------------------------------------------
| Guests are redirected to /login and returned here after signing in.
| Pages only ever load the signed-in customer's own data; record-level
| access uses App\Policies (OrderPolicy, EsimPolicy).
*/

Route::middleware('auth')->group(function () {
    Route::view('/my-account', 'pages.my-account')->name('my-account');

    // Phase 9E: customer portal. Every record is loaded through App\Support\CustomerEsims
    // (owner + paid with a server-verified payment + fulfilled) and EsimPolicy; others get 404.
    Route::get('/my-orders', [MyOrdersController::class, 'index'])->name('my-orders');
    Route::get('/my-esims', [MyEsimsController::class, 'index'])->name('my-esims');
    Route::get('/my-esims/{esim}', [MyEsimsController::class, 'show'])->whereNumber('esim')->name('my-esims.show');
    Route::get('/my-esims/{esim}/qr', [MyEsimsController::class, 'qr'])->whereNumber('esim')->middleware('throttle:60,1')->name('my-esims.qr');

    // Login is mandatory for cart and checkout (confirmed business decision).
    // Phase 7: real cart/checkout while the live catalogue is on; the static
    // pages (and a 501 for POST /checkout) while it is off.
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::middleware('throttle:cart')->group(function () {
        Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.items.update');
        Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.items.destroy');
        Route::post('/cart/gstin', [CartController::class, 'gstin'])->name('cart.gstin');
    });

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.submit');

    // Order Received page — owner only (OrderPolicy), customer-safe fields only.
    Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');

    // Phase 8: Razorpay payment for an order (TEST MODE). Owner only; amounts come from the order.
    Route::middleware('throttle:payment')->group(function () {
        Route::post('/orders/{order:order_number}/payment', [PaymentController::class, 'initiate'])->name('orders.payment.initiate');
        Route::post('/orders/{order:order_number}/payment/verify', [PaymentController::class, 'verify'])->name('orders.payment.verify');
        Route::post('/orders/{order:order_number}/payment/failure', [PaymentController::class, 'failure'])->name('orders.payment.failure');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Admin panel — admins only (Phase 5: catalogue management)
|--------------------------------------------------------------------------
| Session login + CSRF like the rest of the website. Guests are sent to
| /login; signed-in customers get 403 (EnsureUserIsAdmin). There is no
| route that deletes a plan (decision D10).
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::resource('countries', AdminCountryController::class);
    Route::post('countries/{country}/toggle', [AdminCountryController::class, 'toggle'])->name('countries.toggle');

    Route::resource('plans', AdminPlanController::class)->except('destroy');
    Route::post('plans/{plan}/activate', [AdminPlanController::class, 'activate'])->name('plans.activate');
    Route::post('plans/{plan}/deactivate', [AdminPlanController::class, 'deactivate'])->name('plans.deactivate');

    // Phase 9B-1: provider bundles (read-only) and linking a plan to an existing bundle.
    Route::get('provider-bundles', [AdminProviderBundleController::class, 'index'])->name('provider-bundles.index');
    Route::get('provider-bundles/search', [AdminProviderBundleController::class, 'search'])->name('provider-bundles.search');
    Route::get('provider-bundles/{providerBundle}', [AdminProviderBundleController::class, 'show'])->whereNumber('providerBundle')->name('provider-bundles.show');
    Route::get('plans/{plan}/provider-bundle', [AdminPlanBundleController::class, 'edit'])->name('plans.provider-bundle.edit');
    Route::put('plans/{plan}/provider-bundle', [AdminPlanBundleController::class, 'update'])->name('plans.provider-bundle.update');

    // Phase 9F: orders & fulfilment monitoring, plus two audited actions that delegate to
    // FulfilmentService: read-only reconciliation and retry of a confirmed failed purchase.
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:order_number}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('orders/{order:order_number}/reconcile', [AdminOrderController::class, 'reconcile'])->name('orders.reconcile');
        Route::post('orders/{order:order_number}/retry', [AdminOrderController::class, 'retry'])->name('orders.retry');
        // Phase 9D: re-create an eSIM's QR code from its stored activation details (no provider call).
        Route::post('orders/{order:order_number}/esims/{esim}/qr', [AdminOrderController::class, 'regenerateQr'])->name('orders.esims.qr.regenerate');
        // Phase 9E: re-queue a failed / unconfirmed "eSIM ready" email (never touches fulfilment).
        Route::post('orders/{order:order_number}/notifications/esim-ready', [AdminOrderController::class, 'retryEsimReadyEmail'])->name('orders.notifications.esim-ready');
    });
    // Phase 9D: the QR code image (private, decrypted on request; eSIM must belong to the order).
    Route::get('orders/{order:order_number}/esims/{esim}/qr', [AdminOrderController::class, 'qrImage'])->middleware('throttle:60,1')->name('orders.esims.qr');

    Route::scopeBindings()->group(function () {
        Route::post('plans/{plan}/prices', [AdminPlanPriceController::class, 'store'])->name('plans.prices.store');
        Route::get('plans/{plan}/prices/{price}/edit', [AdminPlanPriceController::class, 'edit'])->name('plans.prices.edit');
        Route::put('plans/{plan}/prices/{price}', [AdminPlanPriceController::class, 'update'])->name('plans.prices.update');
        Route::post('plans/{plan}/prices/{price}/approve', [AdminPlanPriceController::class, 'approve'])->name('plans.prices.approve');
        Route::post('plans/{plan}/prices/{price}/reject', [AdminPlanPriceController::class, 'reject'])->name('plans.prices.reject');
        Route::post('plans/{plan}/prices/{price}/end', [AdminPlanPriceController::class, 'end'])->name('plans.prices.end');
        Route::post('plans/{plan}/prices/{price}/deactivate', [AdminPlanPriceController::class, 'deactivate'])->name('plans.prices.deactivate');
    });
});

/*
|--------------------------------------------------------------------------
| Payment gateway webhooks (Phase 8)
|--------------------------------------------------------------------------
| No login and no CSRF token: Razorpay calls this server-to-server. Every
| request must carry a valid X-Razorpay-Signature (HMAC of the raw body with
| RAZORPAY_WEBHOOK_SECRET); anything else is rejected with 400.
*/

Route::post('/webhooks/razorpay', RazorpayWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])
    ->middleware('throttle:webhooks')
    ->name('webhooks.razorpay');
