<?php

namespace App\Services\EsimGo;

use App\Jobs\FulfilOrderJob;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderBundle;
use App\Models\ProviderOrder;
use App\Services\Esim\EsimQrService;
use App\Services\Esim\EsimReadyNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Phase 9C — buys the eSIMs for a PAID order from eSIM Go and stores them.
 *
 * Flow (one provider order per order, enforced by provider_orders.order_id UNIQUE):
 *   1. Checks: fulfilment switched on, server-verified payment (never the
 *      browser), not a Razorpay test payment (unless allowed), valid items.
 *   2. Reserve the provider order row (status pending, submitted_at NULL).
 *   3. Validate the order with eSIM Go (no charge) and check the cost does not
 *      exceed what the customer paid.
 *   4. Mark the row as sent (submitted_at) — THEN send the purchase.
 *   5. Store the eSIM Go order reference (status submitted), then the eSIMs
 *      (from the purchase response and/or GET /esims/assignments).
 *   6. All eSIMs received → provider order completed, order fulfilled.
 *
 * Safety:
 * - A purchase is sent at most once per attempt and NEVER automatically again
 *   when its outcome is uncertain (timeout, 5xx, no reference, interrupted run):
 *   the order becomes needs_attention and the provider order
 *   needs_reconciliation until a person checks the eSIM Go account.
 * - A definite rejection (insufficient balance, invalid bundle, auth) means
 *   nothing was bought: provider order failed, order needs_attention. Retrying
 *   it is a deliberate admin action (`esimgo:fulfil … --retry-failed`).
 * - Fetching eSIM details is read-only and may be repeated freely.
 * - A cache lock per order + row locks stop two workers running at once.
 * - Activation details are stored encrypted (Esim model) and never logged.
 */
class FulfilmentService
{
    public const RESULT_FULFILLED = 'fulfilled';

    public const RESULT_AWAITING_ESIMS = 'awaiting_esims';

    public const RESULT_NEEDS_ATTENTION = 'needs_attention';

    public const RESULT_FAILED = 'failed';

    public const RESULT_SKIPPED = 'skipped';

    public const RESULT_BUSY = 'busy';

    public const RESULT_QUEUED = 'queued';

    public function __construct(private readonly EsimGoClient $client) {}

