@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Catalogue overview</h1>
      <p class="adm-sub">Countries, plans and prices in the Eazy Communication catalogue. Customer prices are shown in {{ $currency }}.</p>
    </div>
  </div>

  {{-- KPI cards: all values come from DashboardController (database), nothing is hard-coded. --}}
  <section class="adm-kpis" aria-label="Catalogue statistics">
    <a class="adm-card adm-kpi" href="{{ route('admin.countries.index', ['status' => 'active']) }}" data-kpi="countries">
      <span class="adm-kpi-top">
        <span class="adm-kpi-label">Active countries</span>
        <span class="adm-kpi-icon">@include('admin.partials.icon', ['name' => 'globe'])</span>
      </span>
      <span class="adm-kpi-value">{{ $countriesActive }} <small>/ {{ $countriesTotal }}</small></span>
      <p class="adm-kpi-desc">Destinations customers can choose, out of {{ $countriesTotal }} in total.</p>
    </a>

    <a class="adm-card adm-kpi" href="{{ route('admin.plans.index', ['status' => 'active']) }}" data-kpi="plans">
      <span class="adm-kpi-top">
        <span class="adm-kpi-label">Active plans</span>
        <span class="adm-kpi-icon">@include('admin.partials.icon', ['name' => 'layers'])</span>
      </span>
      <span class="adm-kpi-value">{{ $plansActive }} <small>/ {{ $plansTotal }}</small></span>
      <p class="adm-kpi-desc">Plans switched on, out of {{ $plansTotal }} in total.</p>
    </a>

    <a class="adm-card adm-kpi" href="{{ route('admin.plans.index', ['status' => 'active']) }}" data-kpi="sellable">
      <span class="adm-kpi-top">
        <span class="adm-kpi-label">Plans customers can buy</span>
        <span class="adm-kpi-icon is-ok">@include('admin.partials.icon', ['name' => 'cart'])</span>
      </span>
      <span class="adm-kpi-value">{{ $plansSellable }}</span>
      <p class="adm-kpi-desc">Active, covering an active country, with an approved {{ $currency }} price in effect.</p>
    </a>

    <a class="adm-card adm-kpi" href="{{ route('admin.plans.index', ['prices' => 'pending']) }}" data-kpi="pending">
      <span class="adm-kpi-top">
        <span class="adm-kpi-label">Prices waiting for approval</span>
        <span @class(['adm-kpi-icon', 'is-warn' => $pendingPrices > 0])>@include('admin.partials.icon', ['name' => 'clock'])</span>
      </span>
      <span class="adm-kpi-value">{{ $pendingPrices }}</span>
      <p class="adm-kpi-desc">{{ $pendingPrices > 0 ? 'Review and approve them so customers can see them.' : 'Nothing to review right now.' }}</p>
    </a>
  </section>

  {{-- Phase 9F: order & fulfillment overview — database figures (OrderLifecycle::overview), each linking to the matching order filter. --}}
  <section class="adm-card" aria-labelledby="orders-overview-title" data-section="orders-overview">
    <div class="adm-card-head">
      <h2 id="orders-overview-title">Order &amp; fulfillment overview</h2>
      <a class="adm-hint" href="{{ route('admin.orders.index') }}">All orders &rarr;</a>
    </div>
    @php
      $orderCards = [
          ['key' => 'total', 'label' => 'Total orders', 'icon' => 'cart', 'tone' => null, 'query' => [], 'desc' => 'Every order placed, whatever its status.'],
          ['key' => 'pending_payment', 'label' => 'Pending payments', 'icon' => 'clock', 'tone' => null, 'query' => ['status' => 'pending_payment'], 'desc' => 'Orders waiting for the customer to pay.'],
          ['key' => 'awaiting', 'label' => 'Paid, awaiting fulfillment', 'icon' => 'layers', 'tone' => null, 'query' => ['fulfilment' => 'awaiting'], 'desc' => 'Paid orders queued or being processed.'],
          ['key' => 'fulfilled', 'label' => 'Fulfilled orders', 'icon' => 'check', 'tone' => 'is-ok', 'query' => ['fulfilment' => 'fulfilled'], 'desc' => 'All eSIMs received from eSIM Go.'],
          ['key' => 'failed', 'label' => 'Failed fulfillment', 'icon' => 'close', 'tone' => 'is-error', 'query' => ['fulfilment' => 'failed'], 'desc' => 'eSIM Go refused the purchase; nothing was bought.'],
          ['key' => 'attention', 'label' => 'Needs attention or reconciliation', 'icon' => 'tag', 'tone' => 'is-warn', 'query' => ['fulfilment' => 'attention'], 'desc' => 'A person must check these orders.'],
      ];
    @endphp
    <div class="adm-kpis adm-kpis-orders" style="margin-bottom:0">
      @foreach ($orderCards as $card)
        <a class="adm-card adm-kpi" href="{{ route('admin.orders.index', $card['query']) }}" data-kpi="orders-{{ $card['key'] }}">
          <span class="adm-kpi-top">
            <span class="adm-kpi-label">{{ $card['label'] }}</span>
            <span @class(['adm-kpi-icon', $card['tone'] => $card['tone'] && $orders[$card['key']] > 0])>@include('admin.partials.icon', ['name' => $card['icon']])</span>
          </span>
          <span class="adm-kpi-value">{{ $orders[$card['key']] }}</span>
          <p class="adm-kpi-desc">{{ $card['desc'] }}</p>
        </a>
      @endforeach
    </div>
  </section>

  <div class="adm-dash-grid" style="margin-top:20px">
    {{-- Live catalogue status (read-only: the switch is a server setting). --}}
    <section class="adm-card" aria-labelledby="live-title">
      <div class="adm-card-head">
        <h2 id="live-title">Live catalogue</h2>
        @if ($liveCatalogue)
          <span class="adm-badge adm-badge-ok"><span class="adm-dot"></span>ON</span>
        @else
          <span class="adm-badge"><span class="adm-dot"></span>OFF</span>
        @endif
      </div>

      <div class="adm-status">
        <span @class(['adm-status-icon', 'is-on' => $liveCatalogue])>@include('admin.partials.icon', ['name' => 'broadcast', 'size' => 22])</span>
        <div>
          @if ($liveCatalogue)
            <h3>The website shows this catalogue</h3>
            <p>The website shows countries, plans and prices from this admin panel.</p>
          @else
            <h3>The website shows its original static design</h3>
            <p>The website still shows its original static design and sample prices. Your countries, plans and prices are saved but not shown on the website yet.</p>
          @endif
          <p>The public catalogue API always lists sellable plans, whichever way this is set.</p>
        </div>
      </div>

      @if ($liveCatalogue && $plansSellable === 0)
        <div class="adm-alert adm-alert-warn" style="margin:14px 0 0">The live catalogue is on but no plan is sellable yet, so the website shows no plans.</div>
      @endif

      <div class="adm-status-note">
        @if ($liveCatalogue)
          <p>To switch it off, a developer removes <code>CATALOGUE_LIVE=true</code> from <code>.env</code> and runs <code>php artisan config:clear</code>.</p>
        @else
          <p>After entering real countries, plans and approved prices, a developer can switch it on by adding
            <code>CATALOGUE_LIVE=true</code> to <code>.env</code> and running <code>php artisan config:clear</code>.</p>
        @endif
      </div>
    </section>

    {{-- Quick actions: links to existing admin routes only. --}}
    <section class="adm-card" aria-labelledby="quick-title">
      <div class="adm-card-head">
        <h2 id="quick-title">Quick actions</h2>
      </div>
      <div class="adm-quick">
        <a class="adm-action" href="{{ route('admin.countries.create') }}">
          <span class="adm-action-icon">@include('admin.partials.icon', ['name' => 'plus'])</span>
          <span class="adm-action-text"><strong>Add country</strong><small>New destination</small></span>
          @include('admin.partials.icon', ['name' => 'chevron', 'size' => 18])
        </a>
        <a class="adm-action" href="{{ route('admin.plans.create') }}">
          <span class="adm-action-icon">@include('admin.partials.icon', ['name' => 'layers'])</span>
          <span class="adm-action-text"><strong>Create plan</strong><small>With its provider bundle</small></span>
          @include('admin.partials.icon', ['name' => 'chevron', 'size' => 18])
        </a>
        <a class="adm-action" href="{{ route('admin.plans.index') }}">
          <span class="adm-action-icon">@include('admin.partials.icon', ['name' => 'tag'])</span>
          <span class="adm-action-text"><strong>Manage prices</strong><small>Open a plan to edit its prices</small></span>
          @include('admin.partials.icon', ['name' => 'chevron', 'size' => 18])
        </a>
        <a class="adm-action" href="{{ route('admin.plans.index', ['prices' => 'pending']) }}">
          <span class="adm-action-icon">@include('admin.partials.icon', ['name' => 'check'])</span>
          <span class="adm-action-text"><strong>Review pending prices</strong><small>Plans with prices to approve</small></span>
          @if ($pendingPrices > 0)<span class="adm-count">{{ $pendingPrices }}</span>@endif
          @include('admin.partials.icon', ['name' => 'chevron', 'size' => 18])
        </a>
      </div>
    </section>
  </div>

  {{-- How to publish a plan --}}
  <section class="adm-card" aria-labelledby="publish-title" style="margin-top:20px">
    <div class="adm-card-head">
      <h2 id="publish-title">How to publish a plan</h2>
      <span class="adm-hint">Five steps from new country to sellable plan</span>
    </div>
    <ol class="adm-steps">
      <li class="adm-step">
        <h3>Add countries</h3>
        <p><a href="{{ route('admin.countries.create') }}">Add the countries</a> the plan covers and keep them active.</p>
      </li>
      <li class="adm-step">
        <h3>Create the plan</h3>
        <p><a href="{{ route('admin.plans.create') }}">Create the plan</a> with its provider bundle code, provider cost and currency.</p>
      </li>
      <li class="adm-step">
        <h3>Add a {{ $currency }} price</h3>
        <p>On the plan page, add the customer {{ $currency }} price and check the preview.</p>
      </li>
      <li class="adm-step">
        <h3>Review &amp; approve</h3>
        <p>Save it as pending, review it, then approve it.</p>
      </li>
      <li class="adm-step">
        <h3>Activate the plan</h3>
        <p>Activate the plan. It becomes visible once the live catalogue is on.</p>
      </li>
    </ol>
  </section>
@endsection
