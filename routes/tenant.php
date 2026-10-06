<?php

use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\Assets\AssetAccountController;
use App\Http\Controllers\Web\Assets\AssetAddController;
use App\Http\Controllers\Web\Assets\AssetCategoryController;
use App\Http\Controllers\Web\Assets\AssetListController;
use App\Http\Controllers\Web\Assets\AssetProjectController;
use App\Http\Controllers\Web\Companies\CompanyAccountController;
use App\Http\Controllers\Web\Companies\CompanyAddController;
use App\Http\Controllers\Web\Companies\CompanyListController;
use App\Http\Controllers\Web\Contractors\ContractorAccountController;
use App\Http\Controllers\Web\Contractors\ContractorAddController;
use App\Http\Controllers\Web\Contractors\ContractorListController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Drivers\DriverAccountController;
use App\Http\Controllers\Web\Facilities\FacilityAccountController;
use App\Http\Controllers\Web\Facilities\FacilityAddController;
use App\Http\Controllers\Web\Facilities\FacilityListController;
use App\Http\Controllers\Web\NewPasswordController;
use App\Http\Controllers\Web\PasswordResetLinkController;
use App\Http\Controllers\Web\Profile\AccountSettingsController;
use App\Http\Controllers\Web\Projects\ProjectAccountController;
use App\Http\Controllers\Web\Projects\ProjectAddController;
use App\Http\Controllers\Web\Projects\ProjectListController;
use App\Http\Controllers\Web\Services\MainServicePageController;
use App\Http\Controllers\Web\Services\SubServicePageController;
use App\Http\Controllers\Web\Users\ClientUserController;
use App\Http\Controllers\Web\Users\ContractorUserController;
use App\Http\Controllers\Web\Users\UserAccountController;
use App\Http\Controllers\Web\Users\UserAddController;
use App\Http\Controllers\Web\Users\UserListController;
use App\Http\Controllers\Web\Vehicles\VehicleAccountController;
use App\Http\Controllers\Web\Vehicles\VehicleAddController;
use App\Http\Controllers\Web\Vehicles\VehicleCategoryController;
use App\Http\Controllers\Web\Vehicles\VehicleListController;
use Illuminate\Support\Facades\Route;

