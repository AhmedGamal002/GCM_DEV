<!-- BEGIN: Vendor JS-->

@vite([
  'resources/assets/vendor/libs/jquery/jquery.js',
  'resources/assets/vendor/libs/popper/popper.js',
  'resources/assets/vendor/js/bootstrap.js',
  'resources/assets/vendor/libs/node-waves/node-waves.js',
  'resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js',
  'resources/assets/vendor/libs/hammer/hammer.js',
  'resources/assets/vendor/libs/typeahead-js/typeahead.js',
  'resources/assets/vendor/js/menu.js'
])

@yield('vendor-script')
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->
@vite(['resources/assets/js/main.js'])

<!-- END: Theme JS-->
<!-- BEGIN: App JS (axios/Sanctum bootstrap) -->
{{-- Translated texts for resources/js/busy.js (slow-save / export overlay). --}}
<script>
  window.gcmTexts = {
    working: @json(__('Working...')),
    uploading: @json(__('Uploading... :percent%')),
    processing: @json(__('Saving — please wait, this can take a moment...')),
    preparing: @json(__('Preparing your file...')),
    preparingHint: @json(__('Large lists can take a while. Please keep this page open.')),
    exportFailed: @json(__('The export could not be created. Please try again, or use Excel for large lists.')),
    fileTooLarge: @json(__('The file ":file" is :size MB — the limit is :max MB. Please choose a smaller file.')),
    close: @json(__('OK'))
  };
</script>
@vite(['resources/js/app.js'])
<!-- END: App JS-->
<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->
<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->
