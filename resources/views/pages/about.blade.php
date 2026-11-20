@extends('layouts.app')

@section('title', 'About Us - Eazy Communications')
@section('meta_description', 'Learn about Eazy Communications, our mission to deliver seamless global eSIM connectivity, instant mobile data activation, and reliable travel plans worldwide.')

@push('head')
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=Questrial&display=swap" rel="stylesheet">

  <!-- Core Global Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">

  <!-- Page-Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/about.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header :mobile-links="[
      ['label' => 'Blog', 'route' => 'blog'],
      ['label' => 'Guides', 'route' => 'guides'],
      ['label' => 'About', 'route' => 'about', 'active' => true],
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main class="about-page-main">
    <!-- ==========================================================================
         Hero Banner (Overwater Bungalow Pier - Figma Node 207:1130)
         ========================================================================== -->
    <section class="page-banner" aria-label="About Banner">
      <img src="{{ asset('assets/images/about-banner.webp') }}" alt="Tropical overwater bungalows with wooden walkway" class="page-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">About</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current">ABOUT</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Section 1: Who We Are? (Figma Node 211:1263)
         ========================================================================== -->
    <section class="about-who-section" aria-labelledby="who-we-are-heading">
      <div class="about-container about-who-container">
        <!-- Text Column -->
        <div class="about-who-content">
          <h2 id="who-we-are-heading" class="about-section-heading">Who We Are?</h2>
          <div class="about-who-text">
            <p>
              Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
            </p>
            <p>
              The purpose of lorem ipsum is to create a natural looking block of text (sentence, paragraph, page, etc.) that doesn't distract from the layout. A practice not without controversy, laying out pages with meaningless filler text can be very useful when the focus is meant to be on design, not content.
            </p>
            <p>
              The passage experienced a surge in popularity during the 1960s when Letraset used it on their dry-transfer sheets, and again during the 90s as desktop publishers bundled the text with their software. Today it's seen all around the web; on templates, websites, and stock designs. Use our generator to get your own, or read on for the authoritative history of lorem ipsum.
            </p>
          </div>
        </div>

        <!-- Image Column -->
        <div class="about-who-image-wrap">
          <img src="{{ asset('assets/images/about-who-we-are.webp') }}" alt="Hand holding smartphone displaying eSIM activation screen" class="about-who-img" width="539" height="540">
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 2: What We Do? (Figma Node 216:1486)
         ========================================================================== -->
    <section class="about-what-section" aria-labelledby="what-we-do-heading">
      <div class="about-container about-what-container">
        <!-- Section Header -->
        <div class="about-what-header">
          <h2 id="what-we-do-heading" class="about-section-heading about-heading-center">What We Do?</h2>
          <p class="about-what-subtitle">
            Japan eSIM includes prepaid data, instant eSIM activation, supported network coverage, and compatibility with eSIM-enabled smartphones. Plan details such as data allowance, validity, network, hotspot, and activation process are listed below.
          </p>
        </div>

        <!-- 4-Card Feature Grid & Slider (Figma Node 216:1484) -->
        <div class="about-cards-slider-wrap">
          <div class="about-cards-grid" id="aboutCardsSlider" role="region" aria-label="What We Do Features Slider">
            <!-- Card 1: Global Coverage -->
            <article class="about-card">
              <div class="about-card-icon-wrap">
                <img src="{{ asset('assets/icons/about-global-coverage.webp') }}" alt="Global Coverage Icon" class="about-card-icon-img" width="70" height="70" loading="lazy">
              </div>
              <h3 class="about-card-title">Global<br>Coverage</h3>
              <p class="about-card-desc">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
            </article>

            <!-- Card 2: Instant Activation -->
            <article class="about-card">
              <div class="about-card-icon-wrap">
                <img src="{{ asset('assets/icons/about-instant-activation.webp') }}" alt="Instant Activation Icon" class="about-card-icon-img" width="70" height="70" loading="lazy">
              </div>
              <h3 class="about-card-title">Instant<br>Activation</h3>
              <p class="about-card-desc">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
            </article>

            <!-- Card 3: Affordable Plans -->
            <article class="about-card">
              <div class="about-card-icon-wrap">
                <img src="{{ asset('assets/icons/about-affordable-plans.webp') }}" alt="Affordable Plans Icon" class="about-card-icon-img" width="70" height="70" loading="lazy">
              </div>
              <h3 class="about-card-title">Affordable<br>Plans</h3>
              <p class="about-card-desc">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
            </article>

            <!-- Card 4: Easy to Use -->
            <article class="about-card">
              <div class="about-card-icon-wrap">
                <img src="{{ asset('assets/icons/about-easy-to-use.webp') }}" alt="Easy to Use Icon" class="about-card-icon-img" width="70" height="70" loading="lazy">
              </div>
              <h3 class="about-card-title">Easy to<br>Use</h3>
              <p class="about-card-desc">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
            </article>
          </div>

          <!-- Slider Pagination Dots (Mobile & Tablet) -->
          <div class="about-slider-dots" id="aboutSliderDots" aria-label="Slider navigation">
            <button class="about-dot active" aria-label="Go to card 1"></button>
            <button class="about-dot" aria-label="Go to card 2"></button>
            <button class="about-dot" aria-label="Go to card 3"></button>
            <button class="about-dot" aria-label="Go to card 4"></button>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 3: Why Choose Us? (Figma Node 230:1533)
         ========================================================================== -->
    <section class="about-why-section" aria-labelledby="why-choose-us-heading">
      <div class="about-container about-why-container">
        <!-- Image Column -->
        <div class="about-why-image-wrap">
          <img src="{{ asset('assets/images/about-why-choose-us.webp') }}" alt="Smiling female traveler in airport with suitcase and smartphone" class="about-why-img" width="571" height="483">
        </div>

        <!-- Content Column -->
        <div class="about-why-content">
          <h2 id="why-choose-us-heading" class="about-section-heading">Why Choose Us?</h2>
          <p class="about-why-subtitle">
            Lorem ipsum is placeholder text commonly used in the graphic, print, and publishing industries for previewing layouts and visual mockups.
          </p>

          <ul class="about-why-list">
            <li class="about-why-item">
              <img src="{{ asset('assets/icons/about-check.svg') }}" alt="" class="about-why-icon" width="37" height="37" aria-hidden="true">
              <span class="about-why-label">Reliable Connectivity</span>
            </li>
            <li class="about-why-item">
              <img src="{{ asset('assets/icons/about-check.svg') }}" alt="" class="about-why-icon" width="37" height="37" aria-hidden="true">
              <span class="about-why-label">Flexible Data Plans</span>
            </li>
            <li class="about-why-item">
              <img src="{{ asset('assets/icons/about-check.svg') }}" alt="" class="about-why-icon" width="37" height="37" aria-hidden="true">
              <span class="about-why-label">No roaming hassles</span>
            </li>
            <li class="about-why-item">
              <img src="{{ asset('assets/icons/about-check.svg') }}" alt="" class="about-why-icon" width="37" height="37" aria-hidden="true">
              <span class="about-why-label">Easy online purchase</span>
            </li>
            <li class="about-why-item">
              <img src="{{ asset('assets/icons/about-check.svg') }}" alt="" class="about-why-icon" width="37" height="37" aria-hidden="true">
              <span class="about-why-label">24/7 customer support</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Have Questions? Section (Global Reusable Component)
         ========================================================================== -->
    <section class="questions-section" aria-labelledby="about-questions-title">
      <div class="questions-container">
        <h2 id="about-questions-title" class="questions-heading">
          Have Questions?<br>We’re Here for You
        </h2>
        <p class="questions-description">
          Let us know what challenges you are<br>trying to solve so we can help.
        </p>
        <a href="#contact" class="questions-btn">Contact us</a>
      </div>
    </section>
  </main>

  <!-- ==========================================================================
       Site Footer (Global Shared Component)
       ========================================================================== -->
  <x-footer variant="alt" />
@endsection

@push('scripts')
  <script src="{{ asset('js/about.js') }}"></script>
@endpush
