@php use App\Support\PdfLabels; @endphp
@include('exports.pdf-table', [
  'title' => __('Users'),
  'headers' => [__('ID'), __('Name'), __('Affiliation'), __('Entity'), __('Role'), __('Status')],
  'rows' => $users->map(fn ($u) => [
    $u->code,
    $u->name,
    PdfLabels::of($u->affiliation),
    $u->entityName() ?? '',
    $u->getRoleNames()->map(fn ($r) => PdfLabels::of($r))->implode(', '),
    PdfLabels::of($u->status),
  ]),
])
