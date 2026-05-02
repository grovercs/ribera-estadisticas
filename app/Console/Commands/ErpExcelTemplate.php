<?php

namespace App\Console\Commands;

use App\Services\ErpImportRegistry;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ErpExcelTemplate extends Command
{
    protected $signature = 'app:erp-excel-template {output? : Ruta de salida del archivo}';

    protected $description = 'Genera una plantilla Excel vacía para importar datos del ERP';

    public function handle(): int
    {
        $outputPath = $this->argument('output') ?? storage_path('app/erp_import_template.xlsx');

        $spreadsheet = new Spreadsheet();
        $registry = ErpImportRegistry::all();
        $first = true;

        foreach ($registry as $sheetName => $config) {
            if ($first) {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($sheetName);
                $first = false;
            } else {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($sheetName);
            }

            $headers = array_keys($config['mapping']);
            foreach ($headers as $colIndex => $header) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . '1';
                $sheet->setCellValue($cell, $header);
            }

            // Auto-ajustar ancho básico
            foreach (array_keys($headers) as $colIndex) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->getColumnDimension($col)->setWidth(18);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $this->info("Plantilla generada: {$outputPath}");
        $this->info('Hojas incluidas: ' . implode(', ', array_keys($registry)));
        $this->info('Rellena los datos en cada hoja y luego ejecuta:');
        $this->info('  php artisan app:import-erp-excel ' . $outputPath);

        return self::SUCCESS;
    }
}
