@extends('layouts.app')

@section('title', 'Eazy Communications — The Smarter Way to Stay Connected Worldwide')
@section('meta_description', 'Instant eSIM activation, reliable global data, and affordable plans in 200+ destinations. Stay connected wherever you go with Eazy Communications.')

@push('head')
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">

  <!-- Stylesheets (Design System Architecture) -->
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/home.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Reusable Header Component
       ========================================================================== -->
  <x-header variant="guest" />

  <main>
    <!-- ==========================================================================
         Section 1: Hero / Banner
         ========================================================================== -->
    <section class="hero-banner" id="heroBanner">
      <!-- Background Circuit Decors -->
      <img src="{{ asset('assets/icons/circuit-left.svg') }}" alt="" class="hero-circuit-left" aria-hidden="true">
      <img src="{{ asset('assets/icons/circuit-right.svg') }}" alt="" class="hero-circuit-right" aria-hidden="true">

      <!-- Floating Badges -->
      <div class="badge-floating badge-left hero-badge-borderless">
        Borderless Connectivity
        <img src="{{ asset('assets/icons/cursor-borderless.svg') }}" alt="" class="cursor-icon" aria-hidden="true">
      </div>
      <div class="badge-floating badge-right hero-badge-instant">
        Instant Activation
        <img src="{{ asset('assets/icons/cursor-instant.svg') }}" alt="" class="cursor-icon" aria-hidden="true">
      </div>

      <!-- Main Content Block -->
      <div class="hero-content">
        <!-- Trust Badge Row -->
        <div class="hero-trust-row">
          <span class="trust-label">Trusted By</span>
          <div class="trust-avatars">
            <img src="{{ asset('assets/images/avatar-1.webp') }}" alt="Founder avatar" class="trust-avatar" width="28" height="28">
            <img src="{{ asset('assets/images/avatar-2.webp') }}" alt="Leader avatar" class="trust-avatar" width="28" height="28">
            <img src="{{ asset('assets/images/avatar-3.webp') }}" alt="Executive avatar" class="trust-avatar" width="28" height="28">
          </div>
          <span class="trust-meta">25+ Founders &amp; Leaders</span>
          <div class="trust-stars" aria-label="5 stars rating">
            <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
            <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
            <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
            <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
            <img src="{{ asset('assets/icons/star-gold.svg') }}" alt="" aria-hidden="true">
          </div>
        </div>

        <!-- Heading -->
        <h1 class="hero-title">
          The Smarter Way to<br>Stay Connected Worldwide
        </h1>

        <p class="hero-subtitle">
          Instant eSIM activation, reliable global data, and affordable plans in 200+ destinations. Stay connected wherever you go.
        </p>

        <p class="hero-search-prompt">
          Shop for the best eSIM offers
        </p>

        <!-- Search Bar -->
        <div class="hero-search-bar" role="search">
          <input type="text" id="heroSearchInput" class="hero-search-input" placeholder="Search for destination..." aria-label="Search destination">
          <div class="hero-search-icon" id="heroSearchIcon" role="button" aria-label="Submit search">
            <img src="{{ asset('assets/icons/search-icon.svg') }}" alt="Search">
          </div>
        </div>
      </div>

      <!-- Bottom Curve Transition -->
      <div class="hero-bottom-curve" aria-hidden="true">
        <img src="{{ asset('assets/icons/hero-curve.svg') }}" alt="">
      </div>
    </section>

    <!-- ==========================================================================
         Section 2: Popular eSIM Destinations
         ========================================================================== -->
    <section class="section destinations-section" id="destinations">
      <div class="container">
        <div class="section-header-block">
          <h2 class="section-heading">Popular eSIM Destinations</h2>
          <p class="section-description">
            Stay connected in popular destinations worldwide with instant eSIM activation and affordable mobile data.
          </p>
        </div>

        <div class="destinations-grid">
