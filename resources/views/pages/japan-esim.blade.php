@extends('layouts.app')

{{-- Static Japan page (live catalogue off) and reusable /esim/{country} template (live catalogue on, decision D6). --}}
@php($countryName = ($catalogueLive ?? false) ? $country->name : 'Japan')

@section('title', $countryName.' eSIM — Unlimited & High-Speed Mobile Data | Eazy Communications')
@section('meta_description', ($catalogueLive ?? false) ? 'Stay connected in '.$countryName.' with Eazy Communications prepaid eSIM data plans. Instant QR activation.' : 'Stay connected across Tokyo, Kyoto, Osaka and all of Japan with Eazy Communications high-speed unlimited 5G/4G prepaid eSIM. Instant QR activation.')
@section('body_class', 'japan-page')

@push('head')
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">

  <!-- Design System Architecture & Shared Global Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/faq-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">

  <!-- Dedicated Page Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/japan-esim.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Header Component (Figma Node 196:343 / 196:351)
       ========================================================================== -->
  <x-header active="new-esim" :mobile-links="[
      ['label' => 'Cart', 'route' => 'cart'],
      ['label' => 'Account', 'route' => 'login'],
  ]" />

    <!-- ==========================================================================
         Hero Banner (Global Shared Banner)
         ========================================================================== -->
    <section class="page-banner" aria-label="{{ $countryName }} eSIM Hero Banner">
      <img src="{{ asset($bannerImage ?? 'assets/images/japan-esim-banner.png') }}" alt="{{ ($catalogueLive ?? false) ? $countryName.' eSIM' : 'Japan eSIM scenic mountains' }}" class="page-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">{{ $countryName }} eSIM</h1>
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
        <span class="breadcrumbs-current" aria-current="page">{{ mb_strtoupper($countryName) }} ESIM</span>
      </div>
    </nav>

    <!-- ==========================================================================
         3. Product Configuration & Hero Details (Figma Node 165:183)
         ========================================================================== -->
    <section class="product-config-section" id="planConfig">
      <div class="japan-container">
        <div class="product-config-grid">
          <!-- Left Column: Product Info & Trip Start Date Box (543px) -->
          <div class="product-info-col">
            <h2 class="product-main-title">{{ $countryName }} eSIM</h2>
            <div class="product-rating-row">
              <div class="rating-stars" aria-label="5 out of 5 stars">
                <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
                <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
                <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
                <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
                <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
              </div>
              <a href="#reviews" class="reviews-link">24 reviews</a>
            </div>

            <p class="product-lead-text">
              Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
            </p>

            <div class="trip-start-card">
              <h3 class="trip-start-title">When does your trip start?</h3>
              <p class="trip-start-desc">Select your travel date on your eSIM is ready when you land.</p>
              <div class="trip-date-input-wrap" id="tripDateWrap">
@if ($catalogueLive ?? false)
                <input type="text" class="trip-date-input" id="tripStartDate" value="" placeholder="Select date (optional)" aria-label="Trip start date (optional)" readonly>
@else
                <input type="text" class="trip-date-input" id="tripStartDate" value="25-08-2026" placeholder="25-08-2026" aria-label="Trip start date" readonly>
@endif
                <img src="{{ asset('assets/icons/calendar-blue.svg') }}" alt="Select date" class="trip-date-icon" id="tripCalendarIcon" role="button" aria-label="Open calendar" tabindex="0">
              </div>
            </div>
          </div>

          <!-- Right Column: Plan Configurator & Add to Cart (602px) -->
          <div class="product-config-col">
            <!-- Unlimited / Fixed Toggle Pill -->
            <div class="data-type-toggle" role="tablist" aria-label="Data Plan Type">
@if ($catalogueLive ?? false)
              <button @class(['toggle-tab-btn', 'active' => $initialType === 'unlimited']) data-type="unlimited" role="tab" aria-selected="{{ $initialType === 'unlimited' ? 'true' : 'false' }}" @if ($planData['unlimited'] === []) hidden @endif>Unlimited Data</button>
              <button @class(['toggle-tab-btn', 'active' => $initialType === 'fixed']) data-type="fixed" role="tab" aria-selected="{{ $initialType === 'fixed' ? 'true' : 'false' }}" @if ($planData['fixed'] === []) hidden @endif>Fixed Data</button>
@else
              <button class="toggle-tab-btn active" data-type="unlimited" role="tab" aria-selected="true">Unlimited Data</button>
              <button class="toggle-tab-btn" data-type="fixed" role="tab" aria-selected="false">Fixed Data</button>
