@extends('layouts/layoutMaster')

@section('title', __('Create a new trip'))

@include('tenant.trips._form-scripts', ['mode' => 'add'])

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create a new trip'), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-trip-list')],
  ['title' => __('Add')],
]])

@include('tenant.trips._form', ['mode' => 'add'])

@endsection
