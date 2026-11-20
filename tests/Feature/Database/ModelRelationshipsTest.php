<?php

namespace Tests\Feature\Database;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Country;
use App\Models\Esim;
use App\Models\EsimUsage;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PromoCode;
use App\Models\ProviderBundle;
use App\Models\ProviderOrder;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * All data here lives only in the in-memory SQLite test database.
 * Values (prices, codes, ICCIDs) are test fixtures, not business data.
 */
class ModelRelationshipsTest extends DatabaseTestCase
{
    public function test_relationship_methods_return_the_expected_relation_types(): void
    {
        $expected = [
            [User::class, 'cart', HasOne::class],
            [User::class, 'orders', HasMany::class],
            [User::class, 'payments', HasMany::class],
            [User::class, 'esims', HasMany::class],
            [Country::class, 'plans', BelongsToMany::class],
            [ProviderBundle::class, 'plan', HasOne::class],
            [Plan::class, 'providerBundle', BelongsTo::class],
            [Plan::class, 'countries', BelongsToMany::class],
            [Plan::class, 'prices', HasMany::class],
            [Plan::class, 'cartItems', HasMany::class],
            [Plan::class, 'orderItems', HasMany::class],
            [PlanPrice::class, 'plan', BelongsTo::class],
            [PlanPrice::class, 'creator', BelongsTo::class],
            [PlanPrice::class, 'approver', BelongsTo::class],
            [PromoCode::class, 'carts', HasMany::class],
            [PromoCode::class, 'orders', HasMany::class],
            [Cart::class, 'user', BelongsTo::class],
            [Cart::class, 'items', HasMany::class],
            [Cart::class, 'promoCode', BelongsTo::class],
            [CartItem::class, 'cart', BelongsTo::class],
            [CartItem::class, 'plan', BelongsTo::class],
            [Order::class, 'user', BelongsTo::class],
            [Order::class, 'items', HasMany::class],
            [Order::class, 'payments', HasMany::class],
            [Order::class, 'providerOrder', HasOne::class],
            [Order::class, 'promoCode', BelongsTo::class],
            [Order::class, 'esims', HasManyThrough::class],
            [Order::class, 'integrationLogs', HasMany::class],
            [OrderItem::class, 'order', BelongsTo::class],
            [OrderItem::class, 'plan', BelongsTo::class],
            [OrderItem::class, 'esims', HasMany::class],
            [Payment::class, 'order', BelongsTo::class],
            [Payment::class, 'user', BelongsTo::class],
            [Payment::class, 'webhookEvents', HasMany::class],
            [Payment::class, 'integrationLogs', HasMany::class],
            [WebhookEvent::class, 'payment', BelongsTo::class],
            [ProviderOrder::class, 'order', BelongsTo::class],
            [ProviderOrder::class, 'esims', HasMany::class],
            [ProviderOrder::class, 'integrationLogs', HasMany::class],
            [Esim::class, 'user', BelongsTo::class],
            [Esim::class, 'orderItem', BelongsTo::class],
            [Esim::class, 'providerOrder', BelongsTo::class],
            [Esim::class, 'usageRecords', HasMany::class],
            [Esim::class, 'latestUsage', HasOne::class],
            [EsimUsage::class, 'esim', BelongsTo::class],
            [IntegrationLog::class, 'order', BelongsTo::class],
            [IntegrationLog::class, 'payment', BelongsTo::class],
            [IntegrationLog::class, 'providerOrder', BelongsTo::class],
        ];

        foreach ($expected as [$model, $method, $relation]) {
            $this->assertInstanceOf($relation, (new $model)->{$method}(), "{$model}::{$method}()");
        }
    }

