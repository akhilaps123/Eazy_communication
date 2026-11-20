{{--
  Live catalogue offer cards (Home + New eSIM). Same markup and CSS classes as
  the static design. Country, image, badge, "From" price and links come from
  the database. Ratings and provider logos stay as the design's static
  placeholders (decision D7) — they are not real data.
--}}
@php
  $placeholderProviders = [
      ['prov-roamvault.webp', 'RoamVault'], ['prov-nextlink.webp', 'NextLink'], ['prov-true.webp', 'True'],
      ['prov-sparks.webp', 'Sparks'], ['prov-esimgo.webp', 'eSIMGO'], ['prov-airalo.webp', 'Airalo'],
      ['prov-3.webp', '3'], ['prov-sparks.webp', 'Sparks'], ['prov-tsimtech.webp', 'tSimTech'],
      ['prov-airalo.webp', 'Airalo'], ['prov-3.webp', '3'], ['prov-sparks.webp', 'Sparks'],
  ];
@endphp
@foreach ($cards as $card)
@php
  $country = $card['country'];
  [$providerLogo, $providerName] = $placeholderProviders[$loop->index % count($placeholderProviders)];
@endphp
          <article class="offer-card" data-category="{{ $card['category'] }}" data-country="{{ $country->name }}" data-plans="{{ $card['plans_count'] }}">
            <div class="offer-card-media">
              <img src="{{ asset($card['card_image']) }}" alt="{{ $country->name }} eSIM" class="offer-photo" loading="lazy">
              @if ($card['badge'])
              <div class="offer-card-badge">
                <span class="badge badge-{{ $card['category'] }}">{{ $card['badge'] }}</span>
              </div>
              @endif
              <div class="offer-card-country">
                @if ($card['flag_image'])
                <img src="{{ asset($card['flag_image']) }}" alt="{{ $country->name }} flag" width="24" height="24">
                @endif
                <span>{{ $country->name }}</span>
              </div>
              <div class="offer-card-rating">
                <img src="{{ asset('assets/icons/star-card.svg') }}" alt="Rating">
                <span>4.9</span>
              </div>
            </div>
            <div class="offer-card-body">
              <div class="offer-provider-row">
                <div class="provider-info">
                  <img src="{{ asset('assets/images/'.$providerLogo) }}" alt="{{ $providerName }}" class="provider-logo">
                  <div class="provider-details">
                    <h5>{{ $country->name }} eSIM</h5>
                    <p>{{ $providerName }}</p>
                  </div>
                </div>
                @if ($card['has_5g'])
                <img src="{{ asset('assets/icons/icon-5g.svg') }}" alt="5G ready" class="icon-5g">
                @endif
              </div>
              <div class="offer-price-row">
                <span class="price-from">From</span>
                <span class="price-current">{{ $card['price']['formatted'] }}</span>
                @if ($card['price']['compare_at_formatted'])
                <span class="price-original">{{ $card['price']['compare_at_formatted'] }}</span>
                @endif
              </div>
              <div class="offer-card-actions">
                <a href="{{ $card['url'] }}" class="btn btn-outline">View Details</a>
                <a href="{{ $card['url'] }}" class="btn btn-primary">Buy Now</a>
              </div>
            </div>
          </article>

@endforeach
