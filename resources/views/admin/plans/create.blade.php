@extends('admin.layouts.app')

@section('title', 'Create plan')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Create plan</h1>
      <p class="adm-sub"><a href="{{ route('admin.plans.index') }}">&larr; Plans</a> · New plans start inactive.</p>
    </div>
  </div>

  <form class="adm-card" method="POST" action="{{ route('admin.plans.store') }}">
    @csrf
    @include('admin.plans._form')
    <div class="adm-actions" style="margin-top:16px">
      <button class="adm-btn" type="submit">Create plan</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.plans.index') }}">Cancel</a>
    </div>
  </form>
@endsection
