<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * The four single-instance types the FRD form defines, plus the
     * repeatable entry permit.
     */
    public const SINGLE_TYPES = [
        'registration_card',
        'fitness_document',
        'inspection_certificate',
        'insurance',
    ];

    protected $fillable = [
        'vehicle_id',
        'type',
        'document_number',
        'area_name',
        'valid_to',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'valid_to' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
