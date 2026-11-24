@extends('layouts/layoutMaster')

@section('title', __('Edit trip :code', ['code' => $trip->code]))

@include('tenant.trips._form-scripts', ['mode' => 'edit', 'trip' => $trip])

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Edit trip :code', ['code' => $trip->code]), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-trip-list')],
  ['title' => $trip->code, 'url' => route('app-trip-view', $trip->id)],
  ['title' => __('Edit')],
]])

<div class="card mb-6" id="tf-loading"><div class="card-body text-center py-6"><div class="spinner-border" role="status"></div></div></div>
<div id="tf-form-holder" class="d-none">
  @include('tenant.trips._form', ['mode' => 'edit', 'trip' => $trip])
</div>

@endsection
