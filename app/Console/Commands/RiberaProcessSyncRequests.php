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

        $this->info("Worker de sync_requests iniciado para dataset: {$dataset}");

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
            $this->info('No hay solicitudes manuales pendientes.');
            return self::SUCCESS;
        }

        $this->info("Solicitud manual encontrada: {$pending->id}. Lanzando sync con lock...");

        // 3. Ejecutar el wrapper con lock global
        $exitCode = Artisan::call('ribera:sync-sales-locked', [
            '--source' => 'manual',
            '--request-id' => $pending->id,
        ]);

        $output = Artisan::output();
        if ($output) {
            $this->line($output);
        }

        if ($exitCode === self::SUCCESS) {
            $this->info('Worker finalizó correctamente.');
        } else {
            $this->warn('Worker finalizó con advertencias o error.');
        }

        return $exitCode;
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
            $this->warn("{$affected} solicitud(es) marcadas como failed por timeout.");
        }
    }
}
