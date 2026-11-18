<?php

namespace App\Domain\Otp;

use App\Domain\Otp\Exceptions\OtpException;
use App\Domain\Otp\Mail\OtpCodeMail;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The e-mailed one-time code (part 7.3, decisions ج15–ج16, FRD "ايميل OTP").
 *
 *  - send():   a fresh code, e-mailed to the account's own address. Asking
 *              again is refused for `otp.resend_after_seconds`; a new code
 *              cancels every older one of the same login.
 *  - verify(): the code is right → the login (session or API token) is
 *              verified for `otp.verified_for_minutes`; wrong → one try is
 *              spent, and at `otp.max_attempts` the code is dead.
 *  - isVerified(): what the RequiresOtp guard asks before a sensitive action.
 *
 * A code, and the verification it gives, belong to one login (OtpScope) —
 * "once per session". Only a keyed hash of the code is stored, and it is
 * compared in constant time. Reads that decide a write run under a lock, so
 * two requests can't both spend the same code.
 */
class OtpService
{
    public function send(User $user, Request $request, ?string $purpose = null): OtpChallenge
    {
        $scope = OtpScope::of($request);
        $length = max(4, (int) config('otp.code_length'));
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('otp.ttl_minutes');

        try {
            return DB::transaction(function () use ($user, $scope, $code, $ttl, $purpose) {
                $latest = OtpChallenge::query()->open()
                    ->where('user_id', $user->id)->where('scope', $scope)
                    ->latest('id')->lockForUpdate()->first();

                if ($latest !== null) {
                    $wait = (int) config('otp.resend_after_seconds') - (int) $latest->last_sent_at->diffInSeconds(now(), true);

                    if ($wait > 0) {
                        throw OtpException::resendTooSoon($wait);
                    }
                }

                // A new code cancels the old one(s) of this login.
                OtpChallenge::query()->open()
                    ->where('user_id', $user->id)->where('scope', $scope)
                    ->update(['invalidated_at' => now()]);

                $challenge = OtpChallenge::create([
                    'user_id' => $user->id,
                    'scope' => $scope,
                    'code_hash' => $this->hash($user, $code),
                    'purpose' => $purpose,
                    'attempts' => 0,
                    'last_sent_at' => now(),
                    'expires_at' => now()->addMinutes($ttl),
                ]);

                // Inside the transaction on purpose: if the e-mail can't be sent no code is left waiting.
                Mail::to($user->email)->send(
                    (new OtpCodeMail($user, $code, $ttl, $purpose))->locale(app()->getLocale())
                );

                return $challenge;
            });
        } catch (OtpException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw OtpException::cannotSend();
        }
    }

    /**
     * @throws OtpException  not sent / expired / wrong / locked
     */
    public function verify(User $user, Request $request, string $code): void
    {
        $scope = OtpScope::of($request);

        // The outcome is decided inside the transaction and thrown AFTER it: an exception would roll back the
        // spent try, and a wrong code would cost nothing.
        $failure = DB::transaction(function () use ($user, $scope, $code): ?OtpException {
            $challenge = OtpChallenge::query()->open()
                ->where('user_id', $user->id)->where('scope', $scope)
                ->latest('id')->lockForUpdate()->first();

            if ($challenge === null) {
                return OtpException::notSent();
            }

            if ($challenge->expires_at->isPast()) {
                $challenge->forceFill(['invalidated_at' => now()])->save();

                return OtpException::expired();
            }

            $max = (int) config('otp.max_attempts');

            if ($challenge->attempts >= $max) {
                $challenge->forceFill(['invalidated_at' => now()])->save();

                return OtpException::locked();
            }

            if (! hash_equals($challenge->code_hash, $this->hash($user, trim($code)))) {
                $attempts = $challenge->attempts + 1;
                $challenge->forceFill([
                    'attempts' => $attempts,
                    'invalidated_at' => $attempts >= $max ? now() : null,
                ])->save();

                return $attempts >= $max ? OtpException::locked() : OtpException::invalid($max - $attempts);
            }

            $challenge->forceFill(['verified_at' => now()])->save();

            return null;
        });

        if ($failure !== null) {
            throw $failure;
        }
    }

    /** Whether this login (session / token) has entered a code recently enough. */
    public function isVerified(User $user, Request $request): bool
    {
        return OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('scope', OtpScope::of($request))
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes((int) config('otp.verified_for_minutes')))
            ->exists();
    }

    /** The address with the middle hidden (a***@gmail.com), for the "we sent it to…" line. */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        // A fixed number of stars: the length of the name is not revealed either.
        return mb_substr($name, 0, 1).'****@'.$domain;
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, (string) config('app.key'));
    }
}
