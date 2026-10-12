<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-scoped vehicle categories. The FRD (§1.5.2) fixes five and the
 * client later added a sixth (Tractor truck); every tenant starts with
 * exactly those (see DEFAULTS / seedDefaultsFor(), run when a Tenant is
 * created), and its system_admin can then add, rename and — while unused —
 * delete categories (client request, outside the FRD).
 *
 * `slug` is a stable machine identifier: generated once on creation, never
 * changed by a rename (API filters and the seeded defaults key on it).
 */
class VehicleCategory extends Model
{
    use BelongsToTenant, HasFactory;

    /** slug => [name_en, name_ar] — FRD §1.5.2 (first five) + Tractor truck (client request). */
    public const DEFAULTS = [
        'hook_lift' => ['Hook lift system', 'مركبات بنظام خطاف رفع'],
        'compactor' => ['Compactor unit', 'مركبات بوحدة ضغط'],
        'dump_truck' => ['Dump truck', 'مركبات قلابة'],
        'water_tanker' => ['Water tanker', 'مركبات صهريج مياه'],
        'dyna_box' => ['Dyna box', 'مركبات بصندوق مغلق'],
        'tractor_truck' => ['Tractor truck', 'شاحنة جرّارة (رأس مقطورة)'],
    ];

    protected $fillable = [
        'slug', 'name_en', 'name_ar',
        // Starting values for the create-vehicle form (client request) —
        // not constraints; the vehicle keeps its own copy.
        'default_has_embedded_container',
        'default_embedded_container_type',
        'default_embedded_asset_capacity_category_id',
    ];

    protected function casts(): array
    {
        return ['default_has_embedded_container' => 'boolean'];
    }

    /**
     * The default_* columns to write for validated request data. Absent
     * from the data → empty array (an API caller that doesn't mention
     * them leaves the stored defaults alone). "No embedded container"
     * clears the kind and the capacity so a stale pair can't linger.
     *
     * @return array<string, mixed>
     */
    public static function embeddedDefaultsFrom(array $data): array
    {
        if (! array_key_exists('default_has_embedded_container', $data)) {
            return [];
        }

        $has = (bool) $data['default_has_embedded_container'];

        return [
            'default_has_embedded_container' => $has,
            'default_embedded_container_type' => $has ? ($data['default_embedded_container_type'] ?? null) : null,
            'default_embedded_asset_capacity_category_id' => $has ? ($data['default_embedded_asset_capacity_category_id'] ?? null) : null,
        ];
    }

    public function defaultEmbeddedCapacityCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCapacityCategory::class, 'default_embedded_asset_capacity_category_id');
    }

    /**
     * Idempotent — gives a tenant any of the defaults it doesn't have yet.
     * Skips a default whose slug OR either name is already taken by a
     * category the tenant created itself (names are unique per tenant).
     * Explicit tenant_id + forceCreate because there is no bound tenant
     * while a Tenant is being created, and tenant_id is (by design) never
     * mass-assignable.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (self::DEFAULTS as $slug => [$en, $ar]) {
            $exists = static::withoutGlobalScope(BelongsToTenant::class)
                ->where('tenant_id', $tenant->id)
                ->where(fn ($q) => $q->where('slug', $slug)->orWhere('name_en', $en)->orWhere('name_ar', $ar))
                ->exists();

            if (! $exists) {
                static::forceCreate(['tenant_id' => $tenant->id, 'slug' => $slug, 'name_en' => $en, 'name_ar' => $ar]);
            }
        }
    }

    /**
     * One of the primary (built-in) categories — the FRD's five plus
     * Tractor truck. They can be renamed but never deleted, even when
     * unused. Keyed on the slug (stable, never changed by a rename); a
     * category an admin adds gets a slug outside DEFAULTS (or a numeric
     * suffix if it would clash), so it stays deletable while unused.
     */
    public function isDefault(): bool
    {
        return array_key_exists($this->slug, self::DEFAULTS);
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

    /** Main services this category is able to perform (FRD §1.9.1). */
    public function mainServices(): BelongsToMany
    {
        return $this->belongsToMany(MainService::class, 'main_service_vehicle_category');
    }

    /**
     * Localised label for the current app locale.
     */
    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