    /** Why this order may not be fulfilled right now (null = it may). */
    public function blockingReason(Order $order): ?string
    {
        if (! config('esimgo.fulfilment.enabled')) {
            return 'eSIM fulfilment is switched off (ESIMGO_FULFILMENT_ENABLED is not true).';
        }
        if (! $this->client->isConfigured()) {
            return 'eSIM Go is not configured.';
        }
        if ($order->payment_status !== Order::PAYMENT_PAID || $order->paid_at === null) {
            return 'The order is not paid.';
        }
        if ($order->cancelled_at !== null) {
            return 'The order is cancelled.';
        }

        // Paid status must be backed by a payment the SERVER verified with the gateway.
        $payment = $order->payments()->where('status', Payment::STATUS_SUCCEEDED)->whereNotNull('verified_at')->latest('id')->first();
        if ($payment === null) {
            return 'No server-verified payment was found for this order.';
        }
        if ($payment->gateway === Payment::GATEWAY_RAZORPAY && config('razorpay.mode') !== 'live' && ! config('esimgo.fulfilment.allow_test_payments')) {
            return 'The payment was made in Razorpay TEST mode — no real eSIM is bought for a test payment (ESIMGO_FULFILMENT_ALLOW_TEST_PAYMENTS).';
        }

        try {
            $this->lines($order);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Run (or continue) fulfilment. Safe to call repeatedly: it never buys twice.
     *
     * @return array{result: string, message: string}
     */
    public function fulfil(Order $order, bool $retryFailed = false): array
    {
        $lock = Cache::lock('esimgo-fulfil:'.$order->getKey(), (int) config('esimgo.fulfilment.lock_seconds', 300));
        if (! $lock->get()) {
            return $this->result(self::RESULT_BUSY, 'Another fulfilment run is already in progress for this order.');
        }

        try {
            $order = Order::query()->with('items')->findOrFail($order->getKey());
            $existing = ProviderOrder::query()->where('order_id', $order->id)->first();

            if ($existing !== null) {
                $early = $this->continueExisting($order, $existing, $retryFailed);
                if ($early !== null) {
                    return $early;
                }
            }

            if ($reason = $this->blockingReason($order)) {
                if ($existing?->status === ProviderOrder::STATUS_PENDING && $existing->submitted_at === null) {
                    // Reserved (e.g. an admin retry) but never sent: definitely nothing bought.
                    return $this->fail($order, $existing, 'Not sent to eSIM Go: '.$reason);
                }
                $this->log('fulfilment_skipped', $order, $existing, true, $reason);

                return $this->result(self::RESULT_SKIPPED, $reason);
            }

            return $this->purchase($order);
        } finally {
            $lock->release();
        }
    }

    // ------------------------------------------------------- admin actions (Phase 9F)

    /**
     * Why a read-only reconciliation is not possible (null = it is).
     * It needs the eSIM Go order reference: eSIM Go cannot look an order up by
     * our own reference, so without it nothing can be verified automatically.
     */
    public function reconcileBlockedReason(Order $order): ?string
    {
        $po = ProviderOrder::query()->where('order_id', $order->id)->first();

        return match (true) {
            $po === null => 'No eSIM Go order exists for this order, so there is nothing to look up.',
            $po->status === ProviderOrder::STATUS_COMPLETED && $order->fulfillment_status === Order::FULFILLMENT_FULFILLED => 'This order is already fulfilled.',
            $po->status === ProviderOrder::STATUS_FAILED => 'The purchase failed and nothing was bought, so there is nothing to look up.',
            $po->status === ProviderOrder::STATUS_PENDING => 'A purchase for this order is queued or in progress. Wait for it to finish.',
            $po->provider_reference === null => 'No eSIM Go order reference was recorded, so the purchase cannot be verified automatically (eSIM Go has no lookup by our own reference). Check the eSIM Go account manually. The purchase is never sent again automatically.',
            ! $this->client->isConfigured() => 'eSIM Go is not configured.',
            default => null,
        };
    }

    /**
     * Read-only reconciliation: fetch the results of an EXISTING eSIM Go order
     * (by its stored reference) and store the eSIMs. Never sends a purchase.
     *
     * @return array{result: string, message: string}
     */
    public function reconcile(Order $order): array
    {
        $lock = Cache::lock('esimgo-fulfil:'.$order->getKey(), (int) config('esimgo.fulfilment.lock_seconds', 300));
        if (! $lock->get()) {
            return $this->result(self::RESULT_BUSY, 'Another fulfilment run is already in progress for this order.');
        }

        try {
            $order = Order::query()->with('items')->findOrFail($order->getKey());
            if ($reason = $this->reconcileBlockedReason($order)) {
                return $this->result(self::RESULT_SKIPPED, $reason);
            }
            $po = ProviderOrder::query()->where('order_id', $order->id)->firstOrFail();
            $this->log('reconciliation_started', $order, $po, true, 'Looking up eSIM Go order '.$po->provider_reference.' (read-only).');

            // Only GET /esims/assignments is called here; completion requires every eSIM to be returned.
            return $this->collectEsims($order, $po);
        } finally {
            $lock->release();
        }
    }

    /** Why an admin may not retry the purchase (null = they may). No provider call. */
    public function retryBlockedReason(Order $order): ?string
    {
        $po = ProviderOrder::query()->where('order_id', $order->id)->first();

        return match (true) {
            $order->fulfillment_status === Order::FULFILLMENT_FULFILLED => 'This order is already fulfilled.',
            $po === null => 'There is no failed purchase to retry. Paid orders are fulfilled automatically by the queue.',
            $po->status === ProviderOrder::STATUS_NEEDS_RECONCILIATION => 'The previous purchase may have gone through (outcome uncertain). It must be checked in the eSIM Go account and is never retried.',
            $po->status === ProviderOrder::STATUS_PENDING => 'A purchase for this order is already queued or in progress.',
            $po->status !== ProviderOrder::STATUS_FAILED => 'The eSIMs for this order were already bought.',
            default => $this->blockingReason($order),
        };
    }

    /**
     * Deliberate admin retry of a CONFIRMED failed purchase (nothing was bought).
     * Re-checks every rule, re-validates bundle and cost with eSIM Go (no charge),
     * then re-reserves the provider order and queues the normal fulfilment job,
     * which validates again before buying. A second request while one is queued
     * is refused (the provider order is no longer "failed").
     *
     * @return array{result: string, message: string}
     */
    public function queueRetry(Order $order): array
    {
        $lock = Cache::lock('esimgo-fulfil:'.$order->getKey(), (int) config('esimgo.fulfilment.lock_seconds', 300));
        if (! $lock->get()) {
            return $this->result(self::RESULT_BUSY, 'Another fulfilment run is already in progress for this order.');
        }

        try {
            $order = Order::query()->with('items')->findOrFail($order->getKey());
            if ($reason = $this->retryBlockedReason($order)) {
                return $this->result(self::RESULT_SKIPPED, $reason);
            }
            $po = ProviderOrder::query()->where('order_id', $order->id)->firstOrFail();
            $lines = $this->lines($order);

            [, $problem] = $this->validate($order, $lines, ['order_id' => $order->id, 'provider_order_id' => $po->id]);
            if ($problem !== null) {
                return $this->result(self::RESULT_SKIPPED, $problem.' The retry was not queued; nothing was bought.');
            }

            try {
                $this->reserve($order, $lines);
            } catch (RuntimeException $e) {
                return $this->result(self::RESULT_BUSY, $e->getMessage().' The retry was not queued.');
            }
            $this->log('fulfilment_retry_queued', $order, $po, true, 'Retry of the failed purchase queued by an administrator.');
        } finally {
            $lock->release();
        }

        // Dispatched after the lock is released, so the job can take it.
        FulfilOrderJob::dispatch($order->id)->afterCommit();

        return $this->result(self::RESULT_QUEUED, 'Retry queued. The fulfilment job validates the bundle and cost again before buying.');
    }

    /**
     * After the job gave up waiting for eSIM details: flag the order for a person.
     * The purchase itself is done; `esimgo:fulfil` can fetch the details later.
     */
    public function stopWaitingForEsims(Order $order): void
    {
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_NEEDS_ATTENTION])->save();
        $this->log('fulfilment_needs_attention', $order, $order->providerOrder, false,
            'The eSIMs were bought, but their details were not received after several checks. Run `php artisan esimgo:fulfil '.$order->order_number.'` to fetch them again.');
    }

