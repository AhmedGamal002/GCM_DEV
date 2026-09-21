@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Assets'),
  'headers' => [__('ID'), __('Name'), __('Type'), __('Capacity Category'), __('Affiliation'), __('Status'), __('Created At')],
  'rows' => $assets->map(fn ($a) => [
    $a->id,
    $a->name,
    PdfLabels::of($a->asset_type),
    $a->capacityCategory?->name,
    PdfLabels::of($a->affiliation),
    PdfLabels::of($a->operational_status),
    $a->created_at,
  ]),
])
