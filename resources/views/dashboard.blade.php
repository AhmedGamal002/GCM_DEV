@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('content')
<div class="row g-6">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h4 class="mb-2">{{ __('Welcome') }}, {{ auth()->user()->name }}</h4>
        <p class="mb-0">
          {{ auth()->user()->tenant->name }} &mdash; {{ auth()->user()->getRoleNames()->first() }}
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
