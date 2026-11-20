@extends('layouts.app')

@section('title', 'Login to Eazy Communication — Stay Connected Worldwide')
@section('meta_description', 'Log in to your Eazy Communication account to manage your global eSIM data plans, check usage, and stay connected anywhere.')
@section('body_class', 'login-page')

@push('head')
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">

  <!-- Universal Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">

  <!-- Login Page Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/login.css') }}">
@endpush

@section('content')
  <!-- Top Hero Scenic Banner (Full Width across entire screen) -->
  <div class="login-banner" role="banner" aria-label="Scenic canyon travel destination">
    <img 
      src="{{ asset('assets/images/login-banner.webp') }}" 
      alt="Traveler overlooking canyon river with Eazy Communication global connectivity" 
      class="login-banner-img"
    >
  </div>

  <!-- Main Login Grid Layout (Full width with strict 100px left and 104px right margin) -->
  <main class="login-main" id="mainContent">
    <div class="login-grid">

      <!-- Left Column: Brand & Information -->
      <div class="login-left">
        <div class="login-left-top">
          <a href="{{ route('home') }}" class="login-logo-link" aria-label="Eazy Communication Home">
            <img src="{{ asset('assets/images/logo.webp') }}" alt="Eazy Communication Logo" class="login-logo-img" width="136" height="64">
          </a>

          <div class="login-hero-content">
            <h1 class="login-heading">Stay Connected Worldwide with Eazy Communication</h1>
            <p class="login-description">
              Eazy Communication is your trusted partner for seamless global connectivity. We provide reliable eSIM solutions that keep you connected wherever you travel.
            </p>
          </div>
        </div>

        <!-- Left Column Footer: Legal Navigation & Copyright -->
        <div class="login-legal-footer">
          <nav class="login-legal-nav" aria-label="Legal Links">
            <a href="#" class="login-legal-link">Terms of Service</a>
            <a href="#" class="login-legal-link">Privacy Policy</a>
          </nav>
          <p class="login-copyright">© 2026 Eazy Communications. All Right Reserved.</p>
        </div>
      </div>

      <!-- Right Column: Login Card -->
      <div class="login-right">
        <section class="login-card" aria-labelledby="loginCardHeading">
          <h2 id="loginCardHeading" class="login-card-title">Welcome back!</h2>

          <form id="loginForm" method="POST" action="{{ route('login.submit') }}" class="login-form-wrapper" novalidate>
            @csrf
            <!-- Social Login Buttons -->
            <div class="login-social-row">
              <button type="button" class="btn btn-social-google" aria-label="Login with Google">
                <img src="{{ asset('assets/icons/social-google.svg') }}" alt="" class="google-icon-img" width="24" height="24">
                <span>Login with Google</span>
              </button>

              <button type="button" class="btn btn-social-icon" aria-label="Log in with Facebook">
                <img src="{{ asset('assets/icons/social-facebook-card.svg') }}" alt="Facebook" class="social-icon-img" width="56" height="56">
              </button>

              <button type="button" class="btn btn-social-icon" aria-label="Log in with Twitter or X">
                <img src="{{ asset('assets/icons/social-twitter.svg') }}" alt="Twitter / X" class="social-icon-img" width="56" height="56">
              </button>
            </div>

            <!-- "Or" Divider -->
            <div class="login-divider" role="separator">
              <div class="login-divider-line"></div>
              <span class="login-divider-text">Or</span>
              <div class="login-divider-line"></div>
            </div>

            <!-- Inline Alert / Feedback Message -->
            <div id="loginFeedback" @class(['form-feedback', 'error' => $errors->any(), 'success' => ! $errors->any() && session('status')]) role="alert" aria-live="polite">{{ $errors->first() ?: session('status') }}</div>

            <!-- Form Fields -->
            <div class="login-fields">
              <div class="form-group">
                <label for="loginEmail" class="form-label">Email</label>
                <input 
                  type="email" 
                  id="loginEmail" 
                  name="email"
                  @class(['input-control', 'error' => $errors->has('email')])
                  value="{{ old('email') }}"
                  placeholder="Enter your email"
                  autocomplete="email"
                  required
                >
              </div>

              <div class="form-group">
                <label for="loginPassword" class="form-label">Password</label>
                <div class="password-group">
                  <input 
                    type="password" 
                    id="loginPassword" 
                    name="password" 
                    class="input-control" 
                    placeholder="Enter your password" 
                    autocomplete="current-password"
                    required
                  >
                  <button type="button" id="toggleLoginPassword" class="btn-toggle-visibility" aria-label="Show password">
                    <img src="{{ asset('assets/icons/icon-visibility.svg') }}" alt="" class="visibility-icon" width="32" height="32">
                  </button>
                </div>
              </div>

              <!-- Remember Me Checkbox -->
              <label class="checkbox-group" for="loginRemember">
                <input type="checkbox" id="loginRemember" name="remember" @checked(old('remember'))>
                <span class="custom-checkbox" aria-hidden="true"></span>
                <span class="checkbox-label">Remember me</span>
              </label>
            </div>

            <!-- Primary Action Submit Button -->
            <button type="submit" id="btnSubmitSignIn" class="btn btn-primary btn-signin-submit">Sign In</button>
          </form>

          <!-- Forgot Password Link -->
          <div class="login-forgot-wrapper">
            <a href="{{ route('password.request') }}" class="login-forgot-link">Forgot your password?</a>
          </div>

          <!-- Footer Prompt: Don't have an account? Sign Up -->
          <div class="login-card-footer">
            <span>Don’t have an account?</span>
            <a href="{{ route('register') }}" class="login-signup-link">Sign Up</a>
          </div>
        </section>
      </div>

    </div>
  </main>
@endsection

@push('scripts')
  <script src="{{ asset('js/login.js') }}"></script>
@endpush
