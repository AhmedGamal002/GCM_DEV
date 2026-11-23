{{-- The legal confirmation before the client approves / rejects a trip (FRD §1.14) — filled and opened by the page's JS. --}}
<div class="modal fade" id="client-trip-confirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-confirm-title></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <div class="alert d-flex align-items-start gap-2 mb-0" data-confirm-box>
          <i class="ti ti-alert-triangle ti-md mt-1"></i>
          <span data-confirm-message></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn" data-confirm-ok>{{ __('Confirm') }}</button>
      </div>
    </div>
  </div>
</div>
