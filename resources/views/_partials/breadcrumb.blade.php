@php
  $breadcrumbs = $breadcrumbs ?? [];
  $homeUrl = $homeUrl ?? url('/');
  // Visible on-page heading, distinct from @section('title', ...) which
  // only sets the browser tab title. Defaults to the last breadcrumb
  // crumb's title (the common case — that crumb is already this page's
  // name) so existing @include callers don't need a new param just to
  // get a heading; pass `pageTitle` explicitly only when the page's
  // heading should read differently from its last breadcrumb entry.
  // Optional: id of a <p> rendered right under the title for the "Last updated by X — date" line.
  $updatedId = $updatedId ?? null;
  $pageTitle = $pageTitle ?? (count($breadcrumbs) ? end($breadcrumbs)['title'] : null);
@endphp
<nav aria-label="breadcrumb" class="mb-2">
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
@if ($pageTitle)
  <h4 class="fw-bold mb-4">{{ $pageTitle }}</h4>
@endif
{{-- FRD: every details/edit page shows "اخر تحديث: تم بواسطة ..... – التاريخ والوقت" right under the page title.
     The page's JS fills #{{ $updatedId }} once the record loads; empty, it takes no space (.gcm-updated-by:empty). --}}
@if ($updatedId)
  <p class="gcm-updated-by small text-muted mt-n3 mb-4" id="{{ $updatedId }}"></p>
@endif
