@extends('admin.layouts.app')

@section('title', 'Plans')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Plans &amp; prices</h1>
      <p class="adm-sub">Plans are never deleted — deactivate a plan to hide it from customers.</p>
    </div>
    <a class="adm-btn" href="{{ route('admin.plans.create') }}">Create plan</a>
  </div>

  <form class="adm-card adm-filters" method="GET" action="{{ route('admin.plans.index') }}">
    <div class="adm-field">
      <label for="q">Search</label>
      <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Name, slug or bundle code">
    </div>
    <div class="adm-field">
      <label for="country">Country</label>
      <select id="country" name="country">
        <option value="">All countries</option>
        @foreach ($countries as $c)
          <option value="{{ $c->id }}" @selected($filters['country'] === $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-field">
      <label for="coverage">Coverage</label>
      <select id="coverage" name="coverage">
        <option value="">All</option>
        @foreach (\App\Models\Plan::COVERAGE_TYPES as $type)
          <option value="{{ $type }}" @selected($filters['coverage'] === $type)>{{ ucfirst($type) }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-field">
      <label for="data_type">Data</label>
      <select id="data_type" name="data_type">
        <option value="">All</option>
        <option value="fixed" @selected($filters['data_type'] === 'fixed')>Fixed</option>
        <option value="unlimited" @selected($filters['data_type'] === 'unlimited')>Unlimited</option>
      </select>
    </div>
    <div class="adm-field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <option value="active" @selected($filters['status'] === 'active')>Active</option>
        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
      </select>
    </div>
    <div class="adm-field">
      <label for="prices">Prices</label>
      <select id="prices" name="prices">
        <option value="">All</option>
        <option value="pending" @selected($filters['prices'] === 'pending')>Waiting for approval</option>
      </select>
    </div>
    <div class="adm-actions">
      <button class="adm-btn" type="submit">Filter</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.plans.index') }}">Reset</a>
    </div>
  </form>

  <div class="adm-card adm-table-wrap">
    <table class="adm-table adm-plans-table">
      <thead>
        <tr>
          <th>Plan</th>
          <th>Countries</th>
          <th>Data / validity</th>
          <th>Customer price ({{ $currency }})</th>
          <th class="adm-col-provider">Provider bundle</th>
          <th>Status</th>
          <th><span class="adm-visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($plans as $plan)
          @php
            $price = $plan->prices->first();
            $hidden = $plan->is_active ? \App\Support\PlanAdminInsights::hiddenReasons($plan) : [];
            $bundle = $plan->providerBundle;
          @endphp
          <tr>
            <td>
              <a href="{{ route('admin.plans.show', $plan) }}" class="adm-strong-link">{{ $plan->name }}</a>
              <small class="adm-cell-sub">{{ ucfirst($plan->coverage_type) }} coverage{{ $plan->network_speed ? ' · '.$plan->network_speed : '' }}</small>
            </td>
            <td data-label="Countries">
              {{ $plan->countries->pluck('name')->take(3)->join(', ') }}@if ($plan->countries->count() > 3) <span class="adm-hint">+{{ $plan->countries->count() - 3 }} more</span>@endif
            </td>
            <td class="adm-nowrap" data-label="Data / validity">{{ $plan->dataLabel() }} · {{ $plan->validity_days }} days</td>
            <td data-label="Customer price ({{ $currency }})">
              @if ($price)
                <strong>{{ \App\Services\CatalogueService::money(\App\Support\PlanAdminInsights::amount($price), $price->currency) }}</strong>
              @else
                <span class="adm-hint">No approved price</span>
              @endif
              @if ($plan->pending_prices_count > 0)
                <a href="{{ route('admin.plans.show', $plan) }}#pending-prices" class="adm-badge adm-badge-warn adm-cell-badge">{{ $plan->pending_prices_count }} pending</a>
              @endif
            </td>
            <td class="adm-col-provider" data-label="Provider bundle">
              <span class="adm-code">{{ $bundle->bundle_code }}</span>
              <small class="adm-cell-sub">Cost {{ \App\Support\PlanAdminInsights::cost($bundle) }}@unless ($bundle->is_available) · <span class="adm-text-error">unavailable</span>@endunless</small>
            </td>
            <td data-label="Status">
              @if (! $plan->is_active)
                <span class="adm-badge">Inactive</span>
              @elseif ($hidden === [])
                <span class="adm-badge adm-badge-ok">Active · on sale</span>
              @else
                <span class="adm-badge adm-badge-warn">Active · hidden</span>
                <small class="adm-cell-sub adm-text-warn">Customers can't buy it: {{ implode('; ', $hidden) }}.</small>
              @endif
            </td>
            <td class="adm-row-actions">
              <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.plans.show', $plan) }}">View</a>
              <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.plans.edit', $plan) }}">Edit</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="adm-empty">No plans found.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="adm-pagination">{{ $plans->links('admin.partials.pagination') }}</div>
  </div>
@endsection
