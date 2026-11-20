@extends('admin.layouts.app')
@use('App\Http\Controllers\Admin\ProviderBundleController')

@section('title', 'Provider Bundles')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Provider bundles</h1>
      <p class="adm-sub">eSIM Go bundles (wholesale). Read-only — synced bundles are updated by the catalogue sync. Customers never see this page.</p>
    </div>
  </div>

  <form class="adm-card adm-filters" method="GET" action="{{ route('admin.provider-bundles.index') }}">
    <div class="adm-field">
      <label for="q">Search</label>
      <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Bundle code or name">
    </div>
    <div class="adm-field">
      <label for="country">Country</label>
      <select id="country" name="country">
        <option value="">All countries</option>
        @foreach ($countries as $c)
          <option value="{{ $c->iso2 }}" @selected($filters['country'] === $c->iso2)>{{ $c->name }} ({{ $c->iso2 }})</option>
        @endforeach
        @if ($filters['country'] && ! $countries->contains('iso2', $filters['country']))
          <option value="{{ $filters['country'] }}" selected>{{ $filters['country'] }}</option>
        @endif
      </select>
    </div>
    <div class="adm-field">
      <label for="data">Data</label>
      <select id="data" name="data">
        <option value="">All</option>
        @foreach (ProviderBundleController::DATA_FILTERS as $value => $label)
          <option value="{{ $value }}" @selected($filters['data'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-field">
      <label for="validity">Validity</label>
      <select id="validity" name="validity">
        <option value="">All</option>
        @foreach (ProviderBundleController::VALIDITY_FILTERS as $value => $label)
          <option value="{{ $value }}" @selected($filters['validity'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-field">
      <label for="availability">Availability</label>
      <select id="availability" name="availability">
        <option value="">All</option>
        <option value="available" @selected($filters['availability'] === 'available')>Available</option>
        <option value="unavailable" @selected($filters['availability'] === 'unavailable')>Unavailable</option>
      </select>
    </div>
    <div class="adm-field">
      <label for="source">Source</label>
      <select id="source" name="source">
        <option value="">All</option>
        <option value="synced" @selected($filters['source'] === 'synced')>Synced from eSIM Go</option>
        <option value="manual" @selected($filters['source'] === 'manual')>Entered manually</option>
      </select>
    </div>
    <div class="adm-field">
      <label for="linked">Plan</label>
      <select id="linked" name="linked">
        <option value="">All</option>
        <option value="unlinked" @selected($filters['linked'] === 'unlinked')>Not used by a plan</option>
        <option value="linked" @selected($filters['linked'] === 'linked')>Used by a plan</option>
      </select>
    </div>
    <div class="adm-actions">
      <button class="adm-btn" type="submit">Filter</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.provider-bundles.index') }}">Reset</a>
    </div>
  </form>

  <div class="adm-card adm-table-wrap">
    <p class="adm-hint adm-bundles-count">
      {{ number_format($bundles->total()) }} of {{ number_format($totals['all']) }} bundles shown · {{ number_format($totals['synced']) }} synced from eSIM Go
    </p>
    <table class="adm-table adm-plans-table adm-bundles-table">
      <thead>
        <tr>
          <th>Bundle</th>
          <th>Countries</th>
          <th>Data</th>
          <th>Validity</th>
          <th>Speed</th>
          <th>Provider cost</th>
          <th>Availability</th>
          <th>Source</th>
          <th>Plan</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($bundles as $bundle)
          @php $codes = $bundle->countryList(); @endphp
          <tr>
            <td>
              <a href="{{ route('admin.provider-bundles.show', $bundle) }}" class="adm-strong-link adm-code">{{ $bundle->bundle_code }}</a>
              @if ($bundle->description || $bundle->name !== $bundle->bundle_code)
                <small class="adm-cell-sub">{{ \Illuminate\Support\Str::limit($bundle->description ?: $bundle->name, 80) }}</small>
              @endif
            </td>
            <td data-label="Countries">
              @if ($codes === [])
                <span class="adm-hint">—</span>
              @else
                {{ collect($codes)->take(5)->join(', ') }}@if (count($codes) > 5) <span class="adm-hint">+{{ count($codes) - 5 }}</span>@endif
              @endif
            </td>
            <td class="adm-nowrap" data-label="Data">{{ $bundle->dataLabel() }}</td>
            <td class="adm-nowrap" data-label="Validity">{{ $bundle->validityLabel() }}</td>
            <td data-label="Speed">{{ $bundle->speed ?: '—' }}</td>
            <td class="adm-nowrap" data-label="Provider cost">{{ $bundle->costLabel() }}</td>
            <td data-label="Availability">
              {!! $bundle->is_available ? '<span class="adm-badge adm-badge-ok">Available</span>' : '<span class="adm-badge adm-badge-error">Unavailable</span>' !!}
            </td>
            <td data-label="Source">
              @if ($bundle->isSynced())
                <span class="adm-badge adm-badge-info">Synced</span>
              @else
                <span class="adm-badge">Manual</span>
              @endif
            </td>
            <td data-label="Plan">
              @if ($bundle->plan)
                <a href="{{ route('admin.plans.show', $bundle->plan) }}">{{ $bundle->plan->name }}</a>
              @elseif ($bundle->is_available)
                <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.plans.create', ['bundle' => $bundle->id]) }}">Create plan</a>
              @else
                <span class="adm-hint">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="adm-empty">
              @if ($totals['all'] === 0)
                No provider bundles yet. They are added by the eSIM Go catalogue sync (<span class="adm-code">php artisan esimgo:sync-catalogue</span>).
              @else
                No provider bundles match these filters.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    <div class="adm-pagination">{{ $bundles->links('admin.partials.pagination') }}</div>
  </div>
@endsection
