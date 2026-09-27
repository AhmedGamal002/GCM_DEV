<?php

namespace App\Domain\Vehicles\Actions;

use App\Models\VehicleCategory;
use Illuminate\Support\Str;

class CreateVehicleCategoryAction
{
    /**
     * @param  array{name_en: string, name_ar: string}  $data
     */
    public function execute(array $data): VehicleCategory
    {
        return VehicleCategory::create([
            'slug' => $this->uniqueSlug($data['name_en']),
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'],
        ]);
    }

    /**
     * Stable machine identifier derived from the English name once, at
     * creation — a later rename never changes it. Unique per tenant
     * (BelongsToTenant scopes the lookup), with a numeric suffix on clash.
     */
    private function uniqueSlug(string $nameEn): string
    {
        $base = Str::slug($nameEn, '_') ?: 'category';
        $slug = $base;

        for ($i = 2; VehicleCategory::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}_{$i}";
        }

        return $slug;
    }
}
