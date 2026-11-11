<script>
  window.poFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    digits_only: @json(__('Digits only, up to 10.')),
    end_before_start: @json(__('The end date cannot be before the start date.')),
    need_service: @json(__('Add at least one service.')),
    duplicate_service: @json(__('This service is already in this purchase order.')),
    min_trips: @json(__('Cannot be lower than the :count trips already recorded.')),
    service_n: @json(__('Service :n')),
    current_attachment: @json(__('Current attachment (choosing a file replaces it)')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    status_updated: @json(__('Purchase order status updated successfully.')),
    list_url: @json(route('app-po-list')),
    view_url_base: @json(url('/app/po/view'))
  };
  @isset($purchaseOrderId)
    window.poEditId = {{ $purchaseOrderId }};
  @endisset
</script>