@if ($catalogueLive ?? false)
@include('catalogue.partials.destination-items', ['destinations' => $homeDestinations])
@else
          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="USA" aria-label="USA eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-usa.webp') }}" alt="USA Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">USA</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="China" aria-label="China eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-china.webp') }}" alt="China Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">China</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="UK" aria-label="UK eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-uk.webp') }}" alt="UK Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">UK</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="UAE" aria-label="UAE eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-uae.webp') }}" alt="UAE Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">UAE</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="Japan" aria-label="Japan eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-japan.webp') }}" alt="Japan Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">Japan</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="Egypt" aria-label="Egypt eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-egypt.webp') }}" alt="Egypt Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">Egypt</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="Qatar" aria-label="Qatar eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-qatar.webp') }}" alt="Qatar Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">Qatar</p>
          </a>

          <a href="{{ route('japan-esim') }}" class="destination-item" data-country="Thailand" aria-label="Thailand eSIM">
            <div class="destination-sphere">
              <img src="{{ asset('assets/images/sphere-thailand.webp') }}" alt="Thailand Flag Sphere" width="154" height="116">
            </div>
            <p class="destination-name">Thailand</p>
          </a>
@endif
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 3: Topup & Data Balance
         ========================================================================== -->
    <section class="section topup-section" id="topup">
      <div class="container">
        <div class="topup-grid">
          <!-- Left CTA Card -->
          <div class="topup-cta-card">
            <img src="{{ asset('assets/icons/topup-circuit.svg') }}" alt="" class="topup-circuit-bg" aria-hidden="true">
            <div class="topup-cta-content">
              <h3 class="topup-title">Stay Connected Anywhere in the World</h3>
              <p class="topup-desc">Top up your data and check your remaining usage anytime, anywhere.</p>
              <div class="topup-actions">
                <a href="#topup" class="btn btn-pill-white">Topup</a>
                <a href="{{ route('check-usage') }}" class="btn btn-outline-white">Check Usage</a>
              </div>
            </div>
          </div>

          <!-- Right Status Info Cards -->
          <div class="topup-status-col">
            <!-- Package Balance Card -->
            <div class="topup-package-card">
              <div class="package-info-left">
                <img src="{{ asset('assets/icons/globe-badge.svg') }}" alt="" class="package-globe-icon" aria-hidden="true">
                <div class="package-texts">
                  <h4>Re-digital Internet</h4>
                  <p>Your data package balance</p>
                </div>
              </div>
              <div class="package-progress-badge">
                <img src="{{ asset('assets/icons/circle-progress.svg') }}" alt="" aria-hidden="true">
                <span>79%</span>
              </div>
            </div>

            <!-- 48 GB Data Card -->
            <div class="topup-data-card">
              <div class="data-header-row">
                <div class="data-number-wrap">
                  <span class="data-number">48</span>
                  <span class="data-unit">GB</span>
                </div>
                <div class="data-flag-wrap">
                  <img src="{{ asset('assets/images/flag-uk-round.webp') }}" alt="UK Flag" width="37" height="37">
                  <span class="data-interview-text">Virtual Interviews in London</span>
                </div>
              </div>

              <div class="data-card-divider"></div>

              <div class="data-footer-row">
                <div>
                  <h5 class="data-provider-name">Re-digital</h5>
                  <p class="data-tagline">Your Trusted Online Ally</p>
                </div>
                <a href="#topup" class="data-globe-link" aria-label="Visit provider">
                  <img src="{{ asset('assets/icons/globe-icon.svg') }}" alt="">
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 4: eSIM Offers (12 Product Cards)
         ========================================================================== -->
    <section class="section offers-section" id="offers">
      <div class="container">
        <div class="section-header-block">
          <h2 class="section-heading">Smart Deals for Smart Travelers</h2>
          <p class="section-description">
            Travel farther for less with exclusive eSIM offers, affordable data plans, and unbeatable savings.
          </p>
        </div>

        <!-- Filter Pill Tabs -->
        <div class="offers-nav-wrap">
          <div class="pill-nav" role="tablist">
            <div class="pill-tab" data-filter="all" role="tab" tabindex="0">All Offers</div>
            <div class="pill-tab active" data-filter="popular" role="tab" tabindex="0">Popular eSIM offers</div>
            <div class="pill-tab" data-filter="latest" role="tab" tabindex="0">Latest eSIM offers</div>
          </div>
        </div>

        <!-- Offers 12-Card Grid -->
        <div class="offers-grid">
