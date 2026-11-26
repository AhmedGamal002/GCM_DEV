<?php

namespace Tests\Feature\Reports;

use App\Domain\Reports\Exports\TripsSummaryExport;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\TripsSummary;
use App\Domain\Trips\Documents\TripDocumentNumber;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Project;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.4 — the reports API: who may use it (staff + the client's accounts; the client's project auditor the Excel
 * only; driver and contractor user nothing), the nine filters and the period presets, the trips summary rows with
 * the FRD's columns, the charts, and the Excel being the same rows. One actor per test.
 */
class ReportApiTest extends TripTestCase
{
    private function read(string $uri, User $as): TestResponse
    {
        return $this->actingAs($as, 'web')->getJson('/api/v1/'.$uri);
    }

    private function codes(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data'))->pluck('code')->sort()->values()->all();
    }

    // ── access ──────────────────────────────────────────────────────

    #[DataProvider('fullUsers')]
    public function test_staff_and_the_clients_project_manager_use_every_report(string $who): void
    {
        $user = $who === 'client' ? $this->clientManager : $this->{$who};

        $this->read('reports/trips-summary', $user)->assertOk();
        $this->read('reports/charts', $user)->assertOk();
        $this->read('reports/filters', $user)->assertOk()->assertJsonPath('data.sees_charts', true);
    }

    public static function fullUsers(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client manager' => ['client']];
    }

    public function test_the_clients_project_auditor_gets_the_excel_but_no_charts(): void
    {
        $this->read('reports/charts', $this->clientAuditor)->assertForbidden();
        $this->read('reports/filters', $this->clientAuditor)->assertOk()->assertJsonPath('data.sees_charts', false);
    }

    public function test_the_clients_project_auditor_exports_the_trips_summary(): void
    {
        Excel::fake();

        $this->actingAs($this->clientAuditor, 'web')->get('/api/v1/reports/trips-summary/export')->assertOk();

        Excel::assertDownloaded('trips-summary.xlsx');
    }

    public function test_the_driver_has_no_reports(): void
    {
        $this->read('reports/trips-summary', $this->driver->user)->assertForbidden();
    }

    public function test_the_contractor_user_has_no_reports(): void
    {
        $this->read('reports/filters', User::factory()->contractorUser(Contractor::factory()->create())->create())->assertForbidden();
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/v1/reports/trips-summary')->assertUnauthorized();
    }

    // ── the trips summary ───────────────────────────────────────────

    public function test_a_row_carries_the_frds_columns(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);
        TripDocumentNumber::reserve($trip);

        $row = $this->read('reports/trips-summary', $this->admin)->assertOk()->json('data.0');

