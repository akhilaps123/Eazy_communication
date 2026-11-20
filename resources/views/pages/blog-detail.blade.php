@extends('layouts.app')

@section('title', 'Grand Prix Hopping: The Ultimate Travel Guide to the Late-Summer F1 Races - Eazy Communications')
@section('meta_description', 'Four Grand Prix weekends, three countries, one continent-spanning stretch of racing: late summer 2026 turns into a genuine circuit-hopping season for F1 fans.')

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
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">

  <!-- Page-Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/blog-detail.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header :mobile-links="[
      ['label' => 'Blog', 'route' => 'blog', 'active' => true],
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main>
    <!-- ==========================================================================
         Banner Section (Figma Node 190:1841)
         ========================================================================== -->
    <section class="page-banner" aria-label="Blog Detail Banner">
        <img src="{{ asset('assets/images/blog-detail-banner.webp') }}" alt="City skyline at night" class="page-banner-img blog-detail-banner-img">
        <div class="page-banner-overlay" aria-hidden="true"></div>
        <div class="page-banner-content">
            <h1 class="page-banner-title">Blog Detail</h1>
        </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <a href="{{ route('blog') }}">BLOG</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current">GRAND PRIX HOPPING...</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Article Header & Large Hero Image Section (Figma Frame 193:2055)
         ========================================================================== -->
    <section class="blog-detail-hero-section" aria-label="Article Header">
      <div class="blog-detail-container">
        <!-- Title and Metadata -->
        <div class="blog-detail-header">
          <h2 class="blog-detail-title">
            Grand Prix Hopping: The Ultimate Travel Guide to the Late-Summer F1 Races
          </h2>
          <div class="blog-detail-meta">
            <span class="blog-detail-author">Cameron Williamson</span>
            <span class="blog-detail-divider" aria-hidden="true"></span>
            <span class="blog-detail-read-time">8 min read</span>
          </div>
        </div>

        <!-- Large Hero Image -->
        <div class="blog-detail-main-img-wrap">
          <img src="{{ asset('assets/images/blog-featured-f1.webp') }}" alt="Formula 1 car driving at high speed around a race circuit" class="blog-detail-main-img">
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Article Body Content Section (Figma Node 190:2027)
         ========================================================================== -->
    <section class="blog-detail-content-section" aria-label="Article Content">
      <div class="blog-detail-container">
        <div class="blog-detail-body-wrapper">
          <!-- Section 1: Dutch GP -->
          <article class="blog-detail-section-block">
            <h3 class="blog-detail-subheading">
              Dutch GP (August 21–23): Transit from Amsterdam to Zandvoort
            </h3>
            <p class="blog-detail-paragraph">
              Book your train ticket before you book anything else. Zandvoort bans cars and motorcycles from the town during race weekend, so the Dutch Railways (NS) train from Amsterdam Centraal is effectively the only practical route in, running every five minutes at peak times and taking about 30 minutes. NS has confirmed extended timetables and over 300 extra staff per day to manage an expected 50,000 passengers daily for this farewell edition, the last Dutch GP scheduled at the circuit.
            </p>
          </article>

          <hr class="blog-detail-separator" aria-hidden="true">

          <!-- Section 2: Italian GP -->
          <article class="blog-detail-section-block">
            <h3 class="blog-detail-subheading">
              Italian GP (September 4–6): Staying in Milan vs. Monza
            </h3>
            <div class="blog-detail-inline-img-wrap">
              <img src="{{ asset('assets/images/blog-detail-monza.webp') }}" alt="Mercedes Formula 1 race car performing a smoking burnout in front of grandstand spectators" class="blog-detail-inline-img">
            </div>
            <p class="blog-detail-paragraph">
              Autodromo Nazionale Monza sits inside the Royal Park of Monza, about 30 kilometers north of central Milan, and almost every visiting fan chooses to base themselves in Milan rather than in Monza town itself. Most international flights land at Milan Malpensa or Linate, and Milan simply has more hotel inventory, better restaurants, and easier onward transit than the smaller town beside the circuit.
            </p>
          </article>

          <hr class="blog-detail-separator" aria-hidden="true">

          <!-- Section 3: Azerbaijan GP -->
          <article class="blog-detail-section-block">
            <h3 class="blog-detail-subheading">
              Azerbaijan GP (September 23–26): Combining Baku City Tours with Race Weekend
            </h3>
            <p class="blog-detail-paragraph">
              Baku closes out this four-race run with a street circuit weekend that runs Wednesday September 23 through Saturday September 26, 2026. For the first time in the event's history, race day falls on a Saturday rather than Sunday, shifted to avoid clashing with an Azerbaijan National Day of Mourning.
            </p>
            <p class="blog-detail-list-title">
              Planning your Baku trip:
            </p>
            <ul class="blog-detail-list">
              <li>
                Entry requirements: Most passport holders, including UK, US, Australian, and EU citizens, can apply for a simple e-Visa online for around $20 plus a small processing fee, typically processed within three working days for stays up to 30 days
              </li>
              <li>
                Circuit: Baku City Circuit is a 6.003 km street track along the Caspian shoreline, the fourth-longest circuit on the calendar
              </li>
              <li>
                Getting around: Heydar Aliyev International Airport serves as the main hub; the circuit and old town are both walkable from central Baku hotels
              </li>
              <li>
                Beyond the race: The UNESCO-listed Old City, the Flame Towers, and the Heydar Aliyev Center (designed by Zaha Hadid) are all realistic half-day additions around race sessions
              </li>
            </ul>
          </article>
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
       Reusable Footer Component (Standard Across All Pages)
       ========================================================================== -->
  <x-footer />
@endsection
