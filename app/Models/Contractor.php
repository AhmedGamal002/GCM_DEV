<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.10 — a contractor ("مقاول الباطن"): a company bound to GCM by
 * a contract that supplies drivers, vehicles and containers / tanks for its
 * trips. The operating company is the Tenant and client companies are
 * `Company` (§1.11) — a separate entity.
 *
 * $fillable excludes:
 *  - tenant_id          — stamped by BelongsToTenant
 *  - sequence, code     — generated in booted() (code = prefix + C + number)
 *  - operational_status — only ever set through UpdateContractorStatusAction
 *  - updated_by         — set explicitly by the create/update actions
 *  - the *_path columns — set by the actions after storing the uploads
 */
class Contractor extends Model
{
    use BelongsToTenant, HasFactory;

    /** The three private attachments, keyed by the URL segment used to download them. */
    public const DOCUMENTS = [
        'contract' => 'contract_attachment_path',
        'cr' => 'cr_attachment_path',
        'tax' => 'tax_attachment_path',
    ];

    protected $fillable = [
        'name',
        'prefix',
        'trading_name',
        'phone',
        'address',
        'location_url',
        'contract_number',
        'contract_start_date',
        'contract_end_date',
        'cr_number',
        'tax_number',
        'additional_data',
    ];

    protected function casts(): array
    {
        return [
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
        ];
    }

    /**
     * `code` is the contractor's ID: the short name (prefix), a "C" and a
     * running number per tenant — ALN-C0001. The "C" keeps it distinct from a
     * client company's ID (ALN-0001) when both use the same three letters.
     * The prefix is locked after creation precisely because the code is built
     * from it, so the stored code can never drift from it.
     */
    protected static function booted(): void
    {
        static::creating(function (Contractor $contractor) {
            if (is_null($contractor->sequence)) {
                $contractor->sequence = ((int) static::max('sequence')) + 1;
            }

            if (is_null($contractor->code)) {
                $contractor->code = sprintf('%s-C%04d', $contractor->prefix, $contractor->sequence);
            }
        });

        // Belt and braces: the request layer never lets the prefix through
        // on edit, and this keeps any other code path from changing it.
        static::updating(function (Contractor $contractor) {
            if ($contractor->isDirty('prefix')) {
                $contractor->prefix = $contractor->getOriginal('prefix');
            }
        });
    }

    /** The main services the contractor supports (FRD "Certified services"); empty = all of them. */
    public function certifiedServices(): BelongsToMany
    {
        return $this->belongsToMany(MainService::class, 'contractor_main_service');
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->operational_status === 'active';
    }
}
