{{-- The shell of every e-mail the system sends: the product name, the message, a quiet footer. Inline styles only
     (mail clients drop <style>); direction follows the language of the message. --}}
@php($rtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ \App\Support\Brand::name() }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#2f3349;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#1b2a4a;color:#ffffff;padding:18px 24px;font-size:16px;font-weight:bold;text-align:{{ $rtl ? 'right' : 'left' }};">
              {{ \App\Support\Brand::name() }}
            </td>
          </tr>
          <tr>
            <td style="padding:24px;font-size:15px;line-height:1.6;text-align:{{ $rtl ? 'right' : 'left' }};">
              @yield('body')
            </td>
          </tr>
          <tr>
            <td style="padding:14px 24px;background:#fafafa;color:#7b7f8f;font-size:12px;text-align:{{ $rtl ? 'right' : 'left' }};">
              {{ __('This message was sent automatically by :app. Please do not reply to it.', ['app' => \App\Support\Brand::name()]) }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
