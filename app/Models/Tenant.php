<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'domain', 'status'];

    protected static function booted(): void
    {
        // Every tenant starts with the FRD's five vehicle categories
        // (they're per-tenant data now, not a shared global list).
        static::created(fn (Tenant $tenant) => VehicleCategory::seedDefaultsFor($tenant));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
