{{-- Admin panel layout — separate from the customer website layout (layouts/app.blade.php). --}}
@php
  $admin = auth()->user();
  $initials = collect(preg_split('/\s+/', trim((string) $admin->name)))->filter()->take(2)
      ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') ?: 'A';

  $navItems = [
      ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
      ['route' => 'admin.countries.index', 'match' => 'admin.countries.*', 'label' => 'Countries', 'icon' => 'globe'],
      ['route' => 'admin.plans.index', 'match' => 'admin.plans.*', 'label' => 'Plans & Prices', 'icon' => 'layers'],
      ['route' => 'admin.provider-bundles.index', 'match' => 'admin.provider-bundles.*', 'label' => 'Provider Bundles', 'icon' => 'list'],
      ['route' => 'admin.orders.index', 'match' => 'admin.orders.*', 'label' => 'Orders & Fulfillment', 'icon' => 'cart', 'group' => 'Sales'],
  ];

  // Breadcrumb from the current route: Dashboard › Section › Page.
  $breadcrumb = [['label' => 'Dashboard', 'url' => route('admin.dashboard')]];
  foreach (array_slice($navItems, 1) as $item) {
      if (request()->routeIs($item['match'])) {
          $breadcrumb[] = ['label' => $item['label'], 'url' => route($item['route'])];
          if (! request()->routeIs($item['route'])) {
              // The section is already escaped; decode so {{ }} escapes it exactly once.
              $breadcrumb[] = ['label' => html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES | ENT_HTML5), 'url' => null];
          }
      }
  }
  $breadcrumb[array_key_last($breadcrumb)]['url'] = null;   // current page is not a link
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title') · Eazy Admin</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
  <script src="{{ asset('js/admin.js') }}" defer></script>
</head>
<body class="adm-body">
  <a class="adm-skip" href="#adm-content">Skip to content</a>

  <aside class="adm-sidebar" id="adm-sidebar" aria-label="Admin navigation">
    <div class="adm-sidebar-brand">
      <a href="{{ route('admin.dashboard') }}" class="adm-brand">
        <span class="adm-brand-mark" aria-hidden="true">E</span>
        <span class="adm-brand-text">Eazy Communication <small>Admin</small></span>
      </a>
      <button type="button" class="adm-icon-btn adm-sidebar-close" data-adm-nav-close aria-label="Close menu">
        @include('admin.partials.icon', ['name' => 'close'])
      </button>
    </div>

    <nav class="adm-sidenav">
      <span class="adm-sidenav-label">Catalogue</span>
      @foreach ($navItems as $item)
        @php
          $current = request()->routeIs($item['match']);
        @endphp
        @if (! empty($item['group']))
          <span class="adm-sidenav-label">{{ $item['group'] }}</span>
        @endif
        <a href="{{ route($item['route']) }}" @class(['adm-sidenav-link', 'is-active' => $current]) @if ($current) aria-current="page" @endif>
          @include('admin.partials.icon', ['name' => $item['icon']])
          <span>{{ $item['label'] }}</span>
        </a>
      @endforeach
    </nav>

    <div class="adm-sidebar-footer">
      <div class="adm-profile">
        <span class="adm-avatar" aria-hidden="true">{{ $initials }}</span>
        <span class="adm-profile-text">
          <strong>{{ $admin->name }}</strong>
          <small>{{ $admin->email }}</small>
        </span>
      </div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="adm-logout">
          @include('admin.partials.icon', ['name' => 'logout', 'size' => 18])
          <span>Log out</span>
        </button>
      </form>
    </div>
  </aside>
  <div class="adm-backdrop" data-adm-nav-close></div>

  <div class="adm-shell">
    <header class="adm-topbar">
      <button type="button" class="adm-icon-btn adm-menu-btn" data-adm-nav-open aria-controls="adm-sidebar" aria-expanded="false" aria-label="Open menu">
        @include('admin.partials.icon', ['name' => 'menu'])
      </button>
      <div class="adm-topbar-title">
        @if (count($breadcrumb) > 1)
          <nav class="adm-breadcrumb" aria-label="Breadcrumb">
            <ol>
              @foreach ($breadcrumb as $crumb)
                <li>
                  @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                  @else
                    <span aria-current="page">{{ $crumb['label'] }}</span>
                  @endif
                </li>
              @endforeach
            </ol>
          </nav>
        @endif
        <p class="adm-page-title">@yield('title')</p>
      </div>
      <div class="adm-topbar-actions">
        <a class="adm-btn adm-btn-light adm-btn-sm adm-view-site" href="{{ route('home') }}" target="_blank" rel="noopener">
          @include('admin.partials.icon', ['name' => 'external', 'size' => 16])
          <span>View website</span>
        </a>
        <span class="adm-topbar-user" title="{{ $admin->email }}">
          <span class="adm-avatar adm-avatar-sm" aria-hidden="true">{{ $initials }}</span>
          <span class="adm-topbar-user-name">{{ $admin->name }}</span>
        </span>
      </div>
    </header>

    <main class="adm-main" id="adm-content">
      @if (session('status'))
        <div class="adm-alert adm-alert-ok" role="status">{{ session('status') }}</div>
      @endif

      @if ($errors->any())
        <div class="adm-alert adm-alert-error" role="alert">
          <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @yield('content')
    </main>
  </div>

  <script>
    (function () {
      var body = document.body;
      var openBtn = document.querySelector('[data-adm-nav-open]');
      function setOpen(open) {
        body.classList.toggle('adm-nav-open', open);
        if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
      if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
      document.querySelectorAll('[data-adm-nav-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    })();
  </script>
</body>
</html>
