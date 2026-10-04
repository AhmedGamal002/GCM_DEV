<?php

namespace App\Domain\Users\Actions;

use App\Models\Contractor;
use App\Models\User;

/**
 * Applies a contractor user's contractor (FRD V01.14 §1.4) and keeps the
 * contractor's "representative account" (§1.10.2) honest: if an edit moves
 * the account to another contractor, the field on the old contractor is
 * cleared — it would otherwise keep naming someone who no longer belongs to
 * it.
 */
final class SyncContractorAccess
{
    public static function apply(User $user, int $contractorId): void
    {
        $user->forceFill(['contractor_id' => $contractorId])->save();

        Contractor::query()
            ->where('representative_id', $user->id)
            ->where('id', '!=', $contractorId)
            ->get()
            ->each(fn (Contractor $contractor) => $contractor->forceFill(['representative_id' => null])->save());
    }
}
