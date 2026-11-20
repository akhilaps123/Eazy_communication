<?php

namespace Tests\Feature\Customer;

use App\Models\Esim;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderOrder;
use App\Models\User;
use App\Services\Esim\EsimQrService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Tests\Feature\Payments\PaymentFixtures;

/** Phase 9E: fake customers, orders and eSIMs (fake activation data only). */
trait CustomerFixtures
{
    use PaymentFixtures;

    protected function customerNamed(string $name): User
    {
        return User::factory()->create(['name' => $name]);
    }

    protected function paidVerified(Order $order, bool $verified = true): Order
    {
        Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay', 'gateway_order_id' => 'order_9E'.$order->id,
            'gateway_payment_id' => 'pay_9E'.$order->id, 'amount' => (string) $order->total, 'currency' => $order->currency,
            'status' => Payment::STATUS_SUCCEEDED, 'paid_at' => now(), 'verified_at' => $verified ? now() : null,
        ]);
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

        return $order->fresh();
    }

    /**
     * A fully delivered eSIM: paid (server-verified), eSIM Go order completed, eSIM stored,
     * QR generated, order fulfilled.
     */
    protected function deliveredEsim(User $user, string $iccid, string $matchingId, string $smdp = 'rsp.fake-9e.example', bool $qr = true): Esim
    {
        $order = $this->paidVerified($this->pendingOrder($user));
        $po = ProviderOrder::query()->create([
            'order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number, 'status' => ProviderOrder::STATUS_COMPLETED,
            'provider_reference' => '9e000000-0000-4000-8000-'.str_pad((string) $order->id, 12, '0', STR_PAD_LEFT), 'attempts' => 1,
            'submitted_at' => now(), 'completed_at' => now(),
        ]);
        $esim = Esim::query()->create([
            'user_id' => $user->id, 'order_item_id' => $order->items()->first()->id, 'provider_order_id' => $po->id,
            'iccid' => $iccid, 'smdp_address' => $smdp, 'activation_code' => $matchingId, 'bundle_code' => 'SECRET_BUNDLE', 'status' => 'assigned',
        ]);
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'fulfilled_at' => now()])->save();
        if ($qr) {
            $this->assertTrue(app(EsimQrService::class)->generate($esim, 'test')['ok']);
        }

        return $esim->fresh();
    }

    protected function decodeQr(string $png): string
    {
        return (string) (new QRCode(new QROptions))->readFromBlob($png);
    }
}
