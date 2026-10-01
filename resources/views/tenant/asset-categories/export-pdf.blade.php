@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Assets Capacities'),
  'headers' => [__('ID'), __('Name'), __('Applies To'), __('Capacity (CBM)'), __('Capacity (TON)'), __('Created At')],
  'rows' => $categories->map(fn ($c) => [
    $c->id,
    $c->name,
    PdfLabels::of($c->applies_to),
    $c->capacity_cbm,
    $c->capacity_ton,
    $c->created_at,
  ]),
])
