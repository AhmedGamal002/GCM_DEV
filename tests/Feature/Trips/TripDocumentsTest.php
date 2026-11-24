<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\UpdateTripAction;
use App\Domain\Trips\Documents\TripDocumentData;
use App\Domain\Trips\Documents\TripDocumentListPages;
use App\Domain\Trips\Documents\TripDocumentNumber;
use App\Domain\Trips\Documents\TripDocumentRenderer;
use App\Domain\Trips\Documents\TripDocuments;
use App\Models\Contractor;
use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Part 8.1 — a trip's generated documents (FRD V01.14 §1.14 "الوثائق المولدة تلقائياً"):
 *
 *  - only once the trip is completed (د3), generated on request; the number (one per trip, shared by
 *    DN- and M-, per tenant from 100001 — د2) is reserved on the first generation and never changes;
 *  - who opens what (د10 and the plan): staff and the client's accounts both documents, the driver and
 *    the contractor user the delivery note, the combined PDF staff only; another's trip is a 404;
 *  - what they print (the FRD's source columns, د5–د9): the data is checked on TripDocumentData, the
 *    PDF bytes only for being a PDF (DomPDF compresses its text).
 *
 * One actor per test method (the actingAs cache trap, CLAUDE.md).
 */
class TripDocumentsTest extends TripTestCase
{
    private function read(User $user, string $uri): TestResponse
    {
        return $this->actingAs($user, 'web')->get('/api/v1/'.$uri, ['Accept' => 'application/json']);
    }

    private function completed(array $overrides = []): Trip
    {
        return $this->finalApprove($this->clientApprove($this->createAwaiting($overrides)));
    }

    private function contractorTrip(): array
    {
        $contractor = Contractor::factory()->create();
        $this->vehicle->forceFill(['affiliation' => 'contractor', 'contractor_id' => $contractor->id])->save();

        return [$this->completed(), User::factory()->contractorUser($contractor)->create()];
    }

    // ------------------------------------------------------------ when: completed only, number reserved once

    public function test_there_are_no_documents_before_the_trip_is_completed(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());

        $this->read($this->admin, "trips/{$trip->id}/documents/delivery-note")->assertStatus(409);
        $this->read($this->admin, "trips/{$trip->id}/documents/export")->assertStatus(409);
        $this->assertNull($trip->fresh()->document_number);
    }

    public function test_a_cancelled_trip_has_no_documents_even_if_they_were_generated(): void
    {
        $trip = $this->completed();
        TripDocuments::render($trip, TripDocuments::DELIVERY_NOTE);
        $this->cancel($trip);

        $this->read($this->admin, "trips/{$trip->id}/documents/delivery-note")->assertStatus(409);
        $this->assertSame(TripDocumentNumber::FIRST, $trip->fresh()->document_number, 'a reserved number is never given back');
    }

    public function test_the_delivery_note_opens_inline_with_its_number_reserved_on_the_first_generation(): void
    {
        $trip = $this->completed();
        $this->assertNull($trip->fresh()->document_number);

        $response = $this->read($this->admin, "trips/{$trip->id}/documents/delivery-note")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline; filename="DN-100001.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(100001, $trip->fresh()->document_number);
    }

    public function test_both_documents_share_one_number_that_runs_across_companies_and_never_changes(): void
    {
        $first = $this->completed();
        $second = $this->completed();

        $otherProject = Project::factory()->for(Company::factory()->create(['prefix' => 'BRK']))->create();
        $third = Trip::factory()->forProject($otherProject)->create(['status' => Trip::STATUS_COMPLETED]);

        // Generated out of order: the number follows the first generation, not the trip.
        $this->assertSame(100001, TripDocumentNumber::reserve($second));
        $this->assertSame(100002, TripDocumentNumber::reserve($third));
        $this->assertSame(100003, TripDocumentNumber::reserve($first));
        $this->assertSame(100001, TripDocumentNumber::reserve($second->fresh()), 'a second generation keeps the number');

        $second->refresh();
        $this->assertSame('DN-100001', TripDocuments::code($second, TripDocuments::DELIVERY_NOTE));
        $this->assertSame('M-100001', TripDocuments::code($second, TripDocuments::WASTE_MANIFEST));
    }

    public function test_reserving_the_number_is_not_an_edit_and_an_edit_cannot_change_it(): void
    {
        $trip = $this->completed();
        $updatedAt = $trip->fresh()->updated_at;
        $events = TripEvent::where('trip_id', $trip->id)->count();

        $this->travel(5)->minutes();
        TripDocumentNumber::reserve($trip);

        $this->assertEquals($updatedAt, $trip->fresh()->updated_at);
        $this->assertSame($events, TripEvent::where('trip_id', $trip->id)->count());

        $fresh = $trip->fresh();
        $fresh->document_number = 999;
        $fresh->save();
        $this->assertSame(100001, $trip->fresh()->document_number);
    }

    public function test_the_number_runs_per_tenant(): void
    {
        // Another tenant's trip with a high number doesn't move ours.
        $other = Trip::factory()->create();
        $otherTenant = \App\Models\Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);
        \Illuminate\Support\Facades\DB::table('trips')->where('id', $other->id)->update(['tenant_id' => $otherTenant->id, 'document_number' => 500000]);

        $this->assertSame(100001, TripDocumentNumber::reserve($this->completed()));
    }

    // ------------------------------------------------------------ who opens what

    /** @return array<string, array{string, int, int, int}> role => [delivery note, manifest, export] status */
    public static function staffAndClients(): array
    {
        return [
            'system admin' => ['admin', 200, 200, 200],
            'data entry' => ['dataEntry', 200, 200, 200],
            'auditor' => ['auditor', 200, 200, 200],
            'client project manager' => ['clientManager', 200, 200, 403],
            'client project auditor' => ['clientAuditor', 200, 200, 403],
        ];
    }

    #[DataProvider('staffAndClients')]
    public function test_staff_and_the_client_open_both_documents_and_only_staff_export_them(string $who, int $note, int $manifest, int $export): void
    {
        $trip = $this->completed();
        $user = $this->{$who};

        $this->read($user, "trips/{$trip->id}/documents/delivery-note")->assertStatus($note);
        $this->read($user, "trips/{$trip->id}/documents/waste-manifest")->assertStatus($manifest);
        $this->read($user, "trips/{$trip->id}/documents/export")->assertStatus($export);
    }

    public function test_the_driver_opens_the_delivery_note_of_their_trip_only(): void
    {
        $trip = $this->completed();
        $driver = $this->driver->user;

        $this->read($driver, "trips/{$trip->id}/documents/delivery-note")->assertOk();
        $this->read($driver, "trips/{$trip->id}/documents/waste-manifest")->assertForbidden();
        $this->read($driver, "trips/{$trip->id}/documents/export")->assertForbidden();
    }

    public function test_another_driver_does_not_find_the_trip(): void
    {
        $trip = $this->completed();
        $other = \App\Models\Driver::factory()->create();

        $this->read($other->user, "trips/{$trip->id}/documents/delivery-note")->assertNotFound();
    }

    public function test_the_contractor_user_opens_the_delivery_note_of_a_trip_with_its_vehicle(): void
    {
        [$trip, $user] = $this->contractorTrip();

        $this->read($user, "trips/{$trip->id}/documents/delivery-note")->assertOk();
        $this->read($user, "trips/{$trip->id}/documents/waste-manifest")->assertForbidden();
        $this->read($user, "trips/{$trip->id}/documents/export")->assertForbidden();
    }

    public function test_another_contractor_user_does_not_find_the_trip(): void
    {
        $trip = $this->completed();
        $user = User::factory()->contractorUser(Contractor::factory()->create())->create();

        $this->read($user, "trips/{$trip->id}/documents/delivery-note")->assertNotFound();
    }

    public function test_a_client_of_another_company_does_not_find_the_trip(): void
    {
        $trip = $this->completed();
        $other = User::factory()->client(Company::factory()->create(['prefix' => 'OTH']), 'client_project_manager')->create();

        $this->read($other, "trips/{$trip->id}/documents/delivery-note")->assertNotFound();
    }

    public function test_the_combined_pdf_is_a_download_named_after_the_trip(): void
    {
        $trip = $this->completed();

        $response = $this->read($this->admin, "trips/{$trip->id}/documents/export")->assertOk();

        $this->assertSame('attachment; filename="ALN-T0001-documents.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_account_on_vacation_previews_but_does_not_export(): void
    {
        $trip = $this->completed();
        $this->auditor->forceFill(['status' => 'on_vacation'])->save();

        $this->read($this->auditor, "trips/{$trip->id}/documents/delivery-note")->assertOk();
        $this->read($this->auditor, "trips/{$trip->id}/documents/export")->assertForbidden();
    }

    public function test_an_unknown_document_kind_is_a_404(): void
    {
        $trip = $this->completed();

        $this->read($this->admin, "trips/{$trip->id}/documents/recycle-receipt")->assertNotFound();
    }

    // ------------------------------------------------------------ the trip resource points at them

    public function test_the_details_list_the_documents_each_role_may_open(): void
    {
        $trip = $this->completed();
        TripDocumentNumber::reserve($trip);

        $documents = $this->read($this->admin, "trips/{$trip->id}")->assertOk()->json('data.documents');

        $this->assertSame('DN-100001', $documents['delivery_note']['number']);
        $this->assertSame('M-100001', $documents['waste_manifest']['number']);
        $this->assertStringEndsWith("/api/v1/trips/{$trip->id}/documents/delivery-note", $documents['delivery_note']['url']);
        $this->assertStringEndsWith("/api/v1/trips/{$trip->id}/documents/export", $documents['export_url']);
    }

    public function test_the_client_sees_both_documents_without_the_export(): void
    {
        $trip = $this->completed();

        $documents = $this->read($this->clientAuditor, "trips/{$trip->id}")->assertOk()->json('data.documents');

        $this->assertSame(['delivery_note', 'waste_manifest', 'export_url'], array_keys($documents));
        $this->assertNull($documents['delivery_note']['number'], 'not generated yet');
        $this->assertNull($documents['export_url']);
    }

    public function test_an_unfinished_trip_lists_no_documents(): void
    {
        $trip = $this->createAwaiting();

        $this->assertSame(['export_url' => null], $this->read($this->admin, "trips/{$trip->id}")->assertOk()->json('data.documents'));
    }

    // ------------------------------------------------------------ a supplying trip: delivery note only

    public function test_a_supplying_trip_has_a_delivery_note_but_no_waste_manifest(): void
    {
        $water = MainService::factory()->create(['name' => 'Water supply', 'service_type' => 'supplying']);
        $sweet = SubService::factory()->forService($water)->create(['name' => 'Sweet water']);
        $this->makeCompatible($this->driver, $this->vehicle, $water);
        $sweet->capacities()->syncWithoutDetaching([$this->capacity->id]);

        $trip = $this->completed([
            'main_service_id' => $water->id, 'sub_service_id' => $sweet->id, 'intermediate_facility_id' => null,
            'requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => 80,
        ]);

        $this->assertSame([TripDocuments::DELIVERY_NOTE], TripDocuments::available($trip));
        $this->read($this->admin, "trips/{$trip->id}/documents/waste-manifest")->assertNotFound();
        $this->read($this->admin, "trips/{$trip->id}/documents/delivery-note")->assertOk();

        $doc = TripDocumentData::for($trip->fresh());
        $this->assertNull($doc['waste_manifest_number']);
        $this->assertSame([['label' => 'Sweet water', 'checked' => true]], $doc['services']['supplying']);
    }

    // ------------------------------------------------------------ what they print

    public function test_the_documents_print_the_trip_and_its_representatives(): void
    {
        $this->admin->forceFill(['name' => 'Gamal Admin', 'phone' => '+966500000001'])->save();
        $trip = $this->completed(['additional_data' => '<p>Food <strong>waste</strong></p><p>Second line</p>']);
        TripDocumentNumber::reserve($trip);

        $doc = TripDocumentData::for($trip->fresh());

        $this->assertSame('DN-100001', $doc['delivery_note_number']);
        $this->assertSame('M-100001', $doc['waste_manifest_number']);
        $this->assertSame('Al Nakheel', $doc['company']);
        $this->assertSame('Riyadh Hotel', $doc['project']);
        $this->assertSame('2026-03-01', $doc['date']);
        $this->assertSame('Driver One', $doc['driver']);
        $this->assertSame('ABC 1234', $doc['vehicle']);
        $this->assertSame('Riyadh Landfill', $doc['facility']);
        $this->assertSame("Food waste\nSecond line", $doc['remarks']);

        // د5: GCM's representative = who gave the final approval, phone from the account, signature snapshot.
        $this->assertSame('Gamal Admin', $doc['gcm']['name']);
        $this->assertSame('+966500000001', $doc['gcm']['phone']);
        $this->assertStringStartsWith('data:image/png;base64,', $doc['gcm']['signature']);

        // د6: the client's signature snapshotted at the approval.
        $this->assertSame('Client Manager', $doc['client']['name']);
        $this->assertStringStartsWith('data:image/png;base64,', $doc['client']['signature']);
    }

    public function test_the_lists_print_every_active_item_with_the_trips_marked(): void
    {
        $hazardous = MainService::factory()->create(['name' => 'Hazardous', 'service_type' => 'hazardous']);
        SubService::factory()->forService($hazardous)->create(['name' => 'Oils']);
        SubService::factory()->forService($hazardous)->create(['name' => 'Retired', 'operational_status' => 'deactivated']);
        \App\Models\AssetCapacityCategory::factory()->create(['name' => '4 m3', 'capacity_cbm' => 4, 'capacity_ton' => 1.25]);
        $this->sub->forceFill(['skip_color_code' => 'yellow_food'])->save();

        $trip = $this->completed();
        $doc = TripDocumentData::for($trip->fresh());

        $this->assertSame([
            ['label' => 'Cardboard collection', 'checked' => false],
            ['label' => 'General collection', 'checked' => true],
        ], $doc['services']['non_hazardous']);
        $this->assertSame([['label' => 'Oils', 'checked' => false]], $doc['services']['hazardous'], 'deactivated ones are left out');
        $this->assertSame([], $doc['services']['supplying']);
        $this->assertSame([['label' => '4 m3', 'checked' => false], ['label' => '12 m3', 'checked' => true]], $doc['capacities'], 'every capacity, smallest first, the trip container\'s marked');

        $colors = collect($doc['colors']);
        $this->assertCount(count(SubService::SKIP_COLORS), $colors);
        $this->assertSame(['Yellow — Food'], $colors->where('checked', true)->pluck('label')->all());
    }

    public function test_the_trips_own_sub_service_stays_listed_after_it_is_deactivated(): void
    {
        $trip = $this->completed();
        $this->sub->forceFill(['operational_status' => 'deactivated'])->save();

        $labels = collect(TripDocumentData::for($trip->fresh())['services']['non_hazardous']);

        $this->assertTrue($labels->contains(['label' => 'General collection', 'checked' => true]));
    }

    public function test_the_permit_is_the_entry_permit_valid_on_the_trip_date(): void
    {
        $permit = fn (string $number, string $validTo) => VehicleDocument::create([
            'vehicle_id' => $this->vehicle->id, 'type' => 'entry_permit', 'document_number' => $number, 'valid_to' => $validTo,
        ]);
        $permit('EXPIRED-1', '2026-02-28');
        $permit('VALID-1', '2026-06-30');
        $permit('VALID-LATEST', '2026-12-31');

        $trip = $this->completed();

        $this->assertSame('VALID-LATEST', TripDocumentData::for($trip->fresh())['permit']);
    }

    public function test_there_is_no_permit_when_none_is_valid_on_the_trip_date(): void
    {
        VehicleDocument::create(['vehicle_id' => $this->vehicle->id, 'type' => 'entry_permit', 'document_number' => 'OLD', 'valid_to' => '2026-01-31']);
        VehicleDocument::create(['vehicle_id' => $this->vehicle->id, 'type' => 'insurance', 'document_number' => 'INS', 'valid_to' => '2026-12-31']);

        $this->assertNull(TripDocumentData::for($this->completed()->fresh())['permit']);
    }

    public function test_editing_a_completed_trip_updates_its_documents_but_not_their_number(): void
    {
        $trip = $this->completed();
        TripDocumentNumber::reserve($trip);

        app(UpdateTripAction::class)->execute($trip->fresh(), $this->admin, ['trip_at' => '2026-03-02 08:00']);

        $doc = TripDocumentData::for($trip->fresh());
        $this->assertSame('2026-03-02', $doc['date']);
        $this->assertSame('DN-100001', $doc['delivery_note_number']);
    }

    // ------------------------------------------------------------ the combined PDF: DN → M → receipt

    public function test_the_combined_pdf_adds_the_recycle_receipt_photo_last_when_there_is_one(): void
    {
        $trip = $this->clientApprove($this->createAwaiting(['sub_service_id' => $this->receiptSub->id]));
        $trip = app(\App\Domain\Trips\Actions\FinalApproveTripAction::class)->execute($trip, $this->admin, ['gcm_signature' => $this->image('gcm.png')], ['recycle_receipt_number' => 'INV-131004461423'], ['recycle_receipt' => UploadedFile::fake()->image('receipt.jpg', 300, 600)]);
        $kinds = [...TripDocuments::available($trip), TripDocuments::RECYCLE_RECEIPT];

        $pages = TripDocumentRenderer::pages($trip->fresh(), $kinds);
        $this->assertSame([TripDocuments::DELIVERY_NOTE, TripDocuments::WASTE_MANIFEST, TripDocuments::RECYCLE_RECEIPT], $pages['kinds']);
        $this->assertSame('INV-131004461423', $pages['doc']['receipt']['number']);

        $html = view('tenant.trips.documents.pdf', ['pages' => [$pages]])->render();
        $this->assertSame(3, substr_count($html, 'class="doc-title"'));
        $this->assertLessThan(strpos($html, 'WASTE MANAGEMENT MANIFEST'), strpos($html, 'DELIVERY NOTE'));
        $this->assertLessThan(strpos($html, 'RECYCLE RECEIPT'), strpos($html, 'WASTE MANAGEMENT MANIFEST'));
    }

    public function test_the_combined_pdf_leaves_out_a_missing_receipt(): void
    {
        $trip = $this->completed();

        $pages = TripDocumentRenderer::pages($trip, [...TripDocuments::available($trip), TripDocuments::RECYCLE_RECEIPT]);

        $this->assertSame([TripDocuments::DELIVERY_NOTE, TripDocuments::WASTE_MANIFEST], $pages['kinds']);
    }

    // ------------------------------------------------------------ long lists: the middle pages drawn on the canvas

    /** A trip whose hazardous list holds `$count` sub-services (a manifest list of $count rows). */
    private function tripWithLongList(int $count): Trip
    {
        $hazardous = MainService::factory()->create(['name' => 'Hazardous', 'service_type' => 'hazardous']);
        for ($i = 1; $i <= $count; $i++) {
            SubService::factory()->forService($hazardous)->create(['name' => sprintf('Item %03d', $i)]);
        }

        return $this->completed()->fresh();
    }

    public function test_a_normal_list_is_plain_html(): void
    {
        $trip = $this->completed();

        TripDocuments::render($trip, TripDocuments::WASTE_MANIFEST);

        $this->assertSame([], TripDocumentListPages::registered());
    }

    public function test_the_middle_pages_of_a_long_list_are_drawn_and_cover_every_row_once(): void
    {
        $trip = $this->tripWithLongList(150);

        $pdf = TripDocuments::render($trip, TripDocuments::WASTE_MANIFEST);
        $pages = array_values(TripDocumentListPages::registered());

        // Manifest: 36 rows under the header, then 44 a page — rows 36–79 and 80–123 drawn, 124–149 HTML.
        $this->assertSame([[36, 80], [80, 124]], array_map(fn ($p) => [$p['from'], $p['to']], $pages));
        $this->assertSame('Type of Waste Collected', $pages[0]['caption']);
        $this->assertCount(150, $pages[0]['columns'][1]['items']);

        $html = view('tenant.trips.documents.pdf', ['pages' => [TripDocumentRenderer::pages($trip, [TripDocuments::WASTE_MANIFEST])]])->render();
        $this->assertSame(2, substr_count($html, 'class="list-page"'));
        $this->assertStringContainsString('Item 036', $html, 'the first page ends at row 35 (Item 036 is row 35)');
        $this->assertStringNotContainsString('Item 037<', $html, 'rows 36–123 are drawn, not HTML');
        $this->assertStringContainsString('Item 125', $html, 'the last page (rows 124+) is HTML again');

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThanOrEqual(4, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf), 'page 1, two drawn pages, the last list page');
    }

    public function test_a_list_label_too_wide_for_its_cell_is_shortened_the_same_way_on_every_page(): void
    {
        $long = ['label' => str_repeat('Contaminated material ', 10), 'checked' => false];
        $short = ['label' => 'Paper', 'checked' => false];

        $this->assertSame('Paper', TripDocumentListPages::label($short, 4));
        $this->assertStringEndsWith('…', TripDocumentListPages::label($long, 4));
        $this->assertLessThan(mb_strlen($long['label']), mb_strlen(TripDocumentListPages::label($long, 4)));
        $this->assertGreaterThan(mb_strlen(TripDocumentListPages::label($long, 4)), mb_strlen(TripDocumentListPages::label($long, 2)), 'a wider cell keeps more');
    }

    public function test_the_recycle_receipt_upload_must_be_a_photo(): void
    {
        $response = $this->actingAs($this->admin, 'web')->post('/api/v1/trips', [
            ...$this->staffData(),
            'recycle_receipt' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors('recycle_receipt');
    }

    public function test_the_printed_text_is_escaped_and_arabic_is_shaped(): void
    {
        $this->company->forceFill(['name' => 'شركة <b>النخيل</b>'])->save();
        $trip = $this->completed();

        $html = view('tenant.trips.documents.pdf', ['pages' => [TripDocumentRenderer::pages($trip->fresh(), [TripDocuments::DELIVERY_NOTE])]])->render();

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('شركة', $html, 'raw Arabic would print unjoined — it goes through ArabicText::forPdf');
        $this->assertStringContainsString(e(\App\Support\ArabicText::forPdf('شركة <b>النخيل</b>')), $html);
    }
}