    /** A job crashed unexpectedly: never leave an in-flight purchase looking safe to resend. */
    public function handleUnexpectedFailure(Order $order, string $error): void
    {
        $po = ProviderOrder::query()->where('order_id', $order->id)->first();
        if ($po?->status === ProviderOrder::STATUS_PENDING) {
            $po->submitted_at !== null
                ? $this->markUncertain($order, $po, 'The fulfilment run stopped unexpectedly after the purchase request was sent: '.$error)
                : $this->fail($order, $po, 'The fulfilment run stopped unexpectedly before anything was bought: '.$error);
        }
    }

    // ------------------------------------------------------------------ steps

    /** @return array{result: string, message: string}|null null = continue with a (new) purchase */
    private function continueExisting(Order $order, ProviderOrder $po, bool $retryFailed): ?array
    {
        return match ($po->status) {
            ProviderOrder::STATUS_COMPLETED => $order->fulfillment_status === Order::FULFILLMENT_FULFILLED
                ? $this->result(self::RESULT_FULFILLED, 'This order is already fulfilled.')
                : $this->collectEsims($order, $po),
            // Already bought: only (read-only) fetching of eSIM details remains.
            ProviderOrder::STATUS_SUBMITTED => $this->collectEsims($order, $po),
            ProviderOrder::STATUS_NEEDS_RECONCILIATION => $this->result(self::RESULT_NEEDS_ATTENTION,
                'A purchase request for this order may have reached eSIM Go, but the result is unknown. Check the eSIM Go account before doing anything else. '.$po->last_error),
            ProviderOrder::STATUS_PENDING => $po->submitted_at !== null
                ? $this->markUncertain($order, $po, 'A previous purchase request was interrupted after it was sent.')
                : null,   // reserved but never sent: safe to continue
            ProviderOrder::STATUS_FAILED => $retryFailed
                ? null
                : $this->result(self::RESULT_FAILED, 'The previous attempt failed and nothing was bought: '.$po->last_error.' Fix the cause, then run `php artisan esimgo:fulfil '.$order->order_number.' --retry-failed`.'),
            default => $this->result(self::RESULT_NEEDS_ATTENTION, 'Unknown provider order status "'.$po->status.'".'),
        };
    }

