@extends('layouts.app')

@section('title', 'Checkout - Eazy Communications')
@section('meta_description', 'Secure Checkout - Complete your eSIM order on Eazy Communications. Instant digital delivery and encrypted payments.')

@push('head')
  <!-- Google Fonts: Questrial, Poppins, Inter, DM Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=Inter:wght@400;500;600&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,200;1,300&family=Questrial&display=swap" rel="stylesheet">

  <!-- Core & Shared Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">

  <!-- Page-Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/checkout.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header :cart-active="true" :mobile-links="[
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main>
    <!-- ==========================================================================
         Hero Banner (Global Shared Banner - Winter Lake Village)
         ========================================================================== -->
    <section class="page-banner" aria-label="Checkout Banner">
      <img src="{{ asset('assets/images/checkout-banner.webp') }}" alt="Winter village lakeside landscape" class="page-banner-img checkout-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">Shopping Cart</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <a href="{{ route('new-esim') }}">NEW ESIM</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <a href="{{ route('japan-esim') }}">JAPAN ESIM</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <a href="{{ route('cart') }}">SHOPPING CART</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current" aria-current="page">CHECKOUT</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Checkout Main Section (Figma Node 189:888 / 189:1195)
         ========================================================================== -->
@if ($checkoutLive ?? false)
@include('checkout.partials.live-checkout')
@else
    <section class="checkout-section" id="checkout">
      <div class="checkout-container">

        <!-- Two Column Layout: Left Form (813px) + Right Summary (359px) -->
        <div class="checkout-grid">

          <!-- Left Column: Checkout Form & Payment Options -->
          <div class="checkout-form-col">
            <h1 class="checkout-main-title">Checkout</h1>

            <form id="checkoutForm" method="POST" action="{{ route('checkout.submit') }}" onsubmit="event.preventDefault();">
              @csrf

              <!-- 1. Contact Block -->
              <section class="checkout-block checkout-block-contact" aria-labelledby="contactHeading">
                <h2 class="checkout-block-title" id="contactHeading">Contact</h2>
                <div class="checkout-contact-user">
                  <img src="{{ asset('assets/images/checkout-user-avatar.png') }}" alt="John Doe" class="checkout-contact-avatar" width="40" height="40">
                  <span class="checkout-contact-email">johndoe1997@gmail.com</span>
                </div>
              </section>

              <!-- 2. Billing Information Block -->
              <section class="checkout-block checkout-block-billing" aria-labelledby="billingHeading">
                <h2 class="checkout-block-title" id="billingHeading">Billing Information</h2>

                <!-- Custom Country Dropdown -->
                <div class="checkout-field-wrap">
                  <div class="checkout-custom-select" id="countryCustomSelect" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="Select Country">
                    <div class="checkout-select-trigger" id="countrySelectTrigger">
                      <span class="checkout-select-text" id="selectedCountryText">Country</span>
                      <img src="{{ asset('assets/icons/chevron-down-form.svg') }}" alt="" class="checkout-select-arrow" aria-hidden="true" width="8" height="5">
                    </div>
                    <ul class="checkout-select-dropdown" id="countryDropdownList" role="listbox">
                      <li class="checkout-select-option" role="option" data-value="United States">United States</li>
                      <li class="checkout-select-option" role="option" data-value="Japan">Japan</li>
                      <li class="checkout-select-option" role="option" data-value="India">India</li>
                      <li class="checkout-select-option" role="option" data-value="United Kingdom">United Kingdom</li>
                      <li class="checkout-select-option" role="option" data-value="Canada">Canada</li>
                      <li class="checkout-select-option" role="option" data-value="Australia">Australia</li>
                      <li class="checkout-select-option" role="option" data-value="Germany">Germany</li>
                      <li class="checkout-select-option" role="option" data-value="France">France</li>
                      <li class="checkout-select-option" role="option" data-value="Singapore">Singapore</li>
                      <li class="checkout-select-option" role="option" data-value="United Arab Emirates">United Arab Emirates</li>
                    </ul>
                  </div>
                  <input type="hidden" name="country" id="countryHiddenInput" value="">
                </div>

                <!-- Postal Code Input -->
                <div class="checkout-field-wrap">
                  <div class="checkout-input-box">
                    <input type="text" class="checkout-text-input" id="postalCodeInput" name="postalCode" placeholder="Postal Code" aria-label="Postal Code">
                  </div>
                </div>

                <!-- Save for Future Purchases Checkbox -->
                <div class="checkout-checkbox-wrap">
                  <label class="checkout-checkbox-label" for="saveFuturePurchases">
                    <input type="checkbox" id="saveFuturePurchases" name="saveFuturePurchases" class="checkout-real-checkbox">
                    <span class="checkout-custom-checkbox" aria-hidden="true"></span>
                    <span class="checkout-checkbox-text">Save for future purchases</span>
                  </label>
                </div>
              </section>

              <!-- 3. Payment Block -->
              <section class="checkout-block checkout-block-payment" aria-labelledby="paymentHeading">
                <h2 class="checkout-block-title" id="paymentHeading">Payment</h2>

                <!-- Stacked Payment Options -->
                <div class="checkout-payment-stack" role="radiogroup" aria-label="Payment Methods">

                  <!-- Option 1: Card -->
                  <div class="checkout-payment-option active" data-method="card" tabindex="0" role="radio" aria-checked="true">
                    <div class="checkout-payment-left">
                      <img src="{{ asset('assets/icons/payment-card.svg') }}" alt="" class="payment-icon-card" aria-hidden="true">
                      <span class="checkout-payment-title">Card</span>
                    </div>
                  </div>

                  <!-- Option 2: Bank -->
                  <div class="checkout-payment-option" data-method="bank" tabindex="0" role="radio" aria-checked="false">
                    <div class="checkout-payment-left">
                      <img src="{{ asset('assets/icons/payment-bank.svg') }}" alt="" class="payment-icon-bank" aria-hidden="true">
                      <span class="checkout-payment-title">Bank</span>
                    </div>
                  </div>

                  <!-- Option 3: PayPal -->
                  <div class="checkout-payment-option" data-method="paypal" tabindex="0" role="radio" aria-checked="false">
                    <div class="checkout-payment-left">
                      <img src="{{ asset('assets/icons/payment-paypal.png') }}" alt="" class="payment-icon-paypal" aria-hidden="true">
                      <span class="checkout-payment-title">Checkout with PayPal</span>
                    </div>
                  </div>

                </div>

                <!-- Checkout CTA Button -->
                <div class="checkout-cta-wrap">
                  <button type="submit" class="btn-checkout-submit" id="btnCheckoutSubmit">
                    Checkout
                  </button>
                </div>
              </section>

            </form>
          </div>

          <!-- Right Column: Order Summary (359px) -->
          <div class="checkout-summary-col">
            <h2 class="checkout-summary-title">Order summary</h2>

            <aside class="checkout-summary-card" aria-label="Order Summary">

              <!-- Promo Code Input & Apply Stack -->
              <div class="checkout-promo-stack">
                <input type="text" class="checkout-promo-input" id="promoCodeInput" placeholder="Discount code or gift card" aria-label="Discount code or gift card">
                <button type="button" class="btn-promo-apply" id="btnApplyPromo">Apply</button>
              </div>
              <div class="checkout-promo-feedback" id="promoFeedback" aria-live="polite"></div>

              <!-- Price Breakdown List -->
              <div class="checkout-breakdown">
                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Subtotal</span>
                  <span class="checkout-breakdown-val" id="summarySubtotal">$34.99</span>
                </div>

                <div class="checkout-breakdown-row">
                  <span class="checkout-breakdown-label">Discount Offer</span>
                  <span class="checkout-breakdown-val" id="summaryDiscount">₹ 0.0</span>
                </div>

                <div class="checkout-breakdown-divider" aria-hidden="true"></div>

                <div class="checkout-breakdown-row checkout-total-row">
                  <span class="checkout-total-label">Total:</span>
                  <span class="checkout-total-val" id="summaryTotal">$34.99</span>
                </div>
              </div>
            </aside>
          </div>

        </div>
      </div>
    </section>
@endif

    <!-- ==========================================================================
         Have Questions? Section (Global Reusable Component)
         ========================================================================== -->
    <section class="questions-section" aria-label="Have Questions">
      <div class="questions-container">
        <h2 class="questions-heading">
          Have Questions?<br>We’re Here for You
        </h2>
        <p class="questions-description">
          Let us know what challenges you are<br>trying to solve so we can help.
        </p>
        <a href="{{ route('home') }}#support" class="btn btn-white questions-btn">
          Contact us
        </a>
      </div>
    </section>
  </main>

  <!-- ==========================================================================
       Reusable Footer Component
       ========================================================================== -->
  <x-footer />
@endsection

@push('scripts')
  <script src="{{ asset('js/checkout.js') }}"></script>
@endpush
