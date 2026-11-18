@extends('emails.layout')

@section('body')
  <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

  <p style="margin:0 0 12px;">{{ __('A trip is waiting for your review and approval:') }}</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px;">
    <tr>
      <td style="padding:2px 0;color:#7b7f8f;">{{ __('Trip No.') }}&nbsp;</td>
      <td style="padding:2px 12px;font-weight:bold;" dir="ltr">{{ $trip->code }}</td>
    </tr>
    <tr>
      <td style="padding:2px 0;color:#7b7f8f;">{{ __('Project') }}&nbsp;</td>
      <td style="padding:2px 12px;font-weight:bold;">{{ $trip->project?->name }}</td>
    </tr>
    <tr>
      <td style="padding:2px 0;color:#7b7f8f;">{{ __('Date & Time') }}&nbsp;</td>
      <td style="padding:2px 12px;" dir="ltr">{{ $trip->trip_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
    </tr>
  </table>

  <p style="margin:18px 0;text-align:center;">
    <a href="{{ $url }}" style="display:inline-block;background:#1b2a4a;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;">{{ __('Open the trip') }}</a>
  </p>

  <p style="margin:0;color:#7b7f8f;font-size:13px;">{{ __('Approving needs a confirmation code that will be e-mailed to you, and it uses your signature and stamp — make sure they are the legal ones.') }}</p>
@endsection
