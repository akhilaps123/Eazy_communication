@extends('layouts.app')

@section('title', 'eSIM Support & Troubleshooting - Eazy Communications')
@section('meta_description', 'Find quick help, troubleshooting steps, and answers to common questions about eSIM installation, activation, network, and device compatibility.')

@push('head')
  <!-- Google Fonts: Questrial, Poppins, Inter, Manrope -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@400;500;600&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=Questrial&display=swap" rel="stylesheet">

  <!-- Core Global Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/faq-section.css') }}">

  <!-- Page-Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/esim-support.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header active="esim-support" :mobile-links="[
      ['label' => 'Blog', 'route' => 'blog'],
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main>
    <!-- ==========================================================================
         Hero Banner (Overwater Thatched Huts - Figma Node 196:183)
         ========================================================================== -->
    <section class="page-banner" aria-label="Support Banner">
      <img src="{{ asset('assets/images/esim-support-banner.webp') }}" alt="Overwater bungalow huts over turquoise lagoon" class="page-banner-img support-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title support-banner-title">How Can We Help You Stay Connected?</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current">ESIM SUPPORT</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Section 1: Quick Help / Common Issues (Figma Node 204:333)
         ========================================================================== -->
    <section class="support-quick-help-section" aria-labelledby="quick-help-heading">
      <div class="support-container">
        <div class="support-section-header">
          <h2 id="quick-help-heading" class="support-section-title">Quick Help / Common Issues</h2>
        </div>

        <div class="support-quick-help-grid">
          <!-- Card 1: eSIM Installation -->
          <a href="#troubleshooting" class="support-card" data-issue="installation">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-qr-code.svg') }}" alt="" class="support-card-icon" width="60" height="60" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">eSIM<br>Installation</h3>
            </div>
          </a>

          <!-- Card 2: eSIM Activation -->
          <a href="#troubleshooting" class="support-card" data-issue="activation">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-activation.svg') }}" alt="" class="support-card-icon" width="66" height="66" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">eSIM<br>Activation</h3>
            </div>
          </a>

          <!-- Card 3: No Internet / Data Issues -->
          <a href="#troubleshooting" class="support-card" data-issue="data-issues">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-no-internet.svg') }}" alt="" class="support-card-icon" width="66" height="66" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">No Internet/<br>Data Issues</h3>
            </div>
          </a>

          <!-- Card 4: Network & Roaming -->
          <a href="#troubleshooting" class="support-card" data-issue="network">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-tower.svg') }}" alt="" class="support-card-icon" width="66" height="66" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">Network &amp;<br>Roaming</h3>
            </div>
          </a>

          <!-- Card 5: eSIM Not Working -->
          <a href="#troubleshooting" class="support-card" data-issue="not-working">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-tools.svg') }}" alt="" class="support-card-icon" width="50" height="50" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">eSIM<br>Not Working</h3>
            </div>
          </a>

          <!-- Card 6: Device Compatibility -->
          <a href="#faq" class="support-card" data-issue="compatibility">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-device.svg') }}" alt="" class="support-card-icon" width="60" height="60" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">Device<br>Compatibility</h3>
            </div>
          </a>

          <!-- Card 7: Payment & Purchase -->
          <a href="#contact" class="support-card" data-issue="payment">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-card.svg') }}" alt="" class="support-card-icon" width="66" height="66" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">Payment &amp;<br>Purchase</h3>
            </div>
          </a>

          <!-- Card 8: Account & Security -->
          <a href="#contact" class="support-card" data-issue="security">
            <div class="support-card-icon-wrap">
              <img src="{{ asset('assets/icons/support-security.svg') }}" alt="" class="support-card-icon" width="66" height="66" aria-hidden="true">
            </div>
            <div class="support-card-content">
              <h3 class="support-card-title">Account &amp;<br>Security</h3>
            </div>
          </a>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 2: Troubleshooting Flow (Figma Node 204:460)
         ========================================================================== -->
    <section class="support-troubleshoot-section" id="troubleshooting" aria-labelledby="troubleshoot-heading">
      <div class="support-container">
        <div class="support-troubleshoot-header">
          <h2 id="troubleshoot-heading" class="support-section-title">Troubleshooting Flow</h2>
          <p class="support-section-subtitle">Select your issues &rarr; Select your device &rarr; Follow step-by-step solutions</p>
        </div>

        <!-- Troubleshooting Box -->
        <div class="support-flow-card">
          <!-- Selection Controls Row -->
          <div class="support-flow-selects-row">
            <!-- Select 1: Issue -->
            <div class="support-select-box" id="issueSelectBox">
              <span class="support-select-label">1. Select your issue</span>
              <div class="support-select-trigger" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" id="issueSelectTrigger">
                <span class="support-select-val" id="selectedIssueText">eSIM Not Working</span>
                <svg class="support-select-arrow" width="15" height="8" viewBox="0 0 15 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M1 1L7.5 7L14 1" stroke="#373737" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <ul class="support-select-dropdown" role="listbox" id="issueDropdown">
                <li class="support-select-option active" role="option" data-value="not-working">eSIM Not Working</li>
                <li class="support-select-option" role="option" data-value="installation">eSIM Installation</li>
                <li class="support-select-option" role="option" data-value="activation">eSIM Activation</li>
                <li class="support-select-option" role="option" data-value="data-issues">No Internet / Data Issues</li>
                <li class="support-select-option" role="option" data-value="network">Network &amp; Roaming</li>
              </ul>
            </div>

            <!-- Select 2: Device -->
            <div class="support-select-box" id="deviceSelectBox">
              <span class="support-select-label">2. Select your device</span>
              <div class="support-select-trigger" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" id="deviceSelectTrigger">
                <span class="support-select-val" id="selectedDeviceText">iPhone</span>
                <svg class="support-select-arrow" width="15" height="8" viewBox="0 0 15 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M1 1L7.5 7L14 1" stroke="#373737" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <ul class="support-select-dropdown" role="listbox" id="deviceDropdown">
                <li class="support-select-option active" role="option" data-value="iphone">iPhone</li>
                <li class="support-select-option" role="option" data-value="samsung">Samsung Galaxy</li>
                <li class="support-select-option" role="option" data-value="pixel">Google Pixel</li>
                <li class="support-select-option" role="option" data-value="other">Other Android Devices</li>
              </ul>
            </div>
          </div>

          <!-- Step-by-Step Solutions Flow -->
          <div class="support-steps-container">
            <!-- Step 1 -->
            <div class="support-step-item">
              <div class="support-step-circle">
                <img src="{{ asset('assets/icons/flow-sim.svg') }}" alt="" class="support-step-icon" width="68" height="68" aria-hidden="true">
              </div>
              <p class="support-step-text">Check eSIM <br>Installation</p>
            </div>

            <!-- Arrow 1 -->
            <div class="support-step-arrow" aria-hidden="true">&rarr;</div>

            <!-- Step 2 -->
            <div class="support-step-item">
              <div class="support-step-circle">
                <img src="{{ asset('assets/icons/flow-mobile.svg') }}" alt="" class="support-step-icon" width="68" height="68" aria-hidden="true">
              </div>
              <p class="support-step-text">Check Mobile <br>Data Settings</p>
            </div>

            <!-- Arrow 2 -->
            <div class="support-step-arrow" aria-hidden="true">&rarr;</div>

            <!-- Step 3 -->
            <div class="support-step-item">
              <div class="support-step-circle">
                <img src="{{ asset('assets/icons/flow-roaming.svg') }}" alt="" class="support-step-icon" width="68" height="68" aria-hidden="true">
              </div>
              <p class="support-step-text">Check Data <br>Roaming</p>
            </div>

            <!-- Arrow 3 -->
            <div class="support-step-arrow" aria-hidden="true">&rarr;</div>

            <!-- Step 4 -->
            <div class="support-step-item">
              <div class="support-step-circle">
                <img src="{{ asset('assets/icons/flow-restart.svg') }}" alt="" class="support-step-icon" width="68" height="68" aria-hidden="true">
              </div>
              <p class="support-step-text">Restart <br>Device</p>
            </div>

            <!-- Arrow 4 -->
            <div class="support-step-arrow" aria-hidden="true">&rarr;</div>

            <!-- Step 5 -->
            <div class="support-step-item">
              <div class="support-step-circle">
                <img src="{{ asset('assets/icons/flow-headphones.svg') }}" alt="" class="support-step-icon" width="68" height="68" aria-hidden="true">
              </div>
              <p class="support-step-text">
                Still Not Working?<br>
                <a href="#contact" class="support-contact-link">Contact Support</a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 3: FAQ Accordion ("Got Questions? We’ve Got Answers" - Reusable Component)
         ========================================================================== -->
    <section class="faq-accordion-section" id="faq" aria-labelledby="faq-heading">
      <div class="support-container">
        <div class="faq-header">
          <h2 id="faq-heading" class="faq-title">Got Questions? We’ve Got Answers</h2>
          <p class="faq-subtitle">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
        </div>

        <div class="faq-accordion-list">
          <!-- FAQ Item 1 (Active / Expanded by default matching Figma node 159:74) -->
          <div class="faq-item active">
            <button class="faq-trigger" aria-expanded="true">
              <span>Does my phone support eSIM technology?</span>
              <img src="{{ asset('assets/icons/faq-chevron.svg') }}" alt="" class="faq-chevron" aria-hidden="true">
            </button>
            <div class="faq-content">
              <div class="faq-inner-body">
                <p>The sure way you can verify if your phone is compatible:</p>
                <p><strong>iPhone:</strong> Go Settings &rarr; Cellular (or Mobile Data). If you see &lsquo;Add Cellular Plan&rsquo; or &lsquo;Add Mobile Plan&rsquo;, the phone supports eSIM</p>
                <p><strong>Samsung:</strong> Go Settings &rarr; Connections &rarr; SIM Card Manager. If you see &lsquo;Add Mobile Plan&rsquo;, the phone supports eSIM</p>
                <p><strong>Google Pixel:</strong> Go Settings &rarr; Networks &amp; Internet &rarr; SIMs &rarr; &lsquo;+Add more&rsquo;. If you are using Pixel 4/4a or a later model, it will be there.</p>
                <p class="faq-important-paragraph"><strong>IMPORTANT!</strong><br>Some models have exceptions depending on models and markets. Operator locked devices: If your device is locked to a specific carrier due to a contract, it needs to be unlocked first to be able to use the eSIMs sold on our platform.</p>
              </div>
            </div>
          </div>

          <!-- FAQ Item 2 -->
          <div class="faq-item">
            <button class="faq-trigger" aria-expanded="false">
              <span>Can I use my physical SIM along with the eSIM?</span>
              <img src="{{ asset('assets/icons/faq-chevron.svg') }}" alt="" class="faq-chevron" aria-hidden="true">
            </button>
            <div class="faq-content">
              <div class="faq-inner-body">
                <p>Yes, you can keep your physical SIM card active for receiving calls and SMS messages while using the eSIM for high-speed mobile data. Most modern dual-SIM smartphones seamlessly support running both simultaneously without interference.</p>
              </div>
            </div>
          </div>

          <!-- FAQ Item 3 -->
          <div class="faq-item">
            <button class="faq-trigger" aria-expanded="false">
              <span>I already have an active eSIM in my phone, can I use your service?</span>
              <img src="{{ asset('assets/icons/faq-chevron.svg') }}" alt="" class="faq-chevron" aria-hidden="true">
            </button>
            <div class="faq-content">
              <div class="faq-inner-body">
                <p>Yes, modern smartphones can store multiple eSIM profiles simultaneously (typically up to 5 to 10 profiles), though usually only one or two can be active at the exact same time depending on your device model. You can simply switch between profiles in your cellular settings.</p>
              </div>
            </div>
          </div>

          <!-- FAQ Item 4 -->
          <div class="faq-item">
            <button class="faq-trigger" aria-expanded="false">
              <span>Can I use Mobile Hotspot or Tethering with my eSIM?</span>
              <img src="{{ asset('assets/icons/faq-chevron.svg') }}" alt="" class="faq-chevron" aria-hidden="true">
            </button>
            <div class="faq-content">
              <div class="faq-inner-body">
                <p>Yes! Mobile hotspot and personal tethering are fully supported with our Japan eSIM, allowing you to share high-speed data with your laptops, tablets, or travel companions without extra fees.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Have Questions Section (Global Shared Component)
         ========================================================================== -->
    <section class="questions-section" id="contact" aria-label="Have Questions">
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
       Reusable Footer Component (Standard Across All Pages)
       ========================================================================== -->
  <x-footer :link-support="true" />
@endsection

@push('scripts')
  <script src="{{ asset('js/esim-support.js') }}"></script>
@endpush
