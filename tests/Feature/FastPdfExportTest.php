<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\PdfTableWriter;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Big list exports. DomPDF's HTML layout costs ~10 ms and ~100 KB per row (2,000 vehicles = 21 s / 240 MB; 50,000
 * never finished), so above `exports.pdf_fast_above` rows PdfExport draws the table straight onto the PDF canvas
 * (PdfTableWriter): 20,000 rows = 527 pages in ~3 s. These tests pin the switch and what the writer produces.
 */
class FastPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf);
    }

    /** @return array{0: float, 1: float} the first page's MediaBox width x height in points */
    private function pageSize(string $pdf): array
    {
        // DomPDF writes it as "[0.000 0.000 841.890 595.280]".
        preg_match('/\/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $m);

        return [(float) $m[1], (float) $m[2]];
    }

    private function rows(int $count, int $columns): array
    {
        return array_map(
            fn ($i) => array_map(fn ($c) => "r{$i}c{$c}", range(1, $columns)),
            range(1, $count)
        );
    }

    public function test_the_writer_makes_a_multi_page_pdf_and_prints_wide_tables_on_landscape(): void
    {
        $pdf = PdfTableWriter::render('Vehicles', ['A', 'B', 'C', 'D', 'E', 'F', 'G'], $this->rows(1000, 7));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(20, $this->pageCount($pdf));
        [$w, $h] = $this->pageSize($pdf);
        $this->assertGreaterThan($h, $w, 'more than 5 columns → landscape');
        $this->assertStringContainsString('DejaVu', $pdf);
    }

    /**
     * The drawing operators of the first page: filled rectangles ("x y w h re f") and text baselines
     * ("BT x y Td ... TJ ET"), in PDF points from the bottom of the page.
     *
     * @return array{rects: list<array{0: float, 1: float}>, baselines: list<float>}  rect = [bottom, top]
     */
    private function drawing(string $pdf): array
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $content = '';
        foreach ($streams[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded !== false && (str_contains($decoded, ' re') || str_contains($decoded, 'Td'))) {
                $content = $decoded;
                break;
            }
        }

        preg_match_all('/^([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) re f$/m', $content, $rects, PREG_SET_ORDER);
        preg_match_all('/^BT [\d.]+ ([\d.]+) Td /m', $content, $texts);

        return [
            'rects' => array_map(fn ($r) => [(float) $r[2], (float) $r[2] + (float) $r[4]], $rects),
            'baselines' => array_map('floatval', $texts[1]),
        ];
    }

    /**
     * Regression: DomPDF's canvas->text() takes the TOP of the text box, but the writer once passed a baseline, so
     * every row's text landed about a line below its own row and the next row's background painted over it
     * ("rows on top of each other"). A row's text must sit inside its own background rectangle.
     */
    public function test_each_rows_text_sits_inside_its_own_row_background(): void
    {
        $rows = [];
        foreach (range(1, 8) as $i) {
            $rows[] = ["a{$i}", "b{$i}", "c{$i}"];
        }

        ['rects' => $rects, 'baselines' => $baselines] = $this->drawing(PdfTableWriter::render('T', ['A', 'B', 'C'], $rows));

        // The heading row's grey background + the zebra rows (every second data row).
        $this->assertCount(1 + 4, $rects);

        foreach ($rects as [$bottom, $top]) {
            $inside = array_filter($baselines, fn ($y) => $y > $bottom + 1 && $y < $top - 1);
            $this->assertCount(3, $inside, 'each background rectangle must hold exactly its own row of 3 cells');
        }
    }

    public function test_a_wrapped_cell_keeps_every_line_inside_its_row(): void
    {
        $rows = [[['one', 'two', 'three'], 'x'], ['y', 'z']];

        ['rects' => $rects, 'baselines' => $baselines] = $this->drawing(PdfTableWriter::render('T', ['List', 'B'], $rows));

        // Heading + one zebra row (the second data row, a single line).
        $this->assertCount(2, $rects);
        [$bottom, $top] = $rects[1];
        $this->assertCount(2, array_filter($baselines, fn ($y) => $y > $bottom + 1 && $y < $top - 1));

        // Below the heading row: the three lines of the list, then the next row — four distinct lines, none stacked
        // on another (each at least a line height below the one above).
        $headingBottom = $rects[0][0];
        $lines = array_values(array_unique(array_map(fn ($y) => round($y, 1), array_filter($baselines, fn ($y) => $y < $headingBottom && $y > 40))));
        rsort($lines);

        $this->assertCount(4, $lines);
        foreach (range(1, 3) as $i) {
            $this->assertGreaterThan(10, $lines[$i - 1] - $lines[$i], 'lines are at least a font height apart');
        }
    }

    public function test_a_narrow_table_stays_portrait(): void
    {
        [$w, $h] = $this->pageSize(PdfTableWriter::render('Few', ['A', 'B', 'C'], $this->rows(10, 3)));

        $this->assertGreaterThan($w, $h);
    }

    public function test_a_list_cell_and_a_huge_unbroken_word_do_not_break_the_writer(): void
    {
        $rows = [
            [['Standard skip', 'Roll-on/off', 'Water tank'], str_repeat('x', 800), 'plain'],
            ['', null, str_repeat('word ', 200)],
        ];

        $pdf = PdfTableWriter::render('Edge cases', ['List', 'Long', 'Text'], $rows);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThanOrEqual(1, $this->pageCount($pdf));
    }

    public function test_the_writer_handles_arabic_mirroring(): void
    {
        app()->setLocale('ar');

        $pdf = PdfTableWriter::render('المركبات', ['الاسم', 'التصنيف', 'الحالة'], [
            ['شاحنة جرّارة', ['مركبات قلابة', 'مركبات بصندوق مغلق'], 'نشط'],
            ['حاوية', 'Hook lift', 'معطّل'],
        ]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('DejaVu', $pdf);
    }

    public function test_five_thousand_rows_take_seconds_not_minutes(): void
    {
        $start = microtime(true);
        $pdf = PdfTableWriter::render('Big', ['A', 'B', 'C', 'D', 'E', 'F', 'G'], $this->rows(5000, 7));
        $seconds = microtime(true) - $start;

        $this->assertGreaterThan(100, $this->pageCount($pdf));
        // ~1 s on a developer machine; the HTML engine needs ~55 s for the same rows. Generous so a slow CI box passes.
        $this->assertLessThan(15, $seconds);
        $this->assertLessThan(256 * 1024 * 1024, memory_get_peak_usage(true));
    }

    private function adminWithVehicles(int $vehicles): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');
        Vehicle::factory()->count($vehicles)->create();

        return $admin;
    }

    public function test_a_list_above_the_threshold_is_rendered_by_the_fast_engine(): void
    {
        config(['exports.pdf_fast_above' => 3]);
        $admin = $this->adminWithVehicles(5);

        $response = $this->actingAs($admin, 'web')->get('/api/v1/vehicles/export?format=pdf')->assertOk();

        $response->assertDownload('vehicles.pdf')->assertHeader('X-Pdf-Engine', 'fast');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_a_list_at_or_below_the_threshold_keeps_the_html_engine(): void
    {
        config(['exports.pdf_fast_above' => 5]);
        $admin = $this->adminWithVehicles(5);

        $this->actingAs($admin, 'web')->get('/api/v1/vehicles/export?format=pdf')
            ->assertOk()
            ->assertDownload('vehicles.pdf')
            ->assertHeader('X-Pdf-Engine', 'html');
    }

    public function test_every_pdf_export_still_works_through_the_fast_engine(): void
    {
        config(['exports.pdf_fast_above' => 0]);
        $admin = $this->adminWithVehicles(2);

        foreach (['users', 'drivers', 'vehicles', 'vehicle-categories', 'assets', 'asset-capacity-categories', 'facilities', 'main-services', 'sub-services', 'contractors', 'companies', 'projects'] as $module) {
            $response = $this->actingAs($admin, 'web')->get("/api/v1/{$module}/export?format=pdf");

            // With the threshold at 0 a list that has at least one row goes through the writer; an empty one
            // (nothing to count above 0) keeps the HTML path. Both must give a real PDF.
            $response->assertOk();
            $this->assertStringStartsWith('%PDF-', $response->getContent(), "{$module} did not return a PDF");
        }
    }
}
