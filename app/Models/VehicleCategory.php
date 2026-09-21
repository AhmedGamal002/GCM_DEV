<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-scoped vehicle categories. The FRD (§1.5.2) fixes five; every
 * tenant starts with exactly those (see seedDefaultsFor(), run when a
 * Tenant is created), and its system_admin can then add, rename and —
 * while unused — delete categories (client request, outside the FRD).
 *
 * `slug` is a stable machine identifier: generated once on creation, never
 * changed by a rename (API filters and the seeded defaults key on it).
 */
class VehicleCategory extends Model
{
    use BelongsToTenant, HasFactory;

    /** slug => [name_en, name_ar] — FRD §1.5.2. */
    public const DEFAULTS = [
        'hook_lift' => ['Hook lift system', 'مركبات بنظام خطاف رفع'],
        'compactor' => ['Compactor unit', 'مركبات بوحدة ضغط'],
        'dump_truck' => ['Dump truck', 'مركبات قلابة'],
        'water_tanker' => ['Water tanker', 'مركبات صهريج مياه'],
        'dyna_box' => ['Dyna box', 'مركبات بصندوق مغلق'],
    ];

    protected $fillable = ['slug', 'name_en', 'name_ar'];

    /**
     * Idempotent — gives a tenant any of the five defaults it doesn't have
     * yet. Explicit tenant_id + forceCreate because there is no bound
     * tenant while a Tenant is being created, and tenant_id is (by design)
     * never mass-assignable.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (self::DEFAULTS as $slug => [$en, $ar]) {
            $exists = static::withoutGlobalScope(BelongsToTenant::class)
                ->where('tenant_id', $tenant->id)
                ->where('slug', $slug)
                ->exists();

            if (! $exists) {
                static::forceCreate(['tenant_id' => $tenant->id, 'slug' => $slug, 'name_en' => $en, 'name_ar' => $ar]);
            }
        }
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** Drivers qualified to drive this category. */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'driver_vehicle_categories');
    }

    /** Assets marked compatible with this category. */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_vehicle_categories');
    }

    /**
     * Localised label for the current app locale.
     */
    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
