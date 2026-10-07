<?php

namespace App\Domain\Assets\Actions;

use App\Models\AssetCapacityCategory;
use App\Models\User;

/**
 * FRD §1.7.2 edit page: "الاسم فقط قابل للتعديل ... لا يمكن تعديل السعة
 * او النوعية لأثر ذلك المباشر علي الأصل و الخدمة الفرعية و الـ PO" — the
 * FRD lets only the name through; the client also asked for the additional
 * data (plain notes) to stay editable. `applies_to` and capacity (CBM/TON)
 * are locked after creation. No delete, no deactivate.
 */
class UpdateAssetCapacityCategoryAction
{
    /**
     * @param  array{name: string, additional_data?: ?string}  $data
     */
    public function execute(AssetCapacityCategory $category, array $data, User $actor): AssetCapacityCategory
    {
        $category->name = $data['name'];
        // Only touched when sent, so a name-only request never wipes the notes.
        if (array_key_exists('additional_data', $data)) {
            $category->additional_data = $data['additional_data'];
        }
        $category->updated_by = $actor->id;
        $category->save();

        return $category->load('updatedBy');
    }
}
