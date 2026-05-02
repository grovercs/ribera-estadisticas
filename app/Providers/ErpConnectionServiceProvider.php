<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;

class ErpConnectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeErpConfig();
    }

    public function boot(): void
    {
        //
    }

    private function mergeErpConfig(): void
    {
        $path = storage_path('app/erp-connection.json');

        if (!file_exists($path)) {
            return;
        }

        $json = file_get_contents($path);
        $config = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            return;
        }

        $defaults = config('database.connections.erp', []);

        $overrides = array_merge($defaults, [
            'driver' => $config['driver'] ?? $defaults['driver'] ?? 'sqlsrv',
            'host' => $config['host'] ?? $defaults['host'] ?? 'localhost',
            'port' => $config['port'] ?? $defaults['port'] ?? 1433,
            'database' => $config['database'] ?? $defaults['database'] ?? '',
            'username' => $config['username'] ?? $defaults['username'] ?? '',
            'password' => $config['password'] ?? $defaults['password'] ?? '',
            'charset' => $config['charset'] ?? $defaults['charset'] ?? 'utf8',
            'encrypt' => $config['encrypt'] ?? $defaults['encrypt'] ?? 'no',
            'trust_server_certificate' => $config['trust_server_certificate'] ?? $defaults['trust_server_certificate'] ?? true,
        ]);

        config(['database.connections.erp' => $overrides]);
    }
}
