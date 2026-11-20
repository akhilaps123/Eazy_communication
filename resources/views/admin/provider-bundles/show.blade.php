@extends('admin.layouts.app')

@section('title', $bundle->bundle_code)

@section('content')
  @php
    $raw = (array) $bundle->raw_payload;
    $mapping = $bundle->isSynced() ? $bundle->locationMapping() : null;
  @endphp

  <div class="adm-head">
    <div>
      <h1 class="adm-code-title"><span class="adm-code">{{ $bundle->bundle_code }}</span></h1>
      <p class="adm-sub"><a href="{{ route('admin.provider-bundles.index') }}">&larr; Provider bundles</a> · Read-only provider information</p>
    </div>
    @if ($selectable)
      <div class="adm-actions">
        <a class="adm-btn" href="{{ route('admin.plans.create', ['bundle' => $bundle->id]) }}">Create plan from this bundle</a>
      </div>
    @endif
  </div>

  <div class="adm-grid adm-grid-2">
    <div class="adm-card">
      <h2>Provider information</h2>
      @include('admin.provider-bundles._summary', ['bundle' => $bundle, 'countryNames' => $countryNames])
      @if (! empty($raw['groups']) || ! empty($raw['billingType']))
        <dl class="adm-dl" style="margin-top:8px">
          @if (! empty($raw['groups']))<dt>eSIM Go groups</dt><dd>{{ implode(', ', array_filter((array) $raw['groups'], 'is_string')) }}</dd>@endif
          @if (! empty($raw['billingType']) && is_string($raw['billingType']))<dt>Billing type</dt><dd>{{ $raw['billingType'] }}</dd>@endif
        </dl>
      @endif
      @if ($mapping && ($mapping['resolved'] !== [] || $mapping['unmapped'] !== []))
        <dl class="adm-dl" style="margin-top:8px">
          @if ($mapping['resolved'] !== [])
            <dt>Provider location</dt>
            <dd>{{ implode(', ', $mapping['resolved']) }} <small class="adm-cell-sub">Countries taken from eSIM Go’s list of supported countries for this bundle.</small></dd>
          @endif
          @if ($mapping['unmapped'] !== [])
            <dt>Not mapped</dt>
            <dd><span class="adm-text-warn">{{ implode(', ', $mapping['unmapped']) }}</span> <small class="adm-cell-sub">Not an ISO country code — not added to the country list. Check the description before using this bundle.</small></dd>
          @endif
        </dl>
      @endif
      <p class="adm-hint" style="margin:14px 0 0">
        @if ($bundle->isSynced())
          This information comes from eSIM Go and is refreshed by the catalogue sync. It cannot be edited here.
        @else
          This bundle was entered by hand. Its cost, currency and availability can still be corrected on the edit page of the plan that uses it.
        @endif
      </p>
    </div>

    <div class="adm-card">
      <h2>Customer-facing plan</h2>
      @if ($bundle->plan)
        <dl class="adm-dl">
          <dt>Plan</dt><dd><a href="{{ route('admin.plans.show', $bundle->plan) }}" class="adm-strong-link">{{ $bundle->plan->name }}</a></dd>
          <dt>Status</dt><dd>{!! $bundle->plan->is_active ? '<span class="adm-badge adm-badge-ok">Active</span>' : '<span class="adm-badge">Inactive</span>' !!}</dd>
        </dl>
        <p class="adm-hint" style="margin:14px 0 0">A bundle can be used by one plan. To move this plan to another bundle, use “Change provider bundle” on the plan page.</p>
      @elseif ($selectable)
        <p class="adm-hint">No plan uses this bundle yet.</p>
        <a class="adm-btn adm-btn-light" href="{{ route('admin.plans.create', ['bundle' => $bundle->id]) }}">Create plan from this bundle</a>
      @else
        <p class="adm-hint">No plan uses this bundle. It is unavailable from eSIM Go, so it cannot be used for a new plan.</p>
      @endif
    </div>
  </div>
@endsection
