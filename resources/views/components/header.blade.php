{{--
    Site Header (shared component)

    The header actions follow the real login state (Phase 4):
      signed out -> Login icon + "Get Started" button
      signed in  -> Cart icon + profile avatar pill (links to My Account)

    Props:
      variant      Kept for backward compatibility; no longer decides the actions.
      active       Key of the highlighted primary nav item:
                   'new-esim' | 'check-usage' | 'esim-support' | null
      cartActive   Highlight the cart icon (cart page)
      accountActive Highlight the avatar pill (my-account page)
      mobileLinks  Extra links appended to the mobile drawer, each:
                   ['label' => 'Blog', 'route' => 'blog', 'active' => false]

--}}
@props([
    'variant' => 'user',
    'active' => null,
    'cartActive' => false,
    'accountActive' => false,
    'mobileLinks' => [],
])

@php
    // On the home page the partner link is an in-page anchor; elsewhere it points back to home.
    $partnerHref = request()->routeIs('home') ? '#partner' : route('home') . '#partner';

    $primaryLinks = [
        ['key' => 'new-esim', 'label' => 'New eSIM', 'href' => route('new-esim')],
        ['key' => 'partner', 'label' => 'Become a Partner', 'href' => $partnerHref],
        ['key' => 'check-usage', 'label' => 'Check Usage', 'href' => route('check-usage')],
        ['key' => 'esim-support', 'label' => 'eSIM Support', 'href' => route('esim-support')],
    ];
@endphp

  <header class="site-header" id="siteHeader">
    <div class="header-container">
      <a href="{{ route('home') }}" class="header-logo" aria-label="Eazy Communications Home">
        <img src="{{ asset('assets/images/logo.webp') }}" alt="Eazy Communications Logo" width="136" height="64">
      </a>

      <!-- Desktop Navigation -->
      <nav class="header-nav" aria-label="Main Navigation">
@foreach ($primaryLinks as $link)
        <a href="{{ $link['href'] }}" @class(['header-nav-link', 'active' => $active === $link['key']])>{{ $link['label'] }}</a>
@endforeach
      </nav>

@if (! auth()->check())
      <!-- Header Actions -->
      <div class="header-actions">
        <a href="{{ route('login') }}" class="header-user-btn" aria-label="Account and Login">
          <img src="{{ asset('assets/icons/user-icon.svg') }}" alt="User Profile" width="18" height="20">
        </a>
        <div class="header-divider" aria-hidden="true"></div>
        <a href="{{ route('new-esim') }}" class="btn btn-get-started">
          <div class="icon-circle">
            <img src="{{ asset('assets/icons/arrow-up-right.svg') }}" alt="" aria-hidden="true">
          </div>
          <span class="btn-label">Get Started</span>
        </a>
@else
      <!-- Header Actions (Cart Icon & User Profile Avatar Pill) -->
      <div class="header-actions header-actions-custom">
        <a href="{{ route('cart') }}" @class(['header-cart-btn', 'active' => $cartActive]) aria-label="Shopping Cart">
          <img src="{{ asset('assets/icons/cart-bag.svg') }}" alt="Cart" width="18" height="20">
        </a>
        <div class="header-divider-custom" aria-hidden="true"></div>
        <a href="{{ route('my-account') }}" @class(['header-avatar-pill', 'active' => $accountActive]) aria-label="User Account">
          <img src="{{ asset('assets/images/user-nav-avatar.png') }}" alt="Profile" class="header-avatar-img" width="40" height="40">
        </a>
@endif

        <!-- Mobile Hamburger Toggle -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu" aria-expanded="false">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav-drawer" id="mobileNavDrawer">
@foreach ($primaryLinks as $link)
      <a href="{{ $link['href'] }}" @class(['header-nav-link', 'active' => $active === $link['key']])>{{ $link['label'] }}</a>
@endforeach
@php
    // Phase 9E: signed-in customers always get their account pages in the mobile menu.
    $accountLinks = auth()->check() ? [
        ['label' => 'My Account', 'route' => 'my-account'],
        ['label' => 'My Orders', 'route' => 'my-orders'],
        ['label' => 'My eSIMs', 'route' => 'my-esims'],
    ] : [];
    $mobileRoutes = array_column($mobileLinks, 'route');
    $accountLinks = array_values(array_filter($accountLinks, fn ($l) => ! in_array($l['route'], $mobileRoutes, true)));
@endphp
@foreach ($mobileLinks as $link)
      <a href="{{ route($link['route']) }}" @class(['header-nav-link', 'active' => $link['active'] ?? false])>{{ $link['label'] }}</a>
@endforeach
@foreach ($accountLinks as $link)
      <a href="{{ route($link['route']) }}" @class(['header-nav-link', 'active' => request()->routeIs($link['route'], $link['route'].'.*')])>{{ $link['label'] }}</a>
@endforeach
    </div>
  </header>
