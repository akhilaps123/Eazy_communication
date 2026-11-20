@extends('layouts.app')

@section('title', 'My Account - Eazy Communications')
@section('meta_description', 'Manage your active eSIMs, view orders, check rewards, change currency and language preferences on Eazy Communications.')

@push('head')
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=Questrial&display=swap" rel="stylesheet">

  <!-- Core Global Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('css/questions-section.css') }}">

  <!-- Page-Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/my-account.css') }}">
  <link rel="stylesheet" href="{{ asset('css/customer-portal.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header :account-active="true" :mobile-links="[
      ['label' => 'Blog', 'route' => 'blog'],
      ['label' => 'Guides', 'route' => 'guides'],
      ['label' => 'About', 'route' => 'about'],
      ['label' => 'My Account', 'route' => 'my-account', 'active' => true],
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main class="account-page-main">
    <!-- ==========================================================================
         Hero Banner (Figma Node 253:196)
         ========================================================================== -->
    <section class="account-banner" aria-label="My Account Banner">
      <img src="{{ asset('assets/images/my-account-banner.webp') }}" alt="Suitcase on asphalt highway in scenic rolling hills" class="account-banner-img">
      <div class="account-banner-overlay" aria-hidden="true"></div>
      <div class="account-banner-content">
        <h1 class="account-banner-title">My Account</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Figma Node 253:194)
         ========================================================================== -->
    <nav class="account-breadcrumbs" aria-label="Breadcrumb">
      <div class="account-breadcrumbs-inner">
        <div class="account-breadcrumbs-list">
          <a href="{{ route('home') }}">HOME</a>
          <span class="account-breadcrumbs-sep" aria-hidden="true">/</span>
          <span class="account-breadcrumbs-current">MY ACCOUNT</span>
        </div>
      </div>
    </nav>

    <!-- ==========================================================================
         Main Account Content Section (Figma Node 255:793)
         ========================================================================== -->
    <section class="account-section" aria-label="Account Settings Dashboard">
      <div class="account-container">
        {{-- Phase 9E: account tabs --}}
        <x-account-nav active="account" />

        <!-- Group 1: MY ACCOUNT -->
        <div class="account-group" data-node-id="255:792">
          <h2 class="account-group-title">MY ACCOUNT</h2>
          <div class="account-card-group">
            <!-- Row 1: My eSIMs -->
            <a href="{{ route('my-esims') }}" class="account-row" aria-label="My eSIMs: View and manage your active eSIMs">
              <div class="account-row-left">
                <div class="account-badge badge-sim">
                  <img src="{{ asset('assets/icons/acc-sim.svg') }}" alt="SIM Card" width="34" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">My eSIMs</h3>
                  <p class="account-subtitle">View and manage your active eSIMs</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>

            <!-- Row 2: Achieved eSIMs -->
            <a href="#" class="account-row" aria-label="Achieved eSIMs: View your achieved eSIMs">
              <div class="account-row-left">
                <div class="account-badge badge-archive">
                  <img src="{{ asset('assets/icons/acc-archive.svg') }}" alt="Archive Box" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Achieved eSIMs</h3>
                  <p class="account-subtitle">View your achieved eSIMs</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>

            <!-- Row 3: Rewards -->
            <a href="#" class="account-row" aria-label="Rewards: Earn and redeem rewards">
              <div class="account-row-left">
                <div class="account-badge badge-rewards">
                  <img src="{{ asset('assets/icons/acc-rewards.svg') }}" alt="Gift Rewards" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Rewards</h3>
                  <p class="account-subtitle">Earn and redeem rewards</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-value">$0.00</span>
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>

            <!-- Row 4: Order History -->
            <a href="{{ route('my-orders') }}" class="account-row" aria-label="My Orders: View your purchase history">
              <div class="account-row-left">
                <div class="account-badge badge-cart">
                  <img src="{{ asset('assets/icons/acc-cart.svg') }}" alt="Shopping Cart" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">My Orders</h3>
                  <p class="account-subtitle">View your purchase history</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>

            <!-- Row 5: Saved Payment Cards -->
            <a href="#" class="account-row" aria-label="Saved Payment Cards: Manage your saved cards">
              <div class="account-row-left">
                <div class="account-badge badge-cards">
                  <img src="{{ asset('assets/icons/acc-card.svg') }}" alt="Credit Card" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Saved Payment Cards</h3>
                  <p class="account-subtitle">Manage your saved cards</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>
          </div>
        </div>

        <!-- Group 2: SETTINGS -->
        <div class="account-group" data-node-id="254:528">
          <h2 class="account-group-title">SETTINGS</h2>
          <div class="account-card-group">
            <!-- Row 1: Currency -->
            <div class="account-row" aria-label="Currency: Choose your preferred Currency">
              <div class="account-row-left">
                <div class="account-badge badge-currency">
                  <img src="{{ asset('assets/icons/acc-currency.svg') }}" alt="Currency Exchange" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Currency</h3>
                  <p class="account-subtitle">Choose your preferred Currency</p>
                </div>
              </div>
              <div class="account-row-right">
                <!-- Interactive Currency Selector Pill -->
                <div class="account-dropdown-wrapper" id="currencyDropdown">
                  <button type="button" class="account-pill-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Select Currency">
                    <div class="account-pill-left">
                      <div class="account-pill-icon">
                        <img src="{{ asset('assets/icons/acc-flag-us.svg') }}" alt="US Flag" width="50" height="50">
                      </div>
                      <span class="account-pill-label">USD</span>
                    </div>
                    <span class="account-pill-arrow" aria-hidden="true">
                      <img src="{{ asset('assets/icons/acc-chevron-down.svg') }}" alt="" width="20" height="10">
                    </span>
                  </button>
                  <ul class="account-dropdown-menu" role="listbox">
                    <li class="account-dropdown-item active" role="option" aria-selected="true" data-value="USD">
                      <span>USD ($)</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="EUR">
                      <span>EUR (€)</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="GBP">
                      <span>GBP (£)</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="JPY">
                      <span>JPY (¥)</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="CAD">
                      <span>CAD ($)</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Row 2: Language -->
            <div class="account-row" aria-label="Language: Choose your preferred languages">
              <div class="account-row-left">
                <div class="account-badge badge-language">
                  <img src="{{ asset('assets/icons/acc-language.svg') }}" alt="Languages" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Language</h3>
                  <p class="account-subtitle">Choose your preferred languages</p>
                </div>
              </div>
              <div class="account-row-right">
                <!-- Interactive Language Selector Pill -->
                <div class="account-dropdown-wrapper" id="languageDropdown">
                  <button type="button" class="account-pill-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Select Language">
                    <div class="account-pill-left">
                      <div class="account-pill-icon account-pill-icon-lang">
                        <img src="{{ asset('assets/icons/acc-language-pill.svg') }}" alt="Translation" width="44" height="44">
                      </div>
                      <span class="account-pill-label">English</span>
                    </div>
                    <span class="account-pill-arrow" aria-hidden="true">
                      <img src="{{ asset('assets/icons/acc-chevron-down.svg') }}" alt="" width="20" height="10">
                    </span>
                  </button>
                  <ul class="account-dropdown-menu" role="listbox">
                    <li class="account-dropdown-item active" role="option" aria-selected="true" data-value="English">
                      <span>English</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="Español">
                      <span>Español</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="Français">
                      <span>Français</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="Deutsch">
                      <span>Deutsch</span>
                    </li>
                    <li class="account-dropdown-item" role="option" aria-selected="false" data-value="日本語">
                      <span>日本語</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Row 3: Marketing Communications -->
            <a href="#" class="account-row" aria-label="Marketing Communications: manage how we communicate with you">
              <div class="account-row-left">
                <div class="account-badge badge-bell">
                  <img src="{{ asset('assets/icons/acc-bell.svg') }}" alt="Notification Bell" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Marketing Communications</h3>
                  <p class="account-subtitle">manage how we communicate with you</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>
          </div>
        </div>

        <!-- Group 3: INFO -->
        <div class="account-group" data-node-id="254:578">
          <h2 class="account-group-title">INFO</h2>
          <div class="account-card-group">
            <!-- Row 1: eSIM Support -->
            <a href="{{ route('esim-support') }}" class="account-row" aria-label="eSIM Support: Get help with your eSIM">
              <div class="account-row-left">
                <div class="account-badge badge-support">
                  <img src="{{ asset('assets/icons/acc-support.svg') }}" alt="Help Support" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">eSIM Support</h3>
                  <p class="account-subtitle">Get help with your eSIM</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>

            <!-- Row 2: Help Center -->
            <a href="{{ route('guides') }}" class="account-row" aria-label="Help Center: Browse articles and FAQs">
              <div class="account-row-left">
                <div class="account-badge badge-help">
                  <img src="{{ asset('assets/icons/acc-help.svg') }}" alt="Information" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Help Center</h3>
                  <p class="account-subtitle">Browse articles and FAQs</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>
          </div>
        </div>

        <!-- Group 4: ACCOUNT SETTINGS -->
        <div class="account-group" data-node-id="254:580">
          <h2 class="account-group-title">ACCOUNT SETTINGS</h2>
          <div class="account-settings-list">
            <!-- Card 1: Logout -->
            {{-- href must not start with "#": Lenis (anchors: true in main.js) would try to scroll to it. --}}
            <a href="{{ route('my-account') }}" class="account-card-single" data-logout role="button" aria-label="Logout: Sign out from your account">
              <div class="account-row-left">
                <div class="account-badge badge-logout">
                  <img src="{{ asset('assets/icons/acc-logout.svg') }}" alt="Logout Door" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Logout</h3>
                  <p class="account-subtitle">Sign out from your account</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-right.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>

            <!-- Card 2: Delete Your Account -->
            <a href="#delete-account" class="account-card-single account-card-delete" aria-label="Delete Your Account: Permanently delete your account and all data">
              <div class="account-row-left">
                <div class="account-badge badge-trash">
                  <img src="{{ asset('assets/icons/acc-trash.svg') }}" alt="Delete Trash Can" width="45" height="45">
                </div>
                <div class="account-text">
                  <h3 class="account-title">Delete Your Account</h3>
                  <p class="account-subtitle">Permanently delete your account and all data</p>
                </div>
              </div>
              <div class="account-row-right">
                <span class="account-chevron" aria-hidden="true">
                  <img src="{{ asset('assets/icons/acc-chevron-red.svg') }}" alt="" width="20" height="10">
                </span>
              </div>
            </a>
          </div>
        </div>

      </div>
    </section>

    <!-- ==========================================================================
         Have Questions? Section (Global Reusable Component)
         ========================================================================== -->
    <section class="questions-section" aria-labelledby="account-questions-title">
      <div class="questions-container">
        <h2 id="account-questions-title" class="questions-heading">
          Have Questions?<br>We’re Here for You
        </h2>
        <p class="questions-description">
          Let us know what challenges you are<br>trying to solve so we can help.
        </p>
        <a href="#contact" class="questions-btn">Contact us</a>
      </div>
    </section>
  </main>

  {{-- Logout must be a POST with a CSRF token; the "Logout" card submits this form (js/my-account.js). --}}
  <form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden>
    @csrf
  </form>

  <!-- ==========================================================================
       Site Footer (Global Shared Component)
       ========================================================================== -->
  <x-footer variant="alt" />
@endsection

@push('scripts')
  <script src="{{ asset('js/my-account.js') }}"></script>
@endpush
