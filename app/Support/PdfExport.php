<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * One entry point for every list PDF export (the controllers call this instead of DomPDF directly).
 *
 * The export templates are tiny: they build [title, headers, rows] and include `exports.pdf-table`. This runs the
 * template once in "collect" mode (the partial hands its data here instead of printing HTML), then:
 *  - up to `exports.pdf_fast_above` rows (default 1,500): the normal HTML → DomPDF path (the template's look);
 *  - above that: PdfTableWriter, which draws the same table directly on the PDF canvas — ~100x cheaper in time and
 *    memory, so a 50,000-row list (≈ 1,300 pages) finishes in seconds instead of never.
 * The response carries `X-Pdf-Engine: html|fast` (handy when checking which one a file came from).
 */
final class PdfExport
{
    public const FAST_ABOVE_DEFAULT = 1500;

    private static bool $collecting = false;

    /** @var array{title: string, headers: array, rows: iterable}|null */
    private static ?array $collected = null;

    public static function collecting(): bool
    {
        return self::$collecting;
    }

    /** Called by exports/pdf-table.blade.php while collecting. */
    public static function collect(string $title, array $headers, iterable $rows): void
    {
        self::$collected = ['title' => $title, 'headers' => $headers, 'rows' => $rows];
    }

    /** Rows read from the database per round trip when a big list is streamed. */
    private const CHUNK = 500;

    /**
     * Export a list straight from its query, WITHOUT loading it whole. A small list is fetched normally; a big one
     * is read lazily in chunks (relations eager-loaded per chunk) and turned into rows as it goes, so memory stays
     * flat — loading 50,000 vehicles with their documents as models alone ran past 512 MB before any PDF work.
     *
     * @param  string  $view  the export template (e.g. tenant.vehicles.export-pdf)
     * @param  string  $dataKey  the variable that template reads the records from (e.g. 'vehicles')
     * @param  \Illuminate\Database\Eloquent\Builder  $query  the filtered, ordered list query
     */
    public static function downloadQuery(string $view, string $dataKey, \Illuminate\Database\Eloquent\Builder $query, string $filename): Response
    {
        // The count must not carry the ORDER BY: an aggregate query with one is rejected by strict MySQL.
        $count = (clone $query)->reorder()->count();
        $big = $count > self::threshold();

        return self::download($view, [$dataKey => $big ? $query->lazy(self::CHUNK) : $query->get()], $filename, $count);
    }

    private static function threshold(): int
    {
        return (int) config('exports.pdf_fast_above', self::FAST_ABOVE_DEFAULT);
    }

    /**
     * @param  string  $view  the export template (e.g. tenant.vehicles.export-pdf)
     * @param  array<string, mixed>  $data  what the template needs
     * @param  int|null  $rowCount  the number of rows when the data is lazy (counting it would read it all)
     */
    public static function download(string $view, array $data, string $filename, ?int $rowCount = null): Response
    {
        // Big lists need room; never lower a limit the server already gave us. Not from the console: there is no
        // web timeout to extend, and set_time_limit() there would arm a clock that kills a long test run.
        if (! app()->runningInConsole()) {
            set_time_limit(300);
        }
        self::ensureMemory('768M');

        self::$collecting = true;
        self::$collected = null;
        try {
            view($view, $data)->render();
        } finally {
            self::$collecting = false;
        }

        $table = self::$collected;
        self::$collected = null;

        if ($rowCount === null && $table !== null) {
            $rowCount = $table['rows'] instanceof \Illuminate\Support\LazyCollection ? PHP_INT_MAX : count($table['rows']);
        }
        $fast = $table !== null && $rowCount > self::threshold();

        $pdf = $fast
            ? PdfTableWriter::render($table['title'], $table['headers'], $table['rows'])
            : app('dompdf.wrapper')->loadView($view, $data)->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Pdf-Engine' => $fast ? 'fast' : 'html',
        ]);
    }

    private static function ensureMemory(string $wanted): void
    {
        $current = ini_get('memory_limit');

        if ($current !== '-1' && self::bytes($current) < self::bytes($wanted)) {
            @ini_set('memory_limit', $wanted);
        }
    }

    private static function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
