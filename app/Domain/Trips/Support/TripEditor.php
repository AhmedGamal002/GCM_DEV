<?php

namespace App\Domain\Trips\Support;

use App\Domain\Trips\Quota\TripQuota;
use App\Models\Trip;
use App\Models\User;

/**
 * GCM's edit of a trip, shared by the plain edit (UpdateTripAction) and the
 * steps that edit on their way (accepting the client's objection, the final
 * approval, rejecting the objection). Runs inside the caller's transaction,
 * on the locked row:
 *
 *  1. apply() — the data, the PO / costs and the files, checked
 *     (TripInput); a completed trip must still meet the completion
 *     requirements afterwards (decision ج9: an edit never changes the
 *     status, so it can't leave a completed trip incomplete). Saved.
 *  2. the caller writes its history row (with the diff apply() returned),
 *  3. effects() — what the edit set off: a new date / project / service /
 *     PO re-charges the quota (ب7), and new containers or a new project on a
 *     completed trip undo the old asset movement and make the new one (ج3).
 */
final class TripEditor
{
    private bool $recharge = false;

    private bool $moveAssets = false;

    public function __construct(private readonly TripQuota $quota) {}

    /** @return array<string, array{from: mixed, to: mixed}> what changed (TripChanges::diff) */
    public function apply(Trip $trip, User $actor, array $data, array $files, TripFiles $store): array
    {
        $before = TripChanges::snapshot($trip);

        TripInput::apply($trip, $data, false);
        TripInput::pricing($trip, $data, false);
        TripInput::files($trip, $files, $store);

        $this->recharge = TripQuota::needsRecharge($trip);
        $this->moveAssets = $trip->isCompleted() && $trip->isDirty(['going_asset_id', 'returning_asset_id', 'project_id']);

        if ($trip->isCompleted()) {
            TripCompletionRequirements::assertMet($trip);
        }

        $trip->updated_by = $actor->id;
        $trip->save();

        return TripChanges::diff($before, TripChanges::snapshot($trip));
    }

    public function effects(Trip $trip, User $actor): void
    {
        if ($this->recharge) {
            $this->quota->recharge($trip, $actor);
        }

        if ($this->moveAssets) {
            TripAssetMovement::revert($trip, $actor);
            TripAssetMovement::apply($trip, $actor);
        }
    }
}
