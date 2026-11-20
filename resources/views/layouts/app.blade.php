<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-url" content="{{ url('/') }}">
  <title>@yield('title', config('app.name'))</title>
@hasSection('meta_description')
  <meta name="description" content="@yield('meta_description')">
@endif

{{--
    Each page pushes its own favicon, Google Fonts and stylesheets here.
    Stylesheet ORDER differs between the original pages and affects the
    CSS cascade, so it is intentionally kept per page.
--}}
@stack('head')
</head>
<body @hasSection('body_class') class="@yield('body_class')" @endif>

@yield('content')

  <!-- Scripts: Lenis Smooth Scrolling Engine & Global Handlers -->
  <script src="{{ asset('js/lenis.min.js') }}"></script>
  <script src="{{ asset('js/main.js') }}"></script>
@stack('scripts')
</body>
</html>
