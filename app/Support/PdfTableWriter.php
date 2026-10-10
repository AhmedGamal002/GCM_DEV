<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The fast PDF renderer for big list exports.
 *
 * Why it exists: DomPDF lays an HTML table out cell by cell, which costs ~10 ms and ~100 KB of memory PER ROW
 * (measured: 2,000 vehicles = 21 s / 240 MB, so 50,000 never finished and even 800 pages did not fit in the
 * memory limit). This class draws the same table straight onto DomPDF's PDF canvas — no HTML, no CSS, no layout
 * engine — which is ~100x cheaper (20,000 rows = 527 pages in ~3 s / ~60 MB). PdfExport picks it automatically
 * above a row threshold; below that the HTML template (exports/pdf-table.blade.php) is used and looks the same.
 *
 * It follows the same rules as the HTML partial:
 *  - more than 5 columns → A4 landscape, a smaller font; fewer → portrait;
 *  - the GCM logo + name and "Exported by / Date / Time" open the first page, then the title;
 *  - in Arabic the table is mirrored (columns right to left, right-aligned) and every string goes through
 *    ArabicText::forPdf() — a line is wrapped BEFORE it is reversed, so wrapping stays correct;
 *  - a cell can be a list (array): one item per line;
 *  - a cell wider than its column wraps (words, then characters), so nothing is cut off.
 * The table heading repeats on every page and every page carries "n / total".
 */
final class PdfTableWriter
{
    private const FONT = 'DejaVu Sans';

    /** Rows sampled to size the columns — later rows that turn out wider simply wrap. */
    private const MEASURE_ROWS = 600;

    private Dompdf $dompdf;

    private $canvas;

    private $regular;

    private $bold;

    private bool $rtl;

    private int $columns;

    private float $size;

    private float $lineHeight;

    private float $padX = 4.0;

    private float $padY = 3.0;

    private float $left;

    private float $right;

    private float $bottom;

    private float $top = 28.0;

    /** @var array<string, float> */
    private array $widthCache = [];

    /** @var array<string, string> */
    private array $shapeCache = [];

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows  each row = one value per column (a value may be a list)
     * @return string the PDF file
     */
    public static function render(string $title, array $headers, iterable $rows): string
    {
        return (new self())->build($title, array_values($headers), $rows);
    }

    private function build(string $title, array $headers, iterable $rows): string
    {
        $this->rtl = app()->getLocale() === 'ar';
        $this->columns = count($headers);
        $landscape = $this->columns > 5;
        $this->size = $this->columns >= 9 ? 7.0 : ($this->columns >= 7 ? 8.0 : 9.0);
        $this->lineHeight = $this->size * 1.35;

        $options = new Options();
        $options->set('enable_font_subsetting', true);
        $this->dompdf = new Dompdf($options);
        $this->dompdf->setPaper('a4', $landscape ? 'landscape' : 'portrait');
        $this->canvas = $this->dompdf->getCanvas();
        $metrics = $this->dompdf->getFontMetrics();
        $this->regular = $metrics->getFont(self::FONT, 'normal');
        $this->bold = $metrics->getFont(self::FONT, 'bold') ?: $this->regular;

        $this->left = $landscape ? 32.0 : 28.0;
        $this->right = $this->canvas->get_width() - $this->left;
        $this->bottom = $this->canvas->get_height() - 36.0;

        $rows = $this->normalise($rows);
        $widths = $this->columnWidths($headers, $rows);

        $y = $this->firstPageHeader($title);
        $y = $this->headingRow($headers, $widths, $y);

        foreach ($rows as $i => $row) {
            $cells = [];
            $lines = 1;
            foreach ($row as $c => $logicalLines) {
                $cells[$c] = $this->wrap($logicalLines, $widths[$c] - 2 * $this->padX);
                $lines = max($lines, count($cells[$c]));
            }
            $height = $lines * $this->lineHeight + 2 * $this->padY;

            if ($y + $height > $this->bottom) {
                $this->canvas->new_page();
                $y = $this->headingRow($headers, $widths, $this->top);
            }

            if ($i % 2) {
                $this->canvas->filled_rectangle($this->left, $y, $this->right - $this->left, $height, [0.969, 0.969, 0.969]);
            }
            $this->drawCells($cells, $widths, $y, $this->regular);
            $y += $height;
        }

        // "3 / 12" at the foot of every page (numbers only, so no shaping needed).
        $this->canvas->page_text($this->canvas->get_width() / 2 - 14, $this->canvas->get_height() - 22, '{PAGE_NUM} / {PAGE_COUNT}', $this->regular, 8, [0.4, 0.4, 0.4]);
        $this->canvas->add_info('Title', $title);

        return $this->dompdf->output();
    }

