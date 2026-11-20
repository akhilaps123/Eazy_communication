@extends('admin.layouts.app')

@section('title', 'Edit '.$plan->name)

@section('content')
  <div class="adm-head">
    <div>
      <h1>Edit {{ $plan->name }}</h1>
      <p class="adm-sub"><a href="{{ route('admin.plans.show', $plan) }}">&larr; Back to plan</a> · Prices are managed on the plan page.</p>
    </div>
  </div>

  <form class="adm-card" method="POST" action="{{ route('admin.plans.update', $plan) }}">
    @csrf
    @method('PUT')
    @include('admin.plans._form')
    <div class="adm-actions" style="margin-top:16px">
      <button class="adm-btn" type="submit">Save changes</button>
      <a class="adm-btn adm-btn-light" href="{{ route('admin.plans.show', $plan) }}">Cancel</a>
    </div>
  </form>
@endsection