    public function test_full_purchase_chain_can_be_stored_and_navigated(): void
    {
        $f = $this->purchaseChain();

        $this->assertTrue($f['plan']->countries->pluck('iso2')->sort()->values()->all() === ['FR', 'JP']);
        $this->assertTrue($f['country']->plans->first()->is($f['plan']));
        $this->assertTrue($f['bundle']->plan->is($f['plan']));
        $this->assertTrue($f['plan']->providerBundle->is($f['bundle']));
        $this->assertCount(1, $f['plan']->prices);
        $this->assertTrue($f['user']->cart->is($f['cart']));
        $this->assertTrue($f['cart']->items->first()->plan->is($f['plan']));
        $this->assertTrue($f['cart']->promoCode->is($f['promo']));
        $this->assertTrue($f['user']->orders->first()->is($f['order']));
        $this->assertTrue($f['order']->items->first()->is($f['item']));
        $this->assertTrue($f['order']->payments->first()->is($f['payment']));
        $this->assertTrue($f['payment']->webhookEvents->first()->is($f['webhook']));
        $this->assertTrue($f['order']->providerOrder->is($f['providerOrder']));
        $this->assertTrue($f['providerOrder']->esims->first()->is($f['esim']));
        $this->assertTrue($f['order']->esims->first()->is($f['esim']));
        $this->assertTrue($f['esim']->orderItem->is($f['item']));
        $this->assertTrue($f['user']->esims->first()->is($f['esim']));
        $this->assertCount(2, $f['esim']->usageRecords);
        $this->assertEquals('900.00', $f['esim']->latestUsage->data_remaining_mb);
        $this->assertTrue($f['order']->integrationLogs->first()->providerOrder->is($f['providerOrder']));
    }

    public function test_duplicate_guards_are_enforced_by_unique_keys(): void
    {
        $f = $this->purchaseChain();

        $this->assertThrowsUnique(fn () => WebhookEvent::create([
            'gateway' => 'test-gateway', 'event_id' => 'evt_1', 'payload' => ['again' => true],
        ]));
        $this->assertThrowsUnique(fn () => ProviderOrder::create([
            'order_id' => $f['order']->id, 'provider' => 'esimgo', 'our_reference' => 'REF-2',
        ]));
        $this->assertThrowsUnique(fn () => Cart::create(['user_id' => $f['user']->id]));
        $this->assertThrowsUnique(fn () => Plan::create($this->planAttributes($f['bundle']->id, 'second-slug')));
        $this->assertThrowsUnique(fn () => PromoCode::create([
            'code' => 'TESTCODE', 'discount_type' => 'percent', 'discount_value' => '5.00',
        ]));
    }

    public function test_financial_records_cannot_be_deleted_while_referenced(): void
    {
        $f = $this->purchaseChain();

        $this->expectException(QueryException::class);
        DB::table('orders')->where('id', $f['order']->id)->delete();
    }

    public function test_deleting_a_cart_removes_its_items_but_never_plans(): void
    {
        $f = $this->purchaseChain();

        $f['cart']->delete();

        $this->assertSame(0, CartItem::count());
        $this->assertTrue(Plan::whereKey($f['plan']->id)->exists());
    }

    public function test_order_item_price_snapshot_cannot_be_changed(): void
    {
        $f = $this->purchaseChain();

        $this->expectException(LogicException::class);
        $f['item']->update(['unit_price' => '1.00']);
    }

    public function test_esim_activation_details_are_encrypted_hidden_and_owner_checked(): void
    {
        $f = $this->purchaseChain();
        $raw = DB::table('esims')->where('id', $f['esim']->id)->first();

        $this->assertNotSame('TEST-SMDP.example', $raw->smdp_address);
        $this->assertNotSame('TEST-MATCHING-ID', $raw->activation_code);
        $this->assertSame('TEST-MATCHING-ID', $f['esim']->fresh()->activation_code);

        $array = $f['esim']->toArray();
        $this->assertArrayNotHasKey('smdp_address', $array);
        $this->assertArrayNotHasKey('activation_code', $array);
        $this->assertArrayNotHasKey('qr_code_path', $array);

        $this->assertTrue($f['esim']->isOwnedBy($f['user']));
        $this->assertFalse($f['esim']->isOwnedBy(User::factory()->create()));
        $this->assertFalse($f['esim']->isOwnedBy(null));
    }

