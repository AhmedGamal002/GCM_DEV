@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Client Companies'),
  'headers' => [__('ID'), __('Company'), __('Short name'), __('Business sector'), __('Projects'), __('Users'), __('Status'), __('Created At')],
  'rows' => $companies->map(fn ($c) => [
    $c->code,
    $c->name,
    $c->prefix,
    $c->business_sector,
    // Projects and client users don't exist yet (FRD §1.12 / §1.4).
    0,
    0,
    PdfLabels::of($c->operational_status),
    $c->created_at,
  ]),
])
