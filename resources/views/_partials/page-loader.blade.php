{{--
  Full-screen loader shown while a page loads (and while its first data requests finish) — logo, product
  name and an indeterminate bar. resources/js/page-loader.js hides it once the page has loaded and its
  initial requests are done; it also comes back when the user clicks through to another page.
  The styles are inline on purpose: they must apply before any bundle has downloaded.
--}}
<style>
  #gcm-page-loader {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem;
    text-align: center;
    background: #f8f7fa;
    color: #163269;
    opacity: 1;
    transition: opacity 0.25s ease;
  }
  .dark-style #gcm-page-loader { background: #25293c; color: #e6e6f1; }
  #gcm-page-loader.gcm-pl-hide { opacity: 0; pointer-events: none; }
  #gcm-page-loader .gcm-pl-logo {
    width: 116px;
    height: 116px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 0.25rem 1.25rem rgba(22, 50, 105, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: gcm-pl-pulse 1.6s ease-in-out infinite;
  }
  #gcm-page-loader .gcm-pl-logo img { width: 92px; height: 92px; object-fit: contain; }
  #gcm-page-loader .gcm-pl-name { font-weight: 700; font-size: 1.05rem; letter-spacing: 0.06em; }
  #gcm-page-loader .gcm-pl-bar {
    width: 168px;
    height: 4px;
    border-radius: 4px;
    overflow: hidden;
    background: rgba(22, 50, 105, 0.15);
  }
  .dark-style #gcm-page-loader .gcm-pl-bar { background: rgba(255, 255, 255, 0.18); }
  #gcm-page-loader .gcm-pl-bar span {
    display: block;
    width: 40%;
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, #3a7d32, #163269);
    animation: gcm-pl-slide 1.15s ease-in-out infinite;
  }
  @keyframes gcm-pl-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.06); } }
  @keyframes gcm-pl-slide { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
  [dir='rtl'] #gcm-page-loader .gcm-pl-bar span { animation-name: gcm-pl-slide-rtl; }
  @keyframes gcm-pl-slide-rtl { 0% { transform: translateX(100%); } 100% { transform: translateX(-250%); } }
  @media (prefers-reduced-motion: reduce) {
    #gcm-page-loader .gcm-pl-logo, #gcm-page-loader .gcm-pl-bar span { animation: none; }
  }
</style>
<div id="gcm-page-loader" role="status" aria-live="polite" aria-label="{{ __('Loading...') }}">
  <div class="gcm-pl-logo"><img src="{{ \App\Support\Brand::logoUrl() }}" alt="" /></div>
  <div class="gcm-pl-name">{{ \App\Support\Brand::name() }}</div>
  <div class="gcm-pl-bar"><span></span></div>
</div>
{{-- No JS at all? Never leave the page covered. --}}
<noscript><style>#gcm-page-loader { display: none !important; }</style></noscript>