        $this->assertSame($trip->code, $row['code']);
        $this->assertSame('Al Nakheel', $row['company']);
        $this->assertSame('Riyadh Hotel', $row['project']);
        $this->assertSame('2026-03-01', $row['date']);
        $this->assertSame('09:30', $row['time']);
        $this->assertSame('DN-'.$trip->fresh()->document_number, $row['delivery_note']);
        $this->assertSame('M-'.$trip->fresh()->document_number, $row['waste_manifest']);
        $this->assertSame('General collection', $row['sub_service']);
        $this->assertSame('12 m3', $row['capacity']);
        $this->assertSame('completed', $row['status']);
    }

    public function test_a_trip_not_completed_has_no_document_numbers_yet(): void
    {
        $this->createAwaiting();

        $row = $this->read('reports/trips-summary', $this->admin)->json('data.0');

        $this->assertNull($row['delivery_note']);
        $this->assertNull($row['waste_manifest']);
    }

    public function test_a_cancelled_trip_is_a_row_of_the_summary(): void
    {
        $this->tripIn(Trip::STATUS_CANCELLED);

        $this->assertSame(['cancelled'], collect($this->read('reports/trips-summary', $this->admin)->json('data'))->pluck('status')->all());
    }

    public function test_a_client_reads_only_the_trips_of_his_projects(): void
    {
        $mine = $this->createAwaiting();
        $other = Project::factory()->create();
        Trip::factory()->forProject($other)->create(['driver_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id, 'main_service_id' => $this->service->id]);

        $this->assertSame([$mine->code], $this->codes($this->read('reports/trips-summary', $this->clientManager)));
    }

    // ── the filters ─────────────────────────────────────────────────

    public function test_the_filters_narrow_the_rows(): void
    {
        $a = $this->createAwaiting(['trip_at' => '2026-02-10 08:00']);
        // the fixture's vehicle (a factory vehicle's category is random — the containers may not fit it)
        $otherDriver = Driver::factory()->create();
        $this->makeCompatible($otherDriver, $this->vehicle);
        $b = $this->createAwaiting(['driver_id' => $otherDriver->id, 'sub_service_id' => $this->receiptSub->id]);
        $this->clientApprove($b);

        $this->assertSame([$b->code], $this->codes($this->read("reports/trips-summary?driver_id={$otherDriver->id}", $this->admin)));
        $this->assertCount(2, $this->read("reports/trips-summary?vehicle_id={$this->vehicle->id}", $this->admin)->json('data'));
        $this->assertSame([], $this->codes($this->read('reports/trips-summary?vehicle_id='.Vehicle::factory()->create()->id, $this->admin)));
        $this->assertSame([$b->code], $this->codes($this->read("reports/trips-summary?sub_service_id={$this->receiptSub->id}", $this->admin)));
        $this->assertSame([$b->code], $this->codes($this->read('reports/trips-summary?status=client_approved', $this->admin)));
        $this->assertSame([$a->code], $this->codes($this->read('reports/trips-summary?period=custom&date_from=2026-02-01&date_to=2026-02-28', $this->admin)));
        $this->assertCount(2, $this->read("reports/trips-summary?company_id={$this->company->id}&project_id={$this->project->id}&facility_id={$this->facility->id}", $this->admin)->json('data'));
    }

    public function test_the_contractor_filter_matches_his_driver_or_vehicle(): void
    {
        $trip = $this->createAwaiting();
        $contractor = Contractor::factory()->create();
        Vehicle::whereKey($this->vehicle->id)->update(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);

        $this->assertSame([$trip->code], $this->codes($this->read("reports/trips-summary?contractor_id={$contractor->id}", $this->admin)));
        $this->assertSame([], $this->codes($this->read('reports/trips-summary?contractor_id='.Contractor::factory()->create()->id, $this->admin)));
    }

    public function test_the_period_presets_follow_the_calendar(): void
    {
        // "now" is Sunday 2026-03-01 10:00 — the first day of the week, the month, the quarter… not of the year
        $today = $this->createAwaiting(['trip_at' => '2026-03-01 08:00']);
        $january = $this->createAwaiting(['trip_at' => '2026-01-15 08:00']);
        $lastYear = $this->createAwaiting(['trip_at' => '2025-12-20 08:00']);

        foreach (['today', 'week', 'month'] as $period) {
            $this->assertSame([$today->code], $this->codes($this->read("reports/trips-summary?period={$period}", $this->admin)), $period);
        }
        $this->assertSame(collect([$today->code, $january->code])->sort()->values()->all(), $this->codes($this->read('reports/trips-summary?period=quarter', $this->admin)));
        $this->assertSame(collect([$today->code, $january->code])->sort()->values()->all(), $this->codes($this->read('reports/trips-summary?period=year', $this->admin)));
        $this->assertCount(3, $this->read('reports/trips-summary', $this->admin)->json('data'));
        $this->assertNotContains($lastYear->code, $this->codes($this->read('reports/trips-summary?period=year', $this->admin)));
    }

    public function test_the_filter_lists_hold_only_what_the_users_trips_hold(): void
    {
        $this->createAwaiting();
        $otherProject = Project::factory()->create(['name' => 'Elsewhere']);
        Trip::factory()->forProject($otherProject)->create(['driver_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id, 'main_service_id' => $this->service->id]);

        $lists = $this->read('reports/filters', $this->clientManager)->json('data');

        $this->assertSame(['Riyadh Hotel'], array_column($lists['projects'], 'name'));
        $this->assertSame(['Al Nakheel'], array_column($lists['companies'], 'name'));
        $this->assertCount(6, $lists['periods']);
        $this->assertCount(5, $lists['statuses']);
    }

    // ── charts ──────────────────────────────────────────────────────

    public function test_the_charts_count_the_filtered_trips_and_leave_the_cancelled_out_of_the_figures(): void
    {
        $this->tripIn(Trip::STATUS_COMPLETED);
        $this->tripIn(Trip::STATUS_CANCELLED);

        $charts = $this->read('reports/charts?period=custom&date_from=2026-02-20&date_to=2026-03-05', $this->admin)->json('data');

        $this->assertSame(1, $charts['totals']['trips']);
        $this->assertSame(1, $charts['totals']['vehicles']);
        $this->assertSame('day', $charts['timeline']['unit']);
        $this->assertCount(14, $charts['timeline']['points']);
        $this->assertSame(1, collect($charts['statuses'])->firstWhere('status', 'cancelled')['count']);
        $this->assertSame([['id' => $this->sub->id, 'name' => 'General collection', 'trips' => 1, 'quantity_ton' => $charts['sub_services'][0]['quantity_ton']]], $charts['sub_services']);
    }

    public function test_a_long_period_is_charted_by_month(): void
    {
        $this->createAwaiting();

        $this->assertSame('month', $this->read('reports/charts?period=custom&date_from=2025-03-01&date_to=2026-03-01', $this->admin)->json('data.timeline.unit'));
    }

    // ── the Excel ───────────────────────────────────────────────────

    public function test_the_excel_holds_the_filtered_rows_numbered_with_the_frds_columns(): void
    {
        Excel::fake();
        $a = $this->createAwaiting(['trip_at' => '2026-02-10 08:00']);
        $this->createAwaiting(['trip_at' => '2026-02-20 08:00']);
        $this->tripIn(Trip::STATUS_CANCELLED);

        $this->actingAs($this->admin, 'web')->get('/api/v1/reports/trips-summary/export?period=custom&date_from=2026-02-01&date_to=2026-02-15')->assertOk();

        Excel::assertDownloaded('trips-summary.xlsx', function (TripsSummaryExport $export) use ($a) {
            $rows = $export->query()->get();
            $line = $export->map($rows->first());

            return $rows->count() === 1 && $line[0] === 1 && $line[1] === $a->code && count($export->headings()) === 11;
        });
    }

    public function test_the_excel_opens_with_the_shared_header_and_the_frds_headings(): void
    {
        $this->createAwaiting();
        $this->actingAs($this->admin, 'web');

        $query = ReportFilters::query($this->admin, request())->with(TripsSummary::RELATIONS);
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, Excel::raw(new TripsSummaryExport($query, $this->admin), \Maatwebsite\Excel\Excel::XLSX));
        $sheet = IOFactory::load($file)->getActiveSheet();
        unlink($file);

        $this->assertSame('GLOBAL CLEAR MISSION', $sheet->getCell('B1')->getValue());   // ExportHeader, like every export
        $this->assertSame('No.', $sheet->getCell('A6')->getValue());
        $this->assertSame('Trip status', $sheet->getCell('K6')->getValue());
        $this->assertSame(1, $sheet->getCell('A7')->getValue());
    }
}
