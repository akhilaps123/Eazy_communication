<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Customer payment endpoints for the Order Received page (Phase 8, Razorpay
 * TEST MODE). Session login + CSRF + rate limit come from the route group.
 * Amounts are never read from the request. Other customers' orders: 404.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** POST /orders/{order}/payment — create (or reuse) the Razorpay order. */
    public function initiate(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        return $this->respond(fn () => $this->payments->initiate($request->user(), $order));
    }

    /** POST /orders/{order}/payment/verify — Checkout success handler fields. */
    public function verify(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64', 'regex:/^pay_[A-Za-z0-9]+$/'],
            'razorpay_order_id' => ['required', 'string', 'max:64', 'regex:/^order_[A-Za-z0-9]+$/'],
            'razorpay_signature' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ]);

        return $this->respond(function () use ($request, $order, $data) {
            $payment = $this->payments->verifyCheckout($request->user(), $order, $data['razorpay_payment_id'], $data['razorpay_order_id'], $data['razorpay_signature']);

            return $this->paymentResult($payment);
        });
    }

    /** POST /orders/{order}/payment/failure — Checkout "payment.failed" (confirmed with Razorpay). */
    public function failure(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64', 'regex:/^pay_[A-Za-z0-9]+$/'],
        ]);

        return $this->respond(fn () => $this->paymentResult($this->payments->recordCheckoutFailure($request->user(), $order, $data['razorpay_payment_id'])));
    }

    private function paymentResult(Payment $payment): array
    {
        return match ($payment->status) {
            Payment::STATUS_SUCCEEDED => ['success' => true, 'status' => 'paid', 'message' => 'Payment received. Thank you — your order is paid.'],
            Payment::STATUS_PENDING => ['success' => true, 'status' => 'processing', 'message' => 'Your payment is being confirmed by the bank. This page will show the result shortly.'],
            Payment::STATUS_FAILED => ['success' => false, 'status' => 'failed', 'message' => 'The payment failed. You have not been charged — you can try again.'],
            default => ['success' => false, 'status' => 'pending', 'message' => 'The payment has not been completed yet.'],
        };
    }

    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (PaymentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $order), 404);
    }
}
