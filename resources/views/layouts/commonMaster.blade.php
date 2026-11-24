<!DOCTYPE html>
@php
$menuFixed = ($configData['layout'] === 'vertical') ? ($menuFixed ?? '') : (($configData['layout'] === 'front') ? '' : $configData['headerType']);
$navbarType = ($configData['layout'] === 'vertical') ? ($configData['navbarType'] ?? '') : (($configData['layout'] === 'front') ? 'layout-navbar-fixed': '');
$isFront = ($isFront ?? '') == true ? 'Front' : '';
$contentLayout = (isset($container) ? (($container === 'container-xxl') ? "layout-compact" : "layout-wide") : "");
@endphp

<html lang="{{ session()->get('locale') ?? app()->getLocale() }}" class="{{ $configData['style'] }}-style {{($contentLayout ?? '')}} {{ ($navbarType ?? '') }} {{ ($menuFixed ?? '') }} {{ $menuCollapsed ?? '' }} {{ $menuFlipped ?? '' }} {{ $menuOffcanvas ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}" dir="{{ $configData['textDirection'] }}" data-theme="{{ $configData['theme'] }}" data-assets-path="{{ asset('/assets') . '/' }}" data-base-url="{{url('/')}}" data-framework="laravel" data-template="{{ $configData['layout'] . '-menu-' . $configData['themeOpt'] . '-' . $configData['styleOpt'] }}" data-style="{{$configData['styleOptVal']}}">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>@yield('title') | {{ \App\Support\Brand::name() }}</title>
  <meta name="description" content="{{ config('variables.templateDescription') ? config('variables.templateDescription') : '' }}" />
  <meta name="keywords" content="{{ config('variables.templateKeyword') ? config('variables.templateKeyword') : '' }}">
  <!-- laravel CRUD token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ config('variables.productPage') ? config('variables.productPage') : '' }}">
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />


  <!-- Include Styles -->
  <!-- $isFront is used to append the front layout styles only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/styles' . $isFront)

  <!-- Include Scripts for customizer, helper, analytics, config -->
  <!-- $isFront is used to append the front layout scriptsIncludes only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scriptsIncludes' . $isFront)

  <!-- GCM custom styles — keep this LAST so it overrides theme/vendor/page styles (edited by the designer, no build step) -->
  <link id="gcm-custom-css" rel="stylesheet" href="{{ asset('assets/css/gcm_custom.css') }}?v={{ @filemtime(public_path('assets/css/gcm_custom.css')) }}" />
</head>

<body @if(auth()->user() instanceof \App\Models\User && auth()->user()->hasAnyRole(['auditor', 'contractor_user']) && ! auth()->user()->hasAnyRole(['system_admin', 'data_entry'])) data-gcm-view-only="1" @endif>

  {{-- Page loader: logo + name while the page (and its first data requests) load — hidden by resources/js/page-loader.js --}}
  @include('_partials.page-loader')

  {{-- Wording for resources/js/gcm-errors.js (clear error messages) and resources/js/required-marks.js (the * legend). --}}
  <script>
    window.gcmI18n = {
      network: @json(__('Cannot reach the server. Check your internet connection and try again.')),
      session_expired: @json(__('Your session has expired. Please sign in again.')),
      forbidden: @json(__("You don't have permission to do this.")),
      not_found: @json(__('This item was not found — it may have been deleted, or you may not have access to it.')),
      too_large: @json(__('The files you are uploading are too large. Choose smaller files and try again.')),
      csrf: @json(__('This page has been open too long. Reload it and try again.')),
      invalid: @json(__('Some of the data is not valid. Please review the form and fix the highlighted fields.')),
      too_many: @json(__('Too many attempts. Wait a minute and try again.')),
      server: @json(__('The server ran into a problem (error :code). Try again in a moment; if it keeps happening, contact support and mention this code.')),
      unknown: @json(__('The request could not be completed. Please try again.')),
      unknown_status: @json(__('The request could not be completed (error :code). Please try again.')),
      required_hint: @json(__('Fields marked with * are required.')),
      view: @json(__('View')),
      geo_unsupported: @json(__('Location is not supported on this device.')),
      geo_insecure: @json(__('Location only works on a secure (HTTPS) connection.')),
      geo_denied: @json(__('Location access was denied. Allow it in the browser settings and try again.')),
      geo_unavailable: @json(__('Your location is not available right now. Move to an open area and try again.')),
      geo_timeout: @json(__('Finding your location took too long. Try again.')),
      camera_unsupported: @json(__('The camera is not supported on this device or browser.')),
      camera_insecure: @json(__('The camera only works on a secure (HTTPS) connection.')),
      camera_denied: @json(__('Camera access was denied. Allow it in the browser settings and try again.')),
      camera_notfound: @json(__('No camera was found on this device.')),
      camera_busy: @json(__('The camera is being used by another app. Close it and try again.')),
      camera_failed: @json(__('The camera could not be started. Try again.')),
      camera_take: @json(__('Take photo')),
      camera_retake: @json(__('Retake')),
      camera_use: @json(__('Use photo')),
      camera_cancel: @json(__('Cancel')),
      trip_default: @json(__('default')),
      signature_hint: @json(__('Sign here')),
      signature_empty: @json(__('Draw your signature first.')),
      signature_too_large: @json(__('The signature is too large. Clear it and sign again.')),
      otp_title: @json(__('Verification code')),
      otp_sending: @json(__('Sending a code to your e-mail…')),
      otp_sent: @json(__('We e-mailed a code to :email. Enter it to continue.')),
      otp_enter: @json(__('Enter the code we e-mailed you.')),
      otp_verify: @json(__('Confirm')),
      otp_cancel: @json(__('Cancel')),
      otp_resend: @json(__('Send a new code')),
      otp_resend_in: @json(__('Send a new code in :s s'))
    };
  </script>

  <!-- Layout Content -->
  @yield('layoutContent')
  <!--/ Layout Content -->

  

  <!-- Include Scripts -->
  <!-- $isFront is used to append the front layout scripts only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scripts' . $isFront)

</body>

</html>
