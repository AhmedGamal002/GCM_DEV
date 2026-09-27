<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ArabicText;
use App\Support\PdfLabels;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PDF exports render through one shared partial that splits the rows into
 * page-sized tables. DomPDF's layout cost grows faster than the row count
 * for a single long table (measured 1200 rows: ~15s as one table vs ~6s
 * chunked), which on shared hosting turned big exports into minutes-long
 * waits. These tests pin the structure that keeps it fast, and the Arabic
 * handling (DomPDF can neither join Arabic letters nor lay out RTL by itself).
 */
class ExportPdfLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function render(int $rows, ?int $perPage = null): string
    {
        return view('exports.pdf-table', array_filter([
            'title' => 'Things',
            'headers' => ['A', 'B'],
            'rows' => collect(range(1, $rows))->map(fn ($i) => ["a{$i}", "b{$i}"]),
            'perPage' => $perPage,
        ], fn ($v) => $v !== null))->render();
    }

    public function test_rows_are_split_into_tables_of_forty(): void
    {
        $html = $this->render(100);

        $this->assertSame(3, substr_count($html, '<table'));            // 40 + 40 + 20
        $this->assertSame(3, substr_count($html, '<thead>'));           // header repeats per page
        $this->assertSame(200, substr_count($html, '<td>'));            // 100 rows x 2 cells, none lost in the split
    }

    public function test_every_table_but_the_last_breaks_the_page(): void
    {
        $html = $this->render(100);

        $this->assertSame(2, substr_count($html, 'page-break-after: always'));
    }

    public function test_a_short_list_is_one_table_with_no_page_break(): void
    {
        $html = $this->render(10);

        $this->assertSame(1, substr_count($html, '<table'));
        $this->assertSame(0, substr_count($html, 'page-break-after'));
    }

    public function test_an_empty_list_still_renders_the_title(): void
    {
        $this->assertStringContainsString('Things', $this->render(0));
    }

    public function test_cells_are_escaped(): void
    {
        $html = view('exports.pdf-table', [
            'title' => 'T',
            'headers' => ['H'],
            'rows' => collect([['<script>alert(1)</script>']]),
        ])->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_real_vehicle_pdf_export_spans_several_pages_and_downloads(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'T', 'slug' => 't', 'status' => 'active']));

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');
        Vehicle::factory()->count(95)->create();

        $response = $this->actingAs($admin, 'web')->get('/api/v1/vehicles/export?format=pdf');

        $response->assertOk()->assertDownload('vehicles.pdf');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        // 95 rows / 40 per table = 3 tables = 3 pages
        $this->assertGreaterThanOrEqual(3, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
    }

    public function test_the_arabic_locale_mirrors_the_columns_and_joins_the_letters(): void
    {
        app()->setLocale('ar');

        $html = view('exports.pdf-table', [
            'title' => 'المركبات',
            'headers' => ['ID', 'الاسم'],
            'rows' => collect([['1', 'محمد']]),
        ])->render();

        // shaped + visual order, never the raw logical string DomPDF would draw as loose letters
        $this->assertStringContainsString(ArabicText::forPdf('محمد'), $html);
        $this->assertStringNotContainsString('محمد', $html);
        // RTL: the last header/cell is painted first, i.e. the name column comes before the ID column
        $this->assertLessThan(strpos($html, '>ID<'), strpos($html, ArabicText::forPdf('الاسم')));
        $this->assertLessThan(strpos($html, '>1<'), strpos($html, ArabicText::forPdf('محمد')));
        $this->assertStringContainsString('text-align: right', $html);
    }

    public function test_the_english_locale_keeps_column_order_but_still_shapes_arabic_data(): void
    {
        app()->setLocale('en');

        $html = view('exports.pdf-table', [
            'title' => 'Vehicles',
            'headers' => ['ID', 'Name'],
            'rows' => collect([['1', 'محمد']]),
        ])->render();

        $this->assertLessThan(strpos($html, '>Name<'), strpos($html, '>ID<'));
        $this->assertStringContainsString(ArabicText::forPdf('محمد'), $html);
        $this->assertStringContainsString('text-align: left', $html);
    }

    public function test_enum_values_are_labelled_in_the_current_language(): void
    {
        app()->setLocale('en');
        $this->assertSame('On Maintenance', PdfLabels::of('on_maintenance'));
        $this->assertSame('Contractor', PdfLabels::of('contractor'));

        app()->setLocale('ar');
        $this->assertSame('في الصيانة', PdfLabels::of('on_maintenance'));
        $this->assertSame('متعهد', PdfLabels::of('contractor'));

        $this->assertSame('something else', PdfLabels::of('something else'));
        $this->assertSame('', PdfLabels::of(null));
    }

    public function test_an_arabic_pdf_export_downloads_and_embeds_only_a_font_subset(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'T', 'slug' => 't', 'status' => 'active']));

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');
        Vehicle::factory()->count(3)->create(['plate_letters' => 'أ ب ج']);

        $response = $this->actingAs($admin, 'web')->withSession(['locale' => 'ar'])->get('/api/v1/vehicles/export?format=pdf');

        $response->assertOk()->assertDownload('vehicles.pdf');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        // Without font subsetting the whole ~750KB DejaVu file is embedded in every PDF.
        $this->assertLessThan(200_000, strlen($pdf));
        $this->assertStringContainsString('DejaVu', $pdf);
    }
}
