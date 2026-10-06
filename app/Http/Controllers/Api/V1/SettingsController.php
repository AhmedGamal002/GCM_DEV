<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\Actions\UpdateDocumentExpirySettingsAction;
use App\Domain\Settings\Support\ExpiryWarning;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDocumentExpirySettingsRequest;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Gate;

/**
 * General settings — system admin only (TenantSettingPolicy). For now just the document-expiry
 * thresholds: how many days before a driver / vehicle document's end date its bar turns red.
 */
class SettingsController extends Controller
{
    public function showDocumentExpiry(ExpiryWarning $warning)
    {
        Gate::authorize('viewAny', TenantSetting::class);

        return response()->json(['data' => $this->payload($warning)]);
    }

    public function updateDocumentExpiry(
        UpdateDocumentExpirySettingsRequest $request,
        UpdateDocumentExpirySettingsAction $action,
        ExpiryWarning $warning,
    ) {
        $action->execute($request->validated());

        return response()->json(['data' => $this->payload($warning)]);
    }

    private function payload(ExpiryWarning $warning): array
    {
        return [
            'driver_expiry_warning_days' => $warning->days(ExpiryWarning::SCOPE_DRIVER),
            'vehicle_expiry_warning_days' => $warning->days(ExpiryWarning::SCOPE_VEHICLE),
        ];
    }
}
