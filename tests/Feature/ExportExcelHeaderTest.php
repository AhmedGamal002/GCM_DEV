<?php

namespace Tests\Feature;

use App\Domain\Assets\Exports\AssetCapacityCategoriesExport;
use App\Domain\Assets\Exports\AssetsExport;
use App\Domain\Companies\Exports\CompaniesExport;
use App\Domain\Contractors\Exports\ContractorsExport;
use App\Domain\Drivers\Exports\DriversExport;
use App\Domain\Facilities\Exports\FacilitiesExport;
use App\Domain\Projects\Exports\ProjectsExport;
use App\Domain\Services\Exports\MainServicesExport;
use App\Domain\Users\Exports\UsersExport;
use App\Domain\Vehicles\Exports\VehicleCategoriesExport;
use App\Domain\Vehicles\Exports\VehiclesExport;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Excel list export opens like its PDF: the GCM logo and product name, then who exported it and when,
 * above the headings (see App\Support\ExportHeader). The file is really written and read back here —
 * the header is added by a sheet event, so the collection()-based tests in ExportFiltersTest can't see it.
 */
class ExportExcelHeaderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: class-string}> */
    public static function exports(): array
    {
        return [
            'assets' => [AssetsExport::class],
            'asset capacities' => [AssetCapacityCategoriesExport::class],
            'companies' => [CompaniesExport::class],
            'contractors' => [ContractorsExport::class],
            'drivers' => [DriversExport::class],
            'facilities' => [FacilitiesExport::class],
            'projects' => [ProjectsExport::class],
            'main services' => [MainServicesExport::class],
            'users' => [UsersExport::class],
            'vehicle categories' => [VehicleCategoriesExport::class],
            'vehicles' => [VehiclesExport::class],
        ];
    }

    #[DataProvider('exports')]
    public function test_the_sheet_starts_with_the_logo_and_who_exported_it_and_when(string $export): void
    {
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $admin = User::factory()->create(['name' => 'Sara Exporter']);
        $admin->assignRole('system_admin');
        $this->actingAs($admin, 'web');
        $this->travelTo(now()->setDateTime(2026, 10, 6, 14, 5, 9));

        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, Excel::raw(new $export(new Collection), ExcelWriter::XLSX));
        $sheet = IOFactory::load($file)->getActiveSheet();
        unlink($file);

        $this->assertSame('GLOBAL CLEAR MISSION', $sheet->getCell('B1')->getValue());
        $this->assertSame('Exported by: Sara Exporter', $sheet->getCell('A2')->getValue());
        $this->assertSame('Date: 2026-10-06', $sheet->getCell('A3')->getValue());
        $this->assertSame('Time: 14:05:09', $sheet->getCell('A4')->getValue());
        $this->assertCount(1, $sheet->getDrawingCollection());
        // The export's own headings follow the header block.
        $this->assertNotEmpty($sheet->getCell('A6')->getValue());
    }
}
