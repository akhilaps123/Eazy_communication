{{--
    Site Footer (shared component)

    Props:
      variant      'default' -> footer used on most pages (footer-col-list markup)
                   'alt'     -> footer markup used on About / My Account
                                (footer-nav-list / footer-description classes)
      linkGuides   'default' variant: point "Guides" at the Guides page
                   instead of the home-page #blog anchor
      linkSupport  'default' variant: point "Esim Support" at the support page
                   instead of the home-page #support anchor

    Both variants are kept because their markup/classes differ in the original
    design and are styled differently by footer.css.
--}}
@props([
    'variant' => 'default',
    'linkGuides' => false,
    'linkSupport' => false,
])

@php
    $home = route('home');
@endphp

@if ($variant === 'alt')
  <footer class="site-footer">
    <div class="footer-container">
      <!-- Top Row: Logo, Socials & Intro Text -->
      <div class="footer-top">
        <div class="footer-brand">
          <a href="{{ $home }}" class="footer-logo" aria-label="Eazy Communications">
            <img src="{{ asset('assets/images/logo.webp') }}" alt="Eazy Communications Logo" width="136" height="64">
          </a>
          <div class="footer-socials">
            <a href="#" class="social-icon-btn" aria-label="Instagram">
              <img src="{{ asset('assets/icons/social-instagram.svg') }}" alt="Instagram" width="18" height="18">
            </a>
            <a href="#" class="social-icon-btn" aria-label="Facebook">
              <img src="{{ asset('assets/icons/social-facebook.svg') }}" alt="Facebook" width="18" height="18">
            </a>
          </div>
        </div>

        <p class="footer-description">
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
        </p>
      </div>

      <!-- Links Grid -->
      <div class="footer-links-grid">
        <!-- Column 1: Quick Links -->
        <div class="footer-col">
          <h4 class="footer-col-title">Quick Links</h4>
          <ul class="footer-nav-list">
            <li><a href="{{ route('blog') }}" class="footer-link">Blogs</a></li>
            <li><a href="{{ route('guides') }}" class="footer-link">Guides</a></li>
            <li><a href="{{ route('about') }}" @class(['footer-link', 'active' => request()->routeIs('about')])>About</a></li>
            <li><a href="{{ route('esim-support') }}" class="footer-link">Esim Support</a></li>
            <li><a href="{{ $home }}#affiliate" class="footer-link">Affiliate</a></li>
            <li><a href="{{ $home }}#destination" class="footer-link">Destination</a></li>
          </ul>
        </div>

        <!-- Column 2: Become a Partner -->
        <div class="footer-col">
          <h4 class="footer-col-title">Become a Partner</h4>
          <ul class="footer-nav-list">
            <li><a href="{{ $home }}#reseller" class="footer-link">Eazy Communication for reseller</a></li>
            <li><a href="{{ $home }}#business" class="footer-link">Eazy Communication for business</a></li>
            <li><a href="{{ $home }}#affiliates" class="footer-link">Eazy Communication for affiliates</a></li>
          </ul>
        </div>

        <!-- Column 3: Countries -->
        <div class="footer-col">
          <h4 class="footer-col-title">Countries</h4>
          <ul class="footer-nav-list">
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for usa</a></li>
            <li><a href="{{ route('japan-esim') }}" class="footer-link">Esim for japan</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for canada</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for spain</a></li>
          </ul>
        </div>

        <!-- Column 4: More Countries -->
        <div class="footer-col footer-col-empty-title">
          <h4 class="footer-col-title sr-only">More Countries</h4>
          <ul class="footer-nav-list">
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for italy</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for uk</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for uae</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for turkey</a></li>
          </ul>
        </div>
      </div>
@else
  <footer class="site-footer" id="siteFooter">
    <div class="footer-container">
      <!-- Top Row: Logo, Socials, About Text -->
      <div class="footer-top">
        <div class="footer-brand">
          <div class="footer-logo">
            <a href="{{ $home }}" aria-label="Eazy Communications Home">
              <img src="{{ asset('assets/images/logo.webp') }}" alt="Eazy Communications Logo" width="136" height="64">
            </a>
          </div>
          <div class="footer-socials">
            <a href="#" class="social-icon-btn" aria-label="Instagram">
              <img src="{{ asset('assets/icons/social-instagram.svg') }}" alt="Instagram">
            </a>
            <a href="#" class="social-icon-btn" aria-label="Facebook">
              <img src="{{ asset('assets/icons/social-facebook.svg') }}" alt="Facebook">
            </a>
          </div>
        </div>

        <p class="footer-top-text">
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
        </p>
      </div>

      <!-- Middle: Link Columns Grid -->
      <div class="footer-links-grid">
        <div class="footer-col">
          <h4 class="footer-col-title">Quick Links</h4>
          <ul class="footer-col-list">
            <li><a href="{{ route('blog') }}" class="footer-link">Blogs</a></li>
            <li><a href="{{ $linkGuides ? route('guides') : $home . '#blog' }}" class="footer-link">Guides</a></li>
            <li><a href="{{ $home }}#about" class="footer-link">About</a></li>
            <li><a href="{{ $linkSupport ? route('esim-support') : $home . '#support' }}" class="footer-link">Esim Support</a></li>
            <li><a href="{{ $home }}#partner" class="footer-link">Affiliate</a></li>
            <li><a href="{{ $home }}#destinations" class="footer-link">Destination</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4 class="footer-col-title">Become A Partner</h4>
          <ul class="footer-col-list">
            <li><a href="{{ $home }}#partner" class="footer-link">Eazy Communication for reseller</a></li>
            <li><a href="{{ $home }}#partner" class="footer-link">Eazy Communication for business</a></li>
            <li><a href="{{ $home }}#partner" class="footer-link">Eazy Communication for affiliates</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4 class="footer-col-title">Countries</h4>
          <ul class="footer-col-list">
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for usa</a></li>
            <li><a href="{{ route('japan-esim') }}" class="footer-link">Esim for japan</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for canada</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for spain</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4 class="footer-col-title">&nbsp;</h4>
          <ul class="footer-col-list">
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for italy</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for uk</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for uae</a></li>
            <li><a href="{{ $home }}#offers" class="footer-link">Esim for turkey</a></li>
          </ul>
        </div>
      </div>
@endif

      <!-- Bottom Row: Copyright & Legal Disclaimer -->
      <div class="footer-bottom">
        <div class="footer-bottom-row">
          <p class="footer-copyright">
            &copy; 2026 Eazy Communications. All Right Reserved.
          </p>
          <div class="footer-legal-links">
            <a href="#" class="footer-legal-link">Privacy policy</a>
            <a href="#" class="footer-legal-link">Terms of service</a>
            <a href="#" class="footer-legal-link">Delivery &amp; refund policy</a>
          </div>
        </div>

        <p class="footer-disclaimer">
          This Privacy Policy supplements our Terms of Service and any capitalized terms used, but not defined in this Privacy Policy, have the meaning given to them in our Terms of Service. Users can access the Services via our website at transparent.com. By using our Services, you are consenting to the collection, transfer, processing, storage, disclosure and other uses described in this privacy Policy.
        </p>
      </div>
    </div>
  </footer>
