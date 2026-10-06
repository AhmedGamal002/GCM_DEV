<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Gives an Excel list export the same opening as its PDF (exports/pdf-table.blade.php): the GCM logo and
 * product name, then who exported it and when, above the usual headings and rows. Add `WithEvents` to the
 * export's `implements` and `use ExportHeader;` — the export's own collection()/headings()/map() are untouched
 * (the rows are pushed down after the sheet is written), so tests reading collection() still see only data.
 */
trait ExportHeader
{
    /** Rows added above the headings: logo/name, exported by, date, time, one blank. */
    private const HEADER_ROWS = 5;

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, self::HEADER_ROWS);

                $now = now();
                $user = auth()->user()?->name;

                $logo = new Drawing();
                $logo->setName('GCM');
                $logo->setPath(Brand::logoPath());
                $logo->setHeight(48);
                $logo->setCoordinates('A1');
                $logo->setOffsetX(4);
                $logo->setOffsetY(2);
                $logo->setWorksheet($sheet);
                $sheet->getRowDimension(1)->setRowHeight(40);

                $sheet->setCellValue('B1', Brand::name());
                $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1B2A5C');
                $sheet->getStyle('B1')->getAlignment()->setVertical('center');

                if ($user) {
                    $sheet->setCellValue('A2', __('Exported by').': '.$user);
                }
                $sheet->setCellValue('A3', __('Date').': '.$now->format('Y-m-d'));
                $sheet->setCellValue('A4', __('Time').': '.$now->format('H:i:s'));
                $sheet->getStyle('A2:A4')->getFont()->setSize(9)->setName('Calibri');

                // The headings are now on row 6 — make them stand out from the data.
                $sheet->getStyle('A'.(self::HEADER_ROWS + 1).':'.$sheet->getHighestColumn().(self::HEADER_ROWS + 1))
                    ->getFont()->setBold(true);
            },
        ];
    }
}
