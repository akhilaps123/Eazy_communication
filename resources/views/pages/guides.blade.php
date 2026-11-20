@extends('layouts.app')

@section('title', 'Guides - Eazy Communications')
@section('meta_description', 'Explore comprehensive eSIM travel guides, destination tips, setup instructions, and mobile connectivity insights from Eazy Communications.')

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
  <link rel="stylesheet" href="{{ asset('css/guides.css') }}">
@endpush

@section('content')
  <!-- ==========================================================================
       Site Header (Global Shared Component)
       ========================================================================== -->
  <x-header :mobile-links="[
      ['label' => 'Blog', 'route' => 'blog'],
      ['label' => 'Guides', 'route' => 'guides', 'active' => true],
      ['label' => 'Shopping Cart', 'route' => 'cart'],
  ]" />

  <main>
    <!-- ==========================================================================
         Hero Banner (Alpine Mountain Landscape - Figma Node 204:949)
         ========================================================================== -->
    <section class="page-banner" aria-label="Guides Banner">
      <img src="{{ asset('assets/images/guides-banner.webp') }}" alt="Alpine mountain peaks with hiker overlooking turquoise lake" class="page-banner-img">
      <div class="page-banner-overlay" aria-hidden="true"></div>
      <div class="page-banner-content">
        <h1 class="page-banner-title">Guides</h1>
      </div>
    </section>

    <!-- ==========================================================================
         Breadcrumbs Navigation (Global Shared Component)
         ========================================================================== -->
    <nav class="breadcrumbs-section" aria-label="Breadcrumb">
      <div class="breadcrumbs-list">
        <a href="{{ route('home') }}">HOME</a>
        <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        <span class="breadcrumbs-current">GUIDES</span>
      </div>
    </nav>

    <!-- ==========================================================================
         All Guides Section (Figma Node 204:994)
         ========================================================================== -->
    <section class="guides-section" aria-labelledby="all-guides-heading">
      <div class="guides-page-container">
        <!-- Section Header -->
        <div class="guides-header">
          <h2 id="all-guides-heading" class="guides-title">All Guides</h2>
          <p class="guides-subtitle">
            Amet quam tellus tempor mauris tristique et volutpat egestas tellus. Dictum tellus eget in diam eu vulputate massa nulla.
          </p>
        </div>

        <!-- 3x3 Guides Grid (Figma Node 204:999) -->
        <div class="guides-grid">
          <!-- Guide 1: Cyprus -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Cyprus eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-cyprus.webp') }}" alt="Cyprus coastal harbour with boats" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">CYPRUS ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Cyprus eSIM Guide 2026: 30 Questions | Best eSIM for Cyprus
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 2: Egypt -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Egypt eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-egypt.webp') }}" alt="Pyramids of Giza in Egypt desert" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">EGYPT ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Egypt eSIM Guide 2026: 30 Questions | Best eSIM for Egypt
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 3: Iraq -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Iraq eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-iraq.webp') }}" alt="Historic Ziggurat of Ur in Iraq" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">IRAQ ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Iraq eSIM Guide 2026: 30 Questions | Best eSIM for Iraq
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 4: Israel -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Israel eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-israel.webp') }}" alt="Old City of Jerusalem and Dome of the Rock" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">ISRAEL ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Israel eSIM Guide 2026: 30 Questions | Best eSIM for Israel
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 5: Jordan -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Jordan eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-jordan.webp') }}" alt="Al-Khazneh Treasury carved in sandstone, Petra, Jordan" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">JORDAN ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Jordan eSIM Guide 2026: 30 Questions | Best eSIM for Jordan
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 6: Netherlands -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Netherlands eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-netherlands.webp') }}" alt="Amsterdam canal lined with trees and boats" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">NETHERLANDS ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Netherlands eSIM Guide 2026: 30 Questions | Best eSIM for Netherlan...
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 7: Malta -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Malta eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-malta.webp') }}" alt="Historic cathedral and fortifications of Malta" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">MALTA ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Malta eSIM Guide 2026: 30 Questions | Best eSIM for Malta
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 8: Luxembourg -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Luxembourg eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-luxembourg.webp') }}" alt="Panoramic view over old Luxembourg City" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">LUXEMBOURG ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Luxembourg eSIM Guide 2026: 30 Questions | Best eSIM for Luxembou...
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-author">Savannah Nguyen</span>
                <span class="guide-meta-divider" aria-hidden="true"></span>
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>

          <!-- Guide 9: Lithuania -->
          <article class="guide-card">
            <a href="{{ route('blog-detail') }}" class="guide-card-img-wrap" aria-label="Lithuania eSIM Guide 2026">
              <img src="{{ asset('assets/images/guide-lithuania.webp') }}" alt="Hot air balloon floating over Vilnius old city" class="guide-card-img" loading="lazy">
            </a>
            <div class="guide-card-content">
              <p class="guide-card-category">LITHUANIA ESIM GUIDE</p>
              <h3 class="guide-card-title">
                <a href="{{ route('blog-detail') }}" style="text-decoration: none; color: inherit;">
                  Lithuania eSIM Guide 2026: 30 Questions | Best eSIM for Lithuania
                </a>
              </h3>
              <p class="guide-card-excerpt">
                Scelerisque et velit id dictumst ultricies convallis magna odio eget. Aliquam augue risus cras nulla massa.
              </p>
              <div class="guide-card-meta">
                <span class="guide-meta-time">2 min read</span>
              </div>
            </div>
          </article>
        </div>

        <!-- Pagination Controls (Figma Node 207:1042) -->
        <div class="guide-pagination-wrap" aria-label="Pagination">
          <ul class="guide-pagination">
            <li class="guide-page-item">
              <a href="#" class="guide-page-link guide-page-control" aria-label="Previous Page">
                <svg class="guide-page-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M10.2325 4.18414C10.4622 4.423 10.4547 4.80282 10.2159 5.0325L7.06566 8L10.2159 10.9675C10.4547 11.1972 10.4622 11.577 10.2325 11.8159C10.0028 12.0547 9.623 12.0622 9.38413 11.8325L5.78413 8.4325C5.66649 8.31938 5.6 8.16321 5.6 8C5.6 7.83679 5.66649 7.68062 5.78413 7.5675L9.38413 4.1675C9.623 3.93783 10.0028 3.94527 10.2325 4.18414Z" fill="currentColor"/>
                </svg>
                <span>Back</span>
              </a>
            </li>
            <li class="guide-page-item">
              <a href="#" class="guide-page-link" aria-label="Page 1">1</a>
            </li>
            <li class="guide-page-item">
              <a href="#" class="guide-page-link active" aria-current="page" aria-label="Page 2">2</a>
            </li>
            <li class="guide-page-item">
              <a href="#" class="guide-page-link" aria-label="Page 3">3</a>
            </li>
            <li class="guide-page-item">
              <a href="#" class="guide-page-link" aria-label="Page 4">4</a>
            </li>
            <li class="guide-page-item">
              <a href="#" class="guide-page-link" aria-label="Page 5">5</a>
            </li>
            <li class="guide-page-item">
              <a href="#" class="guide-page-link guide-page-control" aria-label="Next Page">
                <span>Next</span>
                <svg class="guide-page-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M5.7675 11.8159C5.53783 11.577 5.54527 11.1972 5.78414 10.9675L8.93434 8L5.78414 5.0325C5.54527 4.80282 5.53782 4.423 5.7675 4.18413C5.99718 3.94527 6.377 3.93782 6.61587 4.1675L10.2159 7.5675C10.3335 7.68062 10.4 7.83679 10.4 8C10.4 8.16321 10.3335 8.31938 10.2159 8.4325L6.61587 11.8325C6.377 12.0622 5.99718 12.0547 5.7675 11.8159Z" fill="currentColor"/>
                </svg>
              </a>
            </li>
          </ul>
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
  <x-footer :link-guides="true" :link-support="true" />
@endsection
