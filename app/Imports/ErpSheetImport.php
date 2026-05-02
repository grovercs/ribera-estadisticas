<?php

namespace App\Imports;

use App\Services\ErpImportRegistry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ErpSheetImport implements ToCollection, WithHeadingRow
{
    private array $config;
    private int $chunkSize;

    public function __construct(string $sheetName, int $chunkSize = 500)
    {
        $registry = ErpImportRegistry::all();

        if (!isset($registry[$sheetName])) {
            throw new \InvalidArgumentException("Hoja '{$sheetName}' no está registrada en ErpImportRegistry.");
        }

        $this->config = $registry[$sheetName];
        $this->chunkSize = $chunkSize;
    }

    public function collection(Collection $rows): void
    {
        $modelClass = $this->config['model'];
        $mapping = $this->config['mapping'];
        $uniqueBy = $this->config['unique_by'];
        $now = now();

        $chunks = $rows->chunk($this->chunkSize);

        foreach ($chunks as $chunk) {
            $data = [];

            foreach ($chunk as $row) {
                $item = [];
                foreach ($mapping as $erpCol => $mysqlCol) {
                    $value = $row[$erpCol] ?? null;
                    // Convertir objetos Carbon/DateTime de Excel a string para evitar errores
                    if (is_object($value) && method_exists($value, 'format')) {
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    $item[$mysqlCol] = $value;
                }
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
                $data[] = $item;
            }

            if (!empty($data)) {
                $updateColumns = array_merge(array_values($mapping), ['updated_at']);
                $modelClass::upsert($data, $uniqueBy, $updateColumns);
            }
        }
    }
}
