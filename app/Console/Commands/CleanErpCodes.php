<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanErpCodes extends Command
{
    protected $signature = 'app:clean-erp-codes';

    protected $description = 'Limpia espacios en blanco de códigos clave importados del ERP';

    public function handle(): int
    {
        $tables = [
            'erp_products'  => ['cod_articulo', 'cod_familia', 'cod_subfamilia', 'marca', 'cod_barras'],
            'erp_stocks'    => ['cod_articulo', 'cod_almacen'],
            'erp_sale_lines'=> ['cod_articulo'],
            'erp_families'  => ['cod_familia'],
            'erp_subfamilies'=> ['cod_familia', 'cod_subfamilia'],
            'erp_clients'   => ['cod_cliente'],
            'erp_sellers'   => ['cod_vendedor'],
        ];

        foreach ($tables as $table => $columns) {
            $totalTrimmed = 0;
            foreach ($columns as $column) {
                $trimmed = DB::update("UPDATE {$table} SET {$column} = TRIM({$column}) WHERE {$column} != TRIM({$column})");
                if ($trimmed > 0) {
                    $this->info("{$table}.{$column}: {$trimmed} registros limpiados");
                    $totalTrimmed += $trimmed;
                }
            }
            if ($totalTrimmed == 0) {
                $this->info("{$table}: sin cambios");
            }
        }

        $this->info('Limpieza completada.');
        return self::SUCCESS;
    }
}
