@php
  use App\Domain\Trips\Exports\TripsExport;
  use App\Support\PdfLabels;
@endphp
@include('exports.pdf-table', [
  'title' => __('Trips'),
  'headers' => array_map(fn ($h) => __($h), TripsExport::headers(auth()->user())),
  'rows' => $trips->map(fn ($trip) => TripsExport::row($trip, auth()->user(), fn ($v) => PdfLabels::of($v))),
])
