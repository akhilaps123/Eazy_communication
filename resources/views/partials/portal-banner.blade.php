{{-- Phase 9E: account banner + breadcrumbs. $title, $crumbs = [[label, url|null], ...] --}}
    <section class="account-banner" aria-label="{{ $title }} banner">
      <img src="{{ asset('assets/images/my-account-banner.webp') }}" alt="" class="account-banner-img">
      <div class="account-banner-overlay" aria-hidden="true"></div>
      <div class="account-banner-content">
        <h1 class="account-banner-title">{{ $title }}</h1>
      </div>
    </section>

    <nav class="account-breadcrumbs" aria-label="Breadcrumb">
      <div class="account-breadcrumbs-inner">
        <div class="account-breadcrumbs-list">
          <a href="{{ route('home') }}">HOME</a>
          @foreach ($crumbs as [$label, $url])
            <span class="account-breadcrumbs-sep" aria-hidden="true">/</span>
            @if ($url)
              <a href="{{ $url }}">{{ mb_strtoupper($label) }}</a>
            @else
              <span class="account-breadcrumbs-current" aria-current="page">{{ mb_strtoupper($label) }}</span>
            @endif
          @endforeach
        </div>
      </div>
    </nav>
