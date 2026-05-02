<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

abstract class ImportErpCommand extends Command
{
    /**
     * Laravel Eloquent model class (e.g. App\Models\ErpClient::class)
     */
    abstract protected function getModelClass(): string;

    /**
     * ERP SQL Server table name (e.g. 'clientes')
     */
    abstract protected function getErpTable(): string;

    /**
     * Column mapping: ['erp_column' => 'mysql_column', ...]
     */
    abstract protected function getMapping(): array;

    /**
     * MySQL unique columns for upsert (e.g. ['cod_cliente'])
     */
    abstract protected function getUniqueBy(): array;

    protected function getChunkSize(): int
    {
        return 1000;
    }

    protected function getErpConnection(): string
    {
        return 'erp';
    }

    public function handle(): int
    {
        $modelClass = $this->getModelClass();
        $erpTable = $this->getErpTable();
        $mapping = $this->getMapping();
        $uniqueBy = $this->getUniqueBy();
        $chunkSize = $this->getChunkSize();
        $connection = $this->getErpConnection();

        if (!in_array('sqlsrv', \PDO::getAvailableDrivers(), true)) {
            $this->error('El driver pdo_sqlsrv no está instalado. No se puede conectar al ERP.');
            $this->error('Descarga los drivers de Microsoft para PHP 8.4 desde:');
            $this->error('https://github.com/Microsoft/msphpsql/releases');
            return self::FAILURE;
        }

        $erpColumns = array_keys($mapping);
        $mysqlColumns = array_values($mapping);

        $total = DB::connection($connection)->table($erpTable)->count();

        if ($total === 0) {
            $this->warn("La tabla {$erpTable} del ERP está vacía.");
            return self::SUCCESS;
        }

        $this->info("Importando {$total} registros de {$erpTable}...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $now = now();

        DB::connection($connection)
            ->table($erpTable)
            ->select($erpColumns)
            ->orderBy($erpColumns[0])
            ->chunk($chunkSize, function ($rows) use ($modelClass, $mapping, $uniqueBy, $now, $bar, &$imported) {
                $data = [];

                foreach ($rows as $row) {
                    $item = [];
                    foreach ($mapping as $erpCol => $mysqlCol) {
                        $item[$mysqlCol] = $row->{$erpCol} ?? null;
                    }
                    $item['created_at'] = $now;
                    $item['updated_at'] = $now;
                    $data[] = $item;
                }

                if (!empty($data)) {
                    $updateColumns = array_merge(array_values($mapping), ['updated_at']);
                    $modelClass::upsert($data, $uniqueBy, $updateColumns);
                    $imported += count($data);
                    $bar->advance(count($data));
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Importados {$imported} registros en " . $modelClass::getTable() . ".");

        return self::SUCCESS;
    }
}
