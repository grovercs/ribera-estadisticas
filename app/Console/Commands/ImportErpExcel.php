<?php

namespace App\Console\Commands;

use App\Imports\ErpExcelImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportErpExcel extends Command
{
    protected $signature = 'app:import-erp-excel {path : Ruta al archivo Excel (.xlsx)}';

    protected $description = 'Importa datos del ERP desde un archivo Excel (.xlsx)';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("El archivo no existe: {$path}");
            return self::FAILURE;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (strtolower($extension) !== 'xlsx') {
            $this->error('Solo se admiten archivos .xlsx');
            return self::FAILURE;
        }

        $this->info("Importando desde {$path}...");

        try {
            Excel::import(new ErpExcelImport, $path);
            $this->info('Importación desde Excel completada.');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error durante la importación: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
