@extends('layouts.app')

@section('title', 'Shopping Cart - Eazy Communications')
@section('meta_description', 'View and manage your eSIM plans in your shopping cart on Eazy Communications. Fast digital delivery and instant activation.')

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
  <link rel="stylesheet" href="{{ asset('css/cart.css') }}">
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
         Hero Banner (Global Shared Banner)
         ========================================================================== -->
    <section class="page-banner" aria-label="Shopping Cart Banner">
      <img src="{{ asset('assets/images/cart-banner.webp') }}" alt="Travelers overlooking scenic coast" class="page-banner-img">
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
        <a href="{{ route('home') }}">home</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <a href="{{ route('new-esim') }}">new eSIM</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <a href="{{ route('japan-esim') }}">japan esim</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current" aria-current="page">Shopping cart</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Shopping Cart Table & Summary Section (Figma Node 173:101)
         ========================================================================== -->
@if ($cartLive ?? false)
@include('cart.partials.live-cart')
@else
    <section class="cart-section" id="cart">
      <div class="cart-container">

        <!-- Active Cart Contents -->
        <div class="cart-content-wrap" id="cartContentWrap">
          <!-- Desktop Table Column Headers -->
          <div class="cart-table-header" aria-hidden="true">
            <p class="cart-th cart-th-product">Product</p>
            <p class="cart-th cart-th-price">Price</p>
            <p class="cart-th cart-th-qty">Quantity</p>
            <p class="cart-th cart-th-total">Total</p>
          </div>

          <!-- Cart Items List -->
          <div class="cart-items-list">
            <!-- Cart Item: Japan eSIM -->
            <article class="cart-item" id="cartItemJapan">
              <!-- Left: Product Image & Details -->
              <div class="cart-product-cell">
                <div class="cart-product-main">
                  <div class="cart-product-thumb">
                    <img src="{{ asset('assets/images/cart-japan-product.png') }}" alt="Japan eSIM" width="84" height="84">
                  </div>
                  <div class="cart-product-details">
                    <h2 class="cart-product-title">Japan eSIM</h2>
                    <p class="cart-product-meta">Unlimited - 7 Days</p>
                    <p class="cart-product-meta">Trip Start Date: 2026-08-31</p>
                  </div>
                </div>

                <!-- Deal Notice Pill Alert (aligned directly underneath product info) -->
                <div class="cart-deal-alert" role="status">
                  <img src="{{ asset('assets/icons/alarm-clock.svg') }}" alt="" class="cart-deal-icon" aria-hidden="true" width="22" height="22">
                  <span class="cart-deal-text">Don’t miss out, the deals in your cart won’t last long!</span>
                </div>
              </div>

              <!-- Desktop Price Cell -->
              <div class="cart-price-cell cart-desktop-cell">
                <span class="cart-price-original">$40.99</span>
                <span class="cart-price-current">$34.99</span>
              </div>

              <!-- Desktop Quantity Cell -->
              <div class="cart-qty-cell cart-desktop-cell">
                <div class="cart-qty-control" role="group" aria-label="Quantity Control">
                  <button type="button" class="qty-btn qty-btn-decrease" id="qtyDecrease" aria-label="Decrease quantity">−</button>
                  <span class="qty-num" id="qtyValue" aria-live="polite">1</span>
                  <button type="button" class="qty-btn qty-btn-increase" id="qtyIncrease" aria-label="Increase quantity">
                    <img src="{{ asset('assets/icons/cart-plus.svg') }}" alt="Increase" width="13" height="13">
                  </button>
                </div>
                <button type="button" class="btn-remove-item" id="removeCartItem" aria-label="Remove Japan eSIM from cart">
                  <span>Remove</span>
                  <img src="{{ asset('assets/icons/cart-remove-x.svg') }}" alt="" width="13" height="13" aria-hidden="true">
                </button>
              </div>

              <!-- Desktop Total Cell -->
              <div class="cart-item-total-cell cart-desktop-cell">
                <span class="cart-item-total-original" id="itemTotalOriginal">$40.99</span>
                <span class="cart-item-total-current" id="itemTotalCurrent">$34.99</span>
              </div>

              <!-- Mobile-Only Compact Controls Bar -->
              <div class="cart-mobile-item-bar">
                <div class="cart-price-cell">
                  <span class="cart-mobile-label">Price</span>
                  <span class="cart-price-current">$34.99</span>
                </div>

                <div class="cart-qty-cell">
                  <div class="cart-qty-control" role="group" aria-label="Quantity Control">
                    <button type="button" class="qty-btn qty-btn-decrease" onclick="document.getElementById('qtyDecrease').click()" aria-label="Decrease quantity">−</button>
                    <span class="qty-num" aria-live="polite">1</span>
                    <button type="button" class="qty-btn qty-btn-increase" onclick="document.getElementById('qtyIncrease').click()" aria-label="Increase quantity">
                      <img src="{{ asset('assets/icons/cart-plus.svg') }}" alt="" width="13" height="13">
                    </button>
                  </div>
                  <button type="button" class="btn-remove-item" onclick="document.getElementById('removeCartItem').click()" aria-label="Remove item">
                    <span>Remove</span>
                    <img src="{{ asset('assets/icons/cart-remove-x.svg') }}" alt="" width="13" height="13" aria-hidden="true">
                  </button>
                </div>

                <div class="cart-item-total-cell">
                  <span class="cart-mobile-label">Total</span>
                  <span class="cart-item-total-current">$34.99</span>
                </div>
              </div>
            </article>
          </div>

          <!-- Bottom Summary & Checkout Row -->
          <div class="cart-summary-row">
            <h3 class="cart-summary-label">Total</h3>

            <div class="cart-summary-actions">
              <span class="cart-summary-amount" id="cartSummaryAmount">$34.99</span>

              <!-- GST Number Section: Button, Input Box & Applied State -->
              <button type="button" class="btn-gst-pill" id="btnGstPill" aria-label="Enter GST Number">
                Enter GST Number (If Applicable)
              </button>

              <div class="gst-input-box" id="gstInputBox" style="display: none;">
                <div class="gst-input-inner">
                  <input type="text" id="gstNumberInput" class="gst-input-field" placeholder="Enter GST Number" maxlength="15" autocomplete="off" spellcheck="false" aria-label="GST Number Input">
                  <button type="button" id="gstApplyBtn" class="btn-gst-apply">Apply</button>
                  <button type="button" id="gstCancelBtn" class="btn-gst-cancel" aria-label="Cancel">✕</button>
                </div>
                <p class="gst-feedback-msg" id="gstFeedbackMsg"></p>
              </div>

              <div class="gst-applied-card" id="gstAppliedCard" style="display: none;">
                <div class="gst-applied-info">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#00AEEF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  <span>GST: <span class="gst-applied-num" id="gstAppliedNum"></span></span>
                </div>
                <button type="button" class="btn-gst-remove" id="btnGstRemove" aria-label="Remove GST" title="Remove GST">✕</button>
              </div>

              <a href="{{ route('checkout') }}" class="btn-checkout-cta" aria-label="Proceed to Checkout">
                Checkout
              </a>

              <a href="{{ route('new-esim') }}" class="cart-continue-link">
                Continue shopping
              </a>
            </div>
          </div>
        </div>

        <!-- Empty Cart State (Hidden by default, shown when item is removed) -->
        <div class="cart-empty-state" id="cartEmptyState" role="region" aria-live="polite">
          <img src="{{ asset('assets/icons/cart-bag.svg') }}" alt="" class="cart-empty-icon" aria-hidden="true">
          <h2 class="cart-empty-title">Your Cart is Empty</h2>
          <p class="cart-empty-desc">Explore our top-value eSIM destinations and find the perfect data plan for your next journey.</p>
          <a href="{{ route('new-esim') }}" class="btn btn-primary" style="height: 48px; padding: 0 32px; border-radius: 42px; font-size: 15px;">
            Explore eSIM Plans
          </a>
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
  <script src="{{ asset('js/cart.js') }}"></script>
@endpush
