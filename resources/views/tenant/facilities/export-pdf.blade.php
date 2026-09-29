@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Intermediate Facilities'),
  'headers' => [__('ID'), __('Name'), __('Prefix'), __('Environmental service'), __('Recycling efficiency (%)'), __('Status'), __('Created At')],
  'rows' => $facilities->map(fn ($f) => [
    $f->code,
    $f->name,
    $f->prefix,
    PdfLabels::of($f->environmental_service),
    $f->recycling_efficiency,
    PdfLabels::of($f->operational_status),
    $f->created_at,
  ]),
])
