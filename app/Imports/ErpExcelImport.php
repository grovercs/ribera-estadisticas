<?php

namespace App\Imports;

use App\Services\ErpImportRegistry;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ErpExcelImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $sheets = [];

        foreach (array_keys(ErpImportRegistry::all()) as $sheetName) {
            $sheets[$sheetName] = new ErpSheetImport($sheetName);
        }

        return $sheets;
    }
}
