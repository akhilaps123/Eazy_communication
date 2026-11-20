@extends('layouts.app')
@use('App\Support\CustomerEsims')

@section('title', 'eSIM details - Eazy Communications')
@section('meta_description', 'Install your Eazy Communications eSIM.')

@push('head')
  @include('partials.portal-head')
@endpush

@section('content')
  <x-header :account-active="true" />

  <main class="account-page-main">
    @include('partials.portal-banner', ['title' => 'Your eSIM', 'crumbs' => [['My Account', route('my-account')], ['My eSIMs', route('my-esims')], [$bundle, null]]])

    <section class="portal-section" aria-labelledby="esimHeading">
      <div class="portal-container">
        <x-account-nav active="esims" />
        <p><a class="portal-back" href="{{ route('my-esims') }}">&larr; All my eSIMs</a></p>
        <h2 class="portal-heading" id="esimHeading">{{ $bundle }}</h2>

        <div class="portal-esim-layout">
          <section class="portal-card portal-qr-card" aria-labelledby="qrHeading">
            <h3 class="portal-card-title" id="qrHeading">Scan to install</h3>
            @if ($hasQr)
              <img class="portal-qr" src="{{ route('my-esims.qr', $esim) }}" width="300" height="300" alt="QR code to install your {{ $bundle }} eSIM">
              <a class="portal-btn" href="{{ route('my-esims.qr', $esim) }}?download=1" download>Download QR code (PNG)</a>
              <p class="portal-muted portal-small">Scan this code with the phone you want to use the eSIM on. If you are viewing this page on that phone, use the manual details below or another screen.</p>
            @else
              <div class="portal-qr-empty" role="alert" data-qr-unavailable>
                <strong>QR code not available</strong>
                <span>{{ $qrError }}</span>
                <a href="{{ route('esim-support') }}">Contact support</a>
              </div>
            @endif
          </section>

          <section class="portal-card" aria-labelledby="detailsHeading">
            <h3 class="portal-card-title" id="detailsHeading">eSIM details</h3>
            <dl class="portal-facts portal-facts-stack">
              <div><dt>Plan</dt><dd>{{ $bundle }}</dd></div>
              <div><dt>Order</dt><dd><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></dd></div>
              <div><dt>ICCID</dt><dd class="portal-mono">{{ $esim->iccid }}</dd></div>
              <div><dt>Status</dt><dd>{{ CustomerEsims::activationLabel($esim->status) }}</dd></div>
              <div><dt>Purchased</dt><dd>{{ ($order->paid_at ?? $esim->created_at)->format('d M Y') }}</dd></div>
            </dl>
            <p class="portal-note">Showing the QR code does not mean the eSIM is installed. Activation depends on your plan and the network.</p>

            @if ($payload)
              <details class="portal-manual" data-manual>
                <summary>Manual installation details</summary>
                <p class="portal-muted portal-small">Use these if you cannot scan the QR code. Keep them private — anyone with them can install this eSIM.</p>
                @foreach ([
                  'smdp' => ['SM-DP+ address', $payload->smdpHost],
                  'code' => ['Activation code', $payload->matchingId],
                  'lpa' => ['Full activation code (LPA)', $payload->toString()],
                ] as $key => [$label, $value])
                  <div class="portal-copy">
                    <label for="manual-{{ $key }}">{{ $label }}</label>
                    <div class="portal-copy-row">
                      <input type="text" id="manual-{{ $key }}" value="{{ $value }}" readonly spellcheck="false" autocomplete="off">
                      <button type="button" class="portal-btn portal-btn-light portal-btn-sm" data-copy="manual-{{ $key }}" aria-label="Copy {{ $label }}">Copy</button>
                    </div>
                  </div>
                @endforeach
                <p class="portal-copy-status portal-visually-hidden" role="status" aria-live="polite" data-copy-status></p>
              </details>
            @endif
          </section>
        </div>

        <section class="portal-card portal-help" aria-labelledby="installHeading">
          <h3 class="portal-card-title" id="installHeading">How to install your eSIM</h3>
          <div class="portal-help-grid">
            <div>
              <h4>iPhone</h4>
              <ol>
                <li>Open <strong>Settings</strong>.</li>
                <li>Select <strong>Mobile Service</strong> or <strong>Cellular</strong>.</li>
                <li>Select <strong>Add eSIM</strong>.</li>
                <li>Choose <strong>Use QR Code</strong>.</li>
                <li>Scan the QR code shown on this page.</li>
                <li>Follow the phone’s instructions.</li>
              </ol>
            </div>
            <div>
              <h4>Android</h4>
              <ol>
                <li>Open <strong>Settings</strong>.</li>
                <li>Open <strong>Network &amp; Internet</strong> or <strong>Connections</strong>.</li>
                <li>Select <strong>SIMs</strong> or <strong>SIM Manager</strong>.</li>
                <li>Select <strong>Add eSIM</strong>.</li>
                <li>Scan the QR code.</li>
                <li>Follow the phone’s instructions.</li>
              </ol>
            </div>
          </div>
          <ul class="portal-notes">
            <li>Menu names vary between phone manufacturers and software versions.</li>
            <li>Your phone must support eSIM, and some phones are locked to a carrier or region.</li>
            <li>No QR scanner? Choose “Enter details manually” and use the manual installation details above.</li>
            <li>Installing the eSIM does not necessarily activate the plan — activation depends on the plan and its requirements.</li>
          </ul>
          <p class="portal-muted">Need help? Visit <a href="{{ route('esim-support') }}">eSIM support</a> and quote order {{ $order->order_number }}.</p>
        </section>
      </div>
    </section>
  </main>

  <x-footer variant="alt" />
@endsection

@push('scripts')
  <script src="{{ asset('js/customer-portal.js') }}"></script>
@endpush
