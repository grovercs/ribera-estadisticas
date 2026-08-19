<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class RiberaProcessSyncRequests extends Command
{
    protected $signature = 'ribera:process-sync-requests
                            {--dataset=sales : Dataset a procesar}';

    protected $description = 'Procesa solicitudes manuales de sincronización pendientes en Supabase';

    private const TIMEOUT_MINUTES = 60;

    public function handle(): int
    {
        $dataset = $this->option('dataset');

        $this->workerLog('info', "Worker iniciado para dataset: {$dataset}");

        try {
            // 1. Liberar solicitudes atascadas en running por más de 60 minutos
            $this->releaseTimedOutRequests($dataset);

            // 2. Buscar la solicitud pending más antigua
            $pending = DB::connection('supabase')
                ->table('sync_requests')
                ->where('dataset', $dataset)
                ->where('status', 'pending')
                ->where('source', 'manual')
                ->orderBy('requested_at', 'asc')
                ->first();

            if (!$pending) {
                $this->workerLog('info', 'No hay solicitudes manuales pendientes.');
                return self::SUCCESS;
            }

            $this->workerLog('info', "Solicitud manual encontrada: {$pending->id}. Lanzando quick sync con lock...");

            // 3. Ejecutar el wrapper con lock global en modo quick (ventas de hoy)
            $exitCode = Artisan::call('ribera:sync-sales-locked', [
                '--source' => 'manual',
                '--request-id' => $pending->id,
                '--quick' => true,
            ]);

            $output = Artisan::output();
            if ($output) {
                $this->line($output);
                $this->workerLog('info', 'Salida del comando de sincronización capturada.');
            }

            if ($exitCode === self::SUCCESS) {
                $this->workerLog('info', 'Worker finalizó correctamente.');
            } else {
                $this->workerLog('warn', "Worker finalizó con código de salida {$exitCode}.");
            }

            return $exitCode;
        } catch (\Throwable $e) {
            $this->workerLog('error', 'Excepción en worker: ' . $e->getMessage());
            $this->error('Excepción en worker: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function releaseTimedOutRequests(string $dataset): void
    {
        $timeout = now()->subMinutes(self::TIMEOUT_MINUTES);

        $affected = DB::connection('supabase')
            ->table('sync_requests')
            ->where('dataset', $dataset)
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
