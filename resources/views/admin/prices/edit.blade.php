@extends('admin.layouts.app')

@section('title', 'Edit pending price')

@section('content')
  <div class="adm-head">
    <div>
      <h1>Edit pending price</h1>
      <p class="adm-sub"><a href="{{ route('admin.plans.show', $plan) }}">&larr; {{ $plan->name }}</a> · Only pending prices can be edited.</p>
    </div>
  </div>

  <form class="adm-card" method="POST" action="{{ route('admin.plans.prices.update', [$plan, $price]) }}">
    @csrf
    @method('PUT')
    @include('admin.prices._form')
  </form>
@endsection
