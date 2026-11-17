<?php

namespace App\Domain\Trips\Support;

use App\Models\Asset;
use App\Models\Project;
use App\Models\Trip;
use App\Models\TripAssetMovement as Movement;
use App\Models\TripEvent;
use App\Models\User;

/**
 * Decision ج3 (product owner, 2026-11-10): a trip moves its containers.
 *
 * When the trip is COMPLETED (by any of its paths — our reading of "with the
 * trip's completion", for both containers):
 *  - the going container enters the trip's project (assets.project_id =
 *    the project): it was left at the client's site, so it is no longer
 *    available;
 *  - the returning container leaves the project (project_id = null): it is
 *    "available" again;
 *  - when the going and the returning container are the same asset, nothing
 *    moves (it went and came back).
 * Cancelling a completed trip puts them back, and so does editing the
 * containers or the project of a completed trip before the new ones move.
 *
 * A movement never blocks the trip (it is recorded after it happened —
 * our reading): an asset that is not where the trip expects it (moved by
 * hand meanwhile, or by a later trip) is left alone and the trip's history
 * says so; undoing likewise only moves an asset still where this trip put
 * it. Each asset is changed under the same row lock InsertAssetIntoProjectAction
 * takes, since `assets.project_id` is outside $fillable.
 */
final class TripAssetMovement
{
    /** Moves the trip's containers (call when it reaches `completed`). */
    public static function apply(Trip $trip, User $actor): void
    {
        $going = $trip->going_asset_id;
        $returning = $trip->returning_asset_id;

        if ($going !== null && (int) $going === (int) $returning) {
            return;
        }

        if ($going !== null) {
            self::move($trip, (int) $going, Movement::ROLE_GOING, null, $trip->project_id, $actor);
        }

        if ($returning !== null) {
            self::move($trip, (int) $returning, Movement::ROLE_RETURNING, $trip->project_id, null, $actor);
        }
    }

    /** Puts back what the trip moved and is still where the trip put it (call on cancel, and before re-applying). */
    public static function revert(Trip $trip, User $actor): void
    {
        $movements = Movement::where('trip_id', $trip->id)->whereNull('reversed_at')->orderByDesc('id')->get();

        foreach ($movements as $movement) {
            $asset = Asset::whereKey($movement->asset_id)->lockForUpdate()->first();
            $inPlace = $asset !== null && self::sameProject($asset->project_id, $movement->to_project_id);

            if ($inPlace) {
                $asset->project_id = $movement->from_project_id;
                $asset->updated_by = $actor->id;
                $asset->save();
            }

            $movement->reversed_at = now();
            $movement->reversal_skipped = ! $inPlace;
            $movement->save();

            TripHistory::record($trip, $inPlace ? TripEvent::ASSET_MOVEMENT_REVERSED : TripEvent::ASSET_MOVEMENT_SKIPPED, $actor, [
                'changes' => self::describe($asset, $movement->role, $movement->to_project_id, $movement->from_project_id),
            ]);
        }
    }

    private static function move(Trip $trip, int $assetId, string $role, ?int $from, ?int $to, User $actor): void
    {
        $asset = Asset::whereKey($assetId)->lockForUpdate()->first();

        if ($asset === null || ! self::sameProject($asset->project_id, $from)) {
            TripHistory::record($trip, TripEvent::ASSET_MOVEMENT_SKIPPED, $actor, [
                'changes' => self::describe($asset, $role, $from, $to),
            ]);

            return;
        }

        $asset->project_id = $to;
        $asset->updated_by = $actor->id;
        $asset->save();

        Movement::create([
            'trip_id' => $trip->id,
            'asset_id' => $asset->id,
            'role' => $role,
            'from_project_id' => $from,
            'to_project_id' => $to,
        ]);

        TripHistory::record($trip, TripEvent::ASSET_MOVED, $actor, [
            'changes' => self::describe($asset, $role, $from, $to),
        ]);
    }

    private static function sameProject(mixed $a, mixed $b): bool
    {
        return ($a === null && $b === null) || ($a !== null && $b !== null && (int) $a === (int) $b);
    }

    /** The history row's `changes`: which container (going / returning), and from which project to which (null = available). */
    private static function describe(?Asset $asset, string $role, ?int $from, ?int $to): array
    {
        return [
            $role.'_asset' => ['from' => $asset?->name, 'to' => $asset?->name],
            'asset_project' => [
                'from' => $from === null ? null : Project::whereKey($from)->value('code'),
                'to' => $to === null ? null : Project::whereKey($to)->value('code'),
            ],
        ];
    }
}
