@extends('admin.layouts.app')

@section('title', 'Change provider bundle')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Change provider bundle</h1>
      <p class="adm-sub"><a href="{{ route('admin.plans.show', $plan) }}">&larr; {{ $plan->name }}</a></p>
    </div>
  </div>

  <div class="adm-alert adm-alert-warn" role="note">
    Only the link between this plan and its eSIM Go bundle changes. The plan keeps its id, name, countries,
    active/inactive status and all its prices; the current bundle is not deleted; past orders keep the bundle
    they were sold with. Check the new bundle's cost against the plan's price afterwards.
  </div>

  <div class="adm-grid adm-grid-2">
    <div class="adm-card">
      <h2>Current bundle</h2>
      @include('admin.provider-bundles._summary', ['bundle' => $current])
    </div>
    <div class="adm-card">
      <h2>Find a new bundle</h2>
      <form class="adm-filters" method="GET" action="{{ route('admin.plans.provider-bundle.edit', $plan) }}">
        <div class="adm-field">
          <label for="q">Bundle code or name</label>
          <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="e.g. esim_1GB_7D_JP">
        </div>
        <div class="adm-field">
          <label for="country">Country (ISO code)</label>
          <input type="text" id="country" name="country" value="{{ $filters['country'] }}" maxlength="2" placeholder="JP">
        </div>
        <div class="adm-actions">
          <button class="adm-btn" type="submit">Search</button>
          <a class="adm-btn adm-btn-light" href="{{ route('admin.plans.provider-bundle.edit', $plan) }}">Reset</a>
        </div>
      </form>
      <p class="adm-hint" style="margin:12px 0 0">Only bundles that are available from eSIM Go and not used by another plan are listed.</p>
    </div>
  </div>

  <div class="adm-card adm-table-wrap">
    @error('provider_bundle_id') <p class="adm-error">{{ $message }}</p> @enderror
    <table class="adm-table adm-plans-table adm-bundles-table">
      <thead>
        <tr>
          <th>Bundle</th>
          <th>Countries</th>
          <th>Data</th>
          <th>Validity</th>
          <th>Provider cost</th>
          <th>Source</th>
          <th><span class="adm-visually-hidden">Action</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($candidates as $bundle)
          @php $codes = $bundle->countryList(); @endphp
          <tr>
            <td>
              <a href="{{ route('admin.provider-bundles.show', $bundle) }}" class="adm-strong-link adm-code">{{ $bundle->bundle_code }}</a>
              @if ($bundle->description)<small class="adm-cell-sub">{{ \Illuminate\Support\Str::limit($bundle->description, 80) }}</small>@endif
            </td>
            <td data-label="Countries">{{ $codes === [] ? '—' : collect($codes)->take(5)->join(', ') }}@if (count($codes) > 5) <span class="adm-hint">+{{ count($codes) - 5 }}</span>@endif</td>
            <td class="adm-nowrap" data-label="Data">{{ $bundle->dataLabel() }}</td>
            <td class="adm-nowrap" data-label="Validity">{{ $bundle->validityLabel() }}</td>
            <td class="adm-nowrap" data-label="Provider cost">{{ $bundle->costLabel() }}</td>
            <td data-label="Source">{!! $bundle->isSynced() ? '<span class="adm-badge adm-badge-info">Synced</span>' : '<span class="adm-badge">Manual</span>' !!}</td>
            <td class="adm-row-actions">
              <form method="POST" action="{{ route('admin.plans.provider-bundle.update', $plan) }}"
                    data-confirm="Change the provider bundle of “{{ $plan->name }}” from {{ $current->bundle_code }} to {{ $bundle->bundle_code }}? Prices, status and past orders are not changed.">
                @csrf
                @method('PUT')
                <input type="hidden" name="provider_bundle_id" value="{{ $bundle->id }}">
                <button class="adm-btn adm-btn-sm" type="submit">Use this bundle</button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="adm-empty">
              @if ($filters['q'] !== '' || $filters['country'])
                No available, unused bundle matches this search.
              @else
                No other available, unused bundle exists yet. Bundles are added by the eSIM Go catalogue sync (<span class="adm-code">php artisan esimgo:sync-catalogue</span>).
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    <div class="adm-pagination">{{ $candidates->links('admin.partials.pagination') }}</div>
  </div>

  @include('admin.partials.confirm-script')
@endsection
