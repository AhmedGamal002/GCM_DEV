@extends('layouts/layoutMaster')

@section('title', 'Platform Dashboard')

@section('content')

@include('_partials.breadcrumb', [
  'homeUrl' => route('platform.dashboard'),
  'breadcrumbs' => [
    ['title' => 'Dashboard'],
  ],
])

<div class="row g-6">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h4 class="mb-2">{{ __('Welcome') }}, {{ auth('platform')->user()->name }}</h4>
        <p class="mb-0">Super Admin &mdash; Platform</p>
      </div>
    </div>
  </div>
</div>
@endsection
