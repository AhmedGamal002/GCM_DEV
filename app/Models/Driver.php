<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Extends a `driver`-role User with the extra profile data the FRD
 * requires for drivers specifically (residence/licenses/insurance/entry
 * permits) — deliberately a separate table 1:1 with `users` rather than
 * more columns on `users`, since none of this applies to any other role.
 *
 * "Default Vehicle" + qualified vehicle categories (FRD's "المركبة
 * الافتراضية" section) were deferred until `vehicles`/`vehicle_categories`
 * existed (the Fleet half of Week 3) — now built, see
 * defaultVehicle()/qualifiedVehicleCategories() below.
 */
class Driver extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'contractor_id',
        'default_vehicle_id',
        'residence_number',
        'residence_valid_to',
        'residence_attachment',
        'license_number',
        'license_valid_to',
        'license_attachment',
        'operational_license_number',
        'operational_license_valid_to',
        'operational_license_attachment',
        'insurance_number',
        'insurance_valid_to',
        'insurance_attachment',
    ];

    protected function casts(): array
    {
        return [
            'residence_valid_to' => 'date',
            'license_valid_to' => 'date',
            'operational_license_valid_to' => 'date',
            'insurance_valid_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entryPermits(): HasMany
    {
        return $this->hasMany(DriverEntryPermit::class);
    }

    public function defaultVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'default_vehicle_id');
    }

    /**
     * The vehicle categories (of the 5 fixed FRD categories) this driver
     * is qualified to drive — the Default Vehicle must belong to one of
     * these, enforced in StoreDriverRequest/UpdateDriverRequest, not at
     * the DB level.
     */
    public function qualifiedVehicleCategories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class, 'driver_vehicle_categories');
    }
}
