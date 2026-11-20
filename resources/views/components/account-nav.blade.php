{{-- Phase 9E: customer account tabs (My Account / My Orders / My eSIMs). --}}
@props(['active' => null])
@php
  $tabs = [
      ['key' => 'account', 'label' => 'My Account', 'route' => 'my-account'],
      ['key' => 'orders', 'label' => 'My Orders', 'route' => 'my-orders'],
      ['key' => 'esims', 'label' => 'My eSIMs', 'route' => 'my-esims'],
  ];
@endphp
<nav class="portal-tabs" aria-label="My account">
@foreach ($tabs as $tab)
  <a href="{{ route($tab['route']) }}" @class(['portal-tab', 'is-active' => $active === $tab['key']]) @if ($active === $tab['key']) aria-current="page" @endif>{{ $tab['label'] }}</a>
@endforeach
</nav>
