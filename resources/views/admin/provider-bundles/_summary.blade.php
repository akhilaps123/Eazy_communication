{{--
  Read-only provider bundle facts (admin only, never shown to customers).
  Expects $bundle (ProviderBundle); optional $countryNames (iso2 => name).
--}}
@php
  $countryNames ??= collect();
  $codes = $bundle->countryList();
@endphp
<dl class="adm-dl adm-bundle-summary">
  <dt>Bundle code</dt><dd><span class="adm-code">{{ $bundle->bundle_code }}</span></dd>
  @if ($bundle->name !== $bundle->bundle_code)
    <dt>Bundle name</dt><dd>{{ $bundle->name }}</dd>
  @endif
  @if ($bundle->description)
    <dt>Description</dt><dd>{{ $bundle->description }}</dd>
  @endif
  <dt>Countries</dt>
  <dd>
    @if ($codes === [])
      <span class="adm-hint">Not provided</span>
    @else
      {{ collect($codes)->map(fn ($c) => $countryNames[$c] ?? $c)->take(12)->join(', ') }}@if (count($codes) > 12) <span class="adm-hint">+{{ count($codes) - 12 }} more</span>@endif
      <small class="adm-cell-sub">{{ count($codes) }} {{ count($codes) === 1 ? 'country' : 'countries' }}</small>
    @endif
  </dd>
  <dt>Data allowance</dt><dd>{{ $bundle->dataLabel() }}</dd>
  <dt>Validity</dt><dd>{{ $bundle->validityLabel() }}</dd>
  <dt>Speed</dt><dd>{{ $bundle->speed ?: '—' }}</dd>
  <dt>Provider cost</dt><dd>{{ $bundle->costLabel() }}</dd>
  <dt>Availability</dt>
  <dd>{!! $bundle->is_available ? '<span class="adm-badge adm-badge-ok">Available</span>' : '<span class="adm-badge adm-badge-error">Unavailable</span>' !!}</dd>
  <dt>Source</dt>
  <dd>
    @if ($bundle->isSynced())
      <span class="adm-badge adm-badge-info">Synced from eSIM Go</span>
      <small class="adm-cell-sub">Last synced {{ $bundle->last_synced_at->format('j M Y, H:i') }}</small>
    @else
      <span class="adm-badge">Entered manually</span>
      <small class="adm-cell-sub">Not found in an eSIM Go catalogue sync yet.</small>
    @endif
  </dd>
</dl>
