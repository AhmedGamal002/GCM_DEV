<?php

namespace App\Domain\Otp;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Which login a code, and the verification it gives, belongs to — what makes
 * "once per session" (FRD) true:
 *  - a request authenticated with an API token (Postman, the driver's phone)
 *    is scoped to that token;
 *  - a browser request to the stateful API is scoped to its session (the
 *    session id, hashed: the id is a credential and is never stored).
 * Logging in again gives a new session id, and a new token is a new scope, so
 * both start unverified. Without either (a test or a console call that only
 * acts as a user) the scope is the user.
 */
final class OtpScope
{
    public static function of(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            return 'token:'.$token->getKey();
        }

        if ($request->hasSession()) {
            return 'session:'.hash('sha256', $request->session()->getId());
        }

        return 'user:'.($request->user()?->getAuthIdentifier() ?? 'guest');
    }
}
