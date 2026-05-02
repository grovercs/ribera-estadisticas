<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ErpConfigGenerate extends Command
{
    protected $signature = 'app:erp-config-generate';

    protected $description = 'Genera el archivo storage/app/erp-connection.json de ejemplo';

    public function handle(): int
    {
        $path = storage_path('app/erp-connection.json');

        if (file_exists($path)) {
            $this->warn("Ya existe {$path}. No se sobreescribió.");
            $this->info('Edítalo manualmente con los datos de tu servidor ERP.');
            return self::SUCCESS;
        }

        $template = [
            'driver' => 'sqlsrv',
            'host' => '192.168.1.100',
            'port' => 1433,
            'database' => 'ControlIntegral',
            'username' => 'sa',
            'password' => '',
            'charset' => 'utf8',
            'encrypt' => 'no',
            'trust_server_certificate' => true,
        ];

        file_put_contents($path, json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Creado {$path}");
        $this->info('Edítalo con los datos reales de tu servidor ERP.');

        return self::SUCCESS;
    }
}
