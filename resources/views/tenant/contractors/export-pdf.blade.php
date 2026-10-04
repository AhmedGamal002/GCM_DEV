@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Contractors'),
  'headers' => [__('ID'), __('Contractor'), __('Short name'), __('Trading name'), __('Drivers'), __('Vehicles'), __('Containers'), __('Tanks'), __('Status'), __('Created At')],
  'rows' => $contractors->map(fn ($c) => [
    $c->code,
    $c->name,
    $c->prefix,
    $c->trading_name,
    $c->drivers_count,
    $c->vehicles_count,
    $c->containers_count,
    $c->tanks_count,
    PdfLabels::of($c->operational_status),
    $c->created_at,
  ]),
])
