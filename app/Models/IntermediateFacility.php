<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FRD V01.14 §1.8 — an intermediate waste facility (safe disposal, sewage
 * treatment or recycling) that sub-services and trips deliver waste to.
 *
 * $fillable excludes:
 *  - tenant_id          — stamped by BelongsToTenant
 *  - sequence, code     — generated in booted() (code = prefix + number),
 *                         same "الرقم التعريفي" pattern as Company
 *  - operational_status — only ever set through UpdateFacilityStatusAction
 *  - updated_by         — set explicitly by the actions
 *
 * `prefix` is fillable for creation only; UpdateFacilityRequest never
 * accepts it, so it is effectively locked after creation.
 */
class IntermediateFacility extends Model
{
    use BelongsToTenant, HasFactory;

    public const SERVICES = ['disposal', 'sewage_treatment', 'recycle'];

    protected $fillable = [
        'name',
        'prefix',
        'logo',
        'environmental_service',
        'recycling_efficiency',
        'address',
        'location_url',
        'contract_number',
        'contract_start',
        'contract_end',
        'contract_attachment_path',
        'additional_data',
    ];

    protected function casts(): array
    {
        return [
            'recycling_efficiency' => 'decimal:2',
            'contract_start' => 'date',
            'contract_end' => 'date',
        ];
    }

    /**
     * `code` is the FRD's "الرقم التعريفي": the prefix mixed with a
     * number — ALF-0001 — where the number is a running counter per
     * tenant. The prefix is locked after creation precisely because the
     * code is built from it, so the stored code can never drift from it.
     * Same pattern as Company::booted().
     */
    protected static function booted(): void
    {
        static::creating(function (IntermediateFacility $facility) {
            if (is_null($facility->sequence)) {
                $facility->sequence = ((int) static::max('sequence')) + 1;
            }

            if (is_null($facility->code)) {
                $facility->code = sprintf('%s-%04d', $facility->prefix, $facility->sequence);
            }
        });

        // Belt and braces: the request layer never lets the prefix through
        // on edit, and this keeps any other code path from changing it.
        static::updating(function (IntermediateFacility $facility) {
            if ($facility->isDirty('prefix')) {
                $facility->prefix = $facility->getOriginal('prefix');
            }
        });
    }

    /**
     * Whether the facility is already part of something — a sub-service's
     * facility list or a trip. Once it is, its environmental service and
     * recycling efficiency are locked (they would silently change what past
     * and planned work means).
     *
     * Nothing can reference a facility yet: sub-services (FRD §1.9) and
     * trips don't exist. Wire the `sub_service_facility` pivot in here when
     * the Services module lands (and trips after that).
     */
    public function isInUse(): bool
    {
        return false;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