@if ($catalogueLive ?? false)
@include('catalogue.partials.offer-cards', ['cards' => $homeOffers])
@else

          <!-- Card 1: Japan -->
          <article class="offer-card" data-category="popular" data-country="Japan">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-japan.webp') }}" alt="Japan eSIM scenery" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-popular">Popular</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-japan.png') }}" alt="Japan flag" width="24" height="24">
                <span>Japan</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-roamvault.webp') }}" alt="RoamVault" class="provider-logo">
                  <div class="provider-details">
                    <h5>Japan eSIM</h5>
                    <p>RoamVault</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$34.99</span>
                <span class="price-original">$40.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="{{ route('japan-esim') }}" class="btn btn-outline">View Details</a>
                <a href="{{ route('japan-esim') }}" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 2: USA -->
          <article class="offer-card" data-category="best-seller" data-country="USA">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-usa.webp') }}" alt="USA Golden Gate Bridge" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-best-seller">Best Seller</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-usa.png') }}" alt="USA flag" width="24" height="24">
                <span>United States of America</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-nextlink.webp') }}" alt="NextLink" class="provider-logo">
                  <div class="provider-details">
                    <h5>USA eSIM</h5>
                    <p>NextLink</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$10.99</span>
                <span class="price-original">$20.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 3: Thailand -->
          <article class="offer-card" data-category="popular" data-country="Thailand">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-thailand.webp') }}" alt="Thailand island scenery" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-popular">Popular</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-thailand.png') }}" alt="Thailand flag" width="24" height="24">
                <span>Thailand</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-true.webp') }}" alt="True" class="provider-logo">
                  <div class="provider-details">
                    <h5>Thailand eSIM</h5>
                    <p>True</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$10.49</span>
                <span class="price-original">$20.49</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 4: UAE -->
          <article class="offer-card" data-category="latest" data-country="UAE">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-uae.webp') }}" alt="Dubai skyline" class="offer-photo" loading="lazy">
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-uae.png') }}" alt="UAE flag" width="24" height="24">
                <span>UAE</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-sparks.webp') }}" alt="Sparks" class="provider-logo">
                  <div class="provider-details">
                    <h5>UAE eSIM</h5>
                    <p>Sparks</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$12.99</span>
                <span class="price-original">$20.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 5: European Union -->
          <article class="offer-card" data-category="best-value" data-country="European Union">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-eu.webp') }}" alt="European coastline" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-best-value">Best Value</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-eu.png') }}" alt="European Union flag" width="24" height="24">
                <span>European Union</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-esimgo.webp') }}" alt="eSIMGO" class="provider-logo">
                  <div class="provider-details">
                    <h5>European eSIM</h5>
                    <p>eSIMGO</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$41.99</span>
                <span class="price-original">$50.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 6: China -->
          <article class="offer-card" data-category="popular" data-country="China">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-china.webp') }}" alt="China city street" class="offer-photo" loading="lazy">
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-china.png') }}" alt="China flag" width="24" height="24">
                <span>China</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-airalo.webp') }}" alt="Airalo" class="provider-logo">
                  <div class="provider-details">
                    <h5>China eSIM</h5>
                    <p>Airlo</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$7.99</span>
                <span class="price-original">$10.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 7: Brazil -->
          <article class="offer-card" data-category="popular" data-country="Brazil">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-brazil.webp') }}" alt="Brazil coastline" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-popular">Popular</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-brazil.png') }}" alt="Brazil flag" width="24" height="24">
                <span>Brazil</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-3.webp') }}" alt="3" class="provider-logo">
                  <div class="provider-details">
                    <h5>Brazil eSIM</h5>
                    <p>3</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$13.99</span>
                <span class="price-original">$23.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 8: Australia -->
          <article class="offer-card" data-category="best-seller" data-country="Australia">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-australia.webp') }}" alt="Sydney Opera House" class="offer-photo" loading="lazy">
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-australia.png') }}" alt="Australia flag" width="24" height="24">
                <span>Australia</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-sparks.webp') }}" alt="Sparks" class="provider-logo">
                  <div class="provider-details">
                    <h5>Australia eSIM</h5>
                    <p>Sparks</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$9.99</span>
                <span class="price-original">$10.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 9: Egypt -->
          <article class="offer-card" data-category="popular" data-country="Egypt">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-egypt.webp') }}" alt="Pyramids of Egypt" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-popular">Popular</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-egypt.svg') }}" alt="Egypt flag" width="24" height="24">
                <span>Egypt</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-tsimtech.webp') }}" alt="tSimTech" class="provider-logo">
                  <div class="provider-details">
                    <h5>Egypt eSIM</h5>
                    <p>tSimTech</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$11.99</span>
                <span class="price-original">$25.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 10: New Zealand -->
          <article class="offer-card" data-category="best-seller" data-country="New Zealand">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-newzealand.webp') }}" alt="New Zealand fjord scenery" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-best-seller">Best Seller</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-newzealand.png') }}" alt="New Zealand flag" width="24" height="24">
                <span>New Zeeland</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-airalo.webp') }}" alt="Airalo" class="provider-logo">
                  <div class="provider-details">
                    <h5>New Zeeland eSIM</h5>
                    <p>Airlo</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$17.99</span>
                <span class="price-original">$25.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 11: Norway -->
          <article class="offer-card" data-category="popular" data-country="Norway">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-norway.webp') }}" alt="Norway Lofoten Islands" class="offer-photo" loading="lazy">
              <div class="offer-card-badge">
                <span class="badge badge-popular">Popular</span>
              </div>
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-norway.png') }}" alt="Norway flag" width="24" height="24">
                <span>Norway</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-3.webp') }}" alt="3" class="provider-logo">
                  <div class="provider-details">
                    <h5>Norway eSIM</h5>
                    <p>3</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$19.99</span>
                <span class="price-original">$35.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

          <!-- Card 12: France -->
          <article class="offer-card" data-category="latest" data-country="France">
            <div class="offer-card-media">
              <img src="{{ asset('assets/images/card-france.webp') }}" alt="Paris skyline" class="offer-photo" loading="lazy">
              <div class="offer-card-country">
                <img src="{{ asset('assets/images/flag-round-france.png') }}" alt="France flag" width="24" height="24">
                <span>France</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/prov-sparks.webp') }}" alt="Sparks" class="provider-logo">
                  <div class="provider-details">
                    <h5>Paris eSIM</h5>
                    <p>Sparks</p>
                  </div>
                </div>
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">$14.99</span>
                <span class="price-original">$15.99</span>
              </div>
              <div class="offer-card-actions">
                <a href="#offers" class="btn btn-outline">View Details</a>
                <a href="#offers" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

