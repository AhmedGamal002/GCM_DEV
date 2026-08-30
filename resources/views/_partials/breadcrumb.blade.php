@php
  $breadcrumbs = $breadcrumbs ?? [];
  $homeUrl = $homeUrl ?? url('/');
@endphp
<nav aria-label="breadcrumb" class="mb-4">
  <ol class="breadcrumb breadcrumb-style1">
    <li class="breadcrumb-item">
      <a href="{{ $homeUrl }}"><i class="ti ti-smart-home ti-sm"></i></a>
    </li>
    @foreach ($breadcrumbs as $crumb)
      @if ($loop->last || empty($crumb['url']))
        <li class="breadcrumb-item active">{{ $crumb['title'] }}</li>
      @else
        <li class="breadcrumb-item">
          <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
        </li>
      @endif
    @endforeach
  </ol>
</nav>
