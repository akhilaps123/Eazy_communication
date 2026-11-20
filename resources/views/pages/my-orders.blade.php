@extends('layouts.app')
@use('App\Support\CustomerEsims')
@use('App\Support\OrderLifecycle')

@section('title', 'My Orders - Eazy Communications')
@section('meta_description', 'Your Eazy Communications eSIM orders.')

@push('head')
  @include('partials.portal-head')
@endpush

@section('content')
  <x-header :account-active="true" />

  <main class="account-page-main">
    @include('partials.portal-banner', ['title' => 'My Orders', 'crumbs' => [['My Account', route('my-account')], ['My Orders', null]]])

    <section class="portal-section" aria-labelledby="ordersHeading">
      <div class="portal-container">
        <x-account-nav active="orders" />
        <h2 class="portal-heading" id="ordersHeading">Order history</h2>

        @if ($orders->isEmpty())
          <div class="portal-empty" data-empty="orders">
            <h3>No orders yet</h3>
            <p>When you buy an eSIM, your orders will appear here.</p>
            <a class="portal-btn" href="{{ route('new-esim') }}">Browse eSIM plans</a>
          </div>
        @else
          <div class="portal-list">
            @foreach ($orders as $order)
              @php
                $stage = CustomerEsims::stage($order);
                $paymentLabel = match ($order->payment_status) {
                    \App\Models\Order::PAYMENT_PAID => 'Paid',
                    default => CustomerEsims::paymentLabel($order),
                };
              @endphp
              <article class="portal-card portal-order" data-order="{{ $order->order_number }}">
                <header class="portal-card-head">
                  <div>
                    <h3 class="portal-card-title">Order {{ $order->order_number }}</h3>
                    <p class="portal-muted">Placed {{ $order->created_at->format('d M Y, H:i') }}</p>
                  </div>
                  <span class="portal-badge is-{{ CustomerEsims::stageTone($stage) }}">{{ CustomerEsims::stageLabel($stage) }}</span>
                </header>

                <ul class="portal-items">
                  @foreach ($order->items as $item)
                    <li><span>{{ $item->plan_name }}</span> <span class="portal-muted">× {{ $item->quantity }}</span></li>
                  @endforeach
                </ul>

                <dl class="portal-facts">
                  <div><dt>Total</dt><dd><strong>{{ $money((string) $order->total, $order->currency) }}</strong> <span class="portal-muted">{{ $order->currency }}</span></dd></div>
                  <div><dt>Order status</dt><dd>{{ OrderLifecycle::orderLabel($order->payment_status) }}</dd></div>
                  <div><dt>Payment</dt><dd>{{ $paymentLabel }}</dd></div>
                  <div><dt>eSIM status</dt><dd>{{ CustomerEsims::stageLabel($stage) }}</dd></div>
                </dl>

                <footer class="portal-card-foot">
                  <p class="portal-muted">{{ CustomerEsims::stageMessage($stage) }}</p>
                  <a class="portal-btn portal-btn-light" href="{{ route('orders.show', $order) }}">View order<span class="portal-visually-hidden"> {{ $order->order_number }}</span></a>
                </footer>
              </article>
            @endforeach
          </div>

          @if ($orders->hasPages())
            <nav class="portal-pagination" aria-label="Order pages">
              @if ($orders->onFirstPage())
                <span class="portal-btn portal-btn-light is-disabled" aria-disabled="true">Previous</span>
              @else
                <a class="portal-btn portal-btn-light" href="{{ $orders->previousPageUrl() }}" rel="prev">Previous</a>
              @endif
              <span class="portal-muted">Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
              @if ($orders->hasMorePages())
                <a class="portal-btn portal-btn-light" href="{{ $orders->nextPageUrl() }}" rel="next">Next</a>
              @else
                <span class="portal-btn portal-btn-light is-disabled" aria-disabled="true">Next</span>
              @endif
            </nav>
          @endif
        @endif
      </div>
    </section>
  </main>

  <x-footer variant="alt" />
@endsection
