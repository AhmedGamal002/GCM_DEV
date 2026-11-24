<?php

namespace App\Domain\Trips\Documents;

use App\Support\ArabicText;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;

/**
 * The fast path for LONG check-box lists in the trip documents (decision د8: every sub-service and
 * capacity, no cap — and a main service may hold hundreds of sub-services).
 *
 * DomPDF lays out every table cell itself, ~10 ms per row of the lists (1,000 items: ~15 s), and plain
 * text lines are hardly cheaper. So, like the big list exports (PdfTableWriter), the bulk is drawn straight
 * onto the PDF canvas — but only the MIDDLE pages of a long list: the document is still the HTML template,
 * where _lists.blade.php puts
 *
 *   - the first rows under the document's header (page 1) and the last rows before the sections that
 *     follow (signatures …) as normal HTML tables, and
 *   - for every page in between, an empty placeholder <div class="list-page" data-list="…"> one page tall.
 *
 * When DomPDF has rendered a placeholder (the `end_frame` callback) this draws that page's rows into its
 * box: the same two header rows, row height, borders, check boxes, bold marked item and colour swatches as
 * the HTML table. The page keeps everything else DomPDF puts on every page (the fixed footer), and the pages
 * stay in reading order. A normal document (lists of a few dozen rows) has no placeholder: pure HTML.
 *
 * The placeholders are registered while the Blade view renders and read back by key during DomPDF's render
 * — so anything rendering these templates must go through TripDocumentRenderer (which resets the registry
 * and installs the callback), the sustainability report of 8.5 included.
 */
final class TripDocumentListPages
{
    /** The HTML table's row pitch, measured on the rendered PDF (7.5 pt text, 1.8 pt padding, borders). */
    public const ROW_HEIGHT = 15.72;

    /** Rows a full list page holds under its two header rows (the page body is ~738 pt). */
    public const ROWS_PER_PAGE = 44;

    /** The lists' width: A4 (595.28 pt) less the page margins (30 pt each side, pdf.blade.php). */
    public const TABLE_WIDTH = 535.28;

    private const FONT = 'DejaVu Sans';

    private const SIZE = 7.5;

    /** The check-box cell as DomPDF lays it out: 10 pt + 2 × 2 pt padding (pdf.blade.php `td.box`) + 0.6 pt border. */
    private const BOX_WIDTH = 14.6;

    /** DomPDF adds each collapsed cell's 0.6 pt border to its width. */
    private const CELL_BORDER = 0.6;

    private const PAD = 4.0;

    /**
     * The CSS width (content, without the 2 × 4 pt padding) of a label cell when `$columns` lists share the
     * table — fixed, so the HTML pages and the drawn pages put every border at the same place.
     */
    public static function labelContentWidth(int $columns): float
    {
        return round(self::TABLE_WIDTH / $columns - self::BOX_WIDTH - 2 * self::PAD - self::CELL_BORDER, 2);
    }

    private const BORDER = [0.522, 0.573, 0.722];

    private const HEAD_FILL = [0.89, 0.91, 0.965];

    private const NAVY = [0.106, 0.165, 0.361];

    private const EMPTY_FILL = [0.98, 0.984, 0.996];

    private const TEXT = [0.114, 0.141, 0.2];

    /** @var array<string, array{caption: string, captionSpan: int, columns: list<array{title: string, items: list<array>}>, from: int, to: int}> */
    private static array $pages = [];

    public static function reset(): void
    {
        self::$pages = [];
    }

    /** @return array<string, array{caption: string, captionSpan: int, columns: array, from: int, to: int}> the pages of the last render */
    public static function registered(): array
    {
        return self::$pages;
    }

    /** Called by _lists.blade.php for each middle page; returns the key its placeholder carries. */
    public static function register(string $caption, int $captionSpan, array $columns, int $from, int $to): string
    {
        $key = 'list-'.count(self::$pages);
        self::$pages[$key] = compact('caption', 'captionSpan', 'columns', 'from', 'to');

        return $key;
    }

    /** The placeholder's height: the two header rows and a full page of rows. */
    public static function placeholderHeight(): float
    {
        return (self::ROWS_PER_PAGE + 2) * self::ROW_HEIGHT + 1;
    }

    /** @return list<array{event: string, f: callable}> for Dompdf::setCallbacks() */
    public static function callbacks(): array
    {
        return [[
            'event' => 'end_frame',
            'f' => function (Frame $frame, Canvas $canvas, FontMetrics $metrics) {
                $node = $frame->get_node();

                if (! $node instanceof \DOMElement || ! $node->hasAttribute('data-list')) {
                    return;
                }

                $page = self::$pages[$node->getAttribute('data-list')] ?? null;

                if ($page !== null) {
                    [$x, $y, $width] = $frame->get_padding_box();
                    (new self)->draw($canvas, $metrics, $page, (float) $x, (float) $y, (float) $width);
                }
            },
        ]];
    }

    private Canvas $canvas;

    private $regular;

    private $bold;

