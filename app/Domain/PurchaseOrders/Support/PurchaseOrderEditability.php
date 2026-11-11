<?php

namespace App\Domain\PurchaseOrders\Support;

use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use App\Support\RichText;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * What can still change on a PO — decision ب2 (product owner, 2026-11-09).
 *
 *  - No trip on it yet: everything can be edited — number, company, project,
 *    dates, region, attachment, notes, and the lines (add, remove, change
 *    their service, method and quota). The PO actions still keep it
 *    consistent: the number carries the company's prefix, the project
 *    belongs to the company, and the overlap rule is checked again.
 *  - A trip on it: ONLY THE QUOTA — max trips, trip cost, ancillary cost
 *    per trip and alert percentage of each existing line. The dates lock
 *    too (the product owner's correction the same day), and so does
 *    everything else; no line can be added or removed. Max trips can never
 *    go below the trips already consumed. A new trip cost applies to the
 *    trips recorded after the change only (ب3) — each trip keeps its own.
 *
 * Readings (not stated, change them here if corrected): "a trip" = a trip
 * that is not cancelled — read from the ledger as a consume no release has
 * reversed, since a cancelled trip is "as if it never was"; the quota method
 * and adding / removing lines lock with the first trip; deactivating and
 * re-activating are a separate action outside this lock. And one of ours: a
 * line that has had trips (since cancelled) keeps its service and can't be
 * removed even before any live trip, because its ledger history points at it.
 *
 * Part 6.2 builds the update request on this: editableFields() / toArray()
 * say what the form may send, assertAllows() refuses the rest.
 */
final class PurchaseOrderEditability
{
    /** The PO's own fields, editable only while no trip is recorded on it. */
    public const ORDER_FIELDS = ['paper_number', 'company_id', 'project_id', 'starts_on', 'ends_on', 'region', 'additional_data', 'attachment'];

    /** A line's quota — editable always. */
    public const QUOTA_FIELDS = ['max_trips', 'trip_cost', 'ancillary_cost_per_trip', 'alert_percent'];

    /** What a line is — editable only while the PO has no trip and the line has no ledger history. */
    public const LINE_IDENTITY_FIELDS = ['main_service_id', 'quota_method'];

    /**
     * @param  array<int, int>  $tripsUsed  line id → trips currently consumed
     * @param  list<int>  $linesWithHistory  ids of the lines that have any ledger row
     */
    private function __construct(
        private readonly PurchaseOrder $po,
        private readonly bool $hasTrips,
        private readonly array $tripsUsed,
        private readonly array $linesWithHistory,
    ) {}

    /**
     * `$locking` is for the update action, which holds the PO's line rows
     * locked: the ledger is then read with locking reads (the latest
     * committed rows — a trip charged a moment ago counts).
     */
    public static function for(PurchaseOrder $po, bool $locking = false): self
    {
        $po->loadMissing('services');
        $lineIds = $po->services->modelKeys();

        // Every consume is +1 trip and its release −1, so a line holds a live
        // trip exactly when its trips sum is above zero.
        $tripsUsed = array_map(fn ($b) => $b->tripsUsed, QuotaBalances::forMany($po->services, null, $locking));
        $hasTrips = array_filter($tripsUsed, fn (int $used) => $used > 0) !== [];

        $withHistory = $lineIds === [] ? [] : QuotaLedgerEntry::query()
            ->whereIn('purchase_order_service_id', $lineIds)
            ->when($locking, fn ($q) => $q->sharedLock())
            ->distinct()
            ->pluck('purchase_order_service_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return new self($po, $hasTrips, $tripsUsed, $withHistory);
    }

    /** Whether a (non-cancelled) trip is recorded on the PO — the lock of ب2. */
    public function hasTrips(): bool
    {
        return $this->hasTrips;
    }

    /** @return list<string> the PO's own fields that can change now */
    public function editableFields(): array
    {
        return $this->hasTrips ? [] : self::ORDER_FIELDS;
    }

    public function canEdit(string $field): bool
    {
        if (in_array($field, self::QUOTA_FIELDS, true)) {
            return true;
        }

        return in_array($field, $this->editableFields(), true);
    }

    /** Adding a line, removing one, or changing a line's service or method. */
    public function canChangeServices(): bool
    {
        return ! $this->hasTrips;
    }

    /** Whether this line's service and method can change, and whether it can be removed. */
    public function canChangeLine(PurchaseOrderService $line): bool
    {
        return ! $this->hasTrips && ! in_array($line->id, $this->linesWithHistory, true);
    }

    /** The lowest max trips the line may be given: what it has already consumed (and at least 1). */
    public function minMaxTrips(PurchaseOrderService $line): int
    {
        return max(1, $this->tripsUsed[$line->id] ?? 0);
    }

    /**
     * For the edit form (part 6.2 / 6.3).
     *
     * @return array{has_trips: bool, editable_fields: list<string>, can_change_services: bool, services: array<int, array{can_change: bool, min_max_trips: int, editable_fields: list<string>}>}
     */
    public function toArray(): array
    {
        $services = [];

        foreach ($this->po->services as $line) {
            $canChange = $this->canChangeLine($line);
            $services[$line->id] = [
                'can_change' => $canChange,
                'min_max_trips' => $this->minMaxTrips($line),
                'editable_fields' => $canChange ? [...self::LINE_IDENTITY_FIELDS, ...self::QUOTA_FIELDS] : self::QUOTA_FIELDS,
            ];
        }

        return [
            'has_trips' => $this->hasTrips,
            'editable_fields' => $this->editableFields(),
            'can_change_services' => $this->canChangeServices(),
            'services' => $services,
        ];
    }

    /**
     * Refuses (422) any change the lock doesn't allow. A field sent with its
     * current value is not a change — the edit form sends everything.
     *
     * `$data['services']`, when present, is the full list of lines after the
     * edit: an item with an `id` edits that line, one without adds a line,
     * and a current line missing from the list is removed.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function assertAllows(array $data, bool $replacesAttachment = false): void
    {
        $errors = [];
        $lockedMessage = __('This can no longer be changed: a trip is recorded on this purchase order, so only the quota can be edited.');

        if ($this->hasTrips) {
            foreach (self::ORDER_FIELDS as $field) {
                if ($field === 'attachment') {
                    if ($replacesAttachment) {
                        $errors['attachment'] = $lockedMessage;
                    }

                    continue;
                }

                if (array_key_exists($field, $data) && $this->changes($field, $data[$field])) {
                    $errors[$field] = $lockedMessage;
                }
            }
        }

        if (array_key_exists('services', $data)) {
            $errors += $this->serviceErrors((array) $data['services'], $lockedMessage);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, string> */
    private function serviceErrors(array $items, string $lockedMessage): array
    {
        $errors = [];
        $current = $this->po->services->keyBy('id');
        $kept = [];

        foreach ($items as $key => $item) {
            $id = isset($item['id']) ? (int) $item['id'] : null;

            if ($id === null) {
                if (! $this->canChangeServices()) {
                    $errors["services.{$key}"] = $lockedMessage;
                }

                continue;
            }

            /** @var PurchaseOrderService|null $line */
            $line = $current->get($id);

            if ($line === null) {
                $errors["services.{$key}.id"] = __('This line does not belong to this purchase order.');

                continue;
            }

            $kept[] = $id;

            if (! $this->canChangeLine($line)) {
                foreach (self::LINE_IDENTITY_FIELDS as $field) {
                    if (array_key_exists($field, $item) && (string) $item[$field] !== (string) $line->{$field}) {
                        $errors["services.{$key}.{$field}"] = $this->hasTrips
                            ? $lockedMessage
                            : __('Trips have been recorded on this line before, so its service and method stay as they are.');
                    }
                }
            }

            if (array_key_exists('max_trips', $item) && (int) $item['max_trips'] < $this->minMaxTrips($line)) {
                $errors["services.{$key}.max_trips"] = __('The maximum number of trips cannot be lower than the :count trips already recorded on this line.', ['count' => $this->minMaxTrips($line)]);
            }
        }

        foreach ($current as $id => $line) {
            if (! in_array($id, $kept, true) && ! $this->canChangeLine($line)) {
                $errors['services'] = $this->hasTrips
                    ? $lockedMessage
                    : __('Trips have been recorded on a line of this purchase order before, so that line cannot be removed.');
            }
        }

        return $errors;
    }

    private function changes(string $field, mixed $value): bool
    {
        return match ($field) {
            'paper_number' => (string) $value !== $this->po->paperNumber(),
            'company_id', 'project_id' => (int) $value !== (int) $this->po->{$field},
            'starts_on', 'ends_on' => Carbon::parse($value)->toDateString() !== $this->po->{$field}->toDateString(),
            'additional_data' => RichText::sanitize($value === null ? null : (string) $value) !== $this->po->additional_data,
            default => (($value === '' ? null : $value)) !== $this->po->{$field},
        };
    }
}
