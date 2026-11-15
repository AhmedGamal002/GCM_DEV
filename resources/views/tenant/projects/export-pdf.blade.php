@php
  use App\Support\PdfLabels;
  $canSeeContracts = (bool) auth()->user()?->can('viewAny', \App\Models\PurchaseOrder::class);
@endphp
@include('exports.pdf-table', [
  'title' => __('Projects'),
  'headers' => [__('ID'), __('Company'), __('Project'), __('Operational region'), __('Contracts'), __('Users'), __('Status'), __('Created At')],
  'rows' => $projects->map(fn ($p) => [
    $p->code,
    $p->company?->name,
    $p->name,
    $p->operational_region,
    // Blank for a role without access to purchase orders (Data Entry), like the list's "—".
    $canSeeContracts ? $p->purchase_orders_count : null,
    0,
    PdfLabels::of($p->operational_status),
    $p->created_at,
  ]),
])
