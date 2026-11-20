@extends('admin.layouts.app')

@section('title', $country->name)

@section('content')
  <div class="adm-head">
    <div>
      <h1>{{ $country->name }}
        @if ($country->is_active)
          <span class="adm-badge adm-badge-ok">Active</span>
        @else
          <span class="adm-badge">Inactive</span>
        @endif
      </h1>
      <p class="adm-sub"><a href="{{ route('admin.countries.index') }}">&larr; Countries</a></p>
    </div>
    <div class="adm-actions">
      <a class="adm-btn" href="{{ route('admin.countries.edit', $country) }}">Edit</a>
      <form method="POST" action="{{ route('admin.countries.toggle', $country) }}">
        @csrf
        <button class="adm-btn adm-btn-light" type="submit">{{ $country->is_active ? 'Deactivate' : 'Activate' }}</button>
      </form>
      @if ($country->plans->isEmpty())
        <form method="POST" action="{{ route('admin.countries.destroy', $country) }}" data-confirm="Delete this country? This cannot be undone.">
          @csrf
          @method('DELETE')
          <button class="adm-btn adm-btn-danger" type="submit">Delete</button>
        </form>
      @endif
    </div>
  </div>

  <div class="adm-card">
    <dl class="adm-dl">
      <dt>ISO2</dt><dd>{{ $country->iso2 }}</dd>
      <dt>Website URL</dt><dd>/esim/{{ $country->slug }}</dd>
      {{-- Older records may still hold these values (kept for the API); they are no longer edited. --}}
      @if ($country->iso3)<dt>ISO3</dt><dd>{{ $country->iso3 }}</dd>@endif
      @if ($country->region)<dt>Region</dt><dd>{{ $country->region }}</dd>@endif
      <dt>Popular</dt><dd>{{ $country->is_popular ? 'Yes' : 'No' }}</dd>
      <dt>Sort order</dt><dd>{{ $country->sort_order }}</dd>
      <dt>Images <small class="adm-hint">(automatic)</small></dt>
      <dd class="adm-actions">
        @unless ($country->imagePath('flag'))
          <span class="adm-hint">No website flag image exists for {{ $country->iso2 }} — the flag is left out on customer pages.</span>
        @endunless
        @foreach (['flag', 'sphere', 'card'] as $kind)
          @if ($path = $country->imagePath($kind))
            <span><img src="{{ asset($path) }}" alt="{{ $kind }} image" style="height:40px;vertical-align:middle"> <small class="adm-hint">{{ $kind }}</small></span>
          @endif
        @endforeach
      </dd>
    </dl>
  </div>

  <div class="adm-card adm-table-wrap">
    <h2>Plans covering {{ $country->name }} ({{ $country->plans->count() }})</h2>
    @if ($country->plans->isEmpty())
      <p class="adm-hint">No plan uses this country, so it can be deleted.</p>
    @else
      <p class="adm-hint">This country is used by plans, so it cannot be deleted. Deactivate it instead.</p>
      <table class="adm-table">
        <thead><tr><th>Plan</th><th>Coverage</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($country->plans as $plan)
            <tr>
              <td><a href="{{ route('admin.plans.show', $plan) }}">{{ $plan->name }}</a></td>
              <td>{{ ucfirst($plan->coverage_type) }}</td>
              <td>
                @if ($plan->is_active)
                  <span class="adm-badge adm-badge-ok">Active</span>
                @else
                  <span class="adm-badge">Inactive</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  @include('admin.partials.confirm-script')
@endsection
