@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Main Services'),
  'headers' => [__('Main service name'), __('Type of Service'), __('Supported Vehicle Categories'), __('Sub-services'), __('Status'), __('Created At')],
  'rows' => $services->map(fn ($s) => [
    $s->name,
    PdfLabels::of($s->service_type),
    // Lists go in as arrays: one item per line, so a long list makes the row taller, not the table wider.
    $s->vehicleCategories->map(fn ($c) => $c->name())->all(),
    $s->subServices->pluck('name')->all(),
    PdfLabels::of($s->operational_status),
    $s->created_at,
  ]),
])
