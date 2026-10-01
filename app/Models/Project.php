<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
 *  - representative_id  — the optional representative account (FRD §1.12),
 *                         set by the create/update actions after validation
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

    /** FRD §1.12 "حساب ممثل المشروع" — optional; a client account that has this project. */
  public function representative(): BelongsTo
  {
    return $this->belongsTo(User::class, 'representative_id');
  }

  /** Client accounts assigned to this project by name ("مشروعات محددة") — see scopeWithUsersCount() for the full audience. */
  public function assignedUsers(): BelongsToMany
  {
    return $this->belongsToMany(User::class);
  }

  /**
   * FRD §1.4 — the projects a client account may see: only its own
   * company's, and within that every project when it holds "all projects"
   * (current and future ones), otherwise just the ones assigned to it. An
   * account with no company (GCM staff, drivers) matches nothing — this is
   * the client-side visibility rule, and the trips / contracts / reports
   * modules built on top of projects must filter through it.
   */
  public function scopeVisibleTo(Builder $query, User $user): Builder
  {
    if ($user->company_id === null) {
      return $query->whereRaw('0 = 1');
    }

    return $query
      ->where('projects.company_id', $user->company_id)
      ->when(
        ! $user->all_projects,
        fn (Builder $q) => $q->whereIn('projects.id', DB::table('project_user')->where('user_id', $user->id)->select('project_id'))
      );
  }

  /**
   * "عدد المستخدمين التابعون له": the client accounts of the project's
   * company that hold all projects, plus those assigned to it explicitly.
   */
  public function scopeWithUsersCount(Builder $query): Builder
  {
    return $query->addSelect(['users_count' => User::query()
      ->selectRaw('count(*)')
      ->whereColumn('users.company_id', 'projects.company_id')
      ->where(fn ($u) => $u->where('users.all_projects', true)->orWhereExists(
        fn ($e) => $e->selectRaw('1')->from('project_user')
          ->whereColumn('project_user.user_id', 'users.id')
          ->whereColumn('project_user.project_id', 'projects.id')
      )),
    ]);
  }

  /** Whether a client account has access to this project (same rule as scopeVisibleTo()). */
  public function isVisibleTo(User $user): bool
  {
    return static::query()->whereKey($this->id)->visibleTo($user)->exists();
  }

  public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