    private function draw(Canvas $canvas, FontMetrics $metrics, array $page, float $x, float $y, float $width): void
    {
        $this->canvas = $canvas;
        $this->regular = $metrics->getFont(self::FONT, 'normal');
        $this->bold = $metrics->getFont(self::FONT, 'bold') ?: $this->regular;

        $columns = $page['columns'];
        $span = $page['captionSpan'];
        $colWidth = $width / count($columns);
        $h = self::ROW_HEIGHT;

        // Header row 1: the caption over the first $span columns; the other columns' titles span both rows.
        $this->cell($x, $y, $colWidth * $span, $h, self::HEAD_FILL);
        $this->centered($page['caption'].' (continued)', $x, $y, $colWidth * $span, $h, $this->bold);

        foreach ($columns as $c => $column) {
            $cx = $x + $c * $colWidth;

            if ($c < $span) {
                $this->cell($cx, $y + $h, $colWidth, $h, self::HEAD_FILL);
                $this->centered($column['title'], $cx, $y + $h, $colWidth, $h, $this->bold);
            } else {
                $this->cell($cx, $y, $colWidth, 2 * $h, self::HEAD_FILL);
                $this->centered($column['title'], $cx, $y, $colWidth, 2 * $h, $this->bold);
            }
        }

        $rowY = $y + 2 * $h;

        for ($i = $page['from']; $i < $page['to']; $i++) {
            foreach ($columns as $c => $column) {
                $this->item($column['items'][$i] ?? null, count($columns), $x + $c * $colWidth, $rowY, $colWidth);
            }
            $rowY += $h;
        }
    }

    /** One item: the check-box cell and the label cell (empty and shaded when the column has run out). */
    private function item(?array $item, int $columns, float $x, float $y, float $width): void
    {
        $h = self::ROW_HEIGHT;
        $box = self::BOX_WIDTH;

        if ($item === null) {
            $this->cell($x, $y, $box, $h, self::EMPTY_FILL);
            $this->cell($x + $box, $y, $width - $box, $h, self::EMPTY_FILL);

            return;
        }

        $this->cell($x, $y, $box, $h);
        $this->cell($x + $box, $y, $width - $box, $h);

        // The check box: 7 pt, navy outline; marked = filled navy with a white tick.
        $s = 7.0;
        $bx = $x + ($box - $s) / 2;
        $by = $y + ($h - $s) / 2;
        if ($item['checked']) {
            $this->canvas->filled_rectangle($bx, $by, $s, $s, self::NAVY);
            $this->canvas->text($bx + 1.2, $by - 0.6, '✓', $this->bold, 6.5, [1, 1, 1]);
        } else {
            $this->canvas->rectangle($bx, $by, $s, $s, self::NAVY, 0.7);
        }

        $tx = $x + $box + self::CELL_BORDER + self::PAD;

        if (array_key_exists('hex', $item)) {
            $this->canvas->filled_rectangle($tx, $y + ($h - 6) / 2, 8, 6, self::rgb($item['hex'] ?? '#ffffff'));
            $this->canvas->rectangle($tx, $y + ($h - 6) / 2, 8, 6, [0.333, 0.333, 0.333], 0.5);
            $tx += 11;
        }

        $font = $item['checked'] ? $this->bold : $this->regular;
        $color = $item['checked'] ? self::NAVY : self::TEXT;
        $this->canvas->text($tx, $y + ($h - self::SIZE * 1.17) / 2, self::label($item, $columns), $font, self::SIZE, $color);
    }

    private function cell(float $x, float $y, float $w, float $h, ?array $fill = null): void
    {
        if ($fill !== null) {
            $this->canvas->filled_rectangle($x, $y, $w, $h, $fill);
        }
        $this->canvas->rectangle($x, $y, $w, $h, self::BORDER, 0.6);
    }

    private function centered(string $text, float $x, float $y, float $w, float $h, $font): void
    {
        $text = self::fit($text, $w - 2 * self::PAD, true);
        $this->canvas->text($x + ($w - self::width($text, true)) / 2, $y + ($h - self::SIZE * 1.17) / 2, $text, $font, self::SIZE, self::NAVY);
    }

    /**
     * An item's label as printed in a list cell of `$columns` lists — on the HTML pages (_lists.blade.php)
     * and the drawn ones alike, so both cut it the same way.
     */
    public static function label(array $item, int $columns): string
    {
        $room = self::labelContentWidth($columns) - (array_key_exists('hex', $item) ? 11 : 0);

        return self::fit((string) $item['label'], $room, (bool) $item['checked']);
    }

    /**
     * Text shaped for the PDF (Arabic joined and ordered) and shortened with "…" when it is wider than its
     * cell: the cells are one line (nowrap), and a wider label would push the HTML column wider than the
     * drawn pages' column. Shortened on the LOGICAL text, then shaped.
     */
    private static function fit(string $text, float $room, bool $bold): string
    {
        $shaped = ArabicText::forPdf($text);

        if (self::width($shaped, $bold) <= $room) {
            return $shaped;
        }

        $chars = mb_str_split($text);
        while (count($chars) > 1) {
            array_pop($chars);
            $shaped = ArabicText::forPdf(rtrim(implode('', $chars)).'…');
            if (self::width($shaped, $bold) <= $room) {
                break;
            }
        }

        return $shaped;
    }

    /** @var array<string, float> */
    private static array $widthCache = [];

    private static ?FontMetrics $sharedMetrics = null;

    private static function width(string $text, bool $bold): float
    {
        $key = ($bold ? 'b|' : 'r|').$text;

        if (! isset(self::$widthCache[$key])) {
            // The same DomPDF options (font directories) as the documents' own render.
            $metrics = self::$sharedMetrics ??= app('dompdf.wrapper')->getDomPDF()->getFontMetrics();
            $font = $metrics->getFont(self::FONT, $bold ? 'bold' : 'normal');
            self::$widthCache[$key] = (float) $metrics->getTextWidth($text, $font, self::SIZE);
        }

        return self::$widthCache[$key];
    }

    /** "#rrggbb" → DomPDF's [r, g, b] in 0..1 (the hex values come from SubService::SKIP_COLORS). */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }
}
