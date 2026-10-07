<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Domain\Settings\Support\ExpiryWarning;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * tenant_id is deliberately excluded — stamped by BelongsToTenant.
     * operational_status is excluded from create/update mass assignment
     * too: it only ever changes through UpdateVehicleStatusAction.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plate_letters',
        'plate_numbers',
        'vehicle_category_id',
        'has_embedded_container',
        'embedded_container_type',
        'embedded_asset_capacity_category_id',
        'affiliation',
        'contractor_id',
        'additional_data',
        'photo_front',
        'photo_back',
    ];

    protected function casts(): array
    {
        return [
            'has_embedded_container' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function embeddedCapacityCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCapacityCategory::class, 'embedded_asset_capacity_category_id');
    }

    /** The contractor that supplies this vehicle (null = GCM's own). */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /** FRD: view/edit pages show "Last updated by X — <datetime>". */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function entryPermits(): HasMany
    {
        return $this->hasMany(VehicleDocument::class)->where('type', 'entry_permit');
    }

    /**
     * The plate as one display string. Letters are always shown
     * upper-case (the FRD's "Plate registry (English)"), regardless of
     * how they were stored.
     */
    public function plate(): string
    {
        return trim(mb_strtoupper((string) $this->plate_letters).' '.$this->plate_numbers);
    }

    /**
     * The single "Valid / Need review / Expired" status of the list row, from every dated document and
     * entry permit (see ExpiryWarning::overall()). List queries eager-load `documents`.
     *
     * @return array{status: string, status_label: string}|null
     */
    public function documentsStatus(): ?array
    {
        return app(ExpiryWarning::class)->overall(
            $this->documents->pluck('valid_to'),
            ExpiryWarning::SCOPE_VEHICLE,
        );
    }
}
