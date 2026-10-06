<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Gate;

/**
 * Blade shell for the General Settings page (system admin only). The data goes through
 * /api/v1/settings/document-expiry; the Gate call here is the UX guard, the API's policy is the enforcement.
 */
class SettingsController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', TenantSetting::class);

        return view('tenant.settings.index');
    }
}
