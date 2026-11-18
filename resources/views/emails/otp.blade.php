@extends('emails.layout')

@section('body')
  <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

  <p style="margin:0 0 12px;">
    @if($purpose)
      {{ __('Use this code to confirm: :purpose', ['purpose' => $purpose]) }}
    @else
      {{ __('Use this code to confirm the action you are about to take:') }}
    @endif
  </p>

  <p style="margin:18px 0;text-align:center;">
    <span dir="ltr" style="display:inline-block;background:#eef1f7;border-radius:6px;padding:12px 22px;font-size:30px;letter-spacing:8px;font-weight:bold;color:#1b2a4a;">{{ $code }}</span>
  </p>

  <p style="margin:0 0 12px;">{{ trans_choice('{1} The code is valid for :count minute and can be used once.|[2,*] The code is valid for :count minutes and can be used once.', $ttlMinutes) }}</p>

  <p style="margin:0;color:#7b7f8f;font-size:13px;">{{ __('If you did not ask for this code, ignore this e-mail and do not share the code with anyone — nobody from the company will ask you for it.') }}</p>
@endsection
