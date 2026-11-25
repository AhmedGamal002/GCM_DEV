{{--
  The trips of one vehicle / one driver, for their details page (FRD V01.14 §1.5 / §1.6). Params: $kind ('vehicle' |
  'driver'), $entityId. Rows come from /api/v1/trips with the vehicle_id / driver_id fixed (trip/entity-trips.js), so
  who sees which trip and which fields is the trips list's own rule. The client / project / driver / service pickers
  read lists a contractor user may not read, so they are left out for him.
--}}
@php
  $viewer = auth()->user();
  $byVehicle = $kind === 'vehicle';
  $refFilters = ! $viewer->isContractorUser();
  $viewUrlBase = $viewer->isContractorUser() ? url('/app/contractor-trip/view') : url('/app/trip/view');
@endphp

<script>
  window.entityTripsConfig = {
    kind: @json($kind),
    entityId: {{ (int) $entityId }},
    viewUrlBase: @json($viewUrlBase),
    refFilters: @json($refFilters),
    t: {
      statuses: @json(collect(\App\Models\Trip::STATUSES)->mapWithKeys(fn ($s) => [$s => \App\Models\Trip::statusLabel($s)])),
      driver: @json(__('Driver')),
      vehicle: @json(__('Vehicle')),
      view: @json(__('View')),
      search: @json(__('Search Trip')),
      no_trips: @json(__('No trips found.')),
      no_permission: @json(__("You don't have permission to view this data.")),
      export: @json(__('Export')),
      all_projects: @json(__('All projects')),
      all_clients: @json(__('All clients')),
      all_drivers: @json(__('All drivers')),
      all_services: @json(__('All main services')),
      info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
      info_empty: @json(__('Showing 0 to 0 of 0 entries'))
    }
  };
</script>

<div class="card mb-6" id="et-card">
  <h5 class="card-header">{{ __('Trips') }}</h5>
  <div class="card-body border-bottom">
    <div class="row g-4">
      <div class="col-md-3"><input type="date" class="form-control" id="et-date-from" aria-label="{{ __('Duration from') }}" title="{{ __('Duration from') }}"></div>
      <div class="col-md-3"><input type="date" class="form-control" id="et-date-to" aria-label="{{ __('Duration to') }}" title="{{ __('Duration to') }}"></div>
      <div class="col-md-3">
        <select class="form-select" id="et-status" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option></select>
      </div>
      @if ($refFilters)
        <div class="col-md-3"><select class="form-select" id="et-company"><option value=""></option></select></div>
        <div class="col-md-3"><select class="form-select" id="et-project"><option value=""></option></select></div>
        <div class="col-md-3">
          @if ($byVehicle)
            <select class="form-select" id="et-driver"><option value=""></option></select>
          @else
            <select class="form-select" id="et-service"><option value=""></option></select>
          @endif
        </div>
      @endif
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="table" id="et-table">
      <thead class="border-top">
        <tr>
          <th>{{ __('Trip No.') }}</th>
          <th>{{ __('Date') }}</th>
          <th>{{ $byVehicle ? __('Driver') : __('Vehicle') }}</th>
          <th>{{ __('Client') }}</th>
          <th>{{ __('Project') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>
