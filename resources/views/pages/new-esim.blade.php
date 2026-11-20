@extends('layouts.app')

@section('title', 'New eSIM Plans & Global Data Offers — Eazy Communications')
@section('meta_description', 'Browse our complete collection of high-speed local and regional eSIM plans for 200+ countries with instant digital delivery and 24/7 connectivity.')
@section('body_class', 'new-esim-page')

@push('head')
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">

  <!-- Design System Architecture & Shared Global Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">

  <!-- Dedicated Page Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/new-esim.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Reusable Header Component (Matched from index.html)
       ========================================================================== -->
  <x-header active="new-esim" />

  <main>
    <!-- ==========================================================================
         Hero Banner (Figma Node 132:265)
         ========================================================================== -->
    <section class="new-esim-banner" aria-label="New eSIM Hero Banner">
      <img src="{{ asset('assets/images/new-esim-banner.webp') }}" alt="Travel walkway on sand dunes" class="new-esim-banner-img">
      <div class="new-esim-banner-overlay" aria-hidden="true"></div>
      <div class="new-esim-banner-content">
        <h1 class="new-esim-banner-title">
          Trusted by Travelers<br>Worldwide
        </h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Figma Node 104:35)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current" aria-current="page">NEW ESIM</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Filter & Sorting Toolbar (Figma Node 132:266)
         ========================================================================== -->
    <section class="filter-toolbar-section" id="catalog" aria-label="Catalog Filters">
      <!-- Top Row: Plan count & Sort By -->
      <div class="filter-toolbar-top">
@if ($catalogueLive ?? false)
        <div class="plans-counter">{{ $catalogueCounter }}</div>
@else
        <div class="plans-counter">Showing 1-12 of 256 plans</div>
@endif

        <div class="sort-wrapper">
          <span class="sort-label">Sort by</span>
          <div class="filter-dropdown" data-filter-type="sort">
            <button type="button" id="sortSelectBtn" class="sort-select-btn" aria-expanded="false"
              aria-haspopup="listbox">
              <span class="sort-btn-text">MOST POPULAR</span>
              <svg class="sort-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true">
                <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" />
              </svg>
            </button>
            <ul class="filter-dropdown-menu" role="listbox">
              <li class="dropdown-item active" role="option" aria-selected="true" data-value="popular">Most Popular</li>
              <li class="dropdown-item" role="option" aria-selected="false" data-value="price-low">Price: Low to High
              </li>
              <li class="dropdown-item" role="option" aria-selected="false" data-value="price-high">Price: High to Low
              </li>
              <li class="dropdown-item" role="option" aria-selected="false" data-value="validity">Validity: Longest</li>
              <li class="dropdown-item" role="option" aria-selected="false" data-value="data">Data: Largest</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Bottom Row: Filter Pills with Dropdown Menus -->
      <div class="filter-pills-row" role="toolbar" aria-label="Filter Options">

        <!-- 1: Popular Destination -->
        <div class="filter-dropdown" data-filter-type="destination">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">POPULAR DESTINATION</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">All Destinations</li>
@if ($catalogueLive ?? false)
@foreach ($catalogueCards as $card)
            <li class="dropdown-item" role="option" aria-selected="false" data-value="{{ $card['country']->name }}">{{ $card['country']->name }}</li>
@endforeach
@else
            <li class="dropdown-item" role="option" aria-selected="false" data-value="Japan">Japan</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="USA">United States</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="Thailand">Thailand</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="European Union">European Union
            </li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="UAE">United Arab Emirates</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="China">China</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="Brazil">Brazil</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="Australia">Australia</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="Egypt">Egypt</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="New Zealand">New Zealand</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="Norway">Norway</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="France">France</li>
