@php
  use App\Domain\Trips\Exports\TripsExport;
  use App\Support\PdfLabels;
@endphp
@include('exports.pdf-table', [
  'title' => __('Trips'),
  'headers' => [__('Trip No.'), __('Date'), __('Driver'), __('Driver affiliation'), __('Vehicle'), __('Vehicle affiliation'), __('Client'), __('Project'), __('Sub-service'), __('Status'), __('PO')],
  'rows' => $trips->map(fn ($trip) => TripsExport::row($trip, auth()->user(), fn ($v) => PdfLabels::of($v))),
])