/**
 * GCM Portal's own real tenant-side page routes — split out of web.php so
 * that file can stay the untouched Vuexy template scaffold (kept around
 * as a living UI-kit/pattern reference until each piece is harvested or
 * removed — see ARCHITECTURE.md §9.5). `require`d from routes/web.php, so
 * it inherits the same 'web' middleware group automatically.
 *
 * Every page here is a Blade shell only — the data is loaded by the JS
 * inside it from /api/v1/*. Route paths and names are UNCHANGED from
 * before the folder-structure split; only the controller classes and
 * Blade view locations moved.
 *
 * Login/logout/forgot-password/reset-password themselves are handled
 * entirely by Api\V1\Auth\* (see routes/api.php) — the guest routes below
 * only render the Blade shell; see AuthenticatedSessionController's
 * docblock.
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'tenant'])
    ->name('dashboard');

Route::middleware(['auth', 'tenant'])->group(function () {
    // Users
    Route::get('/app/user/list', [UserListController::class, 'index'])->name('app-user-list');
    Route::get('/app/user/add', [UserAddController::class, 'index'])->middleware('can:create,App\\Models\\User')->name('app-user-add');
    Route::get('/app/user/view/{user}', [UserAccountController::class, 'view'])->whereNumber('user')->name('app-user-view');
    Route::get('/app/user/edit/{user}', [UserAccountController::class, 'edit'])->whereNumber('user')->middleware('can:create,App\\Models\\User')->name('app-user-edit');

    // Client accounts (project managers / auditors of a client company) — the details page is app-user-view.
    Route::get('/app/client-user/add', [ClientUserController::class, 'add'])->middleware('can:create,App\\Models\\User')->name('app-client-user-add');
    Route::get('/app/client-user/edit/{user}', [ClientUserController::class, 'edit'])->whereNumber('user')->middleware('can:create,App\\Models\\User')->name('app-client-user-edit');
    // Contractor users (the users of a contractor company) — the details page is app-user-view.
    Route::get('/app/contractor-user/add', [ContractorUserController::class, 'add'])->middleware('can:create,App\\Models\\User')->name('app-contractor-user-add');
    Route::get('/app/contractor-user/edit/{user}', [ContractorUserController::class, 'edit'])->whereNumber('user')->middleware('can:create,App\\Models\\User')->name('app-contractor-user-edit');

    // Drivers
    Route::get('/app/driver/list', [DriverAccountController::class, 'list'])->name('app-driver-list');
    Route::get('/app/driver/add', [DriverAccountController::class, 'add'])->middleware('can:create,App\\Models\\Driver')->name('app-driver-add');
    Route::get('/app/driver/view/{driver}', [DriverAccountController::class, 'view'])->whereNumber('driver')->name('app-driver-view');
    Route::get('/app/driver/edit/{driver}', [DriverAccountController::class, 'edit'])->whereNumber('driver')->middleware('can:create,App\\Models\\Driver')->name('app-driver-edit');

    // Vehicles — {vehicle} kept as a raw numeric id (see VehicleController's docblock).
    Route::get('/app/vehicle/list', [VehicleListController::class, 'index'])->name('app-vehicle-list');
    Route::get('/app/vehicle/add', [VehicleAddController::class, 'index'])->middleware('can:create,App\\Models\\Vehicle')->name('app-vehicle-add');
    Route::get('/app/vehicle/view/{vehicle}', [VehicleAccountController::class, 'view'])->whereNumber('vehicle')->name('app-vehicle-view');
    Route::get('/app/vehicle/edit/{vehicle}', [VehicleAccountController::class, 'edit'])->whereNumber('vehicle')->middleware('can:create,App\\Models\\Vehicle')->name('app-vehicle-edit');

    // General settings (system_admin only) — document-expiry warning days.
    Route::get('/app/settings', [\App\Http\Controllers\Web\Settings\SettingsController::class, 'index'])->middleware('can:viewAny,App\\Models\\TenantSetting')->name('app-settings');

    // Vehicle categories (system_admin only) — {category} is a raw numeric id too.
    Route::get('/app/vehicle-category/list', [VehicleCategoryController::class, 'list'])->name('app-vehicle-category-list');
    Route::get('/app/vehicle-category/add', [VehicleCategoryController::class, 'add'])->middleware('can:create,App\\Models\\VehicleCategory')->name('app-vehicle-category-add');
    Route::get('/app/vehicle-category/edit/{category}', [VehicleCategoryController::class, 'edit'])->whereNumber('category')->middleware('can:create,App\\Models\\VehicleCategory')->name('app-vehicle-category-edit');

    // Assets & Supply Hub — {asset}/{category} kept as raw numeric ids.
    Route::get('/app/asset/list', [AssetListController::class, 'index'])->name('app-asset-list');
    Route::get('/app/asset/add', [AssetAddController::class, 'index'])->middleware('can:create,App\\Models\\Asset')->name('app-asset-add');
    Route::get('/app/asset/insert-into-project', [AssetProjectController::class, 'create'])->middleware('can:create,App\\Models\\Asset')->name('app-asset-insert-into-project');
    Route::get('/app/asset/view/{asset}', [AssetAccountController::class, 'view'])->whereNumber('asset')->name('app-asset-view');
    Route::get('/app/asset/edit/{asset}', [AssetAccountController::class, 'edit'])->whereNumber('asset')->middleware('can:create,App\\Models\\Asset')->name('app-asset-edit');

    Route::get('/app/asset-category/list', [AssetCategoryController::class, 'list'])->name('app-asset-category-list');
    Route::get('/app/asset-category/add', [AssetCategoryController::class, 'add'])->middleware('can:create,App\\Models\\AssetCapacityCategory')->name('app-asset-category-add');
    Route::get('/app/asset-category/view/{category}', [AssetCategoryController::class, 'view'])->whereNumber('category')->name('app-asset-category-view');
    Route::get('/app/asset-category/edit/{category}', [AssetCategoryController::class, 'edit'])->whereNumber('category')->middleware('can:create,App\\Models\\AssetCapacityCategory')->name('app-asset-category-edit');

    // Intermediate waste facilities — {facility} kept as a raw numeric id.
    Route::get('/app/facility/list', [FacilityListController::class, 'index'])->name('app-facility-list');
    Route::get('/app/facility/add', [FacilityAddController::class, 'index'])->middleware('can:create,App\\Models\\IntermediateFacility')->name('app-facility-add');
    Route::get('/app/facility/view/{facility}', [FacilityAccountController::class, 'view'])->whereNumber('facility')->name('app-facility-view');
    Route::get('/app/facility/edit/{facility}', [FacilityAccountController::class, 'edit'])->whereNumber('facility')->middleware('can:create,App\\Models\\IntermediateFacility')->name('app-facility-edit');

    // Services (FRD §1.9) — {service} kept as a raw numeric id.
    Route::get('/app/main-service/list', [MainServicePageController::class, 'list'])->name('app-main-service-list');
    Route::get('/app/main-service/add', [MainServicePageController::class, 'add'])->middleware('can:create,App\\Models\\MainService')->name('app-main-service-add');
    Route::get('/app/main-service/view/{service}', [MainServicePageController::class, 'view'])->whereNumber('service')->name('app-main-service-view');
    Route::get('/app/main-service/edit/{service}', [MainServicePageController::class, 'edit'])->whereNumber('service')->middleware('can:create,App\\Models\\MainService')->name('app-main-service-edit');
    Route::get('/app/sub-service/list', [SubServicePageController::class, 'list'])->name('app-sub-service-list');
    Route::get('/app/sub-service/add', [SubServicePageController::class, 'add'])->middleware('can:create,App\\Models\\SubService')->name('app-sub-service-add');
    Route::get('/app/sub-service/view/{service}', [SubServicePageController::class, 'view'])->whereNumber('service')->name('app-sub-service-view');
    Route::get('/app/sub-service/edit/{service}', [SubServicePageController::class, 'edit'])->whereNumber('service')->middleware('can:create,App\\Models\\SubService')->name('app-sub-service-edit');

    // Client companies — {company} kept as a raw numeric id (see CompanyController's docblock).
    Route::get('/app/company/list', [CompanyListController::class, 'index'])->name('app-company-list');
    Route::get('/app/company/add', [CompanyAddController::class, 'index'])->middleware('can:create,App\\Models\\Company')->name('app-company-add');
    Route::get('/app/company/view/{company}', [CompanyAccountController::class, 'view'])->whereNumber('company')->name('app-company-view');
    Route::get('/app/company/edit/{company}', [CompanyAccountController::class, 'edit'])->whereNumber('company')->middleware('can:create,App\\Models\\Company')->name('app-company-edit');

    // Contractors — {contractor} kept as a raw numeric id (see ContractorController's docblock).
    Route::get('/app/contractor/list', [ContractorListController::class, 'index'])->name('app-contractor-list');
    Route::get('/app/contractor/add', [ContractorAddController::class, 'index'])->middleware('can:create,App\Models\Contractor')->name('app-contractor-add');
    Route::get('/app/contractor/view/{contractor}', [ContractorAccountController::class, 'view'])->whereNumber('contractor')->name('app-contractor-view');
    Route::get('/app/contractor/edit/{contractor}', [ContractorAccountController::class, 'edit'])->whereNumber('contractor')->middleware('can:create,App\Models\Contractor')->name('app-contractor-edit');

    // Client projects — {project} kept as a raw numeric id (see ProjectController's docblock).
    Route::get('/app/project/list', [ProjectListController::class, 'index'])->name('app-project-list');
    Route::get('/app/project/add', [ProjectAddController::class, 'index'])->middleware('can:create,App\\Models\\Project')->name('app-project-add');
    Route::get('/app/project/view/{project}', [ProjectAccountController::class, 'view'])->whereNumber('project')->name('app-project-view');
    Route::get('/app/project/edit/{project}', [ProjectAccountController::class, 'edit'])->whereNumber('project')->middleware('can:create,App\\Models\\Project')->name('app-project-edit');

    // Profile (any authenticated tenant user) — one page, two cards
    // (photo + password change). Used to be two tabs/pages; merged per the
    // client's request. The old security URL just lands on the same page.
    Route::get('/pages/account-settings-account', [AccountSettingsController::class, 'index'])->name('pages-account-settings-account');
    Route::redirect('/pages/account-settings-security', '/pages/account-settings-account')->name('pages-account-settings-security');
});
