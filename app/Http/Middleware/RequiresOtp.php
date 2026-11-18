<?php

namespace App\Http\Middleware;

use App\Domain\Otp\Exceptions\OtpException;
use App\Domain\Otp\OtpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The guard in front of the sensitive actions (alias `otp`): the client's
 * approve / reject, the final approval, cancelling a trip and rejecting a
 * client's objection (decision ج15 — creating a completed trip directly needs
 * none). If this login hasn't entered the e-mailed code yet it answers 403
 * with `code: otp_required`, and the page opens the "enter your code" box
 * (POST /otp/send, POST /otp/verify) and retries.
 *
 *   Route::post('/trips/{trip}/cancel', …)->middleware('otp');
 *
 * Must come after `auth` / `tenant` (it needs the user).
 */
class RequiresOtp
{
    public function __construct(private readonly OtpService $otp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->otp->isVerified($user, $request)) {
            throw OtpException::required();
        }

        return $next($request);
    }
}
