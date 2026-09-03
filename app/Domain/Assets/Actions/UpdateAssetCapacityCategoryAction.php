<?php

namespace App\Domain\Assets\Actions;

use App\Models\AssetCapacityCategory;
use App\Models\User;

/**
 * FRD §1.7.2 edit page: "الاسم فقط قابل للتعديل ... لا يمكن تعديل السعة
 * او النوعية لأثر ذلك المباشر علي الأصل و الخدمة الفرعية و الـ PO" — the
 * name is the only editable field. `applies_to`, capacity (CBM/TON) and
 * notes are locked after creation. No delete, no deactivate.
 */
class UpdateAssetCapacityCategoryAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function execute(AssetCapacityCategory $category, array $data, User $actor): AssetCapacityCategory
    {
        $category->name = $data['name'];
        $category->updated_by = $actor->id;
        $category->save();

        return $category->load('updatedBy');
    }
}
