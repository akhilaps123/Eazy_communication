{{-- Live catalogue: home "Popular eSIM Destinations" items (same markup as the static design). --}}
@foreach ($destinations as $destination)
@php($country = $destination['country'])
          <a href="{{ route('esim.country', $country) }}" class="destination-item" data-country="{{ $country->name }}" aria-label="{{ $country->name }} eSIM">
            <div class="destination-sphere">
              @if ($sphere = $country->imagePath('sphere'))
              <img src="{{ asset($sphere) }}" alt="{{ $country->name }} Flag Sphere" width="154" height="116">
              @endif
            </div>
            <p class="destination-name">{{ $country->name }}</p>
          </a>

@endforeach
