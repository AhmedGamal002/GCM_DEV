<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Support\ExpiryWarning;
use App\Models\TenantSetting;

class UpdateDocumentExpirySettingsAction
{
    public function __construct(private readonly ExpiryWarning $warning) {}

    /**
     * @param  array{driver_expiry_warning_days: int, vehicle_expiry_warning_days: int}  $data
     */
    public function execute(array $data): void
    {
        foreach ([ExpiryWarning::SCOPE_DRIVER, ExpiryWarning::SCOPE_VEHICLE] as $scope) {
            $key = ExpiryWarning::key($scope);

            TenantSetting::updateOrCreate(['key' => $key], ['value' => (string) (int) $data[$key]]);
        }

        $this->warning->refresh();
    }
}
