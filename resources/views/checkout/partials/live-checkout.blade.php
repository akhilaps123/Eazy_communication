{{--
  Live checkout (Phase 7) — same structure and CSS classes as the static design.
  "Place order" creates an order AWAITING PAYMENT. No payment is taken, there are
  no promo codes (decision 5) and no tax calculation (decision 6).
  expected_total is only compared with the server total; it is never used as a price.
--}}
@php
  $initials = collect(preg_split('/\s+/', trim((string) $customer->name)))->filter()->take(2)
      ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') ?: '?';
  $selectedCountry = old('country');
@endphp
    <section class="checkout-section" id="checkout">
      <div class="checkout-container">
        <div class="checkout-grid">

          <!-- Left Column: Checkout Form -->
          <div class="checkout-form-col">
            <h1 class="checkout-main-title">Checkout</h1>

            <form id="checkoutForm" method="POST" action="{{ route('checkout.submit') }}" data-checkout-live novalidate>
              @csrf
              <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
              <input type="hidden" name="expected_total" value="{{ $cart['subtotal'] }}">

              @if ($errors->any())
                <div class="checkout-live-error" role="alert" style="margin: 0 0 16px;">{{ $errors->first() }}</div>
              @endif

              <!-- 1. Contact Block -->
              <section class="checkout-block checkout-block-contact" aria-labelledby="contactHeading">
                <h2 class="checkout-block-title" id="contactHeading">Contact</h2>
                <div class="checkout-contact-user">
                  <span class="checkout-contact-avatar" aria-hidden="true" style="display: inline-grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; background: #00aeef; color: #fff; font-weight: 600; font-size: 14px;">{{ $initials }}</span>
                  <span class="checkout-contact-email">{{ $customer->name }} · {{ $customer->email }}</span>
                </div>
              </section>

              <!-- 2. Billing Information Block -->
              <section class="checkout-block checkout-block-billing" aria-labelledby="billingHeading">
                <h2 class="checkout-block-title" id="billingHeading">Billing Information</h2>

                <div class="checkout-field-wrap">
                  <div class="checkout-custom-select" id="countryCustomSelect" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="Select Country">
                    <div class="checkout-select-trigger" id="countrySelectTrigger">
                      <span @class(['checkout-select-text', 'has-value' => $selectedCountry]) id="selectedCountryText">{{ $selectedCountry ? ($billingCountries[$selectedCountry] ?? 'Country') : 'Country' }}</span>
                      <img src="{{ asset('assets/icons/chevron-down-form.svg') }}" alt="" class="checkout-select-arrow" aria-hidden="true" width="8" height="5">
                    </div>
                    <ul class="checkout-select-dropdown" id="countryDropdownList" role="listbox" style="max-height: 280px; overflow-y: auto;">
                      @foreach ($billingCountries as $code => $name)
                        <li @class(['checkout-select-option', 'selected' => $selectedCountry === $code]) role="option" data-value="{{ $code }}">{{ $name }}</li>
                      @endforeach
                    </ul>
                  </div>
                  <input type="hidden" name="country" id="countryHiddenInput" value="{{ $selectedCountry }}">
                  @error('country') <p class="checkout-live-error">{{ $message }}</p> @enderror
                </div>

                <div class="checkout-field-wrap">
                  <div class="checkout-input-box">
                    <input type="text" class="checkout-text-input" id="postalCodeInput" name="postalCode" placeholder="Postal Code" aria-label="Postal Code" maxlength="20" value="{{ old('postalCode') }}" autocomplete="postal-code">
                  </div>
                  @error('postalCode') <p class="checkout-live-error">{{ $message }}</p> @enderror
                </div>

                <div class="checkout-field-wrap">
                  <div class="checkout-input-box">
                    <input type="text" class="checkout-text-input" id="gstinInput" name="gstin" placeholder="GST Number (optional)" aria-label="GST Number (optional)" maxlength="15" value="{{ old('gstin', $cart['gstin']) }}" autocomplete="off" spellcheck="false">
                  </div>
                  @error('gstin') <p class="checkout-live-error">{{ $message }}</p> @enderror
                </div>
              </section>

              <!-- 3. Payment Block (payment is not available yet) -->
              <section class="checkout-block checkout-block-payment" aria-labelledby="paymentHeading">
                <h2 class="checkout-block-title" id="paymentHeading">Payment</h2>
                <div class="checkout-payment-stack" role="radiogroup" aria-label="Payment Methods (coming soon)" aria-disabled="true" style="opacity: .55; pointer-events: none;">
                  <div class="checkout-payment-option" data-method="card" role="radio" aria-checked="false" aria-disabled="true">
                    <div class="checkout-payment-left">
                      <img src="{{ asset('assets/icons/payment-card.svg') }}" alt="" class="payment-icon-card" aria-hidden="true">
                      <span class="checkout-payment-title">Card</span>
                    </div>
                  </div>
                  <div class="checkout-payment-option" data-method="bank" role="radio" aria-checked="false" aria-disabled="true">
                    <div class="checkout-payment-left">
                      <img src="{{ asset('assets/icons/payment-bank.svg') }}" alt="" class="payment-icon-bank" aria-hidden="true">
                      <span class="checkout-payment-title">Bank</span>
                    </div>
                  </div>
                  <div class="checkout-payment-option" data-method="paypal" role="radio" aria-checked="false" aria-disabled="true">
                    <div class="checkout-payment-left">
                      <img src="{{ asset('assets/icons/payment-paypal.png') }}" alt="" class="payment-icon-paypal" aria-hidden="true">
                      <span class="checkout-payment-title">Checkout with PayPal</span>
                    </div>
                  </div>
                </div>
                <p class="checkout-live-note" role="note">
                  Coming soon: online payment is not available yet. Placing your order saves it as <strong>Awaiting Payment</strong> — you will not be charged now.
                </p>

                <div class="checkout-cta-wrap">
                  <button type="submit" class="btn-checkout-submit" id="btnCheckoutSubmit">
                    Place order
                  </button>
                </div>
              </section>
            </form>
          </div>

          <!-- Right Column: Order Summary (server-calculated) -->
          <div class="checkout-summary-col">
            <h2 class="checkout-summary-title">Order summary</h2>
            <aside class="checkout-summary-card" aria-label="Order Summary">
              <div class="checkout-breakdown">
                @foreach ($cart['lines'] as $line)
                  <div class="checkout-breakdown-row">
                    <span class="checkout-breakdown-label">
                      {{ $line['title'] }} · {{ $line['subtitle'] }} × {{ $line['quantity'] }}
                      @if ($line['item']->trip_start_date)<br><small>Trip start {{ $line['item']->trip_start_date->format('Y-m-d') }}</small>@endif
                    </span>
                    <span class="checkout-breakdown-val">{{ $line['line_total_formatted'] }}</span>
                  </div>
                @endforeach
                <div class="checkout-breakdown-divider" aria-hidden="true"></div>
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Subtotal</span>
                  <span class="checkout-breakdown-val" id="summarySubtotal">{{ $cart['subtotal_formatted'] }}</span>
                </div>
                <div class="checkout-breakdown-divider" aria-hidden="true"></div>
                <div class="checkout-breakdown-row checkout-total-row">
                  <span class="checkout-total-label">Total:</span>
                  <span class="checkout-total-val" id="summaryTotal">{{ $cart['subtotal_formatted'] }}</span>
                </div>
              </div>
              <p class="checkout-live-note">
                Prices in {{ $cart['currency'] }}. <a href="{{ route('cart') }}">Edit cart</a>
              </p>
            </aside>
          </div>
        </div>
      </div>
    </section>