@endif
          </ul>
        </div>

        <!-- 2: Coverage Type -->
        <div class="filter-dropdown" data-filter-type="coverage">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">COVERAGE TYPE</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">All Coverage Types</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="local">Local eSIM</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="regional">Regional eSIM</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="global">Global eSIM</li>
          </ul>
        </div>

        <!-- 3: Data -->
        <div class="filter-dropdown" data-filter-type="data">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">DATA</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">All Data Plans</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="1-5gb">1 GB - 5 GB</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="10gb">10 GB</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="20gb">20 GB</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="50gb">50 GB+</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="unlimited">Unlimited Data</li>
          </ul>
        </div>

        <!-- 4: Validity -->
        <div class="filter-dropdown" data-filter-type="validity">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">VALIDITY</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">Any Validity</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="7days">7 Days</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="15days">15 Days</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="30days">30 Days</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="60days">60 Days+</li>
          </ul>
        </div>

        <!-- 5: Price -->
        <div class="filter-dropdown" data-filter-type="price">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">PRICE</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">All Prices</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="under10">Under $10</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="10-20">$10 - $20</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="20-35">$20 - $35</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="over35">$35 & Above</li>
          </ul>
        </div>

        <!-- 6: Network Speed -->
        <div class="filter-dropdown" data-filter-type="speed">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">NETWORK SPEED</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">All Speeds</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="5g">5G Ultra Fast</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="4g">4G / LTE</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="3g-4g">3G / 4G</li>
          </ul>
        </div>

        <!-- 7: Features -->
        <div class="filter-dropdown" data-filter-type="features">
          <button type="button" class="filter-pill" aria-expanded="false" aria-haspopup="listbox">
            <span class="pill-label">FEATURES</span>
            <svg class="pill-caret" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true">
              <path d="M1 1L5 5L9 1" stroke="#373737" stroke-width="1.4" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <ul class="filter-dropdown-menu" role="listbox">
            <li class="dropdown-item active" role="option" aria-selected="true" data-value="all">All Features</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="hotspot">Hotspot / Tethering</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="data-only">Data Only</li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="voice-sms">Voice & SMS Included
            </li>
            <li class="dropdown-item" role="option" aria-selected="false" data-value="instant">Instant Activation</li>
          </ul>
        </div>

      </div>
    </section>

    <!-- ==========================================================================
         eSIM Catalog Grid (Figma Node 159:2270 - 12 Product Cards)
         ========================================================================== -->
    <section class="esim-catalog-section" aria-label="eSIM Plans Catalog">
      <div class="esim-catalog-grid">
@if ($catalogueLive ?? false)
@include('catalogue.partials.offer-cards', ['cards' => $catalogueCards])
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
            </div>
          </div>
        </article>

        <!-- Card 3: Thailand -->
        <article class="offer-card" data-category="popular" data-country="Thailand">
          <div class="offer-card-media">
            <img src="{{ asset('assets/images/card-thailand.webp') }}" alt="Thailand island scenery" class="offer-photo"
              loading="lazy">
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
            </div>
          </div>
        </article>

        <!-- Card 10: New Zealand -->
        <article class="offer-card" data-category="best-seller" data-country="New Zealand">
          <div class="offer-card-media">
            <img src="{{ asset('assets/images/card-newzealand.webp') }}" alt="New Zealand fjord scenery" class="offer-photo"
              loading="lazy">
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
            </div>
          </div>
        </article>

        <!-- Card 11: Norway -->
        <article class="offer-card" data-category="popular" data-country="Norway">
          <div class="offer-card-media">
            <img src="{{ asset('assets/images/card-norway.webp') }}" alt="Norway fjord scenery" class="offer-photo" loading="lazy">
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
            </div>
          </div>
        </article>

        <!-- Card 12: France / Paris -->
        <article class="offer-card" data-category="popular" data-country="France">
          <div class="offer-card-media">
            <img src="{{ asset('assets/images/card-france.webp') }}" alt="Paris France Eiffel Tower" class="offer-photo"
              loading="lazy">
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
              <a href="#catalog" class="btn btn-outline">View Details</a>
              <a href="#catalog" class="btn btn-primary">Buy Now</a>
            </div>
          </div>
        </article>

