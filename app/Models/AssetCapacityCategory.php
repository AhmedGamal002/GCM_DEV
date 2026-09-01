<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal for now — see the migration docblock. Only what the vehicle
 * form's capacity dropdown needs; the full Assets-module CRUD extends it.
 */
class AssetCapacityCategory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'capacity_cbm', 'capacity_ton'];

    protected function casts(): array
    {
        return [
            'capacity_cbm' => 'decimal:2',
            'capacity_ton' => 'decimal:2',
        ];
    }
}
