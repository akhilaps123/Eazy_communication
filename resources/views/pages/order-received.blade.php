@extends('layouts.app')

{{--
  Order Received (Phase 7, decision 8) — built from the checkout page design.
  Customer-safe fields only: no provider cost, bundle code, margin or internal details.
  No payment has been taken and no eSIM has been sent at this stage.
--}}

@section('title', 'Order Received - Eazy Communications')
@section('meta_description', 'Your Eazy Communications eSIM order has been received and is awaiting payment.')

@push('head')
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/checkout.css') }}">
  <link rel="stylesheet" href="{{ asset('css/customer-portal.css') }}">
@endpush

@section('content')
  <x-header :cart-active="true" :mobile-links="[
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main>
    <section class="page-banner" aria-label="Order Received Banner">
      <img src="{{ asset('assets/images/checkout-banner.webp') }}" alt="Winter village lakeside landscape" class="page-banner-img checkout-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">Order Received</h1>
      </div>
    </section>

    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current" aria-current="page">ORDER {{ $order->order_number }}</span>
      </div>
    </nav>

    <section class="checkout-section" id="order">
      <div class="checkout-container">
        <div class="checkout-grid">

          <div class="checkout-form-col">
            <h2 class="checkout-main-title">Thank you — your order has been received</h2>

            <section class="checkout-block order-received-block" aria-labelledby="orderDetailsHeading">
              <h3 class="checkout-block-title" id="orderDetailsHeading">Order details</h3>
              <div class="checkout-breakdown">
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Order number</span>
                  <span class="checkout-breakdown-val" data-order-number>{{ $order->order_number }}</span>
                </div>
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Order date</span>
                  <span class="checkout-breakdown-val">{{ $order->created_at->format('d M Y, H:i') }}</span>
                </div>
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Status</span>
                  <span class="checkout-breakdown-val">{{ match ($order->payment_status) { \App\Models\Order::PAYMENT_PENDING => 'Awaiting Payment', \App\Models\Order::PAYMENT_PAID => 'Paid', default => ucfirst(str_replace('_', ' ', $order->payment_status)) } }}</span>
                </div>
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">eSIM status</span>
                  <span class="checkout-breakdown-val" data-fulfilment-status>{{ \App\Support\CustomerEsims::stageLabel($stage) }}</span>
                </div>
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Billing</span>
                  <span class="checkout-breakdown-val">{{ $order->billing_name }} · {{ $billingCountry ?? $order->billing_country }}{{ $order->billing_postal_code ? ' '.$order->billing_postal_code : '' }}</span>
                </div>
                @if ($order->billing_gstin)
                  <div class="checkout-breakdown-row">
                    <span class="checkout-breakdown-label">GST number</span>
                    <span class="checkout-breakdown-val">{{ $order->billing_gstin }}</span>
                  </div>
                @endif
              </div>
            </section>

            <section class="checkout-block order-received-block" aria-labelledby="orderItemsHeading">
              <h3 class="checkout-block-title" id="orderItemsHeading">Items</h3>
              <div class="checkout-breakdown">
                @foreach ($order->items as $item)
                  <div class="checkout-breakdown-row" data-order-item>
                    <span class="checkout-breakdown-label">
                      <strong>{{ $item->plan_name }}</strong><br>
                      <small>
                        Countries: {{ $item->plan?->countries->pluck('name')->join(', ') ?: '—' }}
                        @if ($item->trip_start_date) · Trip start {{ $item->trip_start_date->format('Y-m-d') }} @endif
                        <br>{{ $item->quantity }} × {{ $money((string) $item->unit_price) }}
                      </small>
                    </span>
                    <span class="checkout-breakdown-val">{{ $money((string) $item->line_total) }}</span>
                  </div>
                @endforeach
              </div>
            </section>

            {{-- Phase 9E: eSIMs of this order — only real, delivered eSIMs; otherwise the order's stage. --}}
            <section class="checkout-block order-received-block" aria-labelledby="orderEsimsHeading" data-order-esims>
              <h3 class="checkout-block-title" id="orderEsimsHeading">Your eSIMs</h3>
              @if ($esims->isNotEmpty())
                <div class="checkout-breakdown">
                  @foreach ($esims as $esim)
                    <div class="checkout-breakdown-row" data-order-esim>
                      <span class="checkout-breakdown-label">
                        <strong>{{ $order->items->firstWhere('id', $esim->order_item_id)?->plan_name ?? 'eSIM' }}</strong><br>
                        <small>ICCID {{ \App\Support\CustomerEsims::maskIccid($esim->iccid) }} · {{ \App\Support\CustomerEsims::activationLabel($esim->status) }}</small>
                      </span>
                      <a class="checkout-breakdown-val order-esim-link" href="{{ route('my-esims.show', $esim) }}">View eSIM &amp; QR code</a>
                    </div>
                  @endforeach
                </div>
              @else
                <p class="checkout-live-note" role="status"><strong>{{ \App\Support\CustomerEsims::stageLabel($stage) }}.</strong> {{ \App\Support\CustomerEsims::stageMessage($stage) }}</p>
              @endif
              <p class="checkout-live-note"><a href="{{ route('my-orders') }}">All my orders</a> · <a href="{{ route('my-esims') }}">My eSIMs</a></p>
            </section>
          </div>

          <div class="checkout-summary-col">
            <h2 class="checkout-summary-title">Order total</h2>
            <aside class="checkout-summary-card" aria-label="Order total">
              <div class="checkout-breakdown">
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Subtotal</span>
                  <span class="checkout-breakdown-val">{{ $money((string) $order->subtotal) }}</span>
                </div>
                <div class="checkout-breakdown-divider" aria-hidden="true"></div>
                <div class="checkout-breakdown-row checkout-total-row">
                  <span class="checkout-total-label">Total:</span>
                  <span class="checkout-total-val" data-order-total>{{ $money((string) $order->total) }}</span>
                </div>
              </div>
              {{-- Payment (Phase 8, Razorpay TEST MODE). Public data only: never secrets. --}}
              @php
                $isPaid = $order->payment_status === \App\Models\Order::PAYMENT_PAID;
                $isProcessing = ! $isPaid && $payment?->status === \App\Models\Payment::STATUS_PENDING;
                $lastFailed = ! $isPaid && $payment?->status === \App\Models\Payment::STATUS_FAILED;
                $paymentLabel = $isPaid ? 'Paid' : ($isProcessing ? 'Processing' : ($lastFailed ? 'Payment failed' : 'Awaiting Payment'));
              @endphp
              <section class="order-payment" id="orderPayment" aria-labelledby="paymentHeading">
                <h3 class="checkout-block-title" id="paymentHeading">Payment</h3>
                <div class="checkout-breakdown">
                  <div class="checkout-breakdown-row">
                    <span class="checkout-breakdown-label">Order number</span>
                    <span class="checkout-breakdown-val">{{ $order->order_number }}</span>
                  </div>
                  <div class="checkout-breakdown-row">
                    <span class="checkout-breakdown-label">Amount</span>
                    <span class="checkout-breakdown-val">{{ $money((string) $order->total) }} {{ $order->currency }}</span>
                  </div>
                  <div class="checkout-breakdown-row">
                    <span class="checkout-breakdown-label">Payment status</span>
                    <span class="checkout-breakdown-val" data-payment-status>{{ $paymentLabel }}</span>
                  </div>
                </div>

                @if ($isPaid)
                  <p class="checkout-live-note" role="status">
                    <strong>Payment received{{ $order->paid_at ? ' on '.$order->paid_at->format('d M Y, H:i') : '' }}.</strong>
                    Thank you — your order is paid. {{ $stage === 'ready' ? 'Your eSIM is ready — see “Your eSIMs”.' : 'We will prepare your eSIM next.' }}
                  </p>
                @elseif ($isProcessing)
                  <p class="checkout-live-note" role="status">
                    <strong>Your payment is being confirmed.</strong> It has been authorised by the bank but not yet captured.
                    This page will show “Paid” once it is confirmed — please do not pay again.
                  </p>
                @elseif ($canPay)
                  @if ($lastFailed)
                    <p class="checkout-live-error" role="alert">Your last payment attempt failed. You have not been charged — you can try again.</p>
                  @endif
                  <p class="checkout-live-note">Secure test-mode payment by Razorpay. The amount comes from your order and cannot be changed.</p>
                  <p class="checkout-live-note" data-payment-message role="status" aria-live="polite" hidden></p>
                  <div class="checkout-cta-wrap">
                    <button type="button" class="btn-checkout-submit" id="btnPayNow"
                      data-initiate-url="{{ route('orders.payment.initiate', $order) }}"
                      data-verify-url="{{ route('orders.payment.verify', $order) }}"
                      data-failure-url="{{ route('orders.payment.failure', $order) }}">
                      {{ $lastFailed ? 'Try payment again' : 'Pay Now' }} · {{ $money((string) $order->total) }}
                    </button>
                  </div>
                @else
                  <p class="checkout-live-note" role="status">
                    <strong>Payment will be available soon.</strong> Your order is saved as Awaiting Payment and you have not been charged.
                    Your eSIM will be delivered after payment.
                    @if ($paymentUnavailable && ! str_starts_with($paymentUnavailable, 'Online payment is not available yet'))
                      <br>{{ $paymentUnavailable }}
                    @endif
                  </p>
                @endif
              </section>
              <div class="checkout-cta-wrap">
                <a href="{{ route('home') }}" class="btn-checkout-submit" style="display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">Back to website</a>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </section>
  </main>

  <x-footer />
@endsection

@if ($canPay)
  @push('scripts')
    {{-- Official Razorpay Standard Checkout + our handler (no secrets in either). --}}
    <script src="{{ $checkoutScript }}"></script>
    <script src="{{ asset('js/order-payment.js') }}"></script>
  @endpush
@endif