    /**
     * Every cell becomes a list of logical (not yet shaped) lines.
     *
     * @return list<list<list<string>>>
     */
    private function normalise(iterable $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $cells = [];
            foreach (array_pad(array_slice(array_values($row), 0, $this->columns), $this->columns, '') as $cell) {
                $items = is_array($cell) ? $cell : [$cell];
                $cells[] = array_map(fn ($item) => is_null($item) ? '' : (string) $item, $items) ?: [''];
            }
            $out[] = $cells;
        }

        return $out;
    }

    /**
     * Column widths (points) summing to the printable width. A column gets what its longest sampled line needs;
     * when everything does not fit, short columns keep their natural width and the long ones share what is left.
     *
     * @return list<float>
     */
    private function columnWidths(array $headers, array $rows): array
    {
        $natural = array_fill(0, $this->columns, 0.0);

        foreach ($headers as $c => $header) {
            $natural[$c] = $this->width((string) $header, true);
        }
        foreach (array_slice($rows, 0, self::MEASURE_ROWS) as $row) {
            foreach ($row as $c => $lines) {
                foreach ($lines as $line) {
                    $natural[$c] = max($natural[$c], $this->width($line));
                }
            }
        }
        $natural = array_map(fn ($w) => $w + 2 * $this->padX + 2, $natural);

        $available = $this->right - $this->left;

        if (array_sum($natural) <= $available) {
            $scale = $available / array_sum($natural);

            return array_map(fn ($w) => $w * $scale, $natural);
        }

        // Water-filling: columns narrower than an equal share keep their natural width.
        $widths = array_fill(0, $this->columns, 0.0);
        $open = range(0, $this->columns - 1);
        $left = $available;

        while ($open) {
            $share = $left / count($open);
            $fits = array_filter($open, fn ($c) => $natural[$c] <= $share);

            if (! $fits) {
                $total = array_sum(array_map(fn ($c) => $natural[$c], $open));
                foreach ($open as $c) {
                    $widths[$c] = $left * $natural[$c] / $total;
                }
                break;
            }

            foreach ($fits as $c) {
                $widths[$c] = $natural[$c];
                $left -= $natural[$c];
            }
            $open = array_values(array_diff($open, $fits));
        }

        return array_values($widths);
    }

    /** The shaped (visual-order) form of a logical string, cached — the same values repeat a lot. */
    private function shaped(string $text): string
    {
        return $this->shapeCache[$text] ??= ArabicText::forPdf($text);
    }

    private function width(string $text, bool $bold = false): float
    {
        $key = ($bold ? 'b' : 'r').$text;

        return $this->widthCache[$key] ??= (float) $this->canvas->get_text_width($this->shaped($text), $bold ? $this->bold : $this->regular, $this->size);
    }

    /**
     * Wrap a cell's logical lines to $inner points: words first, then characters for a word wider than the cell.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function wrap(array $lines, float $inner): array
    {
        $out = [];

        foreach ($lines as $line) {
            if ($line === '' || $this->width($line) <= $inner) {
                $out[] = $line;

                continue;
            }

            $current = '';
            foreach (preg_split('/\s+/u', trim($line)) as $word) {
                $candidate = $current === '' ? $word : $current.' '.$word;

                if ($this->width($candidate) <= $inner) {
                    $current = $candidate;

                    continue;
                }

                if ($current !== '') {
                    $out[] = $current;
                    $current = '';
                }

                // A single word wider than the cell: break it by characters.
                while ($word !== '' && $this->width($word) > $inner) {
                    $cut = mb_strlen($word);
                    while ($cut > 1 && $this->width(mb_substr($word, 0, $cut)) > $inner) {
                        $cut--;
                    }
                    $out[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $current = $word;
            }

            if ($current !== '') {
                $out[] = $current;
            }
        }

        return $out ?: [''];
    }

    /** The logo, name and "Exported by / Date / Time", then the title; returns the y where the table starts. */
    private function firstPageHeader(string $title): float
    {
        $y = $this->top;
        $logo = 34.0;
        $name = Brand::name();
        $exportedAt = now();
        $meta = array_filter([
            auth()->user()?->name ? __('Exported by').': '.auth()->user()->name : null,
            __('Date').': '.$exportedAt->format('Y-m-d'),
            __('Time').': '.$exportedAt->format('H:i:s'),
        ]);

        // The logo + name sit on the reading-start side, the meta lines on the other (mirrored in Arabic).
        $logoX = $this->rtl ? $this->right - $logo : $this->left;
        $this->canvas->image(Brand::logoPath(), $logoX, $y, $logo, $logo);

        $nameWidth = $this->textWidth($name, $this->bold, 11);
        $nameX = $this->rtl ? $logoX - 8 - $nameWidth : $logoX + $logo + 8;
        // NOTE: DomPDF's canvas->text() takes the TOP of the text box (it shifts down by the font height itself),
        // not a baseline — every y passed to text() below is a top, centred or stacked by hand.
        $nameTop = $y + ($logo - $this->canvas->get_font_height($this->bold, 11)) / 2;
        $this->canvas->text($nameX, $nameTop, $this->shaped($name), $this->bold, 11, [0.106, 0.165, 0.361]);

        $metaTop = $y + 1;
        foreach ($meta as $line) {
            $w = $this->textWidth($line, $this->regular, 8);
            $x = $this->rtl ? $this->left : $this->right - $w;
            $this->canvas->text($x, $metaTop, $this->shaped($line), $this->regular, 8, [0.33, 0.33, 0.33]);
            $metaTop += 11;
        }

        $y += $logo + 8;
        $this->canvas->line($this->left, $y, $this->right, $y, [0.81, 0.81, 0.81], 0.8);
        $y += 8;

        $titleWidth = $this->textWidth($title, $this->bold, 11);
        $this->canvas->text($this->rtl ? $this->right - $titleWidth : $this->left, $y, $this->shaped($title), $this->bold, 11, [0, 0, 0]);

        return $y + $this->canvas->get_font_height($this->bold, 11) + 8;
    }

    private function textWidth(string $text, $font, float $size): float
    {
        return (float) $this->canvas->get_text_width($this->shaped($text), $font, $size);
    }

    /** The grey, bold heading row; returns the y below it. */
    private function headingRow(array $headers, array $widths, float $y): float
    {
        $height = $this->lineHeight + 2 * $this->padY;
        $this->canvas->filled_rectangle($this->left, $y, $this->right - $this->left, $height, [0.941, 0.941, 0.941]);
        $this->drawCells(array_map(fn ($h) => [(string) $h], $headers), $widths, $y, $this->bold);

        return $y + $height;
    }

    /**
     * Draw one row of already-wrapped cells ($cells[column] = visual lines). The columns run left to right, or
     * right to left (mirrored) in Arabic, with the text aligned to the side the reader starts from.
     *
     * @param  array<int, list<string>>  $cells
     * @param  list<float>  $widths
     */
    private function drawCells(array $cells, array $widths, float $y, $font): void
    {
        $order = range(0, $this->columns - 1);
        if ($this->rtl) {
            $order = array_reverse($order);
        }

        $x = $this->left;
        foreach ($order as $c) {
            // The top of the text box (see the note in firstPageHeader()), not a baseline.
            $lineY = $y + $this->padY;
            foreach ($cells[$c] as $line) {
                if ($line !== '') {
                    $text = $this->shaped($line);
                    $textX = $this->rtl
                        ? $x + $widths[$c] - $this->padX - $this->canvas->get_text_width($text, $font, $this->size)
                        : $x + $this->padX;
                    $this->canvas->text($textX, $lineY, $text, $font, $this->size, [0, 0, 0]);
                }
                $lineY += $this->lineHeight;
            }
            $x += $widths[$c];
        }
    }
}
