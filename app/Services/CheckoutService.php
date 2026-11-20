<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns the customer's cart into an order awaiting payment (Phase 7).
 *
 * No payment is taken, no payment record is created and nothing is ordered
 * from eSIM Go here — the order simply waits for the payment phase.
 *
 * Duplicate protection: (1) one-time checkout token, remembered for 24 hours
 * once used; (2) the cart row is locked for the whole transaction; (3) the cart
 * is emptied in the same transaction that creates the order.
 */
class CheckoutService
{
    private const TOKEN_TTL_SECONDS = 86400;

    public function __construct(private readonly CartService $carts) {}

    /** The order already created with this checkout token (double submit / refresh), if any. */
    public function orderForToken(User $user, string $token): ?Order
    {
        $number = Cache::get($this->tokenKey($user, $token));

        return $number ? Order::query()->where('user_id', $user->id)->where('order_number', $number)->first() : null;
    }

    /**
     * @param  array{billing_country: string, billing_postal_code: string, billing_gstin: ?string}  $billing
     *
     * @throws CartException when the order cannot be created (nothing is saved)
     */
    public function placeOrder(User $user, array $billing, string $expectedTotal, string $token): Order
    {
        // Serialise submissions that carry the same token (double click / two tabs).
        return Cache::lock('checkout-lock:'.$user->id, 20)->block(10, function () use ($user, $billing, $expectedTotal, $token) {
            if ($existing = $this->orderForToken($user, $token)) {
                return $existing;   // already placed — never create a second order
            }

            $order = DB::transaction(fn () => $this->createOrder($user, $billing, $expectedTotal));

            Cache::put($this->tokenKey($user, $token), $order->order_number, self::TOKEN_TTL_SECONDS);

            return $order;
        });
    }

    private function createOrder(User $user, array $billing, string $expectedTotal): Order
    {
        // 1. Lock the customer's cart row and recalculate everything from the catalogue.
        $summary = $this->carts->summary($user, lockCart: true);

        if ($summary['is_empty']) {
            throw new CartException('Your cart is empty.');
        }
        if ($summary['has_unavailable']) {
            throw new CartException('Some items in your cart are no longer available. Please remove them to continue.');
        }
        if (bccomp($summary['subtotal'], $expectedTotal, 2) !== 0) {
            throw new CartException('Prices in your cart have changed since you opened checkout. Please review your cart before placing the order.', 'total');
        }

        $gstin = $billing['billing_gstin'] ?? $summary['gstin'];

        // 2. Create the order (awaiting payment, no expiry — decisions 4 and 6).
        $order = $this->createWithUniqueNumber([
            'user_id' => $user->id,
            'currency' => $summary['currency'],
            'subtotal' => $summary['subtotal'],
            'discount_total' => '0.00',   // no promo codes (decision 5)
            'tax_total' => '0.00',        // no tax calculation (decision 6)
            'total' => $summary['subtotal'],
            'tax_rate' => null,
            'payment_status' => Order::PAYMENT_PENDING,
            'fulfillment_status' => Order::FULFILLMENT_NOT_STARTED,
            'billing_name' => $user->name,
            'billing_email' => $user->email,
            'billing_country' => $billing['billing_country'],
            'billing_postal_code' => $billing['billing_postal_code'],
            'billing_gstin' => $gstin,
            'expires_at' => null,
        ]);

        // 3. Immutable item snapshots (OrderItem refuses later changes).
        foreach ($summary['lines'] as $line) {
            $plan = $line['plan'];
            $bundle = $plan->providerBundle;
            $order->items()->create([
                'plan_id' => $plan->id,
                'plan_name' => $line['title'].' · '.$line['subtitle'],
                'bundle_code' => $bundle->bundle_code,                 // internal only
                'unit_price' => $line['price']['amount'],
                'quantity' => $line['quantity'],
                'line_total' => $line['line_total'],
                'unit_provider_cost' => $bundle->provider_cost,        // internal only
                'provider_currency' => $bundle->provider_currency,
                'trip_start_date' => $line['item']->trip_start_date?->format('Y-m-d'),
            ]);
        }

        // 4. Empty the cart only now, inside the same transaction.
        $summary['cart']->items()->delete();
        $summary['cart']->update(['billing_gstin' => $gstin]);

        return $order;
    }

    private function createWithUniqueNumber(array $attributes): Order
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn () => Order::query()->create($attributes + ['order_number' => OrderNumber::generate()]));
            } catch (UniqueConstraintViolationException) {
                // Extremely unlikely collision between check and insert — try a new number.
            }
        }

        throw new \RuntimeException('Could not create a unique order number.');
    }

    private function tokenKey(User $user, string $token): string
    {
        return 'checkout-token:'.$user->id.':'.hash('sha256', $token);
    }
}
