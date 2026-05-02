<?php

namespace App\Console\Commands;

use App\Models\ErpClient;
use App\Models\ErpFamily;
use App\Models\ErpProduct;
use App\Models\ErpSeller;
use App\Models\ErpStock;
use App\Models\ErpSubfamily;
use App\Models\ErpSupplier;
use App\Models\ErpWarehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportErpMasters extends Command
{
    protected $signature = 'app:import-erp-masters {--table= : Importar solo una tabla (families,subfamilies,sellers,warehouses,products,clients,stocks,suppliers)}';

    protected $description = 'Importa maestros desde el ERP SQL Server a MySQL';

    public function handle(): int
    {
        $only = $this->option('table');

        $tables = [
            'families'    => ['model' => ErpFamily::class,    'erpTable' => 'familias',       'pk' => 'cod_familia'],
            'subfamilies' => ['model' => ErpSubfamily::class, 'erpTable' => 'subfamilias',    'pk' => ['cod_subfamilia', 'cod_familia']],
            'sellers'     => ['model' => ErpSeller::class,     'erpTable' => 'vendedores',      'pk' => 'cod_vendedor'],
            'warehouses'  => ['model' => ErpWarehouse::class,  'erpTable' => 'almacenes',       'pk' => 'cod_almacen'],
            'products'    => ['model' => ErpProduct::class,    'erpTable' => 'articulos',       'pk' => 'cod_articulo'],
            'clients'     => ['model' => ErpClient::class,     'erpTable' => 'clientes',        'pk' => 'cod_cliente'],
            'stocks'      => ['model' => ErpStock::class,      'erpTable' => 'stocks',          'pk' => ['cod_almacen', 'cod_articulo']],
            'suppliers'   => ['model' => ErpSupplier::class,   'erpTable' => 'proveedores',     'pk' => 'cod_proveedor'],
        ];

        foreach ($tables as $name => $config) {
            if ($only && $only !== $name) continue;
            $this->importTable($name, $config);
        }

        return self::SUCCESS;
    }

    private function importTable(string $name, array $config): void
    {
        $this->info("Importando {$name}...");
        $erp = DB::connection('erp');
        $model = $config['model'];
        $erpTable = $config['erpTable'];
        $pk = $config['pk'];

        // Obtener columnas del ERP
        $columns = $erp->select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$erpTable]);

        $columnNames = array_column(array_map(fn($c) => (array)$c, $columns), 'COLUMN_NAME');

        // Filtrar columnas que existen en el modelo (fillable + timestamps) y en la tabla MySQL
        $instance = new $model;
        $allowed = array_merge($instance->getFillable(), ['created_at', 'updated_at']);
        $mysqlColumns = DB::connection()->getSchemaBuilder()->getColumnListing($instance->getTable());
        $toImport = array_values(array_filter($columnNames, fn($c) =>
            in_array(strtolower($c), array_map('strtolower', $allowed)) &&
            in_array(strtolower($c), array_map('strtolower', $mysqlColumns))
        ));

        if (empty($toImport)) {
            $this->warn("No hay columnas coincidentes para {$name}");
            return;
        }

        $selectCols = implode(', ', $toImport);
        $total = $erp->select("SELECT COUNT(*) as total FROM {$erpTable}")[0]->total;
        $this->info("Registros en ERP: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $chunkSize = 500;
        $offset = 0;
        $now = now();

        while ($offset < $total) {
            $rows = $erp->select("SELECT {$selectCols} FROM {$erpTable} ORDER BY 1 OFFSET {$offset} ROWS FETCH NEXT {$chunkSize} ROWS ONLY");

            if (empty($rows)) break;

            $chunk = [];
            foreach ($rows as $row) {
                $data = (array) $row;
                foreach ($data as $key => $value) {
                    if ($value instanceof \DateTime) {
                        $data[$key] = $value->format('Y-m-d H:i:s');
                    } elseif (is_string($value)) {
                        $data[$key] = trim($value);
                    }
                }
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $chunk[] = $data;
            }

            if (!empty($chunk)) {
                $updateColumns = array_diff($toImport, is_array($pk) ? $pk : [$pk]);
                $model::upsert($chunk, $pk, $updateColumns);
            }

            $bar->advance(count($rows));
            $offset += $chunkSize;
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$name} importado.");
    }
}
