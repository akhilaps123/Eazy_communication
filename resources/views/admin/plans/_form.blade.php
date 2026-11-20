{{--
  Plan + provider bundle form (create and edit).
  Sections: A Plan information · B Coverage · C Provider bundle · D Advanced (collapsible).
  Every field is always submitted — a collapsed or hidden field keeps its value.
  Show/hide behaviour (js/admin.js) is optional: without JavaScript all fields stay visible.
--}}
@php
  $mb = $plan->data_amount_mb;
  $defaultAmount = $mb === null ? '' : ($mb % \App\Models\Plan::MB_PER_GB === 0 ? (string) intdiv($mb, \App\Models\Plan::MB_PER_GB) : (string) $mb);
  $defaultUnit = $mb === null || $mb % \App\Models\Plan::MB_PER_GB === 0 ? 'GB' : 'MB';
  $selectedCountries = array_map('intval', (array) old('countries', $selected));
  $currencies = \App\Http\Requests\Admin\PlanRequest::providerCurrencies($plan->exists ? $plan : null);
  $currentCurrency = old('provider_currency', $bundle?->provider_currency ?: ($currencies[0] ?? 'USD'));
  $manualBundle = $plan->exists && $bundle !== null && ! $bundle->isSynced();
  $advancedFields = ['slug', 'display_title', 'description', 'features', 'sort_order'];
  $advancedOpen = collect($advancedFields)->contains(fn ($f) => $errors->has($f));
@endphp

{{-- A. Plan information --}}
<section class="adm-form-section" aria-labelledby="sec-plan">
  <h2 id="sec-plan" class="adm-form-section-title"><span>A</span> Plan information</h2>
  <div class="adm-form">
    <div class="adm-field adm-field-wide">
      <label for="name">Plan name *</label>
      <input type="text" id="name" name="name" value="{{ old('name', $plan->name) }}" maxlength="255" required placeholder="Japan Unlimited 7 days" @class(['is-invalid' => $errors->has('name')])>
      @error('name') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <span class="adm-label" id="data-type-label">Data type *</span>
      <div class="adm-segmented" role="radiogroup" aria-labelledby="data-type-label">
        <label><input type="radio" name="data_type" value="fixed" @checked(old('data_type', $plan->data_type) === 'fixed')> Fixed data</label>
        <label><input type="radio" name="data_type" value="unlimited" @checked(old('data_type', $plan->data_type) === 'unlimited')> Unlimited</label>
      </div>
      @error('data_type') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field" data-show-when="data_type=fixed">
      <label for="data_amount">Data amount *</label>
      <div class="adm-inline">
        <input type="text" id="data_amount" name="data_amount" value="{{ old('data_amount', $defaultAmount) }}" inputmode="decimal" placeholder="5" @class(['is-invalid' => $errors->has('data_amount')])>
        <select name="data_unit" aria-label="Data unit">
          <option value="GB" @selected(old('data_unit', $defaultUnit) === 'GB')>GB</option>
          <option value="MB" @selected(old('data_unit', $defaultUnit) === 'MB')>MB</option>
        </select>
      </div>
      <small>Stored in MB. 1 GB = 1,000 MB.</small>
      @error('data_amount') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <label for="validity_days">Validity (days) *</label>
      <input type="number" id="validity_days" name="validity_days" value="{{ old('validity_days', $plan->validity_days) }}" min="1" max="3650" required @class(['is-invalid' => $errors->has('validity_days')])>
      @error('validity_days') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <label for="network_speed">Network speed</label>
      <input type="text" id="network_speed" name="network_speed" value="{{ old('network_speed', $plan->network_speed) }}" maxlength="50" list="network-speed-options" placeholder="5G / 4G">
      <datalist id="network-speed-options">
        <option value="5G"></option>
        <option value="4G LTE"></option>
        <option value="5G / 4G"></option>
      </datalist>
      <small>Plans containing “5G” show the 5G icon on the website.</small>
      @error('network_speed') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <label for="badge">Badge</label>
      <select id="badge" name="badge">
        <option value="">None</option>
        @foreach (\App\Models\Plan::BADGES as $value => $label)
          <option value="{{ $value }}" @selected(old('badge', $plan->badge) === $value)>{{ $label }}</option>
        @endforeach
      </select>
      @error('badge') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
  </div>
</section>

