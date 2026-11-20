<?php

namespace App\Http\Controllers;

use App\Exceptions\CartException;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\CheckoutRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Services\CartService;
use App\Services\CatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer cart (Phase 7). Real cart only while the live catalogue is on
 * (decision 1); otherwise the existing static Cart page is shown unchanged.
 * Login + CSRF come from the route group; every item lookup is limited to the
 * signed-in customer's own cart (404 otherwise).
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CatalogueService $catalogue,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->catalogue->isLive()) {
            return view('pages.cart');
        }

        return view('pages.cart', [
            'cartLive' => true,
            'cart' => $this->carts->summary($request->user()),
        ]);
    }

    public function store(AddToCartRequest $request): JsonResponse|RedirectResponse
    {
        abort_unless($this->catalogue->isLive(), 404);

        try {
            $item = $this->carts->add(
                $request->user(),
                $request->input('plan'),
                (int) $request->input('quantity'),
                $request->date('trip_start_date'),
            );
        } catch (CartException $e) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 422)
                : back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        $message = 'Added '.CartService::lineTitle($item->plan).' ('.$item->plan->title().') to your cart.';

        if ($request->expectsJson()) {
            // The country page opens the cart next; show the confirmation there too.
            $request->session()->flash('status', $message);

            return response()->json([
                'success' => true,
                'message' => $message,
                'cart_url' => route('cart'),
                'item_count' => (int) $item->cart->items()->sum('quantity'),
            ], 201);
        }

        return redirect()->route('cart')->with('status', $message);
    }

    public function update(UpdateCartItemRequest $request, int $item): RedirectResponse
    {
        abort_unless($this->catalogue->isLive(), 404);

        $this->carts->updateQuantity($request->user(), $item, (int) $request->input('quantity'));

        return redirect()->route('cart')->with('status', 'Quantity updated.');
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        abort_unless($this->catalogue->isLive(), 404);

        $this->carts->remove($request->user(), $item);

        return redirect()->route('cart')->with('status', 'Item removed from your cart.');
    }

    /** Optional GSTIN — stored only; no GST or other tax is calculated (decision 6). */
    public function gstin(Request $request): RedirectResponse
    {
        abort_unless($this->catalogue->isLive(), 404);

        $request->merge(['gstin' => strtoupper(str_replace(' ', '', (string) $request->input('gstin'))) ?: null]);
        $data = $request->validate(
            ['gstin' => ['nullable', 'string', 'regex:'.CheckoutRequest::GSTIN_PATTERN]],
            ['gstin.regex' => 'Enter a valid 15-character GST number, e.g. 22AAAAA0000A1Z5.'],
        );

        $this->carts->saveGstin($request->user(), $data['gstin']);

        return redirect()->route('cart')->with('status', $data['gstin'] ? 'GST number saved.' : 'GST number removed.');
    }
}
