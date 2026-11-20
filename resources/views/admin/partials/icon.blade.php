{{--
  Admin icon set (inline SVG, 24x24 stroke icons, inherits currentColor).
  Usage: @include('admin.partials.icon', ['name' => 'globe'])
--}}
@php
  $paths = [
      'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
      'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
      'layers' => '<path d="M12 3 2.5 8 12 13l9.5-5L12 3z"/><path d="m2.5 12.5 9.5 5 9.5-5"/><path d="m2.5 16.5 9.5 5 9.5-5"/>',
      'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h3l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.1L21 7.5H6.4"/>',
      'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
      'tag' => '<path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9-9-9z"/><circle cx="7.8" cy="7.8" r="1.4"/>',
      'plus' => '<path d="M12 5v14M5 12h14"/>',
      'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
      'external' => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M19 14v4.5A1.5 1.5 0 0 1 17.5 20h-12A1.5 1.5 0 0 1 4 18.5v-12A1.5 1.5 0 0 1 5.5 5H10"/>',
      'logout' => '<path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="M10 16.5 5.5 12 10 7.5"/><path d="M5.5 12H15"/>',
      'menu' => '<path d="M4 6.5h16M4 12h16M4 17.5h16"/>',
      'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
      'chevron' => '<path d="m9.5 6 6 6-6 6"/>',
      'broadcast' => '<circle cx="12" cy="12" r="2"/><path d="M8.2 15.8a5.4 5.4 0 0 1 0-7.6M15.8 8.2a5.4 5.4 0 0 1 0 7.6"/><path d="M5.3 18.7a9.5 9.5 0 0 1 0-13.4M18.7 5.3a9.5 9.5 0 0 1 0 13.4"/>',
      'list' => '<path d="M9 6.5h11M9 12h11M9 17.5h11"/><circle cx="4.5" cy="6.5" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="17.5" r="1"/>',
  ];
@endphp
<svg class="adm-icon {{ $class ?? '' }}" viewBox="0 0 24 24" width="{{ $size ?? 20 }}" height="{{ $size ?? 20 }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$name] ?? '' !!}</svg>
