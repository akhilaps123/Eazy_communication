@extends('layouts.app')

@section('title', 'Blog - Eazy Communications')
@section('meta_description', 'Stay informed with the latest eSIM guides, international travel tips, destination advice, and mobile connectivity insights from Eazy Communications.')

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
  <link rel="stylesheet" href="{{ asset('css/blog.css') }}">
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
         Hero Banner (Alpine Landscape - Figma Node 190:1540)
         ========================================================================== -->
    <section class="page-banner" aria-label="Blog Banner">
      <img src="{{ asset('assets/images/blog-banner.webp') }}" alt="Alpine mountain peaks with hiker" class="page-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">Blog</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current">BLOG</span>
      </div>
    </nav>

    <!-- ==========================================================================
         Top Featured Articles Section (Figma Node 190:1578)
         ========================================================================== -->
    <section class="blog-featured-section" aria-label="Featured Articles">
      <div class="blog-page-container">
        <div class="blog-featured-grid">
          <!-- Main Hero Article (Left Column) -->
          <article class="blog-hero-article">
            <a href="{{ route('blog-detail') }}" class="blog-hero-img-wrap" aria-label="Read full article: Grand Prix Hopping">
              <img src="{{ asset('assets/images/blog-featured-f1.webp') }}" alt="Formula 1 race car racing around a high-speed circuit" class="blog-hero-img">
            </a>
            <div class="blog-hero-content">
              <p class="blog-category-tag">Grand Prix FI Races</p>
              <h2 class="blog-hero-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Grand Prix Hopping: The Ultimate Travel Guide to the Late-Summer F1 Races
                </a>
              </h2>
              <p class="blog-hero-excerpt">
                Four Grand Prix weekends, three countries, one continent-spanning stretch of racing: late summer 2026 turns into a genuine circuit-hopping season for F1 fans.
              </p>
              <div class="blog-meta-info">
                <span class="blog-meta-author">Cameron Williamson</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">8 min read</span>
              </div>
            </div>
          </article>

          <!-- 3 Stacked Side Articles (Right Column) -->
          <aside class="blog-side-articles" aria-label="More Featured Articles">
            <!-- Side Article 1 -->
            <article class="blog-side-item">
              <a href="#article-shoulder-season" class="blog-side-img-wrap" aria-label="Read full article: Top 20 European Shoulder-Season Destinations">
                <img src="{{ asset('assets/images/blog-side-europe.webp') }}" alt="Charming European cobblestone town street" class="blog-side-img">
              </a>
              <div class="blog-side-content">
                <p class="blog-category-tag">September Travel Guide</p>
                <h3 class="blog-side-title">
                  <a href="#article-shoulder-season" style="text-decoration: none; color: inherit;">
                    Top 20 European Shoulder-Season Destinations for September 2026
                  </a>
                </h3>
                <div class="blog-meta-info">
                  <span class="blog-meta-author">Jenny Wilson</span>
                  <span class="blog-meta-divider" aria-hidden="true"></span>
                  <span class="blog-meta-time">4 min read</span>
                </div>
              </div>
            </article>

            <!-- Side Article 2 -->
            <article class="blog-side-item">
              <a href="#article-budget-travel" class="blog-side-img-wrap" aria-label="Read full article: Best Budget-Friendly Travel Destinations">
                <img src="{{ asset('assets/images/blog-side-budget.webp') }}" alt="Scenic coastal highway with turquoise waters" class="blog-side-img">
              </a>
              <div class="blog-side-content">
                <p class="blog-category-tag">Budget Travel</p>
                <h3 class="blog-side-title">
                  <a href="#article-budget-travel" style="text-decoration: none; color: inherit;">
                    The 12 Best Budget-Friendly Travel Destinations with Data Under $1/GB
                  </a>
                </h3>
                <div class="blog-meta-info">
                  <span class="blog-meta-author">Savannah Nguyen</span>
                  <span class="blog-meta-divider" aria-hidden="true"></span>
                  <span class="blog-meta-time">2 min read</span>
                </div>
              </div>
            </article>

            <!-- Side Article 3 -->
            <article class="blog-side-item">
              <a href="#article-japan-korea" class="blog-side-img-wrap" aria-label="Read full article: 14-Day Japan and South Korea Itinerary">
                <img src="{{ asset('assets/images/blog-side-japan.webp') }}" alt="Vibrant neon lights of night street in Tokyo" class="blog-side-img">
              </a>
              <div class="blog-side-content">
                <p class="blog-category-tag">Japan Offer</p>
                <h3 class="blog-side-title">
                  <a href="#article-japan-korea" style="text-decoration: none; color: inherit;">
                    The Ultimate 14-Day Japan and South Korea Itinerary for 2026
                  </a>
                </h3>
                <div class="blog-meta-info">
                  <span class="blog-meta-author">Savannah Nguyen</span>
                  <span class="blog-meta-divider" aria-hidden="true"></span>
                  <span class="blog-meta-time">2 min read</span>
                </div>
              </div>
            </article>
          </aside>
        </div>
      </div>
    </section>

    <!-- ==========================================================================
         Latest Articles Section (3x3 Grid - Figma Node 190:1753)
         ========================================================================== -->
    <section class="blog-latest-section" aria-label="Latest Articles">
      <div class="blog-page-container">
        <!-- Section Header -->
        <div class="blog-latest-header">
          <h2 class="blog-latest-title">Latest Articles</h2>
          <p class="blog-latest-subtitle">
            Amet quam tellus tempor mauris tristique et volutpat egestas tellus. Dictum tellus eget in diam eu vulputate massa nulla.
          </p>
        </div>

        <!-- 3x3 Articles Grid -->
        <div class="blog-articles-grid">
          <!-- Card 1 -->
          <article class="blog-article-card">
            <a href="#article-1" class="blog-card-img-wrap" aria-label="Which Phone Devices Support eSIM?">
              <img src="{{ asset('assets/images/blog-article-devices.webp') }}" alt="Person holding and configuring modern smartphone" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">BLOG</p>
              <h3 class="blog-card-title">
                <a href="#article-1" style="text-decoration: none; color: inherit;">
                  Which Phone Devices Support eSIM? - A Detailed List of eSIM...
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 2 -->
          <article class="blog-article-card">
            <a href="#article-2" class="blog-card-img-wrap" aria-label="15 Late-Summer Hidden Gems">
              <img src="{{ asset('assets/images/blog-article-august.webp') }}" alt="Vintage map with travel itinerary markers" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">August Travel</p>
              <h3 class="blog-card-title">
                <a href="#article-2" style="text-decoration: none; color: inherit;">
                  15 Late-Summer Hidden Gems: Best Places to Visit in August 2026
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 3 -->
          <article class="blog-article-card">
            <a href="#article-3" class="blog-card-img-wrap" aria-label="Does a UK eSIM Work in Ireland?">
              <img src="{{ asset('assets/images/blog-article-uk.webp') }}" alt="London Big Ben and Westminster Bridge" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">eSIM Borders</p>
              <h3 class="blog-card-title">
                <a href="#article-3" style="text-decoration: none; color: inherit;">
                  Does a UK eSIM Work in Ireland? The Ultimate Guide to Confusing eSIM...
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 4 -->
          <article class="blog-article-card">
            <a href="#article-4" class="blog-card-img-wrap" aria-label="Tomorrowland Belgium 2026 Survival Guide">
              <img src="{{ asset('assets/images/blog-article-tomorrowland.webp') }}" alt="Tomorrowland festival concert stage fireworks" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">Tomorrowland Special offer</p>
              <h3 class="blog-card-title">
                <a href="#article-4" style="text-decoration: none; color: inherit;">
                  Tomorrowland Belgium 2026 Survival Guide: Set Times, DreamVille Hacks...
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 5 -->
          <article class="blog-article-card">
            <a href="#article-5" class="blog-card-img-wrap" aria-label="15 Secret Beaches in Southeast Asia">
              <img src="{{ asset('assets/images/blog-article-beaches.webp') }}" alt="Tropical white sand beach with turquoise waves" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">BLOG</p>
              <h3 class="blog-card-title">
                <a href="#article-5" style="text-decoration: none; color: inherit;">
                  15 Secret Beaches in Southeast Asia Worth Chasing in 2026
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 6 -->
          <article class="blog-article-card">
            <a href="#article-6" class="blog-card-img-wrap" aria-label="The Grand Europe Trip">
              <img src="{{ asset('assets/images/blog-article-europe.webp') }}" alt="Hallstatt lakeside alpine village in Austria" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">Europe Summer Travel</p>
              <h3 class="blog-card-title">
                <a href="#article-6" style="text-decoration: none; color: inherit;">
                  The Grand Europe Trip: 20 Multi-Country Circuits for a 4-Week ..
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 7 -->
          <article class="blog-article-card">
            <a href="#article-7" class="blog-card-img-wrap" aria-label="Wimbledon 2026 Connectivity Guide">
              <img src="{{ asset('assets/images/blog-article-wimbledon.webp') }}" alt="Wimbledon tennis tournament stadium court" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">Wimbledon 2026</p>
              <h3 class="blog-card-title">
                <a href="#article-7" style="text-decoration: none; color: inherit;">
                  Wimbledon 2026: Your Ultimate Guide to Staying Connected in...
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 8 -->
          <article class="blog-article-card">
            <a href="#article-8" class="blog-card-img-wrap" aria-label="Top 25 Places to Visit in China">
              <img src="{{ asset('assets/images/blog-article-china.webp') }}" alt="Shanghai modern skyline illuminated at night" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">China offer</p>
              <h3 class="blog-card-title">
                <a href="#article-8" style="text-decoration: none; color: inherit;">
                  Top 25 Places to Visit in China: Your Ultimate 2026 Travel Guide
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Card 9 -->
          <article class="blog-article-card">
            <a href="#article-9" class="blog-card-img-wrap" aria-label="Top 20 Cool Destinations for Summer 2026">
              <img src="{{ asset('assets/images/blog-article-cool.webp') }}" alt="Wooden directional signpost with European country names" class="blog-card-img">
            </a>
            <div class="blog-card-content">
              <p class="blog-card-category">BLOG</p>
              <h3 class="blog-card-title">
                <a href="#article-9" style="text-decoration: none; color: inherit;">
                  Top 20 Cool Destinations for Summer 2026: Best Global "Coolcations" to...
                </a>
              </h3>
              <p class="blog-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="blog-card-meta">
                <span class="blog-meta-author">Savannah Nguyen</span>
                <span class="blog-meta-divider" aria-hidden="true"></span>
                <span class="blog-meta-time">2 min read</span>
              </div>
            </div>
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
