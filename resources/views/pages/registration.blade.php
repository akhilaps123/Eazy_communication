@extends('layouts.app')

@section('title', 'Join Eazy Communication — Stay Connected Worldwide')
@section('meta_description', 'Create your Eazy Communication account to access fast, reliable eSIM data plans in over 200 destinations worldwide.')
@section('body_class', 'registration-page')

@push('head')
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">

  <!-- Universal Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">

  <!-- Registration Page Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/registration.css') }}">
@endpush

@section('content')
  <!-- Top Hero Scenic Banner (Full Width across entire screen) -->
  <div class="reg-banner" role="banner" aria-label="Scenic travel destination">
    <img 
      src="{{ asset('assets/images/registration-banner.webp') }}" 
      alt="Woman overlooking tropical coastal sea with Eazy Communication eSIM" 
      class="reg-banner-img"
    >
  </div>

  <!-- Main Registration Grid Layout (Centered 1440px grid) -->
  <main class="reg-main" id="mainContent">
    <div class="reg-grid">

      <!-- Left Column: Brand & Information -->
      <div class="reg-left">
        <div class="reg-left-top">
          <a href="{{ route('home') }}" class="reg-logo-link" aria-label="Eazy Communication Home">
            <img src="{{ asset('assets/images/logo.webp') }}" alt="Eazy Communication Logo" class="reg-logo-img" width="136" height="64">
          </a>

          <div class="reg-hero-content">
            <h1 class="reg-heading">Stay Connected Worldwide with Eazy Communication</h1>
            <p class="reg-description">
              Eazy Communication is your trusted partner for seamless global connectivity. We provide reliable eSIM solutions that keep you connected wherever you travel.
            </p>
          </div>
        </div>

        <!-- Left Column Footer: Legal Navigation & Copyright -->
        <div class="reg-legal-footer">
          <nav class="reg-legal-nav" aria-label="Legal Links">
            <a href="#" class="reg-legal-link">Terms of Service</a>
            <a href="#" class="reg-legal-link">Privacy Policy</a>
          </nav>
          <p class="reg-copyright">© 2026 Eazy Communications. All Right Reserved.</p>
        </div>
      </div>

      <!-- Right Column: Registration Card -->
      <div class="reg-right">
        <section class="reg-card" aria-labelledby="regCardHeading">
          <h2 id="regCardHeading" class="reg-card-title">Join now!</h2>

          <form id="registrationForm" method="POST" action="{{ route('register.submit') }}" class="reg-form-wrapper" novalidate>
            @csrf
            <!-- Social Sign-up Buttons -->
            <div class="reg-social-row">
              <button type="button" class="btn btn-social-google" aria-label="Register with Google">
                <img src="{{ asset('assets/icons/social-google.svg') }}" alt="" class="google-icon-img" width="24" height="24">
                <span>Register with Google</span>
              </button>

              <button type="button" class="btn btn-social-icon" aria-label="Sign up with Facebook">
                <img src="{{ asset('assets/icons/social-facebook-card.svg') }}" alt="Facebook" class="social-icon-img" width="56" height="56">
              </button>

              <button type="button" class="btn btn-social-icon" aria-label="Sign up with Twitter or X">
                <img src="{{ asset('assets/icons/social-twitter.svg') }}" alt="Twitter / X" class="social-icon-img" width="56" height="56">
              </button>
            </div>

            <!-- "Or" Divider -->
            <div class="reg-divider" role="separator">
              <div class="reg-divider-line"></div>
              <span class="reg-divider-text">Or</span>
              <div class="reg-divider-line"></div>
            </div>

            <!-- Inline Alert / Feedback Message -->
            <div id="formFeedback" @class(['form-feedback', 'error' => $errors->any()]) role="alert" aria-live="polite">{{ $errors->first() }}</div>

            <!-- Form Fields -->
            <div class="reg-fields">
              <div class="form-group">
                <label for="regFullName" class="form-label">Full Name</label>
                <input 
                  type="text" 
                  id="regFullName" 
                  name="fullName"
                  @class(['input-control', 'error' => $errors->has('fullName')])
                  value="{{ old('fullName') }}"
                  placeholder="Enter your full name" 
                  autocomplete="name"
                  required
                >
              </div>

              <div class="form-group">
                <label for="regEmail" class="form-label">Email</label>
                <input 
                  type="email" 
                  id="regEmail"
                  name="email"
                  @class(['input-control', 'error' => $errors->has('email')])
                  value="{{ old('email') }}"
                  placeholder="Enter your email" 
                  autocomplete="email"
                  required
                >
              </div>

              <div class="form-group">
                <label for="regPassword" class="form-label">Password</label>
                <div class="password-group">
                  <input 
                    type="password" 
                    id="regPassword"
                    name="password"
                    @class(['input-control', 'error' => $errors->has('password')])
                    placeholder="Enter your password" 
                    autocomplete="new-password"
                    required
                  >
                  <button type="button" id="togglePassword" class="btn-toggle-visibility" aria-label="Show password">
                    <img src="{{ asset('assets/icons/icon-visibility.svg') }}" alt="" class="visibility-icon" width="32" height="32">
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label for="regPasswordConfirm" class="form-label">Confirm Password</label>
                <input
                  type="password"
                  id="regPasswordConfirm"
                  name="password_confirmation"
                  @class(['input-control', 'error' => $errors->has('password')])
                  placeholder="Re-enter your password"
                  autocomplete="new-password"
                  required
                >
              </div>

              <!-- Terms & Privacy Agreement Checkbox -->
              <label class="checkbox-group" for="regTerms">
                <input type="checkbox" id="regTerms" name="terms" required @checked(old('terms'))>
                <span class="custom-checkbox" aria-hidden="true"></span>
                <span class="checkbox-label">I agree to Terms of Condition and Privacy of Policy</span>
              </label>
            </div>

            <!-- Primary Action Submit Button -->
            <button type="submit" id="btnSubmitSignUp" class="btn btn-primary btn-signup-submit">Sign Up</button>
          </form>

          <!-- Footer Prompt: Already have an account? -->
          <div class="reg-card-footer">
            <span>Already have an account?</span>
            <a href="{{ route('login') }}" class="reg-signin-link">Sign In</a>
          </div>
        </section>
      </div>

    </div>
  </main>
@endsection

@push('scripts')
  <script src="{{ asset('js/registration.js') }}"></script>
@endpush
