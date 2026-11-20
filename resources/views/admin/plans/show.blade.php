@extends('admin.layouts.app')
@use('App\Services\CatalogueService')
@use('App\Support\PlanAdminInsights')

@section('title', $plan->name)

@section('content')
  @php
    $bundle = $plan->providerBundle;
    $pending = $plan->prices->where('approval_status', 'pending')->values();
    $currentId = $currentPrice?->id;
  @endphp

  <div class="adm-head">
    <div>
      <h1>{{ $plan->name }}
        @if ($plan->is_active)
          <span class="adm-badge adm-badge-ok">Active</span>
        @else
          <span class="adm-badge">Inactive</span>
        @endif
      </h1>
      <p class="adm-sub"><a href="{{ route('admin.plans.index') }}">&larr; Plans</a></p>
    </div>
    <div class="adm-actions">
      <a class="adm-btn" href="{{ route('admin.plans.edit', $plan) }}">Edit plan</a>
      @if ($plan->is_active)
        <form method="POST" action="{{ route('admin.plans.deactivate', $plan) }}">
          @csrf
          <button class="adm-btn adm-btn-light" type="submit">Deactivate</button>
        </form>
      @else
        <form method="POST" action="{{ route('admin.plans.activate', $plan) }}">
          @csrf
          <button class="adm-btn adm-btn-light" type="submit">Activate</button>
        </form>
      @endif
    </div>
  </div>

  @if ($problems)
    <div class="adm-alert adm-alert-warn">
      <strong>{{ $plan->is_active ? 'This active plan is hidden from customers:' : 'Before this plan can be activated:' }}</strong>
      <ul>
        @foreach ($problems as $problem)
          <li>{{ $problem }}</li>
        @endforeach
      </ul>
    </div>
  @elseif ($plan->is_active)
    <div class="adm-alert adm-alert-ok">This plan is sellable: customers can see it (once the live catalogue is on) and in the public API.</div>
  @else
    <div class="adm-alert adm-alert-ok">This plan meets all requirements and can be activated.</div>
  @endif

  {{-- Waiting for approval --}}
  @if ($pending->isNotEmpty())
    <section class="adm-card adm-pending" id="pending-prices" aria-labelledby="pending-title">
      <div class="adm-card-head">
        <h2 id="pending-title">Waiting for approval <span class="adm-count">{{ $pending->count() }}</span></h2>
        <span class="adm-hint">Approve to make a price active, or reject it. Both stay in the price history.</span>
      </div>
      <div class="adm-pending-list">
        @foreach ($pending as $price)
          @php
            $margin = PlanAdminInsights::margin(PlanAdminInsights::amount($price), $price->currency, $bundle);
          @endphp
          <div class="adm-pending-item">
            <div class="adm-pending-price">
              <strong>{{ CatalogueService::money(PlanAdminInsights::amount($price), $price->currency) }}</strong>
              <span class="adm-hint">{{ PlanAdminInsights::ruleLabel($price) }}</span>
            </div>
            <dl class="adm-pending-meta">
              <div><dt>Margin</dt><dd>{{ $margin ? $margin['margin'].' ('.$margin['percent'].'%)' : 'n/a (different currency)' }}</dd></div>
              <div><dt>Starts</dt><dd>{{ $price->effective_from->format('d M Y H:i') }}{{ $price->effective_to ? ' → '.$price->effective_to->format('d M Y H:i') : '' }}</dd></div>
              <div><dt>Crossed-out</dt><dd>{{ $price->compare_at_price ? CatalogueService::money((string) $price->compare_at_price, $price->currency) : '—' }}</dd></div>
              <div><dt>Added by</dt><dd>{{ $price->creator?->name ?? '—' }}</dd></div>
            </dl>
            <div class="adm-actions">
              <form method="POST" action="{{ route('admin.plans.prices.approve', [$plan, $price]) }}">
                @csrf
                <button class="adm-btn adm-btn-sm" type="submit">Approve</button>
              </form>
              <form method="POST" action="{{ route('admin.plans.prices.reject', [$plan, $price]) }}" data-confirm="Reject this price? It stays in the history and can't be approved later.">
                @csrf
                <button class="adm-btn adm-btn-danger adm-btn-sm" type="submit">Reject</button>
              </form>
              <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.plans.prices.edit', [$plan, $price]) }}">Edit</a>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <div class="adm-grid">
    <div class="adm-card">
      <h2>Plan</h2>
      <dl class="adm-dl">
        <dt>Card title</dt><dd>{{ $plan->title() }}</dd>
        <dt>Slug</dt><dd>{{ $plan->slug }}</dd>
        <dt>Coverage</dt><dd>{{ ucfirst($plan->coverage_type) }}{{ $plan->region_name ? ' · '.$plan->region_name : '' }}</dd>
        <dt>Data</dt><dd>{{ $plan->dataLabel() }}@if ($plan->data_amount_mb) <small class="adm-hint">({{ number_format($plan->data_amount_mb) }} MB)</small>@endif</dd>
        <dt>Validity</dt><dd>{{ $plan->validity_days }} days</dd>
        <dt>Network</dt><dd>{{ $plan->network_speed ?? '—' }}</dd>
        <dt>Badge</dt><dd>{{ \App\Models\Plan::BADGES[$plan->badge] ?? '—' }}</dd>
        <dt>Features</dt><dd>{{ $plan->features ? implode(' · ', $plan->features) : '—' }}</dd>
        <dt>Countries</dt>
        <dd>
          @foreach ($plan->countries as $c)
            <a href="{{ route('admin.countries.show', $c) }}">{{ $c->name }}</a>@unless ($c->is_active) <span class="adm-badge">inactive</span>@endunless{{ $loop->last ? '' : ',' }}
          @endforeach
        </dd>
      </dl>
    </div>
    <div class="adm-card">
      <div class="adm-card-head">
        <h2>Provider bundle <small class="adm-hint">(internal)</small></h2>
        <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.plans.provider-bundle.edit', $plan) }}">Change provider bundle</a>
      </div>
      <dl class="adm-dl">
        <dt>Bundle code</dt><dd><a href="{{ route('admin.provider-bundles.show', $bundle) }}" class="adm-code">{{ $bundle->bundle_code }}</a></dd>
        <dt>Provider cost</dt><dd>{{ $bundle->provider_cost }} {{ $bundle->provider_currency }}</dd>
        <dt>Available</dt><dd>{!! $bundle->is_available ? '<span class="adm-badge adm-badge-ok">Yes</span>' : '<span class="adm-badge adm-badge-error">No</span>' !!}</dd>
        <dt>Source</dt><dd>{!! $bundle->isSynced() ? '<span class="adm-badge adm-badge-info">Synced from eSIM Go</span>' : '<span class="adm-badge">Entered manually</span>' !!}</dd>
        <dt>Current price</dt>
        <dd>
          @if ($currentPrice)
            {{ CatalogueService::money(PlanAdminInsights::amount($currentPrice), $currentPrice->currency) }}
            @if ($m = PlanAdminInsights::margin(PlanAdminInsights::amount($currentPrice), $currentPrice->currency, $bundle))
              <small class="adm-hint">· margin {{ $m['margin'] }} ({{ $m['percent'] }}%)</small>
            @endif
          @else
            <span class="adm-badge adm-badge-warn">None</span>
          @endif
        </dd>
      </dl>
    </div>
  </div>

  <div class="adm-card adm-table-wrap">
    <h2>Price history ({{ $currency }})</h2>
    @if ($plan->prices->isEmpty())
      <p class="adm-hint">No prices yet. Add one below.</p>
    @else
      <table class="adm-table">
        <thead>
          <tr><th>Selling price</th><th>Rule</th><th>Crossed-out</th><th>Period</th><th>Status</th><th>Added / reviewed by</th><th></th></tr>
        </thead>
        <tbody>
          @foreach ($plan->prices as $price)
            @php
              $status = PlanAdminInsights::priceStatus($price, $currentId);
              $stale = in_array($status['label'], ['In effect', 'Scheduled'], true) ? PlanAdminInsights::staleMarkupAmount($price, $plan) : null;
            @endphp
            <tr>
              <td>
                <strong>{{ CatalogueService::money(PlanAdminInsights::amount($price), $price->currency) }}</strong>
                @if (in_array($price->id, $belowCostPriceIds, true)) <span class="adm-badge adm-badge-error">Below cost</span> @endif
                @if ($stale)
                  <small class="adm-cell-sub adm-text-warn" data-stale-price>
                    Calculated from an older provider cost{{ $stale !== 'unavailable' ? ' — with today’s cost it would be '.$stale : '' }}. Add a new price to update it.
                  </small>
                @endif
              </td>
              <td>{{ PlanAdminInsights::ruleLabel($price) }}</td>
              <td>{{ $price->compare_at_price ?? '—' }}</td>
              <td class="adm-nowrap">
                {{ $price->effective_from->format('d M Y H:i') }} →
                {{ $price->effective_to ? $price->effective_to->format('d M Y H:i') : 'no end' }}
              </td>
              <td><span class="adm-badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
              <td><small>{{ $price->creator?->name ?? '—' }} / {{ $price->approver?->name ?? '—' }}</small></td>
              <td class="adm-actions">
                @if ($price->approval_status === 'pending')
                  <a class="adm-btn adm-btn-light adm-btn-sm" href="#pending-prices">Review</a>
                @elseif ($price->approval_status === 'approved' && $price->is_active && ! ($price->effective_to && $price->effective_to->isPast()))
                  @unless ($price->effective_from->isFuture())
                    <form method="POST" action="{{ route('admin.plans.prices.end', [$plan, $price]) }}" data-confirm="End this price now? It stays in the history.">
                      @csrf
                      <button class="adm-btn adm-btn-light adm-btn-sm" type="submit">End now</button>
                    </form>
                  @endunless
                  <form method="POST" action="{{ route('admin.plans.prices.deactivate', [$plan, $price]) }}" data-confirm="Deactivate this price? It stays in the history.">
                    @csrf
                    <button class="adm-btn adm-btn-light adm-btn-sm" type="submit">Deactivate</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <p class="adm-hint">Approved prices cannot be edited. To change a price, add a new one, approve it, then end or deactivate the old one. When several prices apply, the one with the latest start is “In effect”; older ones are “Superseded”.</p>
    @endif
  </div>

  <form class="adm-card" method="POST" action="{{ route('admin.plans.prices.store', $plan) }}" id="add-price">
    @csrf
    <h2>Add a price</h2>
    <p class="adm-hint">Provider cost: {{ $bundle->provider_cost }} {{ $bundle->provider_currency }}. New prices are saved as pending and must be approved before customers see them.</p>
    @include('admin.prices._form', ['price' => new \App\Models\PlanPrice])
  </form>

  @include('admin.partials.confirm-script')
@endsection