    /** @return array{result: string, message: string} */
    private function purchase(Order $order): array
    {
        $lines = $this->lines($order);

        // Reserve the single provider order row for this order (never sent yet).
        try {
            $po = $this->reserve($order, $lines);
        } catch (RuntimeException $e) {
            return $this->result(self::RESULT_BUSY, $e->getMessage().' Nothing was sent to eSIM Go.');
        }
        $context = ['order_id' => $order->id, 'provider_order_id' => $po->id];
        $this->log('fulfilment_started', $order, $po, true, 'Fulfilment started for '.array_sum(array_column($lines, 'quantity')).' eSIM(s).');

        return $this->validateAndBuy($order, $po, $lines, $context);
    }

    /** @param list<array{item: string, quantity: int}> $lines */
    private function reserve(Order $order, array $lines): ProviderOrder
    {
        // provider_orders.order_id is UNIQUE: a second row for the same order is impossible.
        return DB::transaction(function () use ($order, $lines) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_status !== Order::PAYMENT_PAID || $locked->fulfillment_status === Order::FULFILLMENT_FULFILLED) {
                throw new RuntimeException('The order changed while fulfilment was starting.');
            }
            $row = ProviderOrder::query()->lockForUpdate()->where('order_id', $order->id)->first();
            if ($row !== null && ! ($row->status === ProviderOrder::STATUS_FAILED || ($row->status === ProviderOrder::STATUS_PENDING && $row->submitted_at === null))) {
                throw new RuntimeException('A purchase for this order already exists.');
            }

            $row ??= new ProviderOrder([
                'order_id' => $order->id,
                'provider' => ProviderBundle::PROVIDER_ESIMGO,
                'our_reference' => 'FUL-'.$order->order_number,
            ]);
            $row->fill(['status' => ProviderOrder::STATUS_PENDING, 'submitted_at' => null, 'last_error' => null, 'request_payload' => ['order' => $lines]])->save();
            $locked->forceFill(['fulfillment_status' => Order::FULFILLMENT_PROCESSING])->save();

            return $row;
        });
    }

    /**
     * @param  list<array{item: string, quantity: int}>  $lines
     * @return array{result: string, message: string}
     */
    private function validateAndBuy(Order $order, ProviderOrder $po, array $lines, array $context): array
    {
        // Validate first (no charge) and protect the margin.
        [$validation, $problem] = $this->validate($order, $lines, $context);
        if ($problem !== null) {
            return $this->fail($order, $po, $problem.' Nothing was bought.');
        }

        // Record that the purchase is being sent BEFORE sending it: if this run dies
        // mid-request, the next run sees submitted_at and never resends automatically.
        $po->forceFill([
            'submitted_at' => now(),
            'attempts' => $po->attempts + 1,
            'response_payload' => array_merge((array) $po->response_payload, ['validation' => $validation]),
        ])->save();

        try {
            $purchase = $this->client->purchase($lines, $context);
        } catch (EsimGoException $e) {
            if ($e->outcomeUncertain()) {
                return $this->markUncertain($order, $po, 'The purchase request may or may not have been processed by eSIM Go: '.$e->getMessage());
            }

            return $this->fail($order, $po, ($e->kind === EsimGoException::INSUFFICIENT_BALANCE
                ? 'Not enough eSIM Go balance — nothing was bought. Top up the eSIM Go account, then retry. '
                : 'eSIM Go rejected the purchase — nothing was bought. ').$e->getMessage());
        }

        if ($purchase['reference'] === null) {
            return $this->markUncertain($order, $po, 'eSIM Go answered the purchase without an order reference.');
        }

        try {
            $po->forceFill([
                'provider_reference' => $purchase['reference'],
                'status' => ProviderOrder::STATUS_SUBMITTED,
                'total_provider_cost' => $purchase['total'],
                'provider_currency' => $purchase['currency'],
                'last_error' => null,
                'response_payload' => array_merge((array) $po->response_payload, ['purchase' => array_diff_key($purchase, ['esims' => true])]),
            ])->save();
        } catch (QueryException $e) {
            // Bought, but the result could not be recorded (e.g. reference already used): a person must check.
            $po->refresh();

            return $this->markUncertain($order, $po, 'eSIM Go order '.$purchase['reference'].' was placed but could not be saved: '.mb_substr($e->getMessage(), 0, 200));
        }
        $this->log('fulfilment_purchased', $order, $po, true, 'eSIM Go order '.$purchase['reference'].' placed ('.($purchase['status'] ?? 'no status').').');

        return $this->collectEsims($order, $po, $purchase['esims']);
    }

    /**
     * Validate the order with eSIM Go (type=validate — no charge) and check the
     * cost does not exceed what the customer paid. Used before every purchase
     * and before an admin retry is queued.
     *
     * @param  list<array{item: string, quantity: int}>  $lines
     * @return array{0: ?array, 1: ?string} [validation, problem (null = OK)]
     */
    private function validate(Order $order, array $lines, array $context): array
    {
        try {
            $validation = $this->client->validateOrder($lines, $context);
        } catch (EsimGoException $e) {
            return [null, 'Validation failed: '.$e->getMessage()];
        }
        if (! $validation['valid']) {
            return [$validation, 'eSIM Go says this order cannot be placed (bundle unavailable?).'];
        }
        if ($validation['total'] !== null) {
            if (strtoupper((string) $validation['currency']) !== strtoupper($order->currency)) {
                return [$validation, "eSIM Go prices this order in {$validation['currency']}, the customer paid in {$order->currency}; cost cannot be checked."];
            }
            if (bccomp($validation['total'], (string) $order->total, 4) > 0) {
                return [$validation, "eSIM Go would charge {$validation['total']} {$validation['currency']}, more than the {$order->total} {$order->currency} paid."];
            }
        }

        return [$validation, null];
    }

    /**
     * Store eSIM details (from the purchase response and, if still incomplete,
     * from GET /esims/assignments) and complete the order when all are present.
     *
     * @param  list<array<string, ?string>>  $fromPurchase
     * @return array{result: string, message: string}
     */
    private function collectEsims(Order $order, ProviderOrder $po, array $fromPurchase = []): array
    {
        $expected = (int) $order->items->sum('quantity');

        try {
            if ($fromPurchase !== []) {
                $this->storeEsims($order, $po, $fromPurchase);
            }
            if ($this->completeEsimCount($po) < $expected) {
                try {
                    $rows = $this->client->assignments((string) $po->provider_reference, ['order_id' => $order->id, 'provider_order_id' => $po->id]);
                } catch (EsimGoException $e) {
                    return $this->result(self::RESULT_AWAITING_ESIMS, 'The eSIMs were bought; their details could not be fetched yet: '.$e->getMessage());
                }
                $this->storeEsims($order, $po, $rows);
            }
        } catch (RuntimeException $e) {
            $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_NEEDS_ATTENTION])->save();
            $po->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            $this->log('fulfilment_needs_attention', $order, $po, false, $e->getMessage());

            return $this->result(self::RESULT_NEEDS_ATTENTION, $e->getMessage());
        }

        // Phase 9D: QR codes for the eSIMs stored so far (from the stored activation
        // details only). Never affects the purchase or the order's status.
        try {
            app(EsimQrService::class)->generateMissing($po);
        } catch (Throwable) {
            // Logged per eSIM by EsimQrService; an admin can regenerate.
        }

        $have = $this->completeEsimCount($po);
        if ($have < $expected) {
            return $this->result(self::RESULT_AWAITING_ESIMS, "{$have} of {$expected} eSIM(s) received from eSIM Go; the rest will be fetched again.");
        }

        DB::transaction(function () use ($order, $po) {
            $po->forceFill(['status' => ProviderOrder::STATUS_COMPLETED, 'completed_at' => $po->completed_at ?? now(), 'last_error' => null])->save();
            $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'fulfilled_at' => $order->fulfilled_at ?? now()])->save();
        });
        $this->log('fulfilment_completed', $order, $po, true, "{$expected} eSIM(s) stored for order {$order->order_number}.");

        // Phase 9E: "eSIM ready" email — queued once, only if every condition holds.
        // Runs after the fulfilment is saved; an email problem can never undo it.
        try {
            app(EsimReadyNotifier::class)->queueIfReady($order, 'fulfilment');
        } catch (Throwable) {
            // The notification state stays unset/failed; an admin can retry it.
        }

        return $this->result(self::RESULT_FULFILLED, "Order fulfilled: {$expected} eSIM(s) stored.");
    }

    /**
     * @param  list<array<string, ?string>>  $rows  iccid, matching_id, smdp_address, optional item
     *
     * @throws RuntimeException when the provider data does not fit this order (needs a person)
     */
    private function storeEsims(Order $order, ProviderOrder $po, array $rows): void
    {
        $bundleOf = $this->iccidBundles($po);
        $bundles = $order->items->pluck('bundle_code')->unique()->values();

        DB::transaction(function () use ($order, $po, $rows, $bundleOf, $bundles) {
            foreach ($rows as $row) {
                $iccid = (string) $row['iccid'];
                $existing = Esim::query()->where('iccid', $iccid)->lockForUpdate()->first();
                if ($existing !== null && (int) $existing->provider_order_id !== (int) $po->id) {
                    throw new RuntimeException("eSIM Go returned ICCID {$iccid}, which already belongs to another order.");
                }
                if ($existing !== null) {
                    if (! $existing->activation_code && $row['matching_id'] && $row['smdp_address']) {
                        $existing->forceFill(['activation_code' => $row['matching_id'], 'smdp_address' => $row['smdp_address']])->save();
                    }

                    continue;
                }
                if (! $row['matching_id'] || ! $row['smdp_address']) {
                    continue;   // details not ready yet — fetched again from /esims/assignments
                }

                $bundle = $row['item'] ?? $bundleOf[$iccid] ?? ($bundles->count() === 1 ? $bundles->first() : null);
                if ($bundle === null) {
                    throw new RuntimeException("Cannot tell which bundle ICCID {$iccid} belongs to.");
                }
                $item = $order->items->first(fn ($i) => $i->bundle_code === $bundle && $i->esims()->count() < $i->quantity);
                if ($item === null) {
                    throw new RuntimeException("eSIM Go returned more eSIMs for {$bundle} than were ordered.");
                }

                Esim::query()->create([
                    'user_id' => $order->user_id,
                    'order_item_id' => $item->id,
                    'provider_order_id' => $po->id,
                    'iccid' => $iccid,
                    'smdp_address' => $row['smdp_address'],     // encrypted at rest
                    'activation_code' => $row['matching_id'],   // encrypted at rest
                    'bundle_code' => $bundle,
                    'status' => 'assigned',
                ]);
            }
        });
    }

    /** ICCID => bundle, from the (secret-free) purchase summary stored on the provider order. */
    private function iccidBundles(ProviderOrder $po): array
    {
        $map = [];
        foreach ((array) data_get($po->response_payload, 'purchase.lines', []) as $line) {
            foreach ((array) ($line['iccids'] ?? []) as $iccid) {
                $map[(string) $iccid] = (string) ($line['item'] ?? '');
            }
        }

        return array_filter($map);
    }

    private function completeEsimCount(ProviderOrder $po): int
    {
        return $po->esims()->whereNotNull('activation_code')->whereNotNull('smdp_address')->count();
    }

    /**
     * One line per bundle (items with the same bundle are combined).
     *
     * @return list<array{item: string, quantity: int}>
     */
    private function lines(Order $order): array
    {
        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            throw new RuntimeException('The order has no items.');
        }

        $lines = [];
        foreach ($order->items as $item) {
            if (! preg_match(EsimGoClient::BUNDLE_NAME_PATTERN, (string) $item->bundle_code)) {
                throw new RuntimeException("Order item {$item->id} has no valid eSIM Go bundle code.");
            }
            $lines[$item->bundle_code] = ($lines[$item->bundle_code] ?? 0) + (int) $item->quantity;
        }

        $out = [];
        foreach ($lines as $bundle => $quantity) {
            if ($quantity < 1 || $quantity > 50) {
                throw new RuntimeException("Quantity {$quantity} for {$bundle} is outside 1–50.");
            }
            $out[] = ['item' => (string) $bundle, 'quantity' => $quantity];
        }

        return $out;
    }

    // ---------------------------------------------------------------- outcomes

    /** Definitely nothing bought. */
    private function fail(Order $order, ProviderOrder $po, string $message): array
    {
        DB::transaction(function () use ($order, $po, $message) {
            $po->forceFill(['status' => ProviderOrder::STATUS_FAILED, 'last_error' => mb_substr($message, 0, 1000)])->save();
            $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_NEEDS_ATTENTION])->save();
        });
        $this->log('fulfilment_failed', $order, $po, false, $message);

        return $this->result(self::RESULT_FAILED, $message);
    }

    /** Maybe bought — never resend automatically. */
    private function markUncertain(Order $order, ProviderOrder $po, string $message): array
    {
        $message .= ' Check the eSIM Go account (orders around '.($po->submitted_at?->toDateTimeString() ?? 'now').') before retrying — do not buy again until confirmed.';
        DB::transaction(function () use ($order, $po, $message) {
            $po->forceFill(['status' => ProviderOrder::STATUS_NEEDS_RECONCILIATION, 'last_error' => mb_substr($message, 0, 1000)])->save();
            $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_NEEDS_ATTENTION])->save();
        });
        $this->log('fulfilment_needs_attention', $order, $po, false, $message);

        return $this->result(self::RESULT_NEEDS_ATTENTION, $message);
    }

    private function log(string $operation, Order $order, ?ProviderOrder $po, bool $success, string $message): void
    {
        IntegrationLog::query()->create([
            'service' => IntegrationLog::SERVICE_ESIMGO,
            'operation' => $operation,
            'success' => $success,
            'order_id' => $order->id,
            'provider_order_id' => $po?->id,
            'response_body' => ['order_number' => $order->order_number, 'fulfillment_status' => $order->fulfillment_status],
            'error_message' => $success ? null : mb_substr($message, 0, 2000),
            ...($success ? ['request_body' => ['note' => mb_substr($message, 0, 500)]] : []),
        ]);
    }

    /** @return array{result: string, message: string} */
    private function result(string $result, string $message): array
    {
        return ['result' => $result, 'message' => $message];
    }
}
