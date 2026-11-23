<?php

namespace App\Domain\Trips\Exports;

use App\Domain\Trips\Support\TripFieldVisibility;
use App\Models\Trip;
use App\Models\User;
use App\Support\ExportHeader;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The FRD's trips list columns (§1.14, "إدارة الرحلات"): trip No., date,
 * driver + affiliation, vehicle + affiliation, client, project, sub-service,
 * status, linked to a PO. The PO column follows ج18 (TripFieldVisibility):
 * whoever may not see the number gets "linked" / "not linked".
 *
 * A client account gets the columns of ITS list (FRD "مراجعة العميل للرحلات",
 * part 7.5): trip No., date, driver, vehicle, project, default client
 * approver, sub-service, status, PO — no affiliations, no client company.
 */
class TripsExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    public function __construct(private readonly Collection $trips, private readonly ?User $viewer = null) {}

    public function collection(): Collection
    {
        return $this->trips;
    }

    public function headings(): array
    {
        return self::headers($this->viewer ?? auth()->user());
    }

    /** @return list<string> the untranslated headings for the viewer (the PDF translates them) */
    public static function headers(User $viewer): array
    {
        return $viewer->isClient()
            ? ['Trip No.', 'Date', 'Driver', 'Vehicle', 'Project', 'Default client approver', 'Sub-service', 'Status', 'PO']
            : ['Trip No.', 'Date', 'Driver', 'Driver affiliation', 'Vehicle', 'Vehicle affiliation', 'Client', 'Project', 'Sub-service', 'Status', 'PO'];
    }

    public function map($trip): array
    {
        return self::row($trip, $this->viewer ?? auth()->user(), fn (?string $v) => $v);
    }

    /**
     * One trip as the export prints it — shared by the Excel and the PDF (which passes PdfLabels::of
     * for the raw values). `$label` turns an affiliation into its display text.
     *
     * @return list<?string>
     */
    public static function row(Trip $trip, User $viewer, \Closure $label): array
    {
        $affiliation = fn (?string $value, $contractor) => $value === 'contractor' && $contractor ? $contractor->name : $label($value);
        $po = $trip->purchaseOrderService?->purchaseOrder;
        $poCell = match (true) {
            $trip->purchase_order_service_id === null => __('Not linked to a PO'),
            TripFieldVisibility::seesPurchaseOrderNumber($viewer) => $po?->number,
            default => __('Linked to a PO'),
        };

        if ($viewer->isClient()) {
            return [
                $trip->code,
                $trip->trip_at?->format('Y-m-d H:i'),
                $trip->driver?->user?->name,
                $trip->vehicle?->plate(),
                $trip->project?->name,
                $trip->clientApprover?->name,
                $trip->subService?->name,
                Trip::statusLabel($trip->status),
                $poCell,
            ];
        }

        return [
            $trip->code,
            $trip->trip_at?->format('Y-m-d H:i'),
            $trip->driver?->user?->name,
            $affiliation($trip->driver?->contractor_id === null ? 'gcm' : 'contractor', $trip->driver?->contractor),
            $trip->vehicle?->plate(),
            $affiliation($trip->vehicle?->affiliation, $trip->vehicle?->contractor),
            $trip->company?->name,
            $trip->project?->name,
            $trip->subService?->name,
            Trip::statusLabel($trip->status),
            $poCell,
        ];
    }
}
