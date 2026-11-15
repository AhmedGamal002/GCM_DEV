{{-- The strings and flags the company / project pages' "Contracts" table needs (resources/assets/js/po/related-table.js). --}}
@can('viewAny', \App\Models\PurchaseOrder::class)
<script>
  window.poRelatedTranslations = {
    all_item_statuses: @json(__('All items statuses')),
    valid: @json(__('In force')),
    near_exhaustion: @json(__('About to run out')),
    exhausted: @json(__('Fully exhausted')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    export: @json(__('Export')),
    add_po: @json(__('Add Purchase Order')),
    search_po: @json(__('Search Purchase Order')),
    no_pos_found: @json(__('No purchase orders found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    can_manage: @json(auth()->user()->can('create', \App\Models\PurchaseOrder::class)),
    can_export: @json(auth()->user()->can('export', \App\Models\PurchaseOrder::class)),
    add_po_url: @json(route('app-po-add')),
    view_url_base: @json(url('/app/po/view'))
  };
</script>
@endcan
