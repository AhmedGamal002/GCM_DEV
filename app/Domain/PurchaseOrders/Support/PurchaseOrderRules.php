<?php

namespace App\Domain\PurchaseOrders\Support;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use Illuminate\Validation\Rule;

/**
 * The shape of a PO's input (FRD §1.13 "انشاء تعاقد جديد") — field names,
 * types and ranges — for the store / update requests of part 6.2, so the
 * requests and the actions agree on one vocabulary:
 *
 *  - paper_number   the paper contract's number, digits only (up to 10,
 *                   leading zeros kept); the stored `number` is the company
 *                   prefix + "-" + it (ALN-1200)
 *  - company_id, project_id, starts_on, ends_on (Y-m-d), region,
 *    operational_status (create only — status changes have their own
 *    action), attachment (PDF / image ≤ 5 MB), additional_data
 *  - services[]     the lines: main_service_id, quota_method, max_trips,
 *                   trip_cost, ancillary_cost_per_trip (for
 *                   trips_and_ancillary), alert_percent; on update an `id`
 *                   names an existing line
 *
 * Everything that needs the database or other rows — the company and
 * project being active and belonging together, the number being free, the
 * overlap rule, the edit lock — is checked by the actions themselves, so it
 * holds whoever calls them.
 */
final class PurchaseOrderRules
{
    /** @return array<string, mixed> */
    public static function store(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'paper_number' => ['required', 'string', 'regex:'.PurchaseOrder::PAPER_NUMBER_PATTERN],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'region' => ['nullable', 'string', 'max:255'],
            'operational_status' => ['sometimes', Rule::in(['active', 'deactivated'])],
            'attachment' => ['nullable', 'file', 'mimes:'.implode(',', config('purchase_orders.attachment_mimes')), 'max:'.config('purchase_orders.attachment_max_kb')],
            'additional_data' => ['nullable', 'string'],
            ...self::serviceRules($tenantId, false),
        ];
    }

    /** @return array<string, mixed> */
    public static function update(): array
    {
        $tenantId = app('tenant')->id;
        $rules = self::store();
        unset($rules['operational_status']);

        // Every top-level field may be left out on an edit; a line sent is still complete.
        foreach (['paper_number', 'company_id', 'project_id', 'starts_on', 'ends_on'] as $field) {
            $rules[$field] = ['sometimes', ...array_values(array_filter($rules[$field], fn ($rule) => $rule !== 'required'))];
        }

        // With one date sent alone, `after_or_equal:starts_on` has nothing to compare to;
        // the update action checks the range once it is merged with the stored dates.
        $rules['ends_on'] = ['sometimes', 'date_format:Y-m-d'];

        return [...$rules, ...self::serviceRules($tenantId, true)];
    }

    /** @return array<string, mixed> */
    private static function serviceRules(int $tenantId, bool $forUpdate): array
    {
        $money = ['numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'];

        return [
            'services' => [$forUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'services.*.id' => $forUpdate ? ['sometimes', 'nullable', 'integer'] : ['prohibited'],
            'services.*.main_service_id' => ['required', 'integer', 'distinct', Rule::exists('main_services', 'id')->where('tenant_id', $tenantId)],
            'services.*.quota_method' => ['required', Rule::in(PurchaseOrderService::METHODS)],
            'services.*.max_trips' => ['required', 'integer', 'min:1', 'max:1000000'],
            'services.*.trip_cost' => ['required', ...$money],
            'services.*.ancillary_cost_per_trip' => ['nullable', 'required_if:services.*.quota_method,'.PurchaseOrderService::METHOD_TRIPS_AND_ANCILLARY, ...$money],
            'services.*.alert_percent' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