{{-- B. Coverage --}}
<section class="adm-form-section" aria-labelledby="sec-coverage">
  <h2 id="sec-coverage" class="adm-form-section-title"><span>B</span> Coverage</h2>
  <div class="adm-form">
    <div class="adm-field">
      <label for="coverage_type">Coverage type *</label>
      <select id="coverage_type" name="coverage_type" required>
        @foreach (['local' => 'Local (exactly one country)', 'regional' => 'Regional (two or more countries)', 'global' => 'Global (one or more countries)'] as $value => $label)
          <option value="{{ $value }}" @selected(old('coverage_type', $plan->coverage_type) === $value)>{{ $label }}</option>
        @endforeach
      </select>
      @error('coverage_type') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field" data-show-when="coverage_type=regional,global">
      <label for="region_name">Region name</label>
      <input type="text" id="region_name" name="region_name" value="{{ old('region_name', $plan->region_name) }}" maxlength="100" placeholder="e.g. European Union">
      @error('region_name') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field adm-field-wide" data-country-picker>
      <div class="adm-picker-head">
        <span class="adm-label" id="countries-label">Countries covered *</span>
        <span class="adm-badge adm-badge-info" data-country-count aria-live="polite">{{ count($selectedCountries) }} selected</span>
      </div>
      <input type="search" class="adm-picker-search" data-country-search placeholder="Search countries by name or ISO2 code" aria-label="Search countries" hidden>
      <div class="adm-countries" role="group" aria-labelledby="countries-label">
        @forelse ($countries as $c)
          <label @class(['adm-check', 'adm-country-option', 'is-selected' => in_array($c->id, $selectedCountries, true)]) data-country-name="{{ mb_strtolower($c->name.' '.$c->iso2) }}">
            <input type="checkbox" name="countries[]" value="{{ $c->id }}" @checked(in_array($c->id, $selectedCountries, true))>
            <span>{{ $c->name }} <small class="adm-hint">{{ $c->iso2 }}</small></span>
            @unless ($c->is_active) <span class="adm-badge">inactive</span>@endunless
          </label>
        @empty
          <span class="adm-hint">No active countries yet. <a href="{{ route('admin.countries.create') }}">Add a country</a> first.</span>
        @endforelse
        <span class="adm-hint" data-country-empty hidden>No country matches your search.</span>
      </div>
      <small>Local: exactly 1 country · Regional: 2 or more · Global: 1 or more. Inactive countries can stay linked but cannot be added.</small>
      @error('countries') <span class="adm-error">{{ $message }}</span> @enderror
      @error('countries.*') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
  </div>
</section>

