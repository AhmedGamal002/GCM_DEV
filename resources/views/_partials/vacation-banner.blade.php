{{--
  Shown on every page of an account that is "on vacation" (FRD: such an account "loses any ability to
  interact — it only views the sections it is allowed"). The server refuses every write for it
  (EnsureTenant); this banner says why, and resources/js/vacation-readonly.js greys out the controls
  that would write so nobody fills in a form only to be told "no" at the end.
--}}
@auth
  @if(auth()->user() instanceof \App\Models\User && auth()->user()->status === 'on_vacation')
    <div id="gcm-vacation-banner" class="alert alert-warning d-flex align-items-center mb-6" role="alert"
      data-message="{{ __('Your account is on vacation — you can view data but not export or make changes.') }}"
      data-disabled-hint="{{ __('Unavailable while your account is on vacation') }}">
      <i class="ti ti-beach me-3 ti-md"></i>
      <div>
        <strong>{{ __('Your account is on vacation') }}</strong>
        <div class="small">{{ __('You can only view data. You cannot export, create, edit or change anything until the System Admin or Data Entry puts your account back to active.') }}</div>
      </div>
    </div>
  @endif
@endauth
