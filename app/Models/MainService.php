<?php

namespace App\Models;

use App\Casts\SanitizedRichText;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.9.1 — a main service: a category (non-hazardous waste /
 * hazardous waste / supplying) that groups sub-services and names the
 * vehicle categories able to perform it. It is the building block of a PO.
 *
 * $fillable excludes:
 *  - tenant_id          — stamped by BelongsToTenant
 *  - service_type       — set once by CreateMainServiceAction; the
 *                         sub-services under it (and whether they need a
 *                         facility) are built on it, so it is locked
 *  - operational_status — only ever set through the status action
 *  - updated_by         — set explicitly by the actions
 */
class MainService extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPES = ['non_hazardous', 'hazardous', 'supplying'];

    protected $fillable = ['name', 'additional_data'];

    public function subServices(): HasMany
    {
        return $this->hasMany(SubService::class);
    }

    /** The vehicle categories able to perform this service (FRD "Supported Vehicles Categories"). */
    public function vehicleCategories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class, 'main_service_vehicle_category');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->operational_status === 'active';
    }

    /**
     * A supplying service delivers goods, not waste — its sub-services don't
     * have to name an intermediate facility (FRD §1.9.2 note).
     */
    public function isSupplying(): bool
    {
        return $this->service_type === 'supplying';
    }

    /**
     * Whether any PO includes this service. POs (FRD §1.13) don't exist yet,
     * so nothing can; wire the PO lines in here when the contracts module
     * lands.
     */
    public function hasPurchaseOrders(): bool
    {
        return false;
    }

    /**
     * FRD §1.9.1: editing or deactivating a main service is allowed only
     * while it has never been used — i.e. while no PO includes it.
     */
    public function isInUse(): bool
    {
        return $this->hasPurchaseOrders();
    }

    protected function casts(): array
    {
        return [
            'additional_data' => SanitizedRichText::class,
        ];
    }
}
