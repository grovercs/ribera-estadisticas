<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class RiberaProcessSyncRequests extends Command
{
    protected $signature = 'ribera:process-sync-requests
                            {--dataset= : Dataset a procesar (dashboard_snapshot, sales, o cualquiera si no se especifica)}';

    protected $description = 'Procesa solicitudes manuales de sincronización pendientes en Supabase';

    private const TIMEOUT_MINUTES = 60;
    private const SUPPORTED_DATASETS = ['dashboard_snapshot', 'sales'];

    public function handle(): int
    {
        $datasetOption = $this->option('dataset');
        $datasetsToProcess = $datasetOption ? [$datasetOption] : self::SUPPORTED_DATASETS;

        $this->workerLog('info', 'Worker iniciado. Datasets a evaluar: ' . implode(', ', $datasetsToProcess));

        try {
            // 1. Liberar solicitudes atascadas en running por más de 60 minutos
            $this->releaseTimedOutRequests($datasetsToProcess);

            // 2. Buscar la solicitud pending más antigua
            $query = DB::connection('supabase')
                ->table('sync_requests')
                ->where('status', 'pending')
                ->where('source', 'manual')
                ->orderBy('requested_at', 'asc');

            if ($datasetOption) {
                $query->where('dataset', $datasetOption);
            } else {
                $query->whereIn('dataset', $datasetsToProcess);
            }

            $pending = $query->first();

            if (!$pending) {
                $this->workerLog('info', 'No hay solicitudes manuales pendientes.');
                return self::SUCCESS;
            }

            $this->workerLog('info', "Solicitud manual encontrada: [{$pending->id}] dataset: {$pending->dataset}");

            // 3. Procesar según dataset
            if ($pending->dataset === 'dashboard_snapshot') {
                return $this->processDashboardSnapshot($pending);
            }

            if ($pending->dataset === 'sales') {
                return $this->processSales($pending);
            }

            $this->workerLog('warn', "Dataset no soportado: {$pending->dataset}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->workerLog('error', 'Excepción en worker: ' . $e->getMessage());
            $this->error('Excepción en worker: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function processDashboardSnapshot(object $pending): int
    {
        $this->workerLog('info', "Marcando solicitud {$pending->id} como running...");

        // Marcar como running
        DB::connection('supabase')
            ->table('sync_requests')
            ->where('id', $pending->id)
            ->update([
                'status' => 'running',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        try {
            // Ejecutar publicación del snapshot completo (reutiliza buildDashboardData)
            $exitCode = Artisan::call('ribera:publish-dashboard-snapshot', [
                '--year' => 2026,
                '--period' => 'year',
                '--anio-ant' => 'todos',
            ]);

            $output = Artisan::output();
            if ($output) {
                $this->line($output);
            }

            if ($exitCode === self::SUCCESS) {
                DB::connection('supabase')
                    ->table('sync_requests')
                    ->where('id', $pending->id)
                    ->update([
                        'status' => 'success',
                        'finished_at' => now(),
                        'error_message' => null,
                        'updated_at' => now(),
                    ]);

                $this->workerLog('info', "Solicitud {$pending->id} de dashboard_snapshot completada con éxito.");
                return self::SUCCESS;
            }

            DB::connection('supabase')
                ->table('sync_requests')
                ->where('id', $pending->id)
                ->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => 'ribera:publish-dashboard-snapshot retornó código de error ' . $exitCode,
                    'updated_at' => now(),
                ]);

            $this->workerLog('warn', "Solicitud {$pending->id} falló con código {$exitCode}.");
            return $exitCode;
        } catch (\Throwable $e) {
            DB::connection('supabase')
                ->table('sync_requests')
                ->where('id', $pending->id)
                ->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            $this->workerLog('error', "Excepción procesando snapshot para {$pending->id}: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function processSales(object $pending): int
    {
        $this->workerLog('info', "Lanzando quick sync de ventas con lock para {$pending->id}...");

        $exitCode = Artisan::call('ribera:sync-sales-locked', [
            '--source' => 'manual',
            '--request-id' => $pending->id,
            '--quick' => true,
        ]);

        $output = Artisan::output();
        if ($output) {
            $this->line($output);
            $this->workerLog('info', 'Salida del comando de sincronización de ventas capturada.');
        }

        if ($exitCode === self::SUCCESS) {
            $this->workerLog('info', 'Worker de ventas finalizó correctamente.');
        } else {
            $this->workerLog('warn', "Worker de ventas finalizó con código {$exitCode}.");
        }

        return $exitCode;
    }

    private function releaseTimedOutRequests(array $datasets): void
    {
        $timeout = now()->subMinutes(self::TIMEOUT_MINUTES);

        $affected = DB::connection('supabase')
            ->table('sync_requests')
            ->whereIn('dataset', $datasets)
            ->where('status', 'running')
            ->where('started_at', '<', $timeout)
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => 'Timeout: el proceso no finalizó en ' . self::TIMEOUT_MINUTES . ' minutos.',
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $this->workerLog('warn', "{$affected} solicitud(es) marcadas como failed por timeout.");
            $this->warn("{$affected} solicitud(es) marcadas como failed por timeout.");
        }
    }

    private function workerLog(string $level, string $message): void
    {
        $path = storage_path('logs/sync_manual_worker.log');
        $line = sprintf("[%s] [%s] %s\n", now()->format('Y-m-d H:i:s'), strtoupper($level), $message);

        try {
            file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // El fallo de logging nunca debe tumbar el worker. Se envía a stderr.
            fwrite(STDERR, "[LOG FAIL] {$e->getMessage()} | {$line}");
        }
    }
}