{{-- C. Provider bundle --}}
<section class="adm-form-section" aria-labelledby="sec-bundle">
  <h2 id="sec-bundle" class="adm-form-section-title"><span>C</span> Provider bundle <small class="adm-hint">internal — never shown to customers</small></h2>

  @if (! $plan->exists)
    {{-- Create: select an EXISTING provider bundle. No bundle is created by this form. --}}
    <div class="adm-form" data-bundle-picker data-search-url="{{ route('admin.provider-bundles.search') }}">
      <div class="adm-field adm-field-wide">
        <span class="adm-label" id="bundle-label">Select provider bundle *</span>
        <input type="hidden" name="provider_bundle_id" value="{{ $bundle?->id }}" data-bundle-id>
        <div @class(['adm-bundle-selected', 'is-invalid' => $errors->has('provider_bundle_id')]) data-bundle-card aria-live="polite" aria-labelledby="bundle-label">
          @if ($bundle)
            @include('admin.provider-bundles._summary', ['bundle' => $bundle])
            @if ($bundle->plan)
              <span class="adm-error">This bundle is already used by the plan “{{ $bundle->plan->name }}”. Choose another one.</span>
            @elseif (! $bundle->is_available)
              <span class="adm-error">This bundle is not available from eSIM Go. Choose another one.</span>
            @endif
          @else
            <p class="adm-hint" style="margin:0">No bundle selected yet.</p>
          @endif
        </div>
        <input type="search" class="adm-picker-search" data-bundle-search placeholder="Search available bundles by code or name, e.g. JP or esim_1GB" aria-label="Search provider bundles" autocomplete="off" hidden>
        <div class="adm-bundle-results" data-bundle-results hidden></div>
        <small>
          Only bundles that are available from eSIM Go and not used by another plan can be chosen.
          Provider cost and currency always come from the selected bundle.
          You can also <a href="{{ route('admin.provider-bundles.index', ['availability' => 'available', 'linked' => 'unlinked']) }}">browse provider bundles</a> and click “Create plan”.
        </small>
        @error('provider_bundle_id') <span class="adm-error">{{ $message }}</span> @enderror
      </div>
    </div>
  @else
    {{-- Edit: the linked bundle is shown; changing it is a separate, confirmed action. --}}
    <div class="adm-form">
      <div class="adm-field adm-field-wide">
        <div class="adm-picker-head">
          <span class="adm-label">Linked provider bundle</span>
          <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.plans.provider-bundle.edit', $plan) }}">Change provider bundle</a>
        </div>
        <div class="adm-bundle-selected">
          @include('admin.provider-bundles._summary', ['bundle' => $bundle])
        </div>
        @unless ($manualBundle)
          <small>Synced from eSIM Go — cost, currency and other provider information are read-only and updated by the catalogue sync.</small>
        @endunless
      </div>
    </div>
  @endif

  @if ($manualBundle)
  <h3 class="adm-subsection-title">Manually entered bundle — editable values</h3>
  <div class="adm-form">
    <div class="adm-field">
      <label for="provider_cost">Provider cost *</label>
      <input type="text" id="provider_cost" name="provider_cost" value="{{ old('provider_cost', $bundle->provider_cost !== null ? rtrim(rtrim((string) $bundle->provider_cost, '0'), '.') : '') }}" inputmode="decimal" required placeholder="4.50" @class(['is-invalid' => $errors->has('provider_cost')])>
      @error('provider_cost') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <label for="provider_currency">Provider currency *</label>
      <select id="provider_currency" name="provider_currency" required @class(['is-invalid' => $errors->has('provider_currency')])>
        @foreach ($currencies as $code)
          <option value="{{ $code }}" @selected(strtoupper((string) $currentCurrency) === $code)>{{ $code }}@if (! in_array($code, array_map('strtoupper', (array) config('catalogue.provider_currencies')), true)) (current)@endif</option>
        @endforeach
      </select>
      <small>Markup prices only work when this is {{ config('catalogue.currency') }} (no currency conversion).</small>
      @error('provider_currency') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <span class="adm-label">Availability</span>
      <input type="hidden" name="bundle_available" value="0">
      <label class="adm-check"><input type="checkbox" name="bundle_available" value="1" @checked(old('bundle_available', $bundle->is_available))> Available from eSIM Go</label>
      <small>Untick when eSIM Go stops selling this bundle — the plan is then hidden from customers.</small>
    </div>
  </div>
  @endif
</section>

{{-- D. Advanced settings (collapsed; values are always submitted) --}}
<details class="adm-form-section adm-advanced" @if ($advancedOpen) open @endif>
  <summary class="adm-form-section-title"><span>D</span> Advanced settings <small class="adm-hint">URL slug, card title, description, features, sort order</small></summary>
  <div class="adm-form">
    <div class="adm-field">
      <label for="slug">URL slug</label>
      <input type="text" id="slug" name="slug" value="{{ old('slug', $plan->slug) }}" maxlength="150" @class(['is-invalid' => $errors->has('slug')])>
      <small>Leave empty to create it from the plan name.</small>
      @error('slug') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <label for="display_title">Card title</label>
      <input type="text" id="display_title" name="display_title" value="{{ old('display_title', $plan->display_title) }}" maxlength="255" placeholder="Automatic, e.g. 5 GB - 7 Days">
      @error('display_title') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field">
      <label for="sort_order">Sort order</label>
      <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0" max="1000000">
      <small>Lower numbers are shown first.</small>
      @error('sort_order') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field adm-field-wide">
      <label for="description">Description</label>
      <textarea id="description" name="description" maxlength="5000">{{ old('description', $plan->description) }}</textarea>
      @error('description') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
    <div class="adm-field adm-field-wide">
      <label for="features">Features (one per line, max {{ \App\Http\Requests\Admin\PlanRequest::MAX_FEATURES }})</label>
      <textarea id="features" name="features" maxlength="2000" placeholder="Hotspot supported&#10;Instant activation">{{ old('features', implode("\n", (array) $plan->features)) }}</textarea>
      @error('features') <span class="adm-error">{{ $message }}</span> @enderror
    </div>
  </div>
</details>
