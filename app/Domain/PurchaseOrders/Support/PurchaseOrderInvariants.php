<?php

namespace App\Domain\PurchaseOrders\Support;

use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The checks every PO write shares, wherever it comes from (FRD §1.13):
 *  - the project belongs to the chosen client company; a company or project
 *    picked anew must be active (the create form only lists active ones);
 *  - the number = the company's prefix + the paper number (digits only), and
 *    it is free in the tenant — a number is never given out twice, even when
 *    its PO is deactivated (decision ب4);
 *  - the range ends on or after its start;
 *  - one main service at most once per PO, and a main service added anew
 *    must be active ("any module that picks a service shows the active ones").
 *
 * The project row is read under lockForUpdate so the overlap check that
 * follows (NoOverlappingMainServiceRule) is serialised per project.
 */
final class PurchaseOrderInvariants
{
    /**
     * @return array{0: Company, 1: Project}
     *
     * @throws ValidationException
     */
    public static function parties(int $companyId, int $projectId, bool $companyMustBeActive, bool $projectMustBeActive): array
    {
        $company = Company::find($companyId);

        if ($company === null) {
            throw ValidationException::withMessages(['company_id' => __('The selected client company does not exist.')]);
        }

        if ($companyMustBeActive && $company->operational_status !== 'active') {
            throw ValidationException::withMessages(['company_id' => __('A purchase order can only be made for an active client company.')]);
        }

        $project = Project::whereKey($projectId)->lockForUpdate()->first();

        if ($project === null || (int) $project->company_id !== (int) $company->id) {
            throw ValidationException::withMessages(['project_id' => __('The project must be one of the selected client company\'s projects.')]);
        }

        if ($projectMustBeActive && $project->operational_status !== 'active') {
            throw ValidationException::withMessages(['project_id' => __('A purchase order can only be made for an active project.')]);
        }

        return [$company, $project];
    }

    /** @throws ValidationException */
    public static function number(Company $company, string $paperNumber, ?int $ignorePurchaseOrderId = null): string
    {
        if (! preg_match(PurchaseOrder::PAPER_NUMBER_PATTERN, $paperNumber)) {
            throw ValidationException::withMessages(['paper_number' => __('The purchase order number must be digits only, up to 10.')]);
        }

        $number = PurchaseOrder::composeNumber($company->prefix, $paperNumber);

        $taken = PurchaseOrder::query()
            ->where('number', $number)
            ->when($ignorePurchaseOrderId, fn ($q) => $q->whereKeyNot($ignorePurchaseOrderId))
            ->exists();

        if ($taken) {
            throw self::numberTaken($number);
        }

        return $number;
    }

    public static function numberTaken(string $number): ValidationException
    {
        return ValidationException::withMessages(['paper_number' => __('The purchase order number :number is already used. A number is never given out again, even when its purchase order is deactivated.', ['number' => $number])]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws ValidationException
     */
    public static function dates(mixed $startsOn, mixed $endsOn): array
    {
        $starts = Carbon::parse($startsOn)->startOfDay();
        $ends = Carbon::parse($endsOn)->startOfDay();

        if ($ends->lt($starts)) {
            throw ValidationException::withMessages(['ends_on' => __('The purchase order cannot end before it starts.')]);
        }

        return [$starts, $ends];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items  the lines after the write
     * @param  list<int|string>  $newKeys  the keys of the lines whose service is chosen anew (must be active)
     *
     * @throws ValidationException
     */
    public static function lines(array $items, array $newKeys): void
    {
        if ($items === []) {
            throw ValidationException::withMessages(['services' => __('A purchase order needs at least one main service.')]);
        }

        $errors = [];
        $seen = [];

        foreach ($items as $key => $item) {
            $mainServiceId = (int) ($item['main_service_id'] ?? 0);

            if (isset($seen[$mainServiceId])) {
                $errors["services.{$key}.main_service_id"] = __('This main service is already in this purchase order.');
            }
            $seen[$mainServiceId] = true;

            if (! in_array($item['quota_method'] ?? null, PurchaseOrderService::METHODS, true)) {
                $errors["services.{$key}.quota_method"] = __('Choose how the quota is calculated: trips only, or trips and ancillary services.');
            }
        }

        $newIds = array_map(fn ($key) => (int) $items[$key]['main_service_id'], $newKeys);
        $active = MainService::query()->whereIn('id', $newIds)->where('operational_status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($newKeys as $key) {
            if (! in_array((int) $items[$key]['main_service_id'], $active, true)) {
                $errors["services.{$key}.main_service_id"] = __('Only an active main service can be added to a purchase order.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Writes one line's attributes. On a trips-only line the ancillary cost
     * is 0 — it isn't part of that quota.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fillLine(PurchaseOrderService $line, array $item, bool $identity): void
    {
        if ($identity) {
            $line->main_service_id = (int) $item['main_service_id'];
            $line->quota_method = $item['quota_method'];
        }

        foreach (['max_trips', 'trip_cost', 'alert_percent'] as $field) {
            if (array_key_exists($field, $item)) {
                $line->{$field} = $item[$field];
            }
        }

        if ($line->tracksAncillary()) {
            if (array_key_exists('ancillary_cost_per_trip', $item)) {
                $line->ancillary_cost_per_trip = $item['ancillary_cost_per_trip'] ?? 0;
            }
        } else {
            $line->ancillary_cost_per_trip = 0;
        }
    }
}
