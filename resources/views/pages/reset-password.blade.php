@extends('layouts.app')

@section('title', 'Set a New Password — Eazy Communication')
@section('meta_description', 'Choose a new password for your Eazy Communication account.')
@section('body_class', 'forgot-page')

@push('head')
  <link rel="icon" type="image/png" href="{{ asset('assets/images/logo.webp') }}">

  <!-- Universal Stylesheets -->
  <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
  <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">

  <!-- Forgot Password Page Specific Stylesheet -->
  <link rel="stylesheet" href="{{ asset('css/forgot-password.css') }}">
@endpush

@section('content')
  <!-- Top Hero Scenic Banner (Full Width across entire screen) -->
  <div class="forgot-banner" role="banner" aria-label="Scenic alpine mountains travel destination">
    <img 
      src="{{ asset('assets/images/forgot-banner.webp') }}" 
      alt="Hikers exploring alpine mountains with Eazy Communication global connectivity" 
      class="forgot-banner-img"
    >
  </div>

  <!-- Main Grid Layout (Full width with strict 100px left and 104px right margin) -->
  <main class="forgot-main" id="mainContent">
    <div class="forgot-grid">

      <!-- Left Column: Brand & Information -->
      <div class="forgot-left">
        <div class="forgot-left-top">
          <a href="{{ route('home') }}" class="forgot-logo-link" aria-label="Eazy Communication Home">
            <img src="{{ asset('assets/images/logo.webp') }}" alt="Eazy Communication Logo" class="forgot-logo-img" width="136" height="64">
          </a>

          <div class="forgot-hero-content">
            <h1 class="forgot-heading">Stay Connected Worldwide with Eazy Communication</h1>
            <p class="forgot-description">
              Eazy Communication is your trusted partner for seamless global connectivity. We provide reliable eSIM solutions that keep you connected wherever you travel.
            </p>
          </div>
        </div>

        <!-- Left Column Footer: Legal Navigation & Copyright -->
        <div class="forgot-legal-footer">
          <nav class="forgot-legal-nav" aria-label="Legal Links">
            <a href="#" class="forgot-legal-link">Terms of Service</a>
            <a href="#" class="forgot-legal-link">Privacy Policy</a>
          </nav>
          <p class="forgot-copyright">© 2026 Eazy Communications. All Right Reserved.</p>
        </div>
      </div>

      <!-- Right Column: Recovery Card -->
      <div class="forgot-right">
        <section class="forgot-card" aria-labelledby="forgotCardHeading">
          <h2 id="forgotCardHeading" class="forgot-card-title">Set a New Password</h2>

          {{-- Reuses the Forgot Password card design (forgot-password.css). --}}
          <form id="resetForm" method="POST" action="{{ route('password.update') }}" class="forgot-form-wrapper">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Inline Alert / Feedback Message -->
            <div id="resetFeedback" @class(['form-feedback', 'error' => $errors->any()]) role="alert" aria-live="polite">{{ $errors->first() }}</div>

            <!-- Form Fields -->
            <div class="forgot-fields">
              <div class="form-group">
                <label for="resetEmail" class="form-label">Email</label>
                <input
                  type="email"
                  id="resetEmail"
                  name="email"
                  @class(['input-control', 'error' => $errors->has('email')])
                  value="{{ old('email', $email) }}"
                  placeholder="Enter your email"
                  autocomplete="email"
                  required
                >
              </div>

              <div class="form-group">
                <label for="resetPassword" class="form-label">New password</label>
                <input
                  type="password"
                  id="resetPassword"
                  name="password"
                  @class(['input-control', 'error' => $errors->has('password')])
                  placeholder="At least 8 characters"
                  autocomplete="new-password"
                  minlength="8"
                  required
                >
              </div>

              <div class="form-group">
                <label for="resetPasswordConfirm" class="form-label">Confirm new password</label>
                <input
                  type="password"
                  id="resetPasswordConfirm"
                  name="password_confirmation"
                  @class(['input-control', 'error' => $errors->has('password')])
                  placeholder="Re-enter your new password"
                  autocomplete="new-password"
                  required
                >
              </div>
            </div>

            <!-- Primary Action Submit Button -->
            <button type="submit" id="btnSubmitNewPassword" class="btn btn-primary btn-reset-submit">Save New Password</button>
          </form>
        </section>
      </div>

    </div>
  </main>
@endsection
