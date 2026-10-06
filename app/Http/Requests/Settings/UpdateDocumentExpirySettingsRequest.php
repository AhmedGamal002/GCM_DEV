<?php

namespace App\Http\Requests\Settings;

use App\Domain\Settings\Support\ExpiryWarning;
use App\Models\TenantSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentExpirySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', TenantSetting::class) ?? false;
    }

    public function rules(): array
    {
        $days = ['required', 'integer', 'min:'.ExpiryWarning::MIN_DAYS, 'max:'.ExpiryWarning::MAX_DAYS];

        return [
            'driver_expiry_warning_days' => $days,
            'vehicle_expiry_warning_days' => $days,
        ];
    }
}
