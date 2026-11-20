@extends('admin.layouts.app')

@section('title', 'Countries')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Countries</h1>
      <p class="adm-sub">Destinations customers can choose. A country used by a plan can be deactivated but not deleted.</p>
    </div>
    <a class="adm-btn" href="{{ route('admin.countries.create') }}">Add country</a>
  </div>

  <form class="adm-card adm-filters" method="GET" action="{{ route('admin.countries.index') }}">
    <div class="adm-field">
      <label for="q">Search</label>
      <input type="text" id="q" name="q" value="{{ $search }}" placeholder="Name or ISO2 code">
    </div>
    <div class="adm-field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <option value="active" @selected($status === 'active')>Active</option>
        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
      </select>
    </div>
    <div class="adm-actions">
      <button class="adm-btn" type="submit">Filter</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.countries.index') }}">Reset</a>
    </div>
  </form>

  <div class="adm-card adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr><th>Name</th><th>ISO2</th><th>Plans</th><th>Status</th><th>Order</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($countries as $country)
          <tr>
            <td>
              <a href="{{ route('admin.countries.show', $country) }}">{{ $country->name }}</a>
              @if ($country->is_popular) <span class="adm-badge">Popular</span> @endif
            </td>
            <td>{{ $country->iso2 }}</td>
            <td>{{ $country->plans_count }}</td>
            <td>
              @if ($country->is_active)
                <span class="adm-badge adm-badge-ok">Active</span>
              @else
                <span class="adm-badge">Inactive</span>
              @endif
            </td>
            <td>{{ $country->sort_order }}</td>
            <td class="adm-actions">
              <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.countries.edit', $country) }}">Edit</a>
              <form method="POST" action="{{ route('admin.countries.toggle', $country) }}">
                @csrf
                <button class="adm-btn adm-btn-light adm-btn-sm" type="submit">{{ $country->is_active ? 'Deactivate' : 'Activate' }}</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="adm-empty">No countries found.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="adm-pagination">{{ $countries->links('admin.partials.pagination') }}</div>
  </div>
@endsection
