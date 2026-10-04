{{-- FRD V01.14 §1.9 statistics block, shared by the main-service and
     sub-service details pages. Values are filled by JS from `stats`
     (data-stat="<key>"). --}}
<div class="row g-4 mb-6">
  @foreach ([
    ['key' => 'trips',    'label' => __('Total trips'),              'color' => 'success', 'icon' => 'ti-truck'],
    ['key' => 'projects', 'label' => __('Projects requesting it'),   'color' => 'info',    'icon' => 'ti-briefcase'],
    ['key' => 'drivers',  'label' => __('Drivers involved'),         'color' => 'warning', 'icon' => 'ti-steering-wheel'],
  ] as $c)
    <div class="col-md-4 col-sm-6">
      <div class="card card-border-shadow-{{ $c['color'] }} h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <div class="avatar me-4">
              <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
            </div>
            <h4 class="mb-0" data-stat="{{ $c['key'] }}">0</h4>
          </div>
          <p class="mb-0 text-heading fw-medium">{{ $c['label'] }}</p>
        </div>
      </div>
    </div>
  @endforeach

  <div class="col-md-6">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <p class="text-heading fw-medium mb-3">{{ __('Total quantities moved') }}</p>
        <div class="d-flex gap-6">
          <div><h4 class="mb-0" data-stat="quantity_ton">0</h4><small class="text-muted">{{ __('Ton') }}</small></div>
          <div><h4 class="mb-0" data-stat="quantity_cbm">0</h4><small class="text-muted">{{ __('Cubic meter') }}</small></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card card-border-shadow-secondary h-100">
      <div class="card-body">
        <p class="text-heading fw-medium mb-3">{{ __('Resources used') }}</p>
        <div class="d-flex gap-6">
          <div><h4 class="mb-0" data-stat="vehicles">0</h4><small class="text-muted">{{ __('Vehicles') }}</small></div>
          <div><h4 class="mb-0" data-stat="containers">0</h4><small class="text-muted">{{ __('Containers') }}</small></div>
          <div><h4 class="mb-0" data-stat="tanks">0</h4><small class="text-muted">{{ __('Tanks') }}</small></div>
        </div>
      </div>
    </div>
  </div>
</div>
