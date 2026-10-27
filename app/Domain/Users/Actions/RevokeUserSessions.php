<?php

namespace App\Domain\Users\Actions;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every other way into an account: its API tokens, its browser sessions (database session driver) and its
 * "remember me" cookies. Run whenever the password changes (User::booted) — someone who got hold of the old
 * password, or of a stolen session or token, must not stay signed in after the owner or an admin sets a new one.
 *
 * What the current request is using survives: when the user changed their own password, their own session and token
 * stay valid so the page they are on does not log them out; an admin changing someone else's password, or a password
 * reset from a signed-out browser, keeps nothing of the target's.
 */
final class RevokeUserSessions
{
    public static function execute(User $user): void
    {
        $request = request();
        $keepSession = $request->hasSession() ? $request->session()->getId() : null;

        $actor = $request->user();
        $current = $actor && $actor->is($user) ? $actor->currentAccessToken() : null;
        $keepToken = $current instanceof PersonalAccessToken ? $current->getKey() : null;

        $user->tokens()
            ->when($keepToken !== null, fn ($query) => $query->where('id', '!=', $keepToken))
            ->delete();

        // Only the database driver can be searched by user; with the file driver the old sessions simply expire.
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($keepSession !== null, fn ($query) => $query->where('id', '!=', $keepSession))
                ->delete();
        }

        if (! $user->wasChanged('remember_token')) {
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        }
    }
}
