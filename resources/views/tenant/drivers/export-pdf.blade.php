@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Drivers'),
  'headers' => [__('ID'), __('Driver Name'), __('Affiliation'), __('Driver Availability')],
  'rows' => $drivers->map(fn ($d) => [
    $d->user->code,
    $d->user->name,
    PdfLabels::of($d->user->affiliation),
    PdfLabels::of($d->user->status),
  ]),
])
