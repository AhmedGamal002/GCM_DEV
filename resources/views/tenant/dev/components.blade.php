@extends('layouts/layoutMaster')

@section('title', 'Browser components')

@section('page-script')
@vite('resources/assets/js/dev-components.js')
@endsection

@section('content')

{{-- Developer try-out page (SHOW_COMPONENT_DEMOS, never in production): English only on purpose, like the Platform pages. --}}
@include('_partials.breadcrumb', ['pageTitle' => 'Browser components', 'breadcrumbs' => [
  ['title' => 'Components'],
]])

<div class="alert alert-info">
  The camera and the location need HTTPS (or localhost) and a real device to be tried properly — open this page on a phone.
</div>

<div class="card mb-6">
  <h5 class="card-header">1. Live location → Google Maps link</h5>
  <div class="card-body">
    <button type="button" class="btn btn-primary" id="demo-geo-btn"><i class="ti ti-map-pin me-1"></i>Get my location</button>
    <div class="mt-4 d-none" id="demo-geo-result">
      <p class="mb-1">Coordinates: <span dir="ltr" id="demo-geo-coords"></span> (±<span id="demo-geo-accuracy"></span> m)</p>
      <a href="#" target="_blank" rel="noopener" id="demo-geo-link">Open in Google Maps</a>
    </div>
    <div class="text-danger mt-3 d-none" id="demo-geo-error"></div>
  </div>
</div>

<div class="card mb-6">
  <h5 class="card-header">2. Live camera with date, time and location stamp</h5>
  <div class="card-body">
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="demo-cam-withloc" checked />
      <label class="form-check-label" for="demo-cam-withloc">Stamp the live location too</label>
    </div>
    <button type="button" class="btn btn-primary" id="demo-cam-btn"><i class="ti ti-camera me-1"></i>Open camera</button>
    <div class="mt-4 d-none" id="demo-cam-result">
      <p class="mb-2 text-muted small" id="demo-cam-info"></p>
      <img id="demo-cam-img" alt="" class="img-fluid rounded border" style="max-height: 360px" />
    </div>
    <div class="text-danger mt-3 d-none" id="demo-cam-error"></div>
  </div>
</div>

<div class="card mb-6">
  <h5 class="card-header">3. Signature pad (PNG, up to 2 MB)</h5>
  <div class="card-body">
    <div id="demo-signature" style="max-width: 600px"></div>
    <div class="mt-3 d-flex gap-2">
      <button type="button" class="btn btn-label-secondary" id="demo-sig-clear"><i class="ti ti-eraser me-1"></i>Clear</button>
      <button type="button" class="btn btn-primary" id="demo-sig-export" disabled><i class="ti ti-check me-1"></i>Export PNG</button>
    </div>
    <div class="mt-4 d-none" id="demo-sig-result">
      <p class="mb-2 text-muted small" id="demo-sig-info"></p>
      <img id="demo-sig-img" alt="" class="border rounded" style="background: repeating-conic-gradient(#eee 0 25%, #fff 0 50%) 0 / 16px 16px; max-width: 100%" />
    </div>
    <div class="text-danger mt-3 d-none" id="demo-sig-error"></div>
  </div>
</div>
@endsection
