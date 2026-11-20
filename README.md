# Eazy Communications — Laravel

The Eazy Communications website, converted from the original static HTML site
(`../eazy-communication-html-needs-to-integrate-laravel/`, kept untouched as a backup)
into a Laravel 13 application with Blade templates. The UI is unchanged.

## Run locally

Requirements: PHP 8.3+, Composer.

```bash
composer install
cp .env.example .env        # skip if .env already exists
php artisan key:generate    # skip if APP_KEY is already set
php artisan serve
```

Open http://127.0.0.1:8000

No database is needed to view the pages (sessions/cache use files).
For MySQL later, set the `DB_*` values in `.env` (see `.env.example`) and run `php artisan migrate`.

## Structure

```
app/Http/Controllers/
  Auth/LoginController.php           POST /login            (stub, returns 501)
  Auth/RegisterController.php        POST /register         (stub, returns 501)
  Auth/ForgotPasswordController.php  POST /forgot-password  (stub, returns 501)
  UsageController.php                POST /check-usage      (stub, returns 501)
  CheckoutController.php             POST /checkout         (stub, returns 501)
resources/views/
  layouts/app.blade.php      <html>/<head>/<body> shell, CSRF meta, Lenis + main.js
  components/header.blade.php  <x-header>  guest/user variants, active states, mobile links
  components/footer.blade.php  <x-footer>  default/alt variants
  pages/*.blade.php          15 pages
routes/web.php               all routes
public/css, public/js, public/assets   original front-end files (same names/folders)
```

### Page conventions

```blade
@extends('layouts.app')
@section('title', 'Page title')
@section('meta_description', '...')
@section('body_class', 'login-page')      {{-- optional --}}

@push('head')      {{-- fonts + stylesheets, in the page's original order --}}
@endpush

@section('content')
  <x-header active="new-esim" />
  ...
  <x-footer />
@endsection

@push('scripts')   {{-- page-specific JS; lenis.min.js + main.js are in the layout --}}
@endpush
```

Stylesheets stay per page on purpose: the original pages load them in different
orders, and the order affects the CSS cascade.

## Routes

| URL | Name | View |
|---|---|---|
| `/` | `home` | pages.home |
| `/about` | `about` | pages.about |
| `/blog` | `blog` | pages.blog |
| `/blog-detail` | `blog-detail` | pages.blog-detail |
| `/guides` | `guides` | pages.guides |
| `/new-esim` | `new-esim` | pages.new-esim |
| `/japan-esim` | `japan-esim` | pages.japan-esim |
| `/esim-support` | `esim-support` | pages.esim-support |
| `/check-usage` | `check-usage` | pages.check-usage |
| `/cart` | `cart` | pages.cart |
| `/checkout` | `checkout` | pages.checkout |
| `/my-account` | `my-account` | pages.my-account |
| `/login` | `login` | pages.login |
| `/register` | `register` | pages.registration |
| `/forgot-password` | `password.request` | pages.forgot-password |

POST: `login.submit`, `register.submit`, `password.email`, `check-usage.submit`, `checkout.submit`.

## Backend status

Nothing server-side is implemented yet. Forms carry `method="POST"`, an `action`
pointing at the routes above, and `@csrf`, but the original front-end JavaScript
still intercepts submission and shows its demo behaviour. The POST endpoints
return **501 Not Implemented** until the backend is built.