@endif
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 5: Activation Steps ("How eSIM Works In Just Four Simple Steps")
         ========================================================================== -->
    <section class="section activation-section" id="activation">
      <div class="container">
        <div class="section-header-block">
          <h2 class="section-heading">How eSIM Works In Just<br>Four Simple Steps</h2>
          <p class="section-description">
            Connect with eSIM quickly—activate your data plan in a few steps
          </p>
        </div>

        <div class="steps-grid">
          <!-- Step 1 (Blue Card) -->
          <div class="step-card step-card-blue">
            <div class="step-photo-wrap">
              <img src="{{ asset('assets/images/step-1.webp') }}" alt="Phone in hand selecting country" loading="lazy" decoding="async">
              <div class="step-photo-overlay"></div>
            </div>
            <div class="step-content-wrap">
              <h3 class="step-title">Choose Your Destination</h3>
              <div class="step-btn-link">
                <img src="{{ asset('assets/icons/btn-arrow-white-circle.svg') }}" alt="" aria-hidden="true">
                <span>Learn More</span>
              </div>
              <p class="step-desc">Choose your country or region for global eSIM internet</p>
            </div>
          </div>

          <!-- Step 2 (White Card) -->
          <div class="step-card step-card-white">
            <div class="step-photo-wrap">
              <img src="{{ asset('assets/images/step-2.webp') }}" alt="Traveler choosing data plan on tablet" loading="lazy" decoding="async">
              <div class="step-photo-overlay"></div>
            </div>
            <div class="step-content-wrap">
              <h3 class="step-title">Select Your Data Plan</h3>
              <div class="step-btn-link">
                <img src="{{ asset('assets/icons/btn-arrow-blue-circle.svg') }}" alt="" aria-hidden="true">
                <span>Learn More</span>
              </div>
              <p class="step-desc">Choose a data plan that suits your travel needs</p>
            </div>
          </div>

          <!-- Step 3 (White Card) -->
          <div class="step-card step-card-white">
            <div class="step-photo-wrap">
              <img src="{{ asset('assets/images/step-3.webp') }}" alt="Scanning QR code on phone screen" loading="lazy" decoding="async">
              <div class="step-photo-overlay"></div>
            </div>
            <div class="step-content-wrap">
              <h3 class="step-title">Scan the QR Code</h3>
              <div class="step-btn-link">
                <img src="{{ asset('assets/icons/btn-arrow-blue-circle.svg') }}" alt="" aria-hidden="true">
                <span>Learn More</span>
              </div>
              <p class="step-desc">After purchase, scan the QR code to install the eSIM on your device</p>
            </div>
          </div>

          <!-- Step 4 (Blue Card) -->
          <div class="step-card step-card-blue">
            <div class="step-photo-wrap">
              <img src="{{ asset('assets/images/step-4.webp') }}" alt="Happy traveler browsing outdoors" loading="lazy" decoding="async">
              <div class="step-photo-overlay"></div>
            </div>
            <div class="step-content-wrap">
              <h3 class="step-title">Connect and Start Browsing</h3>
              <div class="step-btn-link">
                <img src="{{ asset('assets/icons/btn-arrow-white-circle.svg') }}" alt="" aria-hidden="true">
                <span>Learn More</span>
              </div>
              <p class="step-desc">Once activated, your phone connects to local networks for browsing</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 6: Testimonials ("Trusted by Travelers Worldwide")
         ========================================================================== -->
    <section class="section testimonials-section" id="testimonials">
      <div class="container">
        <div class="section-header-block">
          <h2 class="section-heading">Trusted by Travelers<br>Worldwide</h2>
          <p class="section-description">
            See why thousands of travelers choose our eSIM for fast activation, reliable connectivity, and seamless global coverage.
          </p>
        </div>
      </div>

      <div class="testimonials-track-container">
        <!-- Row 1 (Slides Left) -->
        <div class="testimonials-row testimonials-row-1">
          <!-- Group 1 -->
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my next trip."
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>

          <!-- Group 2 (Duplicate for seamless loop) -->
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my next trip."
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>

          <!-- Group 3 (Extra duplicate for wide screens) -->
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my next trip."
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
              </p>
              <img src="{{ asset('assets/icons/quote-purple.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>
        </div>

        <!-- Row 2 (Slides Right) -->
        <div class="testimonials-row testimonials-row-2">
          <!-- Group 1 -->
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my next trip."
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>

          <!-- Group 2 (Duplicate for seamless loop) -->
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my next trip."
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>

          <!-- Group 3 (Extra duplicate for wide screens) -->
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>

            <div class="testimonial-card">
              <div class="testimonial-user-row">
                <img src="{{ asset('assets/images/user-emily.webp') }}" alt="Emily Smith" class="testimonial-avatar">
                <div class="testimonial-user-meta">
                  <h4>Emily Smith</h4>
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my next trip."
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made traveling so much easier."
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
                "Excellent coverage and affordable pricing. I stayed connected across multiple countries without worrying about roaming charges."
              </p>
              <img src="{{ asset('assets/icons/quote-green.svg') }}" alt="" class="testimonial-quote-icon" aria-hidden="true">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 7: App Download ("Travel Smarter with Our App")
         ========================================================================== -->
    <section class="app-download-section" id="app">
      <!-- Full-width angled blue background shape from Figma -->
      <div class="app-banner-bg-wrap">
        <img src="{{ asset('assets/images/app-banner-shape.svg') }}" alt="" class="app-banner-shape" aria-hidden="true">
        <img src="{{ asset('assets/icons/app-tech-vector.svg') }}" alt="" class="app-tech-lines" aria-hidden="true">
      </div>

      <div class="container app-download-container">
        <div class="app-download-content">
          <h2 class="app-download-title">Travel Smarter with<br>Our App</h2>
          <p class="app-download-desc">
            Download our app to activate your eSIM, manage data, top up plans, and stay connected wherever you go.
          </p>

          <p class="app-store-label">Download our app</p>
          <div class="app-badges-row">
            <!-- App Store Badge (White) -->
            <a href="#" class="app-badge-btn app-badge-apple" aria-label="Download on the App Store">
              <svg class="badge-icon" viewBox="0 0 170 170" fill="currentColor">
                <path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.7-3.04-7.7-7.85-12.01-14.42-5.46-8.27-9.84-17.65-13.14-28.14-3.3-10.49-4.96-20.89-4.96-31.21 0-14.89 3.8-27.42 11.41-37.59 7.6-10.17 17.2-15.35 28.78-15.54 4.58 0 10.02 1.25 16.32 3.75 6.3 2.5 10.08 3.8 11.34 3.91 1.63-.22 5.56-1.58 11.78-4.08 6.22-2.51 11.49-3.71 15.82-3.61 9.02.44 16.95 3.65 23.79 9.63 4.24 3.7 7.55 8.16 9.94 13.38-8.8 5.33-13.09 12.62-12.87 21.87.22 7.72 3.17 14.28 8.86 19.69 5.68 5.41 12.52 8.43 20.51 9.07-2.07 6.1-4.78 12.54-8.13 19.33zM119.22 33.15c0-5.87 2.12-11.64 6.35-17.31 4.24-5.67 9.47-9.69 15.7-12.06.65 4.79.05 10.03-1.8 15.72-1.85 5.69-4.87 10.59-9.06 14.71-3.7 3.59-7.9 6.04-12.61 7.35-.43-2.83-.65-5.63-.65-8.41z"/>
              </svg>
              <div class="badge-text">
                <span class="badge-sub">Download on the</span>
                <span class="badge-main">App Store</span>
              </div>
            </a>

            <!-- Google Play Badge (Black) -->
            <a href="#" class="app-badge-btn app-badge-google" aria-label="Get it on Google Play">
              <svg class="badge-icon" viewBox="0 0 512 512">
                <path fill="#00e676" d="M380.9 220.5L96.2 56.1c-12.2-7-26.6-4.5-36 6.3L275.9 278l105-57.5z"/>
                <path fill="#ff3d00" d="M380.9 291.5L275.9 234 60.2 449.6c9.4 10.8 23.8 13.3 36 6.3l284.7-164.4z"/>
                <path fill="#4dd0e1" d="M60.2 62.4C55.4 68 52.6 75.7 52.6 85v342c0 9.3 2.8 17 7.6 22.6L267.4 256 60.2 62.4z"/>
                <path fill="#ffc107" d="M433.8 244.2l-52.9-30.5-27.2 27.2 27.2 27.2 52.9-30.5c10.8-6.2 10.8-27.2 0-33.4z"/>
              </svg>
              <div class="badge-text">
                <span class="badge-sub">GET IT ON</span>
                <span class="badge-main">Google Play</span>
              </div>
            </a>
          </div>
        </div>
      </div>

      <!-- 3D Overlapping Phone Mockup placed with exact Figma coordinates -->
      <div class="app-mockup-wrap">
        <img src="{{ asset('assets/images/iphone-mockup.webp') }}" alt="Hand holding iPhone running Eazy Communications app" loading="lazy">
      </div>
    </section>

    <!-- ==========================================================================
         Section 8: Travel Blog ("Recent Travel Tips & Guides")
         ========================================================================== -->
    <section class="section blog-section" id="blog">
      <div class="container">
        <div class="section-header-block">
          <h2 class="section-heading">Recent Travel Tips &amp; Guides</h2>
          <p class="section-description">
            Explore expert travel tips, destination guides, and eSIM insights to make every journey smoother and more connected.
          </p>
        </div>

        <div class="blog-grid">
          <!-- Article 1 -->
          <article class="blog-card">
            <div class="blog-card-media">
              <img src="{{ asset('assets/images/blog-fifa.webp') }}" alt="Soccer Stadium 2026 World Cup" loading="lazy">
            </div>
            <p class="blog-date-meta">Jun 29, 2026 — 11 min read</p>
            <h3 class="blog-title">The Ultimate Multi-City Guide to the 2026 FIFA World Cup Knockout Stages</h3>
            <p class="blog-excerpt">
              The 2026 FIFA World Cup runs June 11 to July 19 across 16 cities in the USA, Canada, and Mexico. Fans chasing the knockout stages will hop between Los Angeles, Miami, New York, Toronto, and Vancouver. The smartest move: grab a MobiMatter North America Regional eSIM before you fly so your data works seamlessly across all three countries, no SIM swaps, no roaming surprises.
            </p>
            <a href="#blog" class="blog-read-more">
              <span>Read more</span>
              <img src="{{ asset('assets/icons/arrow-readmore.svg') }}" alt="" aria-hidden="true">
            </a>
          </article>

          <!-- Article 2 -->
          <article class="blog-card">
            <div class="blog-card-media">
              <img src="{{ asset('assets/images/blog-europe.webp') }}" alt="European flags travel destination" loading="lazy">
            </div>
            <p class="blog-date-meta">Jun 23, 2026 — 8 min read</p>
            <h3 class="blog-title">20 Must-Visit Top European Travel Destinations for First-Time Visitors: Where...</h3>
            <p class="blog-excerpt">
              When it comes to European destinations, first-time travelers are truly spoiled for choice. With its diverse cultures, stunning architecture, historic landmarks, and unforgettable cuisines, Europe presents an unparalleled range of travel experiences. Whether you're seeking romantic vibes, vibrant city life, historic intrigue, or coastal bliss, there’s a perfect destination waiting for you.
            </p>
            <a href="#blog" class="blog-read-more">
              <span>Read more</span>
              <img src="{{ asset('assets/icons/arrow-readmore.svg') }}" alt="" aria-hidden="true">
            </a>
          </article>

          <!-- Article 3 -->
          <article class="blog-card">
            <div class="blog-card-media">
              <img src="{{ asset('assets/images/blog-summer.webp') }}" alt="Summer vacation sunglasses and drink" loading="lazy">
            </div>
            <p class="blog-date-meta">Jun 29, 2026 — 22 min read</p>
            <h3 class="blog-title">25 Best Places to Visit in July for the Ultimate Summer Vacation</h3>
            <p class="blog-excerpt">
              July is the ultimate month for a sun-soaked getaway—whether you’re exploring the best places to visit in July for a lively family adventure, a romantic escape, a solo journey of self-discovery, or a fun-filled friends’ reunion. With schools out and warm weather embracing much of the globe, July sets the perfect stage to dive into new cultures, bask in nature’s beauty, or simply unwind on golden beaches.
            </p>
            <a href="#blog" class="blog-read-more">
              <span>Read more</span>
              <img src="{{ asset('assets/icons/arrow-readmore.svg') }}" alt="" aria-hidden="true">
            </a>
          </article>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Section 9: Reusable "Have Questions? We're Here for You" Component
         ========================================================================== -->
    <section class="questions-section" id="questions">
      <div id="partner" style="position:relative;top:-70px;"></div>
      <div id="support" style="position:relative;top:-70px;"></div>
      <div id="contact" style="position:relative;top:-70px;"></div>
      <div class="questions-container">
        <h2 class="questions-heading">Have Questions?<br>We’re Here for You</h2>
        <p class="questions-description">
          Let us know what challenges you are<br>trying to solve so we can help.
        </p>
        <a href="#contact" class="btn btn-white questions-btn">Contact us</a>
      </div>
    </section>
  </main>

  <!-- ==========================================================================
       Reusable Footer Component
       ========================================================================== -->
  <x-footer />
@endsection
