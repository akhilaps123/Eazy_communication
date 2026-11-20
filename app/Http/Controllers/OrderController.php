<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\CatalogueService;
use App\Services\Payments\PaymentService;
use App\Support\BillingCountries;
use App\Support\CustomerEsims;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Order Received page (decision 8). Only the order's owner may see it
 * (OrderPolicy); anyone else gets 404 so order numbers cannot be probed.
 * Customer-safe fields only — never provider cost, bundle code or margin.
 */
class OrderController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $order), 404);

        $order->load(['items' => fn ($q) => $q->orderBy('id'), 'items.plan.countries' => fn ($q) => $q->ordered()]);

        // Phase 8: Razorpay payment section (public data only — never secrets).
        $payments = app(PaymentService::class);
        $latest = $payments->latestPayment($order);
        $unavailable = $order->payment_status === Order::PAYMENT_PAID ? null : $payments->unavailableReason($order);

        // Phase 9E: fulfilment stage and the order's eSIMs — only once the order is
        // paid (server-verified) and fulfilled; never placeholders.
        $stage = CustomerEsims::stage($order);
        $esims = $stage === 'ready'
            ? CustomerEsims::esims($request->user())->whereIn('order_item_id', $order->items->pluck('id'))->orderBy('esims.id')->get()
            : collect();

        return view('pages.order-received', [
            'stage' => $stage,
            'esims' => $esims,
            'order' => $order,
            'billingCountry' => BillingCountries::name($order->billing_country),
            'money' => fn (string $amount) => CatalogueService::money($amount, $order->currency),
            'payment' => $latest,
            'paymentUnavailable' => $unavailable,
            'canPay' => $order->payment_status === Order::PAYMENT_PENDING && $unavailable === null
                && $latest?->status !== Payment::STATUS_PENDING,
            'checkoutScript' => config('razorpay.checkout_script'),
        ]);
    }
}
