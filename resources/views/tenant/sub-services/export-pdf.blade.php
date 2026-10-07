@php use App\Models\SubService; use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Sub-services'),
  'headers' => [__('Sub-service name'), __('Main service'), __('Type'), __('Compatible capacities'), __('Intermediate facilities'), __('Requires recycle receipt'), __('Skip Color Code'), __('Status'), __('Created At')],
  'rows' => $subServices->map(fn ($s) => [
    $s->name,
    $s->mainService?->name,
    PdfLabels::of($s->asset_type),
    $s->capacities->pluck('name')->implode(', '),
    $s->facilities->pluck('name')->implode(', '),
    $s->requires_recycle_receipt ? __('Yes') : __('No'),
    SubService::skipColorLabel($s->skip_color_code),
    PdfLabels::of($s->operational_status),
    $s->created_at,
  ]),
])
