<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class RiberaSyncSalesLocked extends Command
{
    protected $signature = 'ribera:sync-sales-locked
                            {--source=manual : Origen de la sincronización: manual o auto}
                            {--request-id= : UUID de la solicitud en sync_requests (solo source=manual)}';

    protected $description = 'Ejecuta la sincronización de sales con lock global y auditoría en sync_requests';

    private const DATASET = 'sales';
    private const LOCK_NAME = 'sync-sales-lock';
    private const LOCK_TTL_SECONDS = 4500; // 75 minutos

    public function handle(): int
    {
        $source = $this->option('source');
        $requestId = $this->option('request-id');

        if (!in_array($source, ['manual', 'auto'], true)) {
            $this->error("Source no válido: {$source}");
            return self::FAILURE;
        }

        if ($source === 'manual' && !$requestId) {
            $this->error('Se requiere --request-id cuando source=manual');
            return self::FAILURE;
        }

        $this->info("[{$source}] Iniciando sincronización de SALES con lock global");

        // 1. Insertar/reclamar fila en sync_requests
        $syncRequestId = $this->acquireSyncRequest($source, $requestId);

        if (!$syncRequestId) {
            $this->warn('Ya existe una sincronización activa para sales (lock lógico de sync_requests).');
            return self::SUCCESS; // No es un error, es colisión controlada
        }

        // 2. Lock real de ejecución
        $lock = Cache::store('file')->lock(self::LOCK_NAME, self::LOCK_TTL_SECONDS);

        if (!$lock->get()) {
            $this->markFailed($syncRequestId, 'Lock de ejecución ocupado: otro proceso de sales está corriendo.');
            $this->warn('Lock de ejecución ocupado. No se inicia la sincronización.');
            return self::SUCCESS;
        }

        $exitCode = self::FAILURE;

        try {
            $this->markRunning($syncRequestId);

            $exitCode = Artisan::call('ribera:sync-to-supabase', [
                'dataset' => self::DATASET,
                '--period' => 'current_month',
            ]);

            $output = Artisan::output();

            if ($exitCode === self::SUCCESS) {
                $this->markSuccess($syncRequestId);
                $this->info("[{$source}] Sincronización de SALES completada con éxito.");
            } else {
                $this->markFailed($syncRequestId, $this->sanitizeError($output));
                $this->error("[{$source}] Sincronización de SALES falló con código {$exitCode}.");
            }
        } catch (\Throwable $e) {
            $this->markFailed($syncRequestId, $this->sanitizeError($e->getMessage()));
            $this->error("[{$source}] Excepción durante sincronización: " . $e->getMessage());
        } finally {
            $lock->release();
            $this->info("[{$source}] Lock liberado.");
        }

        return $exitCode;
    }

    private function acquireSyncRequest(string $source, ?string $requestId): ?string
    {
        if ($source === 'manual' && $requestId) {
            // Reclamar la solicitud pending del usuario de forma atómica
            $updated = DB::connection('supabase')
                ->table('sync_requests')
                ->where('id', $requestId)
                ->where('dataset', self::DATASET)
                ->where('status', 'pending')
                ->update([
                    'status' => 'running',
                    'started_at' => now(),
                    'updated_at' => now(),
                ]);

            return $updated > 0 ? $requestId : null;
        }

        // Automático: insertar fila running directamente
        // Si ya hay una activa, el índice único parcial rechazará el insert
        try {
            $id = (string) \Illuminate\Support\Str::uuid();

            DB::connection('supabase')->table('sync_requests')->insert([
                'id' => $id,
                'dataset' => self::DATASET,
                'status' => 'running',
                'requested_by' => null,
                'source' => $source,
                'requested_at' => now(),
                'started_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        } catch (\Illuminate\Database\QueryException $e) {
            // Probablemente violación del índice único parcial
            return null;
        }
    }

    private function markRunning(string $syncRequestId): void
    {
        DB::connection('supabase')
            ->table('sync_requests')
            ->where('id', $syncRequestId)
            ->update([
                'status' => 'running',
                'started_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function markSuccess(string $syncRequestId): void
    {
        DB::connection('supabase')
            ->table('sync_requests')
            ->where('id', $syncRequestId)
            ->update([
                'status' => 'success',
                'finished_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);
    }

    private function markFailed(string $syncRequestId, string $message): void
    {
        DB::connection('supabase')
            ->table('sync_requests')
            ->where('id', $syncRequestId)
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $this->sanitizeError($message),
                'updated_at' => now(),
            ]);
    }

    private function sanitizeError(string $message): string
    {
        $clean = strip_tags($message);
        $clean = str_replace(["\r", "\n"], ' ', $clean);
        $clean = trim($clean);

        return substr($clean, 0, 500);
    }
}
