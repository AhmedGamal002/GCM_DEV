{{-- Vendor assets + wording for resources/assets/js/app-trip-form.js. $mode = 'add' | 'edit'; $trip only on edit. --}}
@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/quill/typography.scss',
  'resources/assets/vendor/libs/quill/editor.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/quill/quill.js'
])
@endsection

@section('page-script')
<script>
  window.tripFormConfig = {
    mode: @json($mode),
    tripId: @json($mode === 'edit' ? $trip->id : null),
    apiBase: @json(url('/api/v1/trips')),
    listUrl: @json(route('app-trip-list')),
    viewUrlBase: @json(url('/app/trip/view')),
    limits: {
      default: @json((int) config('trips.attachment_max_kb')),
      client_signature: @json((int) config('trips.signature_max_kb')),
      client_stamp: @json((int) config('trips.signature_max_kb'))
    },
    t: {
      max_capacity: @json(__('Maximum: :ton ton / :cbm m³')),
      over_capacity: @json(__('The quantity cannot exceed the container capacity (:ton ton / :cbm m³).')),
      cleared_sub_service: @json(__('The sub-service was cleared: it does not suit the container or tank you chose.')),
      cleared_main_service: @json(__('The main service was cleared: the chosen purchase order does not cover it.')),
      cost_from_po: @json(__('Taken from the purchase order — it cannot be changed here.')),
      cost_manual: @json(__('The trip has no purchase order: enter the cost.')),
      cost_none: @json(__('Choose a purchase order and a main service to see the cost.')),
      po_line: @json(__('Trip cost :cost — :status')),
      po_none_for_service: @json(__('This purchase order does not cover the chosen main service.')),
      confirm_final: @json(__('Confirm the final approval')),
      confirm_reject: @json(__('Confirm rejecting the objection')),
      final_purpose: @json(__('Final approval of the trip')),
      reject_purpose: @json(__('Rejecting the client\'s objection')),
      view: @json(__('View')),
      saved: @json(__('The trip was saved.')),
      sign_first: @json(__('Draw your signature or choose your saved one.')),
      upload_client_signature: @json(__('Upload the client\'s signature.')),
      location_taken: @json(__('Location saved.')),
      photo_selected: @json(__('Photo ready.'))
    }
  };
</script>
@vite(['resources/assets/js/app-trip-form.js'])
@endsection
