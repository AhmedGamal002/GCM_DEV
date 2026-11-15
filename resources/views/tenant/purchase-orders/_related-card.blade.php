{{-- The "Contracts" section of a company's / project's page. Rendered only for a role that may view purchase orders:
     the FRD gives Data Entry no access to them at all, not even to read. $showProject: a company's page lists each PO's project. --}}
@can('viewAny', \App\Models\PurchaseOrder::class)
  <div class="card">
    <h5 class="card-header">{{ __('Contracts') }}</h5>
    <div class="card-header border-top pt-4 pb-0">
      <div class="row">
        <div class="col-md-4 related_po_status"></div>
      </div>
    </div>
    <div class="card-datatable table-responsive">
      <table class="datatables-related-pos table">
        <thead class="border-top">
          <tr>
            <th></th>
            <th>{{ __('ID') }}</th>
            @if($showProject ?? false)
              <th>{{ __('Project') }}</th>
            @endif
            <th>{{ __('Duration') }}</th>
            <th>{{ __('Days left') }}</th>
            <th>{{ __('Items status') }}</th>
            <th>{{ __('Account status') }}</th>
            <th>{{ __('Details') }}</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
@endcan
