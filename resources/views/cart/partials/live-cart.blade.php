{{--
  Live cart (Phase 7) — same structure and CSS classes as the static design.
  Every amount is calculated on the server (CartService); the browser only
  submits quantities, removals and the optional GSTIN through CSRF-protected forms.
--}}
@php
  $max = \App\Services\CartService::MAX_QUANTITY;
  $canCheckout = ! $cart['is_empty'] && ! $cart['has_unavailable'];
@endphp
    <section class="cart-section" id="cart" data-cart-live>
      <div class="cart-container">

        <!-- Active Cart Contents -->
        <div class="cart-content-wrap" id="cartContentWrap" @if ($cart['is_empty']) style="display: none;" @endif>

          <div class="cart-table-header" aria-hidden="true">
            <p class="cart-th cart-th-product">Product</p>
            <p class="cart-th cart-th-price">Price</p>
            <p class="cart-th cart-th-qty">Quantity</p>
            <p class="cart-th cart-th-total">Total</p>
          </div>

          <div class="cart-items-list">
            @if (session('status'))
              <div class="cart-deal-alert" role="status">
                <span class="cart-deal-text">{{ session('status') }}</span>
              </div>
            @endif
            @if ($errors->any())
              <div class="cart-deal-alert cart-live-error" role="alert">
                <img src="{{ asset('assets/icons/alarm-clock.svg') }}" alt="" class="cart-deal-icon" aria-hidden="true" width="22" height="22">
                <span class="cart-deal-text">{{ $errors->first() }}</span>
              </div>
            @endif

            @foreach ($cart['lines'] as $line)
              @php
                $item = $line['item'];
                $qty = $line['quantity'];
                $thumb = $line['plan']->countries->first()?->imagePath('card') ?? config('catalogue.fallback_images.card');
                $countries = collect($line['countries']);
                $qtyForm = 'cart-qty-'.$item->id;
                $removeForm = 'cart-remove-'.$item->id;
              @endphp
              <article class="cart-item" id="cartItem{{ $item->id }}" data-cart-item="{{ $item->id }}">
                <div class="cart-product-cell">
                  <div class="cart-product-main">
                    <div class="cart-product-thumb">
                      <img src="{{ asset($thumb) }}" alt="{{ $line['title'] }}" width="84" height="84">
                    </div>
                    <div class="cart-product-details">
                      <h2 class="cart-product-title">{{ $line['title'] }}</h2>
                      <p class="cart-product-meta">{{ $line['subtitle'] }}</p>
                      @if ($countries->count() > 1)
                        <p class="cart-product-meta">Covers: {{ $countries->take(5)->join(', ') }}{{ $countries->count() > 5 ? ' +'.($countries->count() - 5).' more' : '' }}</p>
                      @endif
                      @if ($item->trip_start_date)
                        <p class="cart-product-meta">Trip Start Date: {{ $item->trip_start_date->format('Y-m-d') }}</p>
                      @endif
                    </div>
                  </div>
                  @unless ($line['available'])
                    <div class="cart-deal-alert cart-live-error" role="status">
                      <img src="{{ asset('assets/icons/alarm-clock.svg') }}" alt="" class="cart-deal-icon" aria-hidden="true" width="22" height="22">
                      <span class="cart-deal-text">No longer available – remove this item to continue.</span>
                    </div>
                  @endunless
                </div>

                <!-- Desktop Price Cell -->
                <div class="cart-price-cell cart-desktop-cell">
                  @if ($line['available'])
                    @if ($line['price']['compare_at_formatted'])
                      <span class="cart-price-original">{{ $line['price']['compare_at_formatted'] }}</span>
                    @endif
                    <span class="cart-price-current">{{ $line['price']['formatted'] }}</span>
                  @else
                    <span class="cart-price-current">—</span>
                  @endif
                </div>

                <!-- Desktop Quantity Cell -->
                <div class="cart-qty-cell cart-desktop-cell">
                  @if ($line['available'])
                    <div class="cart-qty-control" role="group" aria-label="Quantity">
                      <button type="submit" form="{{ $qtyForm }}" name="quantity" value="{{ $qty - 1 }}" class="qty-btn qty-btn-decrease" aria-label="Decrease quantity" @if ($qty <= 1) disabled style="opacity: 0; pointer-events: none;" @endif>−</button>
                      <span class="qty-num" aria-live="polite">{{ $qty }}</span>
                      <button type="submit" form="{{ $qtyForm }}" name="quantity" value="{{ $qty + 1 }}" class="qty-btn qty-btn-increase" aria-label="Increase quantity" @if ($qty >= $max) disabled style="opacity: .35; pointer-events: none;" title="Maximum {{ $max }} per plan and trip date" @endif>
                        <img src="{{ asset('assets/icons/cart-plus.svg') }}" alt="Increase" width="13" height="13">
                      </button>
                    </div>
                  @endif
                  <button type="submit" form="{{ $removeForm }}" class="btn-remove-item" aria-label="Remove {{ $line['title'] }} from cart">
                    <span>Remove</span>
                    <img src="{{ asset('assets/icons/cart-remove-x.svg') }}" alt="" width="13" height="13" aria-hidden="true">
                  </button>
                </div>

                <!-- Desktop Total Cell -->
                <div class="cart-item-total-cell cart-desktop-cell">
                  @if ($line['available'])
                    @if ($line['compare_total_formatted'])
                      <span class="cart-item-total-original">{{ $line['compare_total_formatted'] }}</span>
                    @endif
                    <span class="cart-item-total-current">{{ $line['line_total_formatted'] }}</span>
                  @else
                    <span class="cart-item-total-current">—</span>
                  @endif
                </div>

                <!-- Mobile-Only Compact Controls Bar -->
                <div class="cart-mobile-item-bar">
                  <div class="cart-price-cell">
                    <span class="cart-mobile-label">Price</span>
                    <span class="cart-price-current">{{ $line['available'] ? $line['price']['formatted'] : '—' }}</span>
                  </div>
                  <div class="cart-qty-cell">
                    @if ($line['available'])
                      <div class="cart-qty-control" role="group" aria-label="Quantity">
                        <button type="submit" form="{{ $qtyForm }}" name="quantity" value="{{ $qty - 1 }}" class="qty-btn qty-btn-decrease" aria-label="Decrease quantity" @if ($qty <= 1) disabled style="opacity: 0; pointer-events: none;" @endif>−</button>
                        <span class="qty-num" aria-live="polite">{{ $qty }}</span>
                        <button type="submit" form="{{ $qtyForm }}" name="quantity" value="{{ $qty + 1 }}" class="qty-btn qty-btn-increase" aria-label="Increase quantity" @if ($qty >= $max) disabled style="opacity: .35; pointer-events: none;" @endif>
                          <img src="{{ asset('assets/icons/cart-plus.svg') }}" alt="" width="13" height="13">
                        </button>
                      </div>
                    @endif
                    <button type="submit" form="{{ $removeForm }}" class="btn-remove-item" aria-label="Remove item">
                      <span>Remove</span>
                      <img src="{{ asset('assets/icons/cart-remove-x.svg') }}" alt="" width="13" height="13" aria-hidden="true">
                    </button>
                  </div>
                  <div class="cart-item-total-cell">
                    <span class="cart-mobile-label">Total</span>
                    <span class="cart-item-total-current">{{ $line['available'] ? $line['line_total_formatted'] : '—' }}</span>
                  </div>
                </div>

                {{-- Forms used by the buttons above (HTML "form" attribute) --}}
                <form id="{{ $qtyForm }}" method="POST" action="{{ route('cart.items.update', $item->id) }}" hidden>
                  @csrf
                  @method('PATCH')
                </form>
                <form id="{{ $removeForm }}" method="POST" action="{{ route('cart.items.destroy', $item->id) }}" hidden>
                  @csrf
                  @method('DELETE')
                </form>
              </article>
            @endforeach
          </div>

          <!-- Bottom Summary & Checkout Row -->
          <div class="cart-summary-row">
            <h3 class="cart-summary-label">Total</h3>
            <div class="cart-summary-actions">
              <span class="cart-summary-amount" id="cartSummaryAmount">{{ $cart['subtotal_formatted'] }}</span>

              {{-- GSTIN: stored with the cart and order only — no tax is calculated --}}
              <button type="button" class="btn-gst-pill" id="btnGstPill" aria-label="Enter GST Number" @if ($cart['gstin'] || $errors->has('gstin')) style="display: none;" @endif>
                Enter GST Number (If Applicable)
              </button>
              <div class="gst-input-box" id="gstInputBox" @unless ($errors->has('gstin')) style="display: none;" @endunless>
                <div class="gst-input-inner">
                  <input type="text" id="gstNumberInput" name="gstin" form="cartGstinForm" class="gst-input-field" placeholder="Enter GST Number" maxlength="15" autocomplete="off" spellcheck="false" aria-label="GST Number" value="{{ old('gstin') }}">
                  <button type="submit" form="cartGstinForm" id="gstApplyBtn" class="btn-gst-apply">Apply</button>
                  <button type="button" id="gstCancelBtn" class="btn-gst-cancel" aria-label="Cancel">✕</button>
                </div>
                <p @class(['gst-feedback-msg', 'error' => $errors->has('gstin')]) id="gstFeedbackMsg">{{ $errors->first('gstin') }}</p>
              </div>
              <div class="gst-applied-card" id="gstAppliedCard" @unless ($cart['gstin'] && ! $errors->has('gstin')) style="display: none;" @else style="display: flex;" @endunless>
                <div class="gst-applied-info">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  <span>GST: <span class="gst-applied-num" id="gstAppliedNum">{{ $cart['gstin'] }}</span></span>
                </div>
                <button type="submit" form="cartGstinRemoveForm" class="btn-gst-remove" id="btnGstRemove" aria-label="Remove GST" title="Remove GST">✕</button>
              </div>
              <form id="cartGstinForm" method="POST" action="{{ route('cart.gstin') }}" hidden>@csrf</form>
              <form id="cartGstinRemoveForm" method="POST" action="{{ route('cart.gstin') }}" hidden>@csrf<input type="hidden" name="gstin" value=""></form>

              @if ($canCheckout)
                <a href="{{ route('checkout') }}" class="btn-checkout-cta" aria-label="Proceed to Checkout">
                  Checkout
                </a>
              @else
                <span class="btn-checkout-cta" aria-disabled="true" style="opacity: .5; pointer-events: none;">Checkout</span>
                <span role="note" style="font-size: 13.4px; color: #b91c1c;">Remove unavailable items to continue.</span>
              @endif
              <a href="{{ route('new-esim') }}" class="cart-continue-link">
                Continue shopping
              </a>
            </div>
          </div>
        </div>

        <!-- Empty Cart State -->
        <div @class(['cart-empty-state', 'active' => $cart['is_empty']]) id="cartEmptyState" role="region" aria-live="polite">
          @if ($cart['is_empty'] && $errors->any())
            <p class="cart-empty-desc" role="alert">{{ $errors->first() }}</p>
          @elseif ($cart['is_empty'] && session('status'))
            <p class="cart-empty-desc" role="status">{{ session('status') }}</p>
          @endif
          <img src="{{ asset('assets/icons/cart-bag.svg') }}" alt="" class="cart-empty-icon" aria-hidden="true">
          <h2 class="cart-empty-title">Your Cart is Empty</h2>
          <p class="cart-empty-desc">Explore our top-value eSIM destinations and find the perfect data plan for your next journey.</p>
          <a href="{{ route('new-esim') }}" class="btn btn-primary" style="height: 48px; padding: 0 32px; border-radius: 42px; font-size: 15px;">
            Explore eSIM Plans
          </a>
        </div>
      </div>
    </section>
