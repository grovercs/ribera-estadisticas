<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ErpConfigTest extends Command
{
    protected $signature = 'app:erp-config-test';

    protected $description = 'Prueba la conexión al ERP (directa o por archivo externo)';

    public function handle(): int
    {
        $this->info('Conexión ERP activa:');
        $this->info('Driver: ' . config('database.connections.erp.driver'));
        $this->info('Host: ' . config('database.connections.erp.host'));
        $this->info('Base de datos: ' . config('database.connections.erp.database'));

        $path = storage_path('app/erp-connection.json');
        if (file_exists($path)) {
            $this->info('Usando configuración externa: ' . $path);
        } else {
            $this->info('Usando variables de entorno (.env)');
        }

        if (!in_array('sqlsrv', \PDO::getAvailableDrivers(), true)) {
            $this->error('El driver pdo_sqlsrv no está instalado.');
            return self::FAILURE;
        }

        try {
            DB::connection('erp')->getPdo();
            $this->info('Conexión exitosa al ERP.');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error de conexión: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
