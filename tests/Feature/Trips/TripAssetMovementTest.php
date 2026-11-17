<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\UpdateTripAction;
use App\Models\Asset;
use App\Models\Project;
use App\Models\Trip;
use App\Models\TripAssetMovement;
use App\Models\TripEvent;

/**
 * Decision ج3: a completed trip moves its containers — the going one into
 * the project, the returning one out of it — and cancelling puts them back.
 */
class TripAssetMovementTest extends TripTestCase
{
    public function test_nothing_moves_before_completion(): void
    {
        $this->tripIn(Trip::STATUS_CLIENT_APPROVED);

        $this->assertNull($this->goingAsset->fresh()->project_id);
        $this->assertSame($this->project->id, $this->returningAsset->fresh()->project_id);
        $this->assertSame(0, TripAssetMovement::count());
    }

    public function test_completion_moves_the_going_container_in_and_the_returning_one_out(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        $this->assertSame($this->project->id, $this->goingAsset->fresh()->project_id);
        $this->assertNull($this->returningAsset->fresh()->project_id);
        $this->assertSame(2, TripAssetMovement::where('trip_id', $trip->id)->count());
        $this->assertSame(2, TripEvent::where('trip_id', $trip->id)->where('type', TripEvent::ASSET_MOVED)->count());
    }

    public function test_every_path_to_completed_moves_them(): void
    {
        $trip = app(\App\Domain\Trips\Actions\CreateTripByStaffAction::class)->execute($this->admin, $this->staffData(['requires_client_approval' => false]), [], $this->completion());

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame($this->project->id, $this->goingAsset->fresh()->project_id);
    }

    public function test_the_same_container_going_and_returning_does_not_move(): void
    {
        $this->tripInWith(['going_asset_id' => $this->returningAsset->id, 'returning_asset_id' => $this->returningAsset->id]);

        $this->assertSame($this->project->id, $this->returningAsset->fresh()->project_id);
        $this->assertSame(0, TripAssetMovement::count());
    }

    public function test_cancelling_a_completed_trip_puts_them_back(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        $this->cancel($trip);

        $this->assertNull($this->goingAsset->fresh()->project_id);
        $this->assertSame($this->project->id, $this->returningAsset->fresh()->project_id);
        $this->assertSame(2, TripAssetMovement::whereNotNull('reversed_at')->where('reversal_skipped', false)->count());
    }

    public function test_an_asset_moved_since_is_left_alone_and_the_history_says_so(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);
        $elsewhere = Project::factory()->for($this->company)->create();
        $this->goingAsset->forceFill(['project_id' => $elsewhere->id])->save(); // moved by hand meanwhile

        $this->cancel($trip);

        $this->assertSame($elsewhere->id, $this->goingAsset->fresh()->project_id);
        $this->assertSame($this->project->id, $this->returningAsset->fresh()->project_id);
        $this->assertSame(1, TripEvent::where('trip_id', $trip->id)->where('type', TripEvent::ASSET_MOVEMENT_SKIPPED)->count());
    }

    public function test_a_container_not_where_the_trip_expects_it_does_not_block_completion(): void
    {
        $this->goingAsset->forceFill(['project_id' => Project::factory()->for($this->company)->create()->id])->save();

        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame(1, TripAssetMovement::count(), 'Only the returning container moved.');
        $this->assertSame(1, TripEvent::where('type', TripEvent::ASSET_MOVEMENT_SKIPPED)->count());
    }

    public function test_new_containers_on_a_completed_trip_undo_the_old_movement_and_make_the_new_one(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);
        $replacement = Asset::factory()->container()->create(['name' => 'C-NEW', 'asset_capacity_category_id' => $this->capacity->id]);

        app(UpdateTripAction::class)->execute($trip, $this->admin, ['going_asset_id' => $replacement->id]);

        $this->assertNull($this->goingAsset->fresh()->project_id, 'The old going container is available again.');
        $this->assertSame($this->project->id, $replacement->fresh()->project_id);
        $this->assertNull($this->returningAsset->fresh()->project_id, 'The returning one is still out.');
    }

    public function test_an_edit_that_keeps_the_containers_moves_nothing(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        app(UpdateTripAction::class)->execute($trip, $this->admin, ['quantity_ton' => 1]);

        $this->assertSame(2, TripAssetMovement::count());
        $this->assertSame(0, TripAssetMovement::whereNotNull('reversed_at')->count());
    }

    private function tripInWith(array $overrides): Trip
    {
        return $this->finalApprove($this->clientApprove($this->createAwaiting($overrides)));
    }
}
