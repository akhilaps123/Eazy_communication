@extends('admin.layouts.app')

@section('title', 'Edit '.$country->name)

@section('content')
  <div class="adm-head">
    <div>
      <h1>Edit {{ $country->name }}</h1>
      <p class="adm-sub"><a href="{{ route('admin.countries.show', $country) }}">&larr; Back to country</a></p>
    </div>
  </div>

  <form class="adm-card" method="POST" action="{{ route('admin.countries.update', $country) }}">
    @csrf
    @method('PUT')
    @include('admin.countries._form')
    <div class="adm-actions" style="margin-top:16px">
      <button class="adm-btn" type="submit">Save changes</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.countries.show', $country) }}">Cancel</a>
    </div>
  </form>
@endsection
