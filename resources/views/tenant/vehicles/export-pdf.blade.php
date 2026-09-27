@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Vehicles'),
  'headers' => [__('ID'), __('Plate'), __('Category'), __('Affiliation'), __('Status'), __('Created At')],
  'rows' => $vehicles->map(fn ($v) => [
    $v->id,
    $v->plate(),
    $v->category?->name(),
    PdfLabels::of($v->affiliation),
    PdfLabels::of($v->operational_status),
    $v->created_at,
  ]),
])
