{{--
  Shared price rule fields. Currency is fixed to the display currency.
  Only the amount box for the chosen method is shown (js/admin.js); the others
  are still submitted but ignored by the server (PlanPriceRequest::priceAttributes()).
--}}
@php
  $fmt = fn ($date) => $date ? $date->format('Y-m-d\TH:i') : '';
  $preview = session('price_preview');
@endphp
@if ($preview)
  <div class="adm-preview" role="status">
    <div class="adm-preview-main">
      <span>Customers would pay</span>
      <strong>{{ $preview['formatted'] }}</strong>
    </div>
    @if (! empty($preview['margin']))
      <dl class="adm-preview-margin">
        <div><dt>Provider cost</dt><dd>{{ $preview['margin']['cost'] }}</dd></div>
        <div><dt>Customer price</dt><dd>{{ $preview['margin']['price'] }}</dd></div>
        <div><dt>Margin</dt><dd>{{ $preview['margin']['margin'] }} ({{ $preview['margin']['percent'] }}%)</dd></div>
      </dl>
    @else
      <p class="adm-hint">Margin not shown: the provider cost is in a different currency (no conversion).</p>
    @endif
    <p class="adm-hint">Nothing has been saved yet — click “Save as pending” to keep this price.</p>
  </div>
@endif
<div class="adm-form">
  <div class="adm-field">
    <label for="pricing_method">Pricing method *</label>
    <select id="pricing_method" name="pricing_method" required>
      <option value="manual" @selected(old('pricing_method', $price->pricing_method ?? 'manual') === 'manual')>Manual selling price</option>
      <option value="markup_percent" @selected(old('pricing_method', $price->pricing_method) === 'markup_percent') @disabled(! $markupAvailable)>Percentage markup on provider cost</option>
      <option value="markup_fixed" @selected(old('pricing_method', $price->pricing_method) === 'markup_fixed') @disabled(! $markupAvailable)>Fixed markup on provider cost</option>
    </select>
    @unless ($markupAvailable)
      <small>Markup is unavailable: the provider currency is not {{ $currency }} and there is no currency conversion. Use a manual price.</small>
    @endunless
    @error('pricing_method') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field" data-show-when="pricing_method=manual">
    <label for="manual_price">Selling price ({{ $currency }}) *</label>
    <input type="text" id="manual_price" name="manual_price" value="{{ old('manual_price', $price->manual_price) }}" inputmode="decimal" placeholder="19.99" @class(['is-invalid' => $errors->has('manual_price')])>
    @error('manual_price') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field" data-show-when="pricing_method=markup_percent,markup_fixed">
    <label for="markup_value">
      <span data-show-when="pricing_method=markup_percent">Markup percentage (%) *</span>
      <span data-show-when="pricing_method=markup_fixed">Fixed markup amount ({{ $currency }}) *</span>
    </label>
    <input type="text" id="markup_value" name="markup_value" value="{{ old('markup_value', $price->markup_value !== null ? rtrim(rtrim((string) $price->markup_value, '0'), '.') : '') }}" inputmode="decimal" placeholder="25" @class(['is-invalid' => $errors->has('markup_value')])>
    <small data-show-when="pricing_method=markup_percent">Added to the provider cost, e.g. 25 = cost + 25%.</small>
    <small data-show-when="pricing_method=markup_fixed">Added to the provider cost, e.g. 2.50 = cost + {{ $currency }} 2.50.</small>
    @error('markup_value') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <label for="compare_at_price">Crossed-out price ({{ $currency }})</label>
    <input type="text" id="compare_at_price" name="compare_at_price" value="{{ old('compare_at_price', $price->compare_at_price) }}" inputmode="decimal" placeholder="Optional">
    <small>Display only, never charged. Must be higher than the selling price.</small>
    @error('compare_at_price') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <label for="effective_from">Starts *</label>
    <input type="datetime-local" id="effective_from" name="effective_from" value="{{ old('effective_from', $fmt($price->effective_from ?? now()->startOfMinute())) }}" required>
    @error('effective_from') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <label for="effective_to">Ends</label>
    <input type="datetime-local" id="effective_to" name="effective_to" value="{{ old('effective_to', $fmt($price->effective_to)) }}">
    <small>Optional. Leave empty for no end date.</small>
    @error('effective_to') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
</div>
<div class="adm-actions adm-form-actions">
  <button class="adm-btn adm-btn-light" type="submit" name="intent" value="preview">Preview price</button>
  <button class="adm-btn" type="submit" name="intent" value="save">Save as pending</button>
</div>
