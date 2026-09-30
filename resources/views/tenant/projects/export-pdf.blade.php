@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Projects'),
  'headers' => [__('ID'), __('Company'), __('Project'), __('Operational region'), __('Contracts'), __('Users'), __('Status'), __('Created At')],
  'rows' => $projects->map(fn ($p) => [
    $p->code,
    $p->company?->name,
    $p->name,
    $p->operational_region,
    // Contracts and client users don't exist yet (FRD §1.13 / §1.4).
    0,
    0,
    PdfLabels::of($p->operational_status),
    $p->created_at,
  ]),
])