@endif
      </div>
    </section>

    <!-- ==========================================================================
         Compare Popular Plans (Figma Node 130:75)
         ========================================================================== -->
    <section class="comparison-section" aria-labelledby="comparisonTitle">
      <div class="comparison-header">
        <h2 id="comparisonTitle" class="comparison-title">Compare Popular Plans</h2>
        <a href="#catalog" class="comparison-link">View all comparison</a>
      </div>

      <div class="comparison-table-wrapper">
        <div class="comparison-table-scroll">
          <table class="comparison-table">
            <thead>
              <tr>
                <th class="col-label">Plans</th>
                <th>
                  <span class="comparison-plan-name">Japan</span>
                  <span class="comparison-plan-sub">Local eSIM</span>
                </th>
                <th>
                  <span class="comparison-plan-name">USA</span>
                  <span class="comparison-plan-sub">Local eSIM</span>
                </th>
                <th>
                  <span class="comparison-plan-name">Thailand</span>
                  <span class="comparison-plan-sub">Local eSIM</span>
                </th>
                <th>
                  <span class="comparison-plan-name">Europe</span>
                  <span class="comparison-plan-sub">Local eSIM</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="col-label">Data</td>
                <td>10 GB</td>
                <td>20 GB</td>
                <td>10 GB</td>
                <td>50 GB</td>
              </tr>
              <tr>
                <td class="col-label">Validity</td>
                <td>30 Days</td>
                <td>30 Days</td>
                <td>15 Days</td>
                <td>30 Days</td>
              </tr>
              <tr>
                <td class="col-label">Speed</td>
                <td>5G</td>
                <td>5G</td>
                <td>4G/5G</td>
                <td>5G</td>
              </tr>
              <tr>
                <td class="col-label">Price</td>
                <td>$18 USD</td>
                <td>$28 USD</td>
                <td>$12 USD</td>
                <td>$35 USD</td>
              </tr>
              <tr class="row-action">
                <td class="col-label">&nbsp;</td>
                <td>
                  <button type="button" class="btn-table-buy">Buy Now</button>
                </td>
                <td>
                  <button type="button" class="btn-table-buy">Buy Now</button>
                </td>
                <td>
                  <button type="button" class="btn-table-buy">Buy Now</button>
                </td>
                <td>
                  <button type="button" class="btn-table-buy">Buy Now</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Best Sellers Strip (Figma Node 130:77)
         ========================================================================== -->
    <section class="bestsellers-section" aria-labelledby="bestsellersTitle">
      <div class="bestsellers-header">
        <h2 id="bestsellersTitle" class="bestsellers-title">Best Sellers</h2>
      </div>

      <div class="bestsellers-slider" id="bestsellersSlider" data-lenis-prevent role="region" aria-label="Best Sellers Slider">
        <!-- 1: Japan -->
        <a href="{{ route('japan-esim') }}" class="bestseller-card" style="text-decoration: none; color: inherit;">
          <img src="{{ asset('assets/images/bestseller-japan.webp') }}" alt="Japan eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">Japan eSIM</h4>
            <span class="bestseller-price">From $18</span>
          </div>
        </a>

        <!-- 2: USA -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/bestseller-usa.webp') }}" alt="USA eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">USA eSIM</h4>
            <span class="bestseller-price">From $28</span>
          </div>
        </div>

        <!-- 3: Thailand -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/bestseller-thailand.webp') }}" alt="Thailand eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">Thailand eSIM</h4>
            <span class="bestseller-price">From $12</span>
          </div>
        </div>

        <!-- 4: UAE -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/bestseller-uae.webp') }}" alt="UAE eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">UAE eSIM</h4>
            <span class="bestseller-price">From $22</span>
          </div>
        </div>

        <!-- 5: Europe -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/bestseller-europe.webp') }}" alt="Europe eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">Europe</h4>
            <span class="bestseller-price">From $36</span>
          </div>
        </div>

        <!-- 6: France -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/card-france.webp') }}" alt="France eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">France eSIM</h4>
            <span class="bestseller-price">From $19</span>
          </div>
        </div>

        <!-- 7: Australia -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/card-australia.webp') }}" alt="Australia eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">Australia eSIM</h4>
            <span class="bestseller-price">From $21</span>
          </div>
        </div>

        <!-- 8: China -->
        <div class="bestseller-card">
          <img src="{{ asset('assets/images/card-china.webp') }}" alt="China eSIM" class="bestseller-thumb">
          <div class="bestseller-info">
            <h4 class="bestseller-name">China eSIM</h4>
            <span class="bestseller-price">From $24</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Testimonials ("Trusted by Travelers Worldwide")
         (Repeating from index.html)
         ========================================================================== -->
    <section class="section testimonials-section" id="testimonials">
      <div class="container">
        <div class="section-header-block">
          <h2 class="section-heading">Trusted by Travelers<br>Worldwide</h2>
          <p class="section-description">
            See why thousands of travelers choose our eSIM for fast activation, reliable connectivity, and seamless
            global coverage.
          </p>
        </div>
      </div>

      <div class="testimonials-track-container">
        <!-- Row 1 (Slides Left) -->
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made
                traveling so much easier."
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
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my
                next trip."
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
          </div>

          <!-- Duplicate Group for Continuous Seamless Loop -->
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
                "Activation took less than two minutes, and the connection was reliable throughout my trip. It made
                traveling so much easier."
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
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my
                next trip."
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
          </div>
        </div>

        <!-- Row 2 (Slides Right) -->
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
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my
                next trip."
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
          </div>

          <!-- Duplicate Group for Continuous Seamless Loop -->
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
                  <p>Sales Head</p>
                </div>
              </div>
              <p class="testimonial-quote-text">
                "The setup was simple, and the data speeds were impressive. I'll definitely use this eSIM again for my
                next trip."
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
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         CTA / Questions Section ("Have Questions? We're Here for You")
         (Repeating from index.html & questions-section.css)
         ========================================================================== -->
    <section class="questions-section" id="support">
      <div class="questions-container">
        <h2 class="questions-heading">
          Have Questions?<br>We’re Here for You
        </h2>
        <p class="questions-description">
          Let us know what challenges you are<br>trying to solve so we can help.
        </p>
        <a href="#support" class="questions-btn">
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
  <script src="{{ asset('js/new-esim.js') }}"></script>
@endpush
