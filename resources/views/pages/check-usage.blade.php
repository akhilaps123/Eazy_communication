@extends('layouts.app')

@section('title', 'Check eSIM Usage & Status - Eazy Communications')
@section('meta_description', 'Check your eSIM data usage, status, and remaining balance quickly with your order number and email.')

@push('head')
  <!-- Google Fonts: Questrial, Poppins, Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@300;400;500;600;700&family=Questrial&display=swap" rel="stylesheet">

  <!-- Core & Shared Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">

  <!-- Page-Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/check-usage.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header active="check-usage" :mobile-links="[
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main>
    <!-- ==========================================================================
         Hero Banner (Yellow Travel Suitcase - Figma Node 189:1286)
         ========================================================================== -->
    <section class="page-banner" aria-label="Check Usage Banner">
      <img src="{{ asset('assets/images/check-usage-banner.webp') }}" alt="Yellow travel suitcase on counter" class="page-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">Check Usage</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current">CHECK USAGE</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Main Check Usage Section (Figma Node 189:1452)
         ========================================================================== -->
    <section class="check-usage-section" id="checkUsageSection" aria-label="eSIM Status and Usage Check">
      <div class="check-usage-container">
        <!-- Text Headings -->
        <div class="check-usage-header">
          <h2 class="check-usage-main-title">eSIM Status and Usage Check</h2>
          <p class="check-usage-lead">You can see your data usage directly from your phone settings.</p>
          <p class="check-usage-prompt">Alternatively, enter your order number and associated email address:</p>
        </div>

        <!-- Cyan-Tinted Check Usage Form Card -->
        <div class="check-usage-card">
          <form id="checkUsageForm" method="POST" action="{{ route('check-usage.submit') }}" class="check-usage-form" novalidate>
            @csrf
            <!-- Order Number Input Pill with Search Icon -->
            <div class="check-input-group order-group">
              <input 
                type="text" 
                id="orderNumberInput"
                name="order_number"
                class="check-input-control" 
                placeholder="MM-12345" 
                required 
                autocomplete="off"
                aria-label="Order Number"
              >
              <span class="check-input-icon" aria-hidden="true">
                <img src="{{ asset('assets/icons/search-input-icon.svg') }}" alt="">
              </span>
            </div>

            <!-- Email Address Input Pill -->
            <div class="check-input-group email-group">
              <input 
                type="email" 
                id="emailAddressInput"
                name="email"
                class="check-input-control" 
                placeholder="Email Address" 
                required 
                autocomplete="email"
                aria-label="Email Address"
              >
            </div>

            <!-- Submit Check Button -->
            <button type="submit" id="btnCheckUsage" class="check-submit-btn">
              Check
            </button>
          </form>

          <div id="checkFormMessage" class="check-form-message"></div>
        </div>

        <!-- Interactive Result Card (Reveals on Submit) -->
        <div class="check-result-card" id="checkResultCard" aria-live="polite">
          <div class="check-result-header">
            <h3 class="check-result-title">eSIM Usage Overview</h3>
            <span class="check-result-badge">Active</span>
          </div>

          <div class="check-result-grid">
            <div class="check-result-item">
              <span class="check-result-label">Order Number</span>
              <span class="check-result-value" id="resOrderNumber">MM-12345</span>
            </div>
            <div class="check-result-item">
              <span class="check-result-label">Associated Email</span>
              <span class="check-result-value" id="resEmailAddress">user@example.com</span>
            </div>
            <div class="check-result-item">
              <span class="check-result-label">Active Plan</span>
              <span class="check-result-value">Japan Unlimited - 7 Days</span>
            </div>
            <div class="check-result-item">
              <span class="check-result-label">Expiration</span>
              <span class="check-result-value">Sept 22, 2026 (6 Days left)</span>
            </div>
          </div>

          <div class="check-progress-wrapper">
            <div class="check-progress-labels">
              <span><strong>Data Used:</strong> 3.8 GB</span>
              <span><strong>High-Speed Balance:</strong> 6.2 GB remaining</span>
            </div>
            <div class="check-progress-bar">
              <div class="check-progress-fill" style="width: 38%;"></div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- ==========================================================================
         Have Questions Section (Global Shared Component)
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
  <script src="{{ asset('js/check-usage.js') }}"></script>
@endpush
