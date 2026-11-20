<?php

namespace App\Http\Controllers;

use App\Exceptions\CartException;
use App\Http\Requests\Cart\CheckoutRequest;
use App\Services\CartService;
use App\Services\CatalogueService;
use App\Services\CheckoutService;
use App\Support\BillingCountries;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Checkout (Phase 7). While the live catalogue is off the existing static page
 * is shown and POST keeps answering 501. When live: a real summary, and
 * "Place order" creates an order awaiting payment — no payment is taken.
 */
class CheckoutController extends Controller
{
    private const TOKEN_KEY = 'checkout.token';

    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly CatalogueService $catalogue,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->catalogue->isLive()) {
            return view('pages.checkout');
        }

        $summary = $this->carts->summary($request->user());
        if ($summary['is_empty']) {
            return redirect()->route('cart')->withErrors(['cart' => 'Your cart is empty. Add a plan before checking out.']);
        }
        if ($summary['has_unavailable']) {
            return redirect()->route('cart')->withErrors(['cart' => 'Some items in your cart are no longer available. Please remove them to continue.']);
        }

        // One-time token for this checkout (duplicate-submission protection).
        $token = $request->session()->get(self::TOKEN_KEY) ?? tap(Str::random(40), fn ($t) => $request->session()->put(self::TOKEN_KEY, $t));

        return view('pages.checkout', [
            'checkoutLive' => true,
            'cart' => $summary,
            'checkoutToken' => $token,
            'billingCountries' => BillingCountries::all(),
            'customer' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->catalogue->isLive(), 501, 'This feature has not been implemented yet.');

        /** @var CheckoutRequest $form */
        $form = app(CheckoutRequest::class);   // validates (redirects back with errors)
        $user = $request->user();
        $token = (string) $form->input('checkout_token');

        // Same token submitted again (double click, refresh): show the existing order.
        if ($existing = $this->checkout->orderForToken($user, $token)) {
            return redirect()->route('orders.show', $existing->order_number);
        }

        if (! hash_equals((string) $request->session()->get(self::TOKEN_KEY), $token)) {
            $request->session()->forget(self::TOKEN_KEY);

            return redirect()->route('checkout')->withErrors(['checkout_token' => 'Your checkout session expired. Please review your order and try again.']);
        }

        try {
            $order = $this->checkout->placeOrder($user, $form->billing(), $form->input('expected_total'), $token);
        } catch (CartException $e) {
            $request->session()->forget(self::TOKEN_KEY);

            return redirect()->route('cart')->withErrors(['cart' => $e->getMessage()]);
        } catch (LockTimeoutException) {
            return redirect()->route('checkout')->withErrors(['checkout' => 'Your order is already being processed. Please wait a moment and refresh.']);
        }

        $request->session()->forget(self::TOKEN_KEY);

        return redirect()->route('orders.show', $order->order_number);
    }
}
