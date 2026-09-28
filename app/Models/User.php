<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
  use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable;

  protected $guard_name = 'web';

  /**
   * The attributes that are mass assignable.
   *
   * tenant_id is deliberately excluded — it is only ever set via
   * BelongsToTenant's auto-stamp or explicit server-side code, never
   * via mass assignment from request input.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'email',
    'phone',
    'photo',
    'password',
    'status',
    'additional_data',
    'affiliation',
    'contractor_id',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
    ];
  }

  /**
   * `code` is the FRD's "الرقم التعريفي" — a random, non-guessable
   * identifier distinct from the raw db `id`, unique per tenant.
   * Deliberately not in $fillable: it's never settable via mass
   * assignment, only here.
   */
  protected static function booted(): void
  {
    static::creating(function (User $user) {
      if (is_null($user->code)) {
        do {
            $candidate = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('code', $candidate)->exists());

        $user->code = $candidate;
      }
    });
  }

  public function driver(): HasOne
  {
    return $this->hasOne(Driver::class);
  }

  /** FRD: view/edit pages show "Last updated by X — <datetime>". */
  public function updatedBy(): BelongsTo
  {
    return $this->belongsTo(User::class, 'updated_by');
  }

  /**
   * Every role changes its OWN password from the profile page (the
   * "Security" tab; current password required).
   *
   * The FRD is narrower: V01.09 lets only client/contractor users do it
   * ("غير قابلة للتعديل من قبل المستخدم ولكن من قبل مدير النظام او مدخل
   * البيانات" for GCM staff), and V01.14 adds the auditor and the driver
   * ("لا يملك المراقب تعديل أي بيانات من خلال صفحة الملف الشخصي الا صورته
   * او كلمة المرور" / the driver's edit-account section). The client
   * asked for system_admin and data_entry to be able to change their own
   * password as well, so all GCM roles can — an admin can still set
   * someone else's password from that user's edit page.
   *
   * Name and email stay read-only on the profile page for every role.
   */
  public function canChangeOwnPassword(): bool
  {
    return $this->hasAnyRole(['system_admin', 'data_entry', 'auditor', 'driver', 'client_project_manager', 'client_project_auditor', 'contractor_user']);
  }
}