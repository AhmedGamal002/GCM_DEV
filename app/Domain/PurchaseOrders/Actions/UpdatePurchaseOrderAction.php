<?php

namespace App\Domain\PurchaseOrders\Actions;

use App\Domain\PurchaseOrders\Events\PurchaseOrderLineStatusChanged;
use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\QuotaBalance;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Rules\NoOverlappingMainServiceRule;
use App\Domain\PurchaseOrders\Support\PurchaseOrderEditability;
use App\Domain\PurchaseOrders\Support\PurchaseOrderInvariants;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.13 "تعديل تعاقد" — System Admin only. What may change is
 * PurchaseOrderEditability's call (decision ب2: everything until a trip is
 * recorded on the PO, the quota only after that); this action applies the
 * rest consistently:
 *  - a new company / project / paper number rebuilds the number from the
 *    company's prefix and keeps the project inside the company;
 *  - `services`, when sent, is the whole list of lines after the edit (an
 *    item with `id` edits that line, one without adds a line, a line left
 *    out is removed);
 *  - if the dates, the project or the lines' services changed on an active
 *    PO, the overlap rule runs again (ب5);
 *  - a quota change that moves a line to another status fires
 *    PurchaseOrderLineStatusChanged, like a trip would.
 * operational_status has its own action.
 */
class UpdatePurchaseOrderAction
{
    /**
     * @param  array<string, mixed>  $data  input in the shape of PurchaseOrderRules::update()
     */
    public function execute(PurchaseOrder $purchaseOrder, array $data, ?UploadedFile $attachment, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $attachment, $actor) {
            // Lock the PO and its lines first: a trip charged meanwhile (DeductQuotaAction
            // locks the line) would change what may be edited, so it waits for us or we for it.
            $po = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $po->setRelation('services', PurchaseOrderService::where('purchase_order_id', $po->id)->orderBy('id')->lockForUpdate()->get());

            PurchaseOrderEditability::for($po, true)->assertAllows($data, $attachment !== null);

            $statusesBefore = $this->lineStatuses($po->services);

            $companyId = (int) ($data['company_id'] ?? $po->company_id);
            $projectId = (int) ($data['project_id'] ?? $po->project_id);
            $paperNumber = (string) ($data['paper_number'] ?? $po->paperNumber());
            $companyChanged = $companyId !== (int) $po->company_id;
            $projectChanged = $projectId !== (int) $po->project_id;

            if ($companyChanged || $projectChanged || $paperNumber !== $po->paperNumber()) {
                [$company, $project] = PurchaseOrderInvariants::parties($companyId, $projectId, $companyChanged, $projectChanged);
                $po->company_id = $company->id;
                $po->project_id = $project->id;
                $po->number = PurchaseOrderInvariants::number($company, $paperNumber, $po->id);
            } else {
                // Same lock as a create, for the overlap check below.
                Project::whereKey($po->project_id)->lockForUpdate()->first();
            }

            [$starts, $ends] = PurchaseOrderInvariants::dates($data['starts_on'] ?? $po->starts_on, $data['ends_on'] ?? $po->ends_on);
            $datesChanged = ! $starts->equalTo($po->starts_on) || ! $ends->equalTo($po->ends_on);
            $po->starts_on = $starts;
            $po->ends_on = $ends;

            foreach (['region', 'additional_data'] as $field) {
                if (array_key_exists($field, $data)) {
                    $po->{$field} = $data[$field] === '' ? null : $data[$field];
                }
            }

            // The main services after the edit, keyed like the `services` input (so an error
            // names the line the user sent) or by position when no lines were sent.
            $mainServiceIds = $po->services->pluck('main_service_id')->map(fn ($id) => (int) $id)->all();
            $servicesChanged = false;

            if (array_key_exists('services', $data)) {
                [$servicesChanged, $mainServiceIds] = $this->syncLines($po, (array) $data['services']);
            }

            if ($po->isActive() && ($datesChanged || $projectChanged || $servicesChanged)) {
                NoOverlappingMainServiceRule::assertNone($po->project_id, $mainServiceIds, $starts, $ends, $po->id);
            }

            StorePurchaseOrderAttachment::into($po, $attachment);
            $po->updated_by = $actor->id;

            try {
                $po->save();
            } catch (UniqueConstraintViolationException) {
                throw PurchaseOrderInvariants::numberTaken($po->number);
            }

            $po->load('services');

            foreach ($this->lineStatuses($po->services) as $lineId => $after) {
                $before = $statusesBefore[$lineId] ?? null;

                if ($before !== null && $before !== $after) {
                    PurchaseOrderLineStatusChanged::dispatch($po->services->find($lineId), $before, $after);
                }
            }

            return $po->load(['company', 'project', 'services.mainService', 'updatedBy']);
        });
    }

    /**
     * Applies the full list of lines. Returns whether the set of main
     * services changed (a line added, removed or moved to another service),
     * and the main services keyed like the input.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{0: bool, 1: array<int|string, int>}
     */
    private function syncLines(PurchaseOrder $po, array $items): array
    {
        $current = $po->services->keyBy('id');

        // The final list, to check it as a whole: an edited line keeps what wasn't sent.
        $final = [];
        $newKeys = [];

        foreach ($items as $key => $item) {
            $line = isset($item['id']) ? $current->get((int) $item['id']) : null;

            $final[$key] = $line === null ? $item : [
                ...$item,
                'main_service_id' => $item['main_service_id'] ?? $line->main_service_id,
                'quota_method' => $item['quota_method'] ?? $line->quota_method,
            ];

            if ($line === null || (int) $final[$key]['main_service_id'] !== (int) $line->main_service_id) {
                $newKeys[] = $key;
            }
        }

        PurchaseOrderInvariants::lines($final, $newKeys);

        // Remove first, so a line removed and added again for the same service
        // doesn't trip the one-service-per-PO unique index.
        $keptIds = collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $removed = $current->keys()->diff($keptIds);

        if ($removed->isNotEmpty()) {
            PurchaseOrderService::whereIn('id', $removed->all())->delete();
        }

        foreach ($items as $key => $item) {
            $line = isset($item['id']) ? $current->get((int) $item['id']) : null;

            if ($line === null) {
                $line = new PurchaseOrderService;
                $line->purchase_order_id = $po->id;
            }

            PurchaseOrderInvariants::fillLine($line, $final[$key], true);
            $line->save();
        }

        return [
            $newKeys !== [] || $removed->isNotEmpty(),
            array_map(fn ($item) => (int) $item['main_service_id'], $final),
        ];
    }

    /**
     * @param  iterable<PurchaseOrderService>  $lines
     * @return array<int, string> line id → status
     */
    private function lineStatuses(iterable $lines): array
    {
        return array_map(fn (QuotaBalance $b) => PurchaseOrderStatus::line($b), QuotaBalances::forMany($lines, null, true));
    }
}
