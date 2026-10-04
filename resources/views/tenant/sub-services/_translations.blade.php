{{-- window.subServiceFormTranslations — shared by the create & edit pages. --}}
<script>
  window.subServiceFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    choose_type_first: @json(__('Choose the asset type first.')),
    choose_one_capacity: @json(__('Choose at least one capacity.')),
    choose_one_facility: @json(__('Choose at least one intermediate facility (optional only for supplying services).')),
    no_capacities: @json(__('No capacities of this type are defined yet.')),
    no_facilities: @json(__('No active intermediate facilities found.')),
    from_main_service: @json(__('Shown automatically from the main service.')),
    vehicles_count: @json(__(':count vehicles')),
    cbm: @json(__('CBM')),
    ton: @json(__('TON')),
    services: {
      disposal: @json(__('Safe disposal')),
      sewage_treatment: @json(__('Sewage treatment')),
      recycle: @json(__('Recycling'))
    },
    asset_types: {
      container: @json(__('Container')),
      tank: @json(__('Tank'))
    },
    service_types: {
      non_hazardous: @json(__('Non-Hazardous waste')),
      hazardous: @json(__('Hazardous waste')),
      supplying: @json(__('Supplying'))
    },
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    status_updated: @json(__('Sub-service status updated successfully.')),
    view_url_base: @json(url('/app/sub-service/view'))
  };
</script>
