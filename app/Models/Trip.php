<?php

namespace App\Models;

use App\Casts\SanitizedRichText;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.14 — a trip: "the heart of the system". One driver and one
 * vehicle (plus an optional going and returning container) serve ONE main
 * service and at most one sub-service at one project of a client company,
 * charged to a PO line (or "without PO") and carried through five statuses:
 *
 *   awaiting_client_approval → client_approved | client_issue → completed
 *   cancelled — from any status
 *
 * Every move between them is its own Action in App\Domain\Trips\Actions,
 * each checking who acts and from which status, and writing its row of the
 * trip's history (trip_events, decision ج8) in the same transaction.
 *
 * $fillable is the trip's plain data only. Everything else is set by the
 * actions after they checked it:
 *  - tenant_id, sequence, code     — stamped here / by BelongsToTenant
 *  - company_id, project_id        — must agree with each other and with the PO
 *  - status, source, requires_*    — the transitions
 *  - purchase_order_service_id, trip_cost, ancillary_cost — the quota (TripQuota)
 *  - the *_path columns            — files, stored by TripFiles on the private disk
 *  - client_* / gcm_* / completed_* / cancelled_* / created_by / updated_by
 */
class Trip extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_AWAITING_CLIENT_APPROVAL = 'awaiting_client_approval';

    public const STATUS_CLIENT_APPROVED = 'client_approved';

    public const STATUS_CLIENT_ISSUE = 'client_issue';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_AWAITING_CLIENT_APPROVAL,
        self::STATUS_CLIENT_APPROVED,
        self::STATUS_CLIENT_ISSUE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const SOURCE_DRIVER = 'driver';

    public const SOURCE_STAFF = 'staff';

    /** The FRD's status names (§1.14, "دورة حياة الرحلة"), translation keys. */
    public const STATUS_LABELS = [
        self::STATUS_AWAITING_CLIENT_APPROVAL => 'Awaiting client approval',
        self::STATUS_CLIENT_APPROVED => 'Client approved',
        self::STATUS_CLIENT_ISSUE => 'Client issue',
        self::STATUS_COMPLETED => 'Completed trip',
        self::STATUS_CANCELLED => 'Cancelled trip',
    ];

    /** GCM staff who see every trip of the tenant. */
    public const STAFF_VIEW_ROLES = ['system_admin', 'data_entry', 'auditor'];

    public static function statusLabel(?string $status): string
    {
        return isset(self::STATUS_LABELS[$status]) ? __(self::STATUS_LABELS[$status]) : (string) $status;
    }

    /**
     * The one visibility rule for trips — every list, count, export, details
     * page and file download goes through it (security checklist §1.2):
     *
     *  - GCM staff (System Admin, Data Entry, Auditor): every trip;
     *  - a client account: the trips of the projects it sees (Project::scopeVisibleTo);
     *  - a driver: their own trips;
     *  - a contractor user: a trip whose driver OR vehicle belongs to their
     *    contractor — not the containers (decision ج19);
     *  - anyone else: none.
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->hasAnyRole(self::STAFF_VIEW_ROLES)) {
            return $query;
        }

        if ($user->isClient()) {
            return $query->whereIn('trips.project_id', Project::query()->visibleTo($user)->select('projects.id'));
        }

        if ($user->hasRole('driver')) {
            $driverId = $user->driver?->id;

            return $driverId === null ? $query->whereRaw('1 = 0') : $query->where('trips.driver_id', $driverId);
        }

        if ($user->isContractorUser() && $user->contractor_id !== null) {
            $contractor = $user->contractor_id;

            return $query->where(fn ($q) => $q
                ->whereIn('trips.driver_id', Driver::query()->where('drivers.contractor_id', $contractor)->select('drivers.id'))
                ->orWhereIn('trips.vehicle_id', Vehicle::query()->where('vehicles.contractor_id', $contractor)->select('vehicles.id')));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    protected $fillable = [
        'trip_at',
        'driver_id',
        'vehicle_id',
        'going_asset_id',
        'returning_asset_id',
        'main_service_id',
        'sub_service_id',
        'quantity_ton',
        'quantity_cbm',
        'intermediate_facility_id',
        'delivery_note_number',
        'waste_manifest_number',
        'recycle_receipt_number',
        'location_url',
        'additional_data',
        'client_approver_id',
        'gcm_approver_id',
    ];

    protected function casts(): array
    {
        return [
            'trip_at' => 'datetime',
            'requires_client_approval' => 'boolean',
            'requires_purchase_order' => 'boolean',
            'trip_cost' => 'decimal:2',
            'ancillary_cost' => 'decimal:2',
            'quantity_ton' => 'decimal:3',
            'quantity_cbm' => 'decimal:3',
            'additional_data' => SanitizedRichText::class,
            'client_approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * `code` is the trip number of decision ج14: the client company's short
     * name + "T" + a number that runs per company from 1 — ALN-T0001, … The
     * company's row is locked first and the last number read with a locking
     * read, so two trips created for the same company at the same moment get
     * consecutive numbers (unique (company_id, sequence) is the backstop).
     * The number never changes, so neither does the company (a trip's project
     * can only move to another project of the same company).
     */
    protected static function booted(): void
    {
        static::creating(function (Trip $trip) {
            $prefix = Company::whereKey($trip->company_id)->lockForUpdate()->value('prefix');

            if (is_null($trip->sequence)) {
                $last = static::where('company_id', $trip->company_id)
                    ->lockForUpdate()
                    ->max('sequence');

                $trip->sequence = ((int) $last) + 1;
            }

            if (is_null($trip->code)) {
                $trip->code = sprintf('%s-T%04d', $prefix, $trip->sequence);
            }
        });

        static::updating(function (Trip $trip) {
            foreach (['code', 'sequence', 'company_id', 'source'] as $locked) {
                if ($trip->isDirty($locked)) {
                    $trip->{$locked} = $trip->getOriginal($locked);
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function goingAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'going_asset_id');
    }

    public function returningAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'returning_asset_id');
    }

    public function mainService(): BelongsTo
    {
        return $this->belongsTo(MainService::class);
    }

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    /** The PO line the trip is charged to — null = "without PO". */
    public function purchaseOrderService(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderService::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(IntermediateFacility::class, 'intermediate_facility_id');
    }

    public function clientApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_approver_id');
    }

    public function gcmApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gcm_approver_id');
    }

    public function clientApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_approved_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The trip's history, oldest first (decision ج8). */
    public function events(): HasMany
    {
        return $this->hasMany(TripEvent::class)->orderBy('id');
    }

    public function assetMovements(): HasMany
    {
        return $this->hasMany(TripAssetMovement::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(QuotaLedgerEntry::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** "Without PO": no PO line to charge (the costs were entered by hand). */
    public function isWithoutPurchaseOrder(): bool
    {
        return $this->purchase_order_service_id === null;
    }

    /** Whether the client's signature is on the trip (their approval's snapshot, or one uploaded by GCM staff — د4). */
    public function hasClientSignature(): bool
    {
        return $this->client_signature_path !== null;
    }
}
