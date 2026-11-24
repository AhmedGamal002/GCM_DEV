@extends('layouts/layoutMaster')

@section('title', __('Trip :code', ['code' => $trip->code]))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
])
@endsection

@section('page-script')
<script>
  window.tripViewConfig = {
    id: @json($trip->id),
    apiBase: @json(url('/api/v1/trips')),
    editUrl: @json(route('app-trip-edit', $trip->id)),
    listUrl: @json(route('app-trip-list')),
    canManage: @json($canManage),
    statuses: @json(collect(\App\Models\Trip::STATUSES)->mapWithKeys(fn ($s) => [$s => \App\Models\Trip::statusLabel($s)])),
    t: {
      ton: @json(__('ton')),
      cbm: @json(__('m³')),
      gcm: @json(__('GCM')),
      without_po: @json(__('Without a PO')),
      open_map: @json(__('Open in Google Maps')),
      yes: @json(__('Yes')),
      no: @json(__('No')),
      reminder_sent: @json(__('The reminder was sent to :count.')),
      cancel_purpose: @json(__('Cancelling the trip')),
      cancel_done: @json(__('The trip was cancelled.')),
      info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
      info_empty: @json(__('Showing 0 to 0 of 0 entries')),
      search: @json(__('Search')),
      no_events: @json(__('No history yet.')),
      changed_from: @json(__('from')),
      changed_to: @json(__('to')),
      empty: @json(__('empty'))
    }
  };
</script>
@vite(['resources/assets/js/app-trip-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Trip :code', ['code' => $trip->code]), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-trip-list')],
  ['title' => $trip->code],
]])

<div class="alert alert-success d-none" id="tv-flash"></div>
<div class="alert alert-danger d-none" id="tv-error"></div>

<div class="row">
  {{-- Left: the trip at a glance --}}
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class="d-flex align-items-center flex-column">
            <div class="avatar avatar-xl mb-4"><span class="avatar-initial rounded-circle bg-label-primary"><i class="ti ti-truck-delivery ti-xl"></i></span></div>
            <div class="user-info text-center">
              <h5 class="mb-1" dir="ltr" data-v="code">—</h5>
              <span class="badge bg-label-secondary" id="tv-status-badge"></span>
            </div>
          </div>
        </div>
        <h5 class="pb-4 border-bottom mt-6 mb-4">{{ __('Details') }}</h5>
        <ul class="list-unstyled my-3 py-1">
          @foreach ([
            'date' => __('Date & Time'), 'driver' => __('Driver'), 'vehicle' => __('Vehicle'),
            'going' => __('Going container / tank'), 'returning' => __('Returning container / tank'),
            'company' => __('Client company'), 'project' => __('Project'), 'representative' => __('Field officer in the project'),
            'location' => __('Client location'), 'po' => __('Purchase order'),
            'main_service' => __('Main service'), 'sub_service' => __('Sub-service'), 'capacity' => __('Container capacity'),
            'quantity' => __('Waste quantity'), 'facility' => __('Waste disposal & recycle facility'),
            'trip_cost' => __('Trip cost'), 'ancillary_cost' => __('Ancillary services cost'),
            'client_approver' => __('Default client approver'), 'gcm_approver' => __('Default GCM approver'),
            'updated' => __('Last updated'),
          ] as $key => $label)
            <li class="d-flex flex-wrap mb-3" data-row="{{ $key }}">
              <span class="fw-medium me-2">{{ $label }}:</span>
              <span data-v="{{ $key }}">—</span>
            </li>
          @endforeach
        </ul>
        <div class="d-flex flex-wrap justify-content-center gap-2 pt-2">
          <a href="#" class="btn btn-primary d-none" id="tv-edit">{{ __('Edit') }}</a>
          <button type="button" class="btn btn-outline-primary d-none" id="tv-remind"><i class="ti ti-mail-forward me-1"></i>{{ __('Remind the client to approve') }}</button>
          <button type="button" class="btn btn-outline-danger d-none" id="tv-cancel"><i class="ti ti-ban me-1"></i>{{ __('Cancel the trip') }}</button>
          <a href="{{ route('app-trip-list') }}" class="btn btn-label-secondary">{{ __('Back') }}</a>
        </div>
      </div>
    </div>
  </div>

  {{-- Right: photo, documents, the client's remarks, history --}}
  <div class="col-xl-8 col-lg-7">
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Live photo from the site') }}</h5>
      <div class="card-body" id="tv-photo">—</div>
    </div>

    <div class="card mb-6">
      <h5 class="card-header">{{ __('Documents') }}</h5>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Number') }}</th><th>{{ __('Preview') }}</th></tr></thead>
            <tbody>
              @foreach (['delivery-note' => __('Delivery Note'), 'waste-manifest' => __('Waste Manifest'), 'recycle-receipt' => __('Recycle Receipt'), 'other' => __('Other attachments')] as $kind => $label)
                <tr>
                  <td>{{ $label }}</td>
                  <td dir="ltr" data-number="{{ $kind }}">—</td>
                  <td data-file="{{ $kind }}">—</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mb-6">
      <h5 class="card-header">{{ __('The client\'s remarks') }}</h5>
      <div class="card-body">
        <div class="mb-3"><span class="fw-medium">{{ __('The client\'s comment') }}:</span> <span id="tv-client-comment">—</span></div>
        <div><span class="fw-medium">{{ __('The client\'s attachment') }}:</span> <span data-file="client-attachment">—</span></div>
      </div>
    </div>

    <div class="card mb-6" id="tv-additional-card">
      <h5 class="card-header">{{ __('Additional data') }}</h5>
      <div class="card-body" id="tv-additional"></div>
    </div>

    {{-- ج8: the trip's history, for GCM's staff only (the API sends no `events` to anyone else). --}}
    <div class="card mb-6 d-none" id="tv-history-card">
      <h5 class="card-header">{{ __('Trip history') }}</h5>
      <div class="card-datatable table-responsive">
        <table class="table" id="tv-history">
          <thead>
            <tr><th>{{ __('Date & Time') }}</th><th>{{ __('Event') }}</th><th>{{ __('By') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reason / changes') }}</th></tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- Cancelling needs a confirmation (FRD) and the e-mailed code (the request carries it). --}}
<div class="modal fade" id="tv-cancel-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Cancel the trip') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning d-flex align-items-start gap-2">
          <i class="ti ti-alert-triangle ti-md mt-1"></i>
          <span>{{ __('Cancelling the trip treats it as if it never happened: its quota and containers are given back. This cannot be undone.') }}</span>
        </div>
        <label class="form-label" for="tv-cancel-reason">{{ __('Reason (optional)') }}</label>
        <textarea class="form-control" id="tv-cancel-reason" rows="3" maxlength="2000"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Back') }}</button>
        <button type="button" class="btn btn-danger" id="tv-cancel-ok">{{ __('Cancel the trip') }}</button>
      </div>
    </div>
  </div>
</div>

@endsection
