<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One setting of the current tenant (key/value). Read through App\Domain\Settings\Support\ExpiryWarning
 * rather than directly, so the defaults live in one place.
 */
class TenantSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['key', 'value'];
}
