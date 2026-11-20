@extends('admin.layouts.app')
@use('App\Support\OrderLifecycle')

@section('title', 'Orders & Fulfillment')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Orders &amp; fulfillment</h1>
      <p class="adm-sub">Payment and eSIM fulfillment status of every order. Read-only — nothing on these pages charges a customer or buys an eSIM.</p>
    </div>
  </div>

  @if ($attention > 0)
    <div class="adm-alert adm-alert-warn" role="status">
      {{ $attention }} {{ \Illuminate\Support\Str::plural('order', $attention) }} {{ $attention === 1 ? 'needs' : 'need' }} attention.
      <a href="{{ route('admin.orders.index', ['fulfilment' => 'needs_attention']) }}">Show</a> ·
      <a href="{{ route('admin.orders.index', ['fulfilment' => 'needs_reconciliation']) }}">Needs reconciliation</a> ·
      <a href="{{ route('admin.orders.index', ['fulfilment' => 'failed']) }}">Failed</a>
    </div>
  @endif

  <form class="adm-card adm-filters" method="GET" action="{{ route('admin.orders.index') }}">
    <div class="adm-field">
      <label for="q">Search</label>
      <input type="text" id="q" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Order number, name or email">
    </div>
    <div class="adm-field">
      <label for="payment">Payment status</label>
      <select id="payment" name="payment">
        <option value="">All</option>
        @foreach (OrderLifecycle::PAYMENT_FILTERS as $value => $label)
          <option value="{{ $value }}" @selected($filters['payment'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-field">
      <label for="status">Order status</label>
      <select id="status" name="status">
        <option value="">All</option>
        @foreach (OrderLifecycle::ORDER_STATUSES as $value => $label)
          <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-field">
      <label for="fulfilment">Fulfillment status</label>
      <select id="fulfilment" name="fulfilment">
        <option value="">All</option>
        @foreach (OrderLifecycle::FULFILMENT_FILTERS as $value => $label)
          <option value="{{ $value }}" @selected($filters['fulfilment'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="adm-actions">
      <button class="adm-btn" type="submit">Filter</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.orders.index') }}">Reset</a>
    </div>
  </form>

  <div class="adm-card adm-table-wrap">
    <p class="adm-hint adm-bundles-count">{{ number_format($orders->total()) }} of {{ number_format($total) }} orders shown</p>
    <table class="adm-table adm-plans-table adm-orders-table">
      <thead>
        <tr>
          <th>Order number</th>
          <th>Customer name</th>
          <th>Customer email</th>
          <th>Order date</th>
          <th>Order total</th>
          <th>Currency</th>
          <th>Payment status</th>
          <th>Order status</th>
          <th>Fulfillment status</th>
          <th><span class="adm-visually-hidden">Action</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($orders as $order)
          @php $stage = OrderLifecycle::stage($order); @endphp
          <tr>
            <td><a href="{{ route('admin.orders.show', $order) }}" class="adm-strong-link adm-code">{{ $order->order_number }}</a></td>
            <td data-label="Customer name">{{ $order->billing_name ?: $order->user?->name }}</td>
            <td data-label="Customer email">{{ $order->billing_email ?: $order->user?->email }}</td>
            <td class="adm-nowrap" data-label="Order date">{{ $order->created_at?->format('d M Y, H:i') }}</td>
            <td class="adm-nowrap" data-label="Order total">{{ number_format((float) $order->total, 2) }}</td>
            <td data-label="Currency">{{ $order->currency }}</td>
            <td data-label="Payment status"><span class="adm-badge adm-badge-{{ OrderLifecycle::paymentTone($order->latest_payment_status) }}">{{ OrderLifecycle::paymentLabel($order->latest_payment_status) }}</span></td>
            <td data-label="Order status"><span class="adm-badge adm-badge-{{ OrderLifecycle::orderTone($order->payment_status) }}">{{ OrderLifecycle::orderLabel($order->payment_status) }}</span></td>
            <td data-label="Fulfillment status"><span class="adm-badge adm-badge-{{ OrderLifecycle::stageTone($stage) }}">{{ OrderLifecycle::stageLabel($stage) }}</span></td>
            <td><a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.orders.show', $order) }}">View details</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="adm-empty">{{ $total === 0 ? 'No orders yet.' : 'No orders match these filters.' }}</td>
          </tr>
        @endforelse
      </tbody>
    </table>
    <div class="adm-pagination">{{ $orders->links('admin.partials.pagination') }}</div>
  </div>
@endsection
