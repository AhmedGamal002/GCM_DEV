@include('exports.pdf-table', [
  'title' => __('Vehicle Categories'),
  'headers' => [__('ID'), __('Name (English)'), __('Name (Arabic)'), __('Vehicles'), __('Created At')],
  'rows' => $categories->map(fn ($c) => [
    $c->id,
    $c->name_en,
    $c->name_ar,
    $c->vehicles_count,
    $c->created_at,
  ]),
])
