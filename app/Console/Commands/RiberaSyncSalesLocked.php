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
                            {--request-id= : UUID de la solicitud en sync_requests (solo source=manual)}
                            {--quick : Si se indica, sincroniza solo las ventas de hoy (period=today)}';

    protected $description = 'Ejecuta la sincronización de sales con lock global y auditoría en sync_requests';

    private const DATASET = 'sales';
    private const LOCK_NAME = 'sync-sales-lock';
    private const LOCK_TTL_SECONDS = 4500; // 75 minutos (full / auto)
    private const LOCK_TTL_QUICK_SECONDS = 300; // 5 minutos (quick manual)

    public function handle(): int
    {
        $source = $this->option('source');
        $requestId = $this->option('request-id');
        $isQuick = (bool) $this->option('quick');

        if (!in_array($source, ['manual', 'auto'], true)) {
            $this->syncLog('error', "Source no válido: {$source}");
            return self::FAILURE;
        }

        if ($source === 'manual' && !$requestId) {
            $this->syncLog('error', 'Se requiere --request-id cuando source=manual');
            return self::FAILURE;
        }

        $modeLabel = $isQuick ? 'quick' : 'full';
        $this->syncLog('info', "[{$source}] Iniciando sincronización de SALES (modo {$modeLabel}) con lock global");

        // 1. Insertar/reclamar fila en sync_requests
        $syncRequestId = $this->acquireSyncRequest($source, $requestId);

        if (!$syncRequestId) {
            $message = 'Colisión lógica: ya existe una sincronización activa para sales o la solicitud fue reclamada por otro worker.';
            $this->syncLog('warn', $message);
            $this->warn($message);
            return self::SUCCESS; // No es un error, es colisión controlada
        }

        $this->syncLog('info', "[{$source}] Solicitud reclamada: {$syncRequestId}");

        // 2. Lock real de ejecución: TTL corto para quick manual, TTL largo para full/auto
        $lockTtl = ($isQuick && $source === 'manual')
            ? self::LOCK_TTL_QUICK_SECONDS
            : self::LOCK_TTL_SECONDS;

        $this->syncLog('info', "[{$source}] Adquiriendo lock con TTL de {$lockTtl} segundos.");

        $lock = Cache::store('file')->lock(self::LOCK_NAME, $lockTtl);

        if (!$lock->get()) {
            $message = 'Lock de ejecución ocupado: otro proceso de sales está corriendo.';
            $this->markFailed($syncRequestId, $message);
            $this->syncLog('warn', "[{$source}] {$message}");
            $this->warn($message);
            return self::SUCCESS;
        }

        $this->syncLog('info', "[{$source}] Lock de ejecución adquirido.");

        $exitCode = self::FAILURE;

        try {
            $this->markRunning($syncRequestId);
            $this->syncLog('info', "[{$source}] Estado cambiado a running.");

            $period = $isQuick ? 'today' : 'current_month';

            $exitCode = Artisan::call('ribera:sync-to-supabase', [
                'dataset' => self::DATASET,
                '--period' => $period,
            ]);

            $output = Artisan::output();

            if ($exitCode === self::SUCCESS) {
                $this->markSuccess($syncRequestId);
                $this->syncLog('info', "[{$source}] Sincronización de SALES completada con éxito.");
            } else {
                $sanitized = $this->sanitizeError($output);
                $this->markFailed($syncRequestId, $sanitized);
                $this->syncLog('error', "[{$source}] Sincronización de SALES falló con código {$exitCode}: {$sanitized}");
            }
        } catch (\Throwable $e) {
            $sanitized = $this->sanitizeError($e->getMessage());
            $this->markFailed($syncRequestId, $sanitized);
            $this->syncLog('error', "[{$source}] Excepción durante sincronización: {$sanitized}");
        } finally {
            $lock->release();
            $this->syncLog('info', "[{$source}] Lock liberado.");
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

    private function syncLog(string $level, string $message): void
    {
        $path = storage_path('logs/sync_sales_locked.log');
        $line = sprintf("[%s] [%s] %s\n", now()->format('Y-m-d H:i:s'), strtoupper($level), $message);
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
