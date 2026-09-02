<?php

namespace App\Console\Commands;

use App\Http\Controllers\StoreDashboardController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RiberaPublishDashboardSnapshot extends Command
{
    protected $signature = 'ribera:publish-dashboard-snapshot
                            {--year= : Año a calcular (por defecto el año actual)}
                            {--period=year : Período de márgenes (hoy, quincena, year, etc.)}
                            {--anio-ant=todos : Años atrás para Anteriores (1, 2, 3, 5, 10, todos)}
                            {--scope=store_dashboard : Ámbito del snapshot (por defecto store_dashboard)}';

    protected $description = 'Calcula el dashboard completo desde el ERP local y publica un snapshot único en Supabase';

    public function handle(): int
    {
        $scope = (string) ($this->option('scope') ?: 'store_dashboard');
        $year = (int) ($this->option('year') ?: date('Y'));
        $periodo = (string) ($this->option('period') ?: 'year');
        $anioAnt = (string) ($this->option('anio-ant') ?: 'todos');

        $this->info("=== PUBLICANDO SNAPSHOT DEL DASHBOARD EN SUPABASE ===");
        $this->info("Ámbito:     {$scope}");
        $this->info("Año:        {$year}");
        $this->info("Período:    {$periodo}");
        $this->info("Anio Ant:   {$anioAnt}");

        $startTime = microtime(true);

        try {
            // 1. CÁLCULO LOCAL: Reutilizar exactamente la lógica validada del controlador
            $this->info("Calculando dashboard desde SQL Server ERP (lógica local validada)...");
            $controller = app(StoreDashboardController::class);
            $data = $controller->buildDashboardData($year, $anioAnt, $periodo);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $generatedAtIso = now()->toISOString();
            $generatedAtDb = now();

            $this->info("✓ Cálculo completado en {$durationMs} ms.");

            // 2. CONSTRUCCIÓN DEL PAYLOAD CANÓNICO (DashboardCommonPayload)
            $payload = [
                'mode' => 'supabase',
                'source' => 'erp_integral_snapshot',
                'generated_at' => $generatedAtIso,
                'execution_time_ms' => $durationMs,
                'reference_date' => $data['ultimoDiaVentas'] ?? date('Y-m-d'),
                'ultimo_dia_ventas' => $data['ultimoDiaVentas'] ?? null,
                'penultimo_dia_ventas' => $data['penultimoDiaVentas'] ?? null,
                'periodo' => $periodo,
                'year' => $year,
                'anio_anteriores' => $anioAnt,
                'sales' => $data['sales_data'] ?? [],
                'sales_periods' => $data['sales_periods'] ?? [],
                'margins' => $data['margins_data'] ?? [],
                'impagados' => $data['impagados_data'] ?? [],
                'albaranes' => $data['albaranes_data'] ?? [],
                'purchases_periods' => $data['purchases_periods'] ?? [],
                'payables' => $data['payables_data'] ?? ['periodos' => [], 'total_importe' => 0, 'total_ops' => 0],
                // Datos adicionales del controlador para paridad completa
                'tiendas' => $data['tiendas'] ?? [],
                'totales' => $data['totales'] ?? [],
                'sparklines' => $data['sparklines'] ?? [],
                'facturasCompras' => $data['facturasCompras'] ?? [],
                'pagosPendientes' => $data['pagosPendientes'] ?? [],
                'ticketMedio' => $data['ticketMedio'] ?? 0,
                'ticketMedioAnt' => $data['ticketMedioAnt'] ?? 0,
                'active_sync' => null,
            ];

            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payloadJson === false) {
                throw new \RuntimeException('Error serializando el payload a JSON: ' . json_last_error_msg());
            }

            $sizeBytes = strlen($payloadJson);
            $sizeKb = round($sizeBytes / 1024, 2);

            // 3. GUARDAR EN SUPABASE: UPSERT atómico en dashboard_snapshots
            $this->info("Guardando snapshot en Supabase (tabla dashboard_snapshots)...");
            
            DB::connection('supabase')->table('dashboard_snapshots')->upsert([
                'scope' => $scope,
                'year' => $year,
                'periodo' => $periodo,
                'anio_ant' => $anioAnt,
                'payload' => $payloadJson,
                'generated_at' => $generatedAtDb,
                'execution_time_ms' => $durationMs,
                'source' => 'erp_integral_snapshot',
                'version' => 1,
                'updated_at' => now(),
            ], ['scope', 'year', 'periodo', 'anio_ant'], [
                'payload',
                'generated_at',
                'execution_time_ms',
                'source',
                'version',
                'updated_at',
            ]);

            $this->info("✓ Snapshot publicado con éxito en Supabase.");
            $this->table(
                ['Métrica / Parámetro', 'Valor'],
                [
                    ['Ámbito (scope)', $scope],
                    ['Año (year)', $year],
                    ['Período (periodo)', $periodo],
                    ['Anteriores (anio_ant)', $anioAnt],
                    ['Generated At', $generatedAtIso],
                    ['Tiempo de Cómputo', "{$durationMs} ms"],
                    ['Tamaño del Payload', "{$sizeKb} KB ({$sizeBytes} bytes)"],
                    ['Resultado UPSERT', 'Guardado / Actualizado correctamente'],
                ]
            );

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $this->error("✗ Error generando/publicando snapshot del dashboard tras {$durationMs} ms:");
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
