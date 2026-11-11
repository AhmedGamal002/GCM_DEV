<?php

namespace App\Domain\PurchaseOrders\Actions;

use App\Domain\PurchaseOrders\Rules\NoOverlappingMainServiceRule;
use App\Domain\PurchaseOrders\Support\PurchaseOrderInvariants;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.13 "انشاء تعاقد جديد" — System Admin only (the policy of
 * part 6.2 says who may call it). Takes the input described in
 * PurchaseOrderRules and checks everything that needs other rows:
 * PurchaseOrderInvariants (company / project / number / dates / lines) and,
 * for a PO created active, the overlap rule. The region defaults to the
 * project's operational region when the input doesn't carry one (decision
 * ب11: "pre-filled from the project's region, and changeable").
 */
class CreatePurchaseOrderAction
{
    /**
     * @param  array<string, mixed>  $data  input in the shape of PurchaseOrderRules::store()
     */
    public function execute(array $data, ?UploadedFile $attachment, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $attachment, $actor) {
            [$company, $project] = PurchaseOrderInvariants::parties((int) $data['company_id'], (int) $data['project_id'], true, true);
            $number = PurchaseOrderInvariants::number($company, (string) $data['paper_number']);
            [$starts, $ends] = PurchaseOrderInvariants::dates($data['starts_on'], $data['ends_on']);

            $items = (array) ($data['services'] ?? []);
            PurchaseOrderInvariants::lines($items, array_keys($items));

            $status = $data['operational_status'] ?? 'active';

            if ($status === 'active') {
                NoOverlappingMainServiceRule::assertNone($project->id, array_map(fn ($i) => (int) $i['main_service_id'], $items), $starts, $ends);
            }

            $po = new PurchaseOrder([
                'starts_on' => $starts,
                'ends_on' => $ends,
                'region' => array_key_exists('region', $data) ? $data['region'] : $project->operational_region,
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            // Outside $fillable — set explicitly, after the checks above.
            $po->company_id = $company->id;
            $po->project_id = $project->id;
            $po->number = $number;
            $po->operational_status = $status;
            $po->updated_by = $actor->id;

            try {
                $po->save();
            } catch (UniqueConstraintViolationException) {
                // Someone took the same number a moment ago (the check above ran first).
                throw PurchaseOrderInvariants::numberTaken($number);
            }

            foreach ($items as $item) {
                $line = new PurchaseOrderService;
                $line->purchase_order_id = $po->id;
                PurchaseOrderInvariants::fillLine($line, $item, true);
                $line->save();
            }

            if ($attachment !== null) {
                // Stored under the PO's id, which only exists after the first save.
                StorePurchaseOrderAttachment::into($po, $attachment);
                $po->save();
            }

            return $po->load(['company', 'project', 'services.mainService', 'updatedBy']);
        });
    }
}