@endif
            </div>

            <!-- 2x2 Plan Cards Grid -->
            <div class="plan-cards-grid" id="planCardsGrid" role="radiogroup" aria-label="Select Plan Option">
              <!-- Rendered dynamically via js/japan-esim.js -->
            </div>

            <!-- Add to Cart Button (Matching global .btn.btn-primary) -->
            <button class="btn btn-primary btn-add-cart" id="addCartBtn" type="button">
              <span>Add to Cart</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         4. "What Does Japan eSIM Include?" Section (Figma Node 165:184)
         ========================================================================== -->
    <section class="specs-section" id="specs">
      <div class="japan-container">
        <h2 class="section-heading">What Does {{ $countryName }} eSIM Include?</h2>
        <p class="section-subtitle">
          {{ $countryName }} eSIM includes prepaid data, instant eSIM activation, supported network coverage, and compatibility with
          eSIM-enabled smartphones. Plan details such as data allowance, validity, network, hotspot, and activation process are
          listed below.
        </p>

        <!-- Navigation Tabs Bar -->
        <div class="specs-tabs-wrap" role="tablist" aria-label="Plan Specification Tabs">
          <button class="spec-tab-btn" data-tab="overview" role="tab" aria-selected="false">Overview</button>
          <button class="spec-tab-btn active" data-tab="network" role="tab" aria-selected="true">Network</button>
          <button class="spec-tab-btn" data-tab="activation" role="tab" aria-selected="false">Activation</button>
          <button class="spec-tab-btn" data-tab="compatibility" role="tab" aria-selected="false">Compatibility</button>
        </div>

        <!-- Specs Table Card -->
        <div class="specs-table-card" id="specsTableCard" role="tabpanel">
          <!-- Rendered dynamically via js/japan-esim.js -->
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         5. "Full product information" Section (Figma Node 165:185)
         ========================================================================== -->
    <section class="product-details-section">
      <div class="japan-container">
        <h2 class="section-heading">Full product information</h2>
        <ul class="product-details-list">
          <li>This is a data-only eSIM. It does not come with a phone number.</li>
          <li>Simply scan the QR code to download and use the eSIM.</li>
          <li>Package validity will start upon scanning the QR code. The eSIM must be activated within 2 months from purchase.</li>
          <li>Full data speeds - no daily limits, no throttling. Mobile hotspot is supported.</li>
          <li>The eSIM will automatically connect to a major local mobile network in eligible countries with 5G or 4G LTE speeds.</li>
          <li>Usable only with eSIM compatible phones and tablets which are not carrier-locked. If in doubt, please check the FAQ section.</li>
        </ul>
      </div>
    </section>

    <!-- ==========================================================================
         6. Testimonials Section ("Trusted by Travelers Worldwide")
         (Figma Node 159:159 - Reused from Home & New eSIM page)
         ========================================================================== -->
    <section class="testimonials-section" id="reviews">
      <div class="japan-container">
        <div class="section-title-wrap">
          <h2 class="section-heading">
            Trusted by Travelers<br>Worldwide
          </h2>
          <p class="section-subtitle">
            See why thousands of travelers choose our eSIM for fast, activation, reliable connectivity, and seamless global coverage.
          </p>
        </div>
      </div>

      <!-- Animated Infinite Testimonial Rows -->
      <div class="testimonials-track-container">
        <!-- Row 1: Leftward Scroll -->
        <div class="testimonials-row testimonials-row-1">
          <div class="testimonials-group">
            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-john.webp') }}" alt="John Doe" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>John Doe</h4>
                  <p>USA</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was effortless, and the connection remained stable throughout my travels. I will definitely
                use this service again on my next trip."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-michael.webp') }}" alt="Michael Johnson" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Michael Johnson</h4>
                  <p>Australia</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without
                worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>United Kingdom</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The data speeds were fast, and the instructions were clear and easy to follow. Everything worked seamlessly
                right from the airport."
              </p>
              <img src="{{ asset('assets/icons/quote-yellow.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/avatar-1.webp') }}" alt="Sarah Jenkins" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Sarah Jenkins</h4>
                  <p>Canada</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Superb speed throughout Tokyo and Kyoto! Never dropped once on the Shinkansen high-speed trains. Highly recommend!"
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>

          <!-- Duplicate Group for Seamless Marquee Loop -->
          <div class="testimonials-group" aria-hidden="true">
            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-john.webp') }}" alt="John Doe" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>John Doe</h4>
                  <p>USA</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was effortless, and the connection remained stable throughout my travels. I will definitely
                use this service again on my next trip."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-michael.webp') }}" alt="Michael Johnson" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Michael Johnson</h4>
                  <p>Australia</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without
                worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>United Kingdom</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The data speeds were fast, and the instructions were clear and easy to follow. Everything worked seamlessly
                right from the airport."
              </p>
              <img src="{{ asset('assets/icons/quote-yellow.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/avatar-1.webp') }}" alt="Sarah Jenkins" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Sarah Jenkins</h4>
                  <p>Canada</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Superb speed throughout Tokyo and Kyoto! Never dropped once on the Shinkansen high-speed trains. Highly recommend!"
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>
        </div>

        <!-- Row 2: Rightward Scroll -->
        <div class="testimonials-row testimonials-row-2">
          <div class="testimonials-group">
            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-michael.webp') }}" alt="Michael Johnson" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Michael Johnson</h4>
                  <p>Australia</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without
                worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>United Kingdom</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The other way around, and it could be great, were it because it bids they are the little ugly in my morality."
              </p>
              <img src="{{ asset('assets/icons/quote-yellow.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-john.webp') }}" alt="John Doe" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>John Doe</h4>
                  <p>USA</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made
                traveling so much easier."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/avatar-2.webp') }}" alt="Kenji Sato" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Kenji Sato</h4>
                  <p>Japan</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Instant activation upon arriving at Haneda airport. Full KDDI 5G network speed, very smooth for video calls and maps."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>

          <!-- Duplicate Group for Seamless Marquee Loop -->
          <div class="testimonials-group" aria-hidden="true">
            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-michael.webp') }}" alt="Michael Johnson" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Michael Johnson</h4>
                  <p>Australia</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without
                worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>United Kingdom</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The other way around, and it could be great, were it because it bids they are the little ugly in my morality."
              </p>
              <img src="{{ asset('assets/icons/quote-yellow.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-john.webp') }}" alt="John Doe" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>John Doe</h4>
                  <p>USA</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made
                traveling so much easier."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/avatar-2.webp') }}" alt="Kenji Sato" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Kenji Sato</h4>
                  <p>Japan</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "Instant activation upon arriving at Haneda airport. Full KDDI 5G network speed, very smooth for video calls and maps."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         7. "Got Questions? We’ve Got Answers" FAQ Accordion Section (Figma Node 173:100)
         ========================================================================== -->
    <section class="faq-accordion-section" id="faq">
      <div class="japan-container">
        <div class="faq-header">
          <h2 class="faq-title">Got Questions? We’ve Got Answers</h2>
          <p class="faq-subtitle">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
          </p>
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
                <p><strong>iPhone:</strong> Go Settings -&gt; Cellular (or Mobile Data). If you see &lsquo;Add Cellular Plan&rsquo; or &lsquo;Add Mobile Plan&rsquo;, the phone supports eSIM</p>
                <p><strong>Samsung:</strong> Go Settings -&gt; Connections -&gt; SIM Card Manager. If you see &lsquo;Add Mobile Plan&rsquo;, the phone supports eSIM</p>
                <p><strong>Google Pixel:</strong> Go Settings -&gt; Networks &amp; Internet -&gt; SIMs -&gt; &lsquo;+Add more&rsquo;. If you are using Pixel 4/4a or a later model, it will be there.</p>
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
                <p>
                  Yes, you can keep your physical SIM card active for receiving calls and SMS messages while using the eSIM for high-speed mobile data. Most modern dual-SIM smartphones seamlessly support running both simultaneously without interference.
                </p>
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
                <p>
                  Yes, modern smartphones can store multiple eSIM profiles simultaneously (typically up to 5 to 10 profiles), though usually only one or two can be active at the exact same time depending on your device model. You can simply switch between profiles in your cellular settings.
                </p>
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
                <p>
                  Yes! Mobile hotspot and personal tethering are fully supported with our {{ $countryName }} eSIM, allowing you to share high-speed data with your laptops, tablets, or travel companions without extra fees.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         8. CTA / Questions Section ("Have Questions? We're Here for You")
         (Figma Node 159:147 - Reused from index.html & questions-section.css)
         ========================================================================== -->
    <section class="questions-section" id="support">
      <div class="questions-container">
        <h2 class="questions-heading">
          Have Questions?<br>We’re Here for You
        </h2>
        <p class="questions-description">
          Let us know what challenges you are<br>trying to solve so we can help.
        </p>
        <a href="#support" class="btn btn-white questions-btn">
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
@if ($catalogueLive ?? false)
  {{-- Live catalogue data for js/japan-esim.js (customer-safe fields only, HTML-escaped). --}}
  <script>
    window.EAZY_PLANS = @json($planData);
    window.EAZY_INITIAL_TYPE = @json($initialType);
    window.EAZY_SPECS = @json($specData);
    window.EAZY_ASSET_BASE = @json(rtrim(asset('/'), '/').'/');
    // Phase 7: real Add to Cart (the server decides prices; only the plan slug is sent).
    window.EAZY_CART = @json(['url' => route('cart.items.store'), 'cartUrl' => route('cart'), 'signedIn' => auth()->check()]);
  </script>
@endif
  <script src="{{ asset('js/japan-esim.js') }}"></script>
@endpush
