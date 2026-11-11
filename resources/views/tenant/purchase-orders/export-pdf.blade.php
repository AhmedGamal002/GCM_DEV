@php
  use App\Domain\PurchaseOrders\Support\PurchaseOrderStates;
  use App\Support\PdfLabels;
@endphp
@include('exports.pdf-table', [
  'title' => __('Purchase Orders'),
  'headers' => [__('PO No.'), __('Client company'), __('Project'), __('From'), __('To'), __('Days left'), __('Items status'), __('Account status')],
  'rows' => $purchaseOrders->map(function ($po) {
    $state = PurchaseOrderStates::of($po);

    return [
      $po->number,
      $po->company?->name,
      $po->project?->name,
      $po->starts_on?->toDateString(),
      $po->ends_on?->toDateString(),
      $state['days_label'],
      $state['status_label'],
      PdfLabels::of($po->operational_status),
    ];
  }),
])
