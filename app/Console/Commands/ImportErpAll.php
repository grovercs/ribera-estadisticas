<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportErpAll extends Command
{
    protected $signature = 'app:import-erp-all';

    protected $description = 'Importa todas las tablas del ERP a MySQL';

    public function handle(): int
    {
        $commands = [
            'app:import-erp-families',
            'app:import-erp-subfamilies',
            'app:import-erp-warehouses',
            'app:import-erp-sellers',
            'app:import-erp-clients',
            'app:import-erp-products',
            'app:import-erp-sales',
            'app:import-erp-sale-lines',
            'app:import-erp-invoices',
            'app:import-erp-stock',
            'app:import-erp-stock-movements',
        ];

        foreach ($commands as $cmd) {
            $this->info("Ejecutando {$cmd}...");
            $result = $this->call($cmd);
            if ($result !== self::SUCCESS) {
                $this->error("{$cmd} falló. Abortando.");
                return $result;
            }
            $this->newLine();
        }

        $this->info('Importación completa.');
        return self::SUCCESS;
    }
}
