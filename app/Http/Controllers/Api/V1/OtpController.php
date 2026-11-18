<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Otp\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The e-mailed one-time code (FRD V01.14 "ايميل OTP", part 7.3): the
 * three calls the "enter your code" box makes. The sensitive trip actions
 * sit behind the `otp` middleware and answer 403 `otp_required` until this
 * login (session or API token) has been through send → verify once.
 *
 *  - POST /otp/send     e-mails a fresh 6-digit code to the account's own address
 *  - POST /otp/verify   {code} — accepts it; the session / token is verified
 *  - GET  /otp/status   {verified} — whether this login already is
 *
 * Only the accounts the FRD makes confirm trip actions with a code may ask
 * for one (config otp.roles); asking and entering are throttled per account +
 * IP (the `otp-send` / `otp-verify` limiters) on top of the rules in OtpService.
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function send(Request $request)
    {
        $user = $this->actor($request);

        $data = $request->validate(['purpose' => ['nullable', 'string', 'max:100']]);

        $this->otp->send($user, $request, $data['purpose'] ?? null);

        return response()->json(['data' => [
            'sent_to' => OtpService::maskEmail($user->email),
            'expires_in_seconds' => (int) config('otp.ttl_minutes') * 60,
            'resend_after_seconds' => (int) config('otp.resend_after_seconds'),
            'code_length' => (int) config('otp.code_length'),
        ]]);
    }

    public function verify(Request $request)
    {
        $user = $this->actor($request);

        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        $this->otp->verify($user, $request, $data['code']);

        return response()->json(['data' => ['verified' => true]]);
    }

    public function status(Request $request)
    {
        $user = $this->actor($request);

        return response()->json(['data' => ['verified' => $this->otp->isVerified($user, $request)]]);
    }

    private function actor(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasAnyRole(config('otp.roles')), 403, __('Your account does not use verification codes.'));

        return $user;
    }
}
