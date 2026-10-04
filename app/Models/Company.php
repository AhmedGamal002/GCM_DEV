<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.11 — a CLIENT company (the operating company is the
 * Tenant; contractors are a separate entity, §1.10).
 *
 * $fillable excludes:
 *  - tenant_id          — stamped by BelongsToTenant
 *  - sequence, code     — generated in booted() (code = prefix + number)
 *  - operational_status — only ever set through UpdateCompanyStatusAction
 *  - updated_by         — set explicitly by the create/update actions
 *  - the *_path columns — set by the actions after storing the uploads
 *  - representative_id  — the optional representative account (FRD §1.11),
 *                         set by the update action after validation
 */
class Company extends Model
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
        'business_sector',
        'phone',
        'email',
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
     * `code` is the FRD's "الرقم التعريفي": the short name (prefix) mixed
     * with a number — ALN-0001 — where the number is a running counter per
     * tenant (the tenant scope applies to the lookup). The prefix is locked
     * after creation precisely because the code is built from it, so the
     * stored code can never drift from it.
     */
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (is_null($company->sequence)) {
                $company->sequence = ((int) static::max('sequence')) + 1;
            }

            if (is_null($company->code)) {
                $company->code = sprintf('%s-%04d', $company->prefix, $company->sequence);
            }
        });

        // Belt and braces: the request layer never lets the prefix through
        // on edit, and this keeps any other code path from changing it.
        static::updating(function (Company $company) {
            if ($company->isDirty('prefix')) {
                $company->prefix = $company->getOriginal('prefix');
            }
        });
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Every client account of this company (FRD §1.4), whatever its status. */
  public function users(): HasMany
  {
    return $this->hasMany(User::class);
  }

  /** FRD §1.11 "حساب ممثل العميل" — optional; one of the company's project managers. */
  public function representative(): BelongsTo
  {
    return $this->belongsTo(User::class, 'representative_id');
  }

  public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
