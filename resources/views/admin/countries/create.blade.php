@extends('admin.layouts.app')

@section('title', 'Add country')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Add country</h1>
      <p class="adm-sub"><a href="{{ route('admin.countries.index') }}">&larr; Countries</a></p>
    </div>
  </div>

  <form class="adm-card" method="POST" action="{{ route('admin.countries.store') }}">
    @csrf
    @include('admin.countries._form')
    <div class="adm-actions" style="margin-top:16px">
      <button class="adm-btn" type="submit">Create country</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.countries.index') }}">Cancel</a>
    </div>
  </form>
@endsection
