<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Extends a `driver`-role User with the extra profile data the FRD
 * requires for drivers specifically (residence/licenses/insurance/entry
 * permits) — deliberately a separate table 1:1 with `users` rather than
 * more columns on `users`, since none of this applies to any other role.
 *
 * "Default Vehicle" (from the FRD's driver form) is deliberately not
 * modeled yet — it needs `vehicles`/`vehicle_categories`, which are the
 * Fleet half of Week 3, not built yet.
 */
class Driver extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'contractor_id',
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
}