    public function test_users_are_soft_deleted_and_role_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['role' => User::ROLE_ADMIN])->save();

        $this->assertSame(User::ROLE_CUSTOMER, $user->fresh()->role);
        $this->assertFalse($user->fresh()->isAdmin());

        $user->delete();
        $this->assertSoftDeleted($user);
    }

    private function assertThrowsUnique(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a unique constraint violation.');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function planAttributes(int $bundleId, string $slug): array
    {
        return [
            'provider_bundle_id' => $bundleId, 'slug' => $slug, 'name' => 'Test plan',
            'coverage_type' => Plan::COVERAGE_REGIONAL, 'data_type' => Plan::DATA_FIXED,
            'data_amount_mb' => 5120, 'validity_days' => 7,
        ];
    }

    /** Builds one complete, linked set of test records. */
    private function purchaseChain(): array
    {
        $user = User::factory()->create();
        $japan = Country::create(['iso2' => 'JP', 'name' => 'Japan', 'slug' => 'japan']);
        $france = Country::create(['iso2' => 'FR', 'name' => 'France', 'slug' => 'france']);

        $bundle = ProviderBundle::create([
            'provider' => ProviderBundle::PROVIDER_ESIMGO, 'bundle_code' => 'TEST_BUNDLE_1', 'name' => 'Test bundle',
            'provider_cost' => '10.0000', 'provider_currency' => 'XXX',
        ]);
        $plan = Plan::create($this->planAttributes($bundle->id, 'test-plan'));
        $plan->countries()->attach([$japan->id, $france->id]);

        PlanPrice::create([
            'plan_id' => $plan->id, 'currency' => 'XXX', 'pricing_method' => PlanPrice::METHOD_MANUAL,
            'manual_price' => '12.00', 'calculated_price' => '12.00', 'effective_from' => now(),
        ]);

        $promo = PromoCode::create(['code' => 'TESTCODE', 'discount_type' => 'percent', 'discount_value' => '10.00']);
        $cart = Cart::create(['user_id' => $user->id, 'promo_code_id' => $promo->id]);
        CartItem::create(['cart_id' => $cart->id, 'plan_id' => $plan->id, 'quantity' => 1]);

        $order = Order::create([
            'order_number' => 'TEST-0001', 'user_id' => $user->id, 'currency' => 'XXX',
            'subtotal' => '12.00', 'total' => '12.00', 'billing_name' => $user->name, 'billing_email' => $user->email,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'plan_id' => $plan->id, 'plan_name' => $plan->name, 'bundle_code' => $bundle->bundle_code,
            'unit_price' => '12.00', 'quantity' => 1, 'line_total' => '12.00',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'gateway' => 'test-gateway',
            'gateway_payment_id' => 'pay_1', 'amount' => '12.00', 'currency' => 'XXX',
        ]);
        $webhook = WebhookEvent::create([
            'gateway' => 'test-gateway', 'event_id' => 'evt_1', 'payment_id' => $payment->id, 'payload' => ['test' => true],
        ]);
        $providerOrder = ProviderOrder::create(['order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'REF-1']);
        $esim = Esim::create([
            'user_id' => $user->id, 'order_item_id' => $item->id, 'provider_order_id' => $providerOrder->id,
            'iccid' => '0000000000000000001', 'smdp_address' => 'TEST-SMDP.example', 'activation_code' => 'TEST-MATCHING-ID',
        ]);
        EsimUsage::create(['esim_id' => $esim->id, 'data_remaining_mb' => '1000.00', 'fetched_at' => now()->subHour()]);
        EsimUsage::create(['esim_id' => $esim->id, 'data_remaining_mb' => '900.00', 'fetched_at' => now()]);
        IntegrationLog::create([
            'service' => IntegrationLog::SERVICE_ESIMGO, 'operation' => 'test', 'order_id' => $order->id,
            'provider_order_id' => $providerOrder->id, 'success' => true,
        ]);

        return compact('user', 'bundle', 'plan', 'promo', 'cart', 'order', 'item', 'payment', 'webhook', 'providerOrder', 'esim')
            + ['country' => $japan];
    }
}
