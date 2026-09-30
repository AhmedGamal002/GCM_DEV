<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FRD V01.14 §1.12 — a client project: a work site of one client company,
 * the place trips leave from to collect waste.
 *
 * $fillable excludes:
 *  - tenant_id          — stamped by BelongsToTenant
 *  - company_id         — set once by CreateProjectAction; the project's
 *                         code is built from the company, so it is locked
 *  - sequence, code     — generated in booted()
 *  - operational_status — only ever set through UpdateProjectStatusAction
 *  - updated_by         — set explicitly by the create/update actions
 */
class Project extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'operational_region',
        'phone',
        'email',
        'address',
        'location_url',
        'additional_data',
    ];

    /**
     * `code` is the FRD's "الرقم التعريفي": the owning company's short name
     * (prefix) + "P" + a number that runs per company — ALN-P0001,
     * ALN-P0002, … — so project numbers are unique per company and read as
     * that company's ("أرقام المشروعات من نفس الـprefix"). The company can't
     * change after creation, so the stored code never drifts from it.
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (is_null($project->sequence)) {
                $project->sequence = ((int) static::where('company_id', $project->company_id)->max('sequence')) + 1;
            }

            if (is_null($project->code)) {
                $prefix = Company::whereKey($project->company_id)->value('prefix');
                $project->code = sprintf('%s-P%04d', $prefix, $project->sequence);
            }
        });

        // Belt and braces: nothing may move a project to another company.
        static::updating(function (Project $project) {
            if ($project->isDirty('company_id')) {
                $project->company_id = $project->getOriginal('company_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
