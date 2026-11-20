@extends('layouts.app')
@use('App\Support\CustomerEsims')

@section('title', 'My eSIMs - Eazy Communications')
@section('meta_description', 'Your Eazy Communications eSIMs and installation details.')

@push('head')
  @include('partials.portal-head')
@endpush

@section('content')
  <x-header :account-active="true" />

  <main class="account-page-main">
    @include('partials.portal-banner', ['title' => 'My eSIMs', 'crumbs' => [['My Account', route('my-account')], ['My eSIMs', null]]])

    <section class="portal-section" aria-labelledby="esimsHeading">
      <div class="portal-container">
        <x-account-nav active="esims" />
        <h2 class="portal-heading" id="esimsHeading">My eSIMs</h2>

        @if ($esims->isEmpty() && $pending->isEmpty())
          <div class="portal-empty" data-empty="esims">
            <h3>No eSIM purchased yet</h3>
            <p>You have not received any eSIM. Once you buy a plan and your payment is confirmed, your eSIM appears here with its QR code.</p>
            <a class="portal-btn" href="{{ route('new-esim') }}">Browse eSIM plans</a>
          </div>
        @endif

        @if ($esims->isNotEmpty())
          <div class="portal-grid">
            @foreach ($esims as $esim)
              @php $order = $esim->orderItem->order; @endphp
              <article class="portal-card portal-esim" data-esim-card="{{ $esim->id }}">
                <header class="portal-card-head">
                  <div>
                    <h3 class="portal-card-title">{{ $esim->orderItem->plan_name }}</h3>
                    <p class="portal-muted">ICCID <span class="portal-mono">{{ CustomerEsims::maskIccid($esim->iccid) }}</span></p>
                  </div>
                  <span class="portal-badge is-ok">eSIM ready</span>
                </header>
                <dl class="portal-facts">
                  <div><dt>Order</dt><dd><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></dd></div>
                  <div><dt>Purchased</dt><dd>{{ ($order->paid_at ?? $esim->created_at)->format('d M Y') }}</dd></div>
                  <div><dt>Status</dt><dd>{{ CustomerEsims::activationLabel($esim->status) }}</dd></div>
                  <div><dt>QR code</dt><dd>{{ $qrReady[$esim->id] ? 'Available' : 'Not available — contact support' }}</dd></div>
                </dl>
                <footer class="portal-card-foot">
                  <a class="portal-btn" href="{{ route('my-esims.show', $esim) }}">View details &amp; QR code<span class="portal-visually-hidden"> for {{ $esim->orderItem->plan_name }}</span></a>
                </footer>
              </article>
            @endforeach
          </div>
        @endif

        @if ($pending->isNotEmpty())
          <h3 class="portal-subheading">Orders in progress</h3>
          <div class="portal-list">
            @foreach ($pending as $order)
              @php $stage = CustomerEsims::stage($order); @endphp
              <article class="portal-card portal-pending" data-pending-order="{{ $order->order_number }}" data-stage="{{ $stage }}">
                <header class="portal-card-head">
                  <div>
                    <h3 class="portal-card-title">Order {{ $order->order_number }}</h3>
                    <p class="portal-muted">{{ $order->items->pluck('plan_name')->join(', ') }}</p>
                  </div>
                  <span class="portal-badge is-{{ CustomerEsims::stageTone($stage) }}">{{ CustomerEsims::stageLabel($stage) }}</span>
                </header>
                <p class="portal-note">{{ CustomerEsims::stageMessage($stage) }}</p>
                <footer class="portal-card-foot">
                  <a class="portal-btn portal-btn-light" href="{{ route('orders.show', $order) }}">View order</a>
                  @if ($stage === 'attention')
                    <a class="portal-btn portal-btn-light" href="{{ route('esim-support') }}">Contact support</a>
                  @endif
                </footer>
              </article>
            @endforeach
          </div>
        @endif
      </div>
    </section>
  </main>

  <x-footer variant="alt" />
@endsection
