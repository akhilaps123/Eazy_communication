<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Plan;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The customer's cart (Phase 7). One cart per customer (carts.user_id is unique).
 *
 * Cart items never store a price: every price and total is recalculated here
 * from CatalogueService (approved current USD prices only), with bcmath —
 * nothing the browser sends is trusted as a price.
 */
class CartService
{
    public const MAX_QUANTITY = 10;

    public function __construct(private readonly CatalogueService $catalogue) {}

    /** The customer's cart, created on first use (safe if two requests race). */
    public function cartFor(User $user): Cart
    {
        $cart = Cart::query()->where('user_id', $user->id)->first();
        if ($cart) {
            return $cart;
        }

        try {
            return Cart::query()->create(['user_id' => $user->id]);
        } catch (UniqueConstraintViolationException) {
            return Cart::query()->where('user_id', $user->id)->firstOrFail();
        }
    }

    /**
     * Add a sellable plan. Same plan + same trip date = quantity increases
     * (max 10 per line); a different trip date = a separate line.
     *
     * @throws CartException
     */
    public function add(User $user, string $planSlug, int $quantity, ?DateTimeInterface $tripStart): CartItem
    {
        $plan = $this->catalogue->findSellablePlan($planSlug);
        if ($plan === null) {
            throw new CartException('This plan is not available right now.', 'plan');
        }

        $cart = $this->cartFor($user);
        $date = $tripStart?->format('Y-m-d');

        return DB::transaction(function () use ($cart, $plan, $quantity, $date) {
            // Lock the cart row: concurrent adds for this customer run one at a time.
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $item = $cart->items()
                ->where('plan_id', $plan->id)
                ->when($date, fn ($q) => $q->whereDate('trip_start_date', $date), fn ($q) => $q->whereNull('trip_start_date'))
                ->first();

            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            if ($newQuantity > self::MAX_QUANTITY) {
                throw new CartException(sprintf(
                    'You can add up to %d of the same plan for the same trip date (you already have %d in your cart).',
                    self::MAX_QUANTITY, $item?->quantity ?? 0,
                ), 'quantity');
            }

            if ($item) {
                $item->update(['quantity' => $newQuantity]);

                return $item;
            }

            return $cart->items()->create([
                'plan_id' => $plan->id,
                'quantity' => $quantity,
                'trip_start_date' => $date,
            ]);
        });
    }

    /** @throws CartException */
    public function updateQuantity(User $user, int $itemId, int $quantity): CartItem
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new CartException('Quantity must be between 1 and '.self::MAX_QUANTITY.'.', 'quantity');
        }

        $item = $this->ownedItem($user, $itemId);
        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function remove(User $user, int $itemId): void
    {
        $this->ownedItem($user, $itemId)->delete();
    }

    public function saveGstin(User $user, ?string $gstin): void
    {
        $this->cartFor($user)->update(['billing_gstin' => $gstin]);
    }

    /**
     * A cart item that belongs to this customer's cart — otherwise 404
     * (changing the item ID in a request never reaches another customer's cart).
     */
    public function ownedItem(User $user, int $itemId): CartItem
    {
        return CartItem::query()
            ->whereKey($itemId)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();
    }

    /**
     * Everything the cart and checkout pages show, calculated on the server.
     *
     * @return array{cart: ?Cart, lines: Collection, subtotal: string, subtotal_formatted: string,
     *               currency: string, item_count: int, has_unavailable: bool, is_empty: bool, gstin: ?string}
     */
    public function summary(User $user, bool $lockCart = false): array
    {
        $currency = $this->catalogue->currency();
        $cart = Cart::query()->where('user_id', $user->id)->when($lockCart, fn ($q) => $q->lockForUpdate())->first();
        $items = $cart
            ? $cart->items()->with(['plan.countries' => fn ($q) => $q->ordered(), 'plan.providerBundle'])->orderBy('id')->get()
            : collect();

        // One query set for all sellable plans in the cart (existing catalogue rules).
        $sellable = $items->isEmpty()
            ? collect()
            : $this->catalogue->sellablePlans(null, $items->pluck('plan_id')->unique()->values()->all())->keyBy('id');

        $subtotal = '0.00';
        $lines = $items->map(function (CartItem $item) use ($sellable, &$subtotal) {
            $plan = $sellable->get($item->plan_id);
            $line = [
                'item' => $item,
                'plan' => $item->plan,
                'available' => $plan !== null,
                'title' => self::lineTitle($item->plan),
                'subtitle' => $item->plan->title(),
                'countries' => ($plan ?? $item->plan)->countries->pluck('name')->all(),
                'quantity' => $item->quantity,
                'price' => null,
                'line_total' => null,
                'line_total_formatted' => null,
                'compare_total_formatted' => null,
            ];

            if ($plan) {
                $price = $this->catalogue->priceData($plan->cataloguePrice);
                $lineTotal = bcmul($price['amount'], (string) $item->quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $line['plan'] = $plan;   // carries the current price (cataloguePrice)
                $line['price'] = $price;
                $line['line_total'] = $lineTotal;
                $line['line_total_formatted'] = CatalogueService::money($lineTotal, $price['currency']);
                $line['compare_total_formatted'] = $price['compare_at'] === null ? null
                    : CatalogueService::money(bcmul($price['compare_at'], (string) $item->quantity, 2), $price['currency']);
            }

            return $line;
        });

        return [
            'cart' => $cart,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'subtotal_formatted' => CatalogueService::money($subtotal, $currency),
            'currency' => $currency,
            'item_count' => (int) $items->sum('quantity'),
            'has_unavailable' => $lines->contains(fn ($l) => ! $l['available']),
            'is_empty' => $items->isEmpty(),
            'gstin' => $cart?->billing_gstin,
        ];
    }

    /** Customer-facing line title, e.g. "Japan eSIM" or "European Union eSIM". */
    public static function lineTitle(Plan $plan): string
    {
        $where = $plan->coverage_type === Plan::COVERAGE_LOCAL
            ? ($plan->countries->first()?->name ?? $plan->name)
            : ($plan->region_name ?: $plan->name);

        return $where.' eSIM';
    }
}
