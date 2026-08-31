<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global reference table — the FRD's five fixed vehicle categories,
 * identical across every tenant and never user-editable. Deliberately
 * has no BelongsToTenant trait (same as Spatie's Role model).
 */
class VehicleCategory extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name_en', 'name_ar'];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Localised label for the current app locale.
     */
    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
