<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A driver can hold any number of these (or none) — FRD: "يمكن ان يكون
 * لدي السائق أي عدد من تصاريح المرور او لا يملك منها أي تصاريح منها على
 * الاطلاق".
 */
class DriverEntryPermit extends Model
{
    use BelongsToTenant;

    protected $fillable = ['driver_id', 'area_name', 'permit_number', 'valid_to', 'attachment'];

    protected function casts(): array
    {
        return ['valid_to' => 'date'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
