{{-- Logo + product name (App\Support\Brand). `textClass`: 'menu-text' inside the sidebar/navbar, 'text-heading' on the sign-in cards. --}}
@php($textClass = $textClass ?? 'menu-text')
<span class="app-brand-logo demo gcm-brand-logo"><img src="{{ \App\Support\Brand::logoUrl() }}" alt="" width="36" height="36" /></span>
<span class="app-brand-text demo {{ $textClass }} fw-bold gcm-brand-text">{{ \App\Support\Brand::name() }}</span>
