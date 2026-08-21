<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RiberaSyncToSupabase extends Command
{
    protected $signature = 'ribera:sync-to-supabase
                            {dataset? : Dataset específico (kpis, warehouses, sellers, stock, clients, suppliers, monthly_history, sales, purchases, receivables, historical)}
                            {--period= : Período personalizado para ventas. Opciones: test_1month, current_month, today}';

    protected $description = 'Sincroniza datos del ERP SQL Server local hacia la base de datos de reporting en Supabase';

    public function handle(): int
    {
        $dataset = $this->argument('dataset');
        $availableDatasets = [
            'kpis',
            'warehouses',
            'sellers',
            'stock',
            'clients',
            'suppliers',
            'monthly_history',
            'sales',
            'purchases',
            'receivables',
            'historical'
        ];

        if ($dataset && !in_array($dataset, $availableDatasets, true)) {
            $this->error("Dataset no reconocido. Disponibles: " . implode(', ', $availableDatasets));
            return self::INVALID;
        }

        // purchases requiere un periodo explícito y no debe alterar el recorrido
        // histórico de una ejecución global existente.
        $datasetsToSync = $dataset
            ? [$dataset]
            : array_values(array_filter($availableDatasets, fn ($name) => $name !== 'purchases'));

        $this->info("=== INICIANDO PROCESO DE SINCRONIZACIÓN RIBERA ESTADÍSTICAS ===");

        foreach ($datasetsToSync as $ds) {
            $this->newLine();
            $this->info("--------------------------------------------------");
            $this->info("Sincronizando dataset: {$ds}...");
            $this->info("--------------------------------------------------");

            try {
                $runId = $this->startSyncRun($ds);

                $recordsProcessed = match ($ds) {
                    'kpis' => $this->syncKpis($runId),
                    'warehouses' => $this->syncWarehouses($runId),
                    'sellers' => $this->syncSellers($runId),
                    'stock' => $this->syncStock($runId),
                    'clients' => $this->syncClients($runId),
                    'suppliers' => $this->syncSuppliers($runId),
                    'monthly_history' => $this->syncMonthlyHistory($runId),
                    'sales' => $this->syncSales($runId),
                    'purchases' => $this->syncPurchases($runId),
                    'receivables' => $this->syncReceivables($runId),
                    'historical' => $this->syncHistorical($runId),
                    default => 0
                };

                $this->completeSyncRun($runId, 'success', $recordsProcessed);
                $this->info("✓ Dataset {$ds} sincronizado correctamente (Registros: {$recordsProcessed}).");

            } catch (\Exception $e) {
                $this->error("✗ Error sincronizando {$ds}: " . $e->getMessage());
                if (isset($runId)) {
                    $this->completeSyncRun($runId, 'failed', 0, $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info("Proceso de sincronización finalizado.");
        return self::SUCCESS;
    }

    private function startSyncRun(string $dataset): string
    {
        $runId = (string) Str::uuid();

        DB::connection('supabase')->table('sync_runs')->insert([
            'id' => $runId,
            'dataset' => $dataset,
            'started_at' => now(),
            'status' => 'running',
            'records_processed' => 0,
            'error_message' => null
        ]);

        return $runId;
    }

    private function completeSyncRun(string $runId, string $status, int $recordsProcessed, ?string $errorMessage = null): void
    {
        DB::connection('supabase')->table('sync_runs')->where('id', $runId)->update([
            'completed_at' => now(),
            'status' => $status,
            'records_processed' => $recordsProcessed,
            'error_message' => $errorMessage ? substr($errorMessage, 0, 1000) : null
        ]);
    }

    /**
     * Helper para parsear fechas locales de Madrid y convertirlas a UTC para Supabase.
     * Usar para marcas temporales reales (modificaciones, altas).
     */
    private function parseDateToUtc(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }
        try {
            // SQL Server retorna fechas sin zona horaria. Las asumimos de Europa/Madrid
            return Carbon::createFromFormat('Y-m-d H:i:s.u', $dateStr, 'Europe/Madrid')
                ->setTimezone('UTC')
                ->toDateTimeString();
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', $dateStr, 'Europe/Madrid')
                    ->setTimezone('UTC')
                    ->toDateTimeString();
            } catch (\Exception $ex) {
                return Carbon::parse($dateStr, 'Europe/Madrid')
                    ->setTimezone('UTC')
                    ->toDateTimeString();
            }
        }
    }

    /**
     * Helper para fechas de negocio (ej. fecha_venta) que conceptualmente son locales.
     * Se almacenan como TIMESTAMP WITHOUT TIME ZONE para que dia/mes/año coincidan
     * con las consultas del ERP original.
     */
    private function parseLocalDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }
        // SQL Server devuelve 'Y-m-d H:i:s.u' o 'Y-m-d H:i:s'. Nos quedamos con la
        // parte local sin desplazar la zona horaria.
        $clean = substr($dateStr, 0, 19);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $clean)) {
            try {
                return Carbon::parse($dateStr, 'Europe/Madrid')->toDateTimeString();
            } catch (\Exception $e) {
                return null;
            }
        }
        return $clean;
    }

    /**
     * Procesar registros eliminados físicamente en el ERP
     */
    private function syncDeletedRecords(string $datasetName, string $erpTableName, string $supabaseTableName, string $pkFieldInSupabase): int
    {
        $this->info("Buscando registros eliminados físicamente en ERP...");
        
        $stateRow = DB::connection('supabase')
            ->table('sync_state')
            ->where('dataset', $datasetName)
            ->first();

        $lastSuccess = $stateRow ? $stateRow->last_success_at : null;
        if (!$lastSuccess) {
            return 0; // Primera ejecución, no hay borrados incrementales
        }

        // Ventana de seguridad de 24 horas para asegurar consistencia
        $checkTime = date('Ymd H:i:s', strtotime($lastSuccess . ' -24 hours'));
        
        $deletions = DB::connection('erp')->select("
            SELECT codigo2, codigo5, codigo6, codigo7
            FROM registros_eliminados
            WHERE tabla = ? AND fecha_eliminacion >= ?
        ", [$erpTableName, $checkTime]);

        if (empty($deletions)) {
            return 0;
        }

        $deletedCount = 0;
        foreach ($deletions as $del) {
            if ($supabaseTableName === 'sales_headers') {
                // Clave compuesta transaccional
                $codVenta = trim($del->codigo2);
                $tipoVenta = (int)$del->codigo5;
                $codEmpresa = (int)$del->codigo6;
                $codCaja = (int)$del->codigo7;

                // Borrar líneas asociadas primero (evitar huérfanos)
                DB::connection('supabase')->table('sales_lines')
                    ->where('cod_venta', $codVenta)
                    ->where('tipo_venta', $tipoVenta)
                    ->where('cod_empresa', $codEmpresa)
                    ->where('cod_caja', $codCaja)
                    ->delete();

                // Borrar cabecera
                DB::connection('supabase')->table('sales_headers')
                    ->where('cod_venta', $codVenta)
                    ->where('tipo_venta', $tipoVenta)
                    ->where('cod_empresa', $codEmpresa)
                    ->where('cod_caja', $codCaja)
                    ->delete();

                $deletedCount++;
            } else {
                // Clave única directa (clientes / proveedores)
                $pkValue = trim($del->codigo2);
                DB::connection('supabase')->table($supabaseTableName)->where($pkFieldInSupabase, $pkValue)->delete();
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->warn("! Se eliminaron {$deletedCount} registros en Supabase correspondientes a borrados físicos en el ERP.");
        }

        return $deletedCount;
    }

    /**
     * 1. stats_kpis (SNAPSHOT)
     */
    private function syncKpis(string $runId): int
    {
        $batchId = (string) Str::uuid();
        $currentYear = (int) date('Y');

        $erpRow = DB::connection('erp')->select("
            SELECT
                SUM(importe_impuestos) as total_sales,
                COUNT(*) as total_orders,
                AVG(importe_impuestos) as avg_ticket,
                SUM(importe_pendiente) as pending_amount,
                COUNT(DISTINCT cod_cliente) as unique_clients
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) = {$currentYear}
                AND tipo_venta IN (2, 4, 5)
                AND ISNULL(anulada, '') <> 'S'
        ")[0] ?? null;

        if (!$erpRow) {
            throw new \Exception("No se pudieron obtener datos del ERP para los KPIs.");
        }

        $erpCostRow = DB::connection('erp')->select("
            SELECT
                SUM(l.precio_coste * l.cantidad) as total_cost,
                SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            WHERE YEAR(v.fecha_venta) = {$currentYear}
                AND v.tipo_venta IN (2, 4, 5)
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL
                AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                AND l.precio_coste IS NOT NULL
                AND l.precio_coste > 0
                AND l.precio_coste < 100000
                AND l.cantidad > 0
        ")[0] ?? null;

        DB::connection('supabase')->table('stats_kpis')->insert([
            'batch_id' => $batchId,
            'period_key' => 'year_actual',
            'total_sales' => (float)($erpRow->total_sales ?? 0),
            'total_orders' => (int)($erpRow->total_orders ?? 0),
            'avg_ticket' => (float)($erpRow->avg_ticket ?? 0),
            'pending_amount' => (float)($erpRow->pending_amount ?? 0),
            'unique_clients' => (int)($erpRow->unique_clients ?? 0),
            'total_cost' => (float)($erpCostRow->total_cost ?? 0),
            'gross_profit' => (float)($erpCostRow->gross_profit ?? 0),
            'updated_at' => now()
        ]);

        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'kpis')->first();
        $lotePrevio = $stateRow ? $stateRow->active_batch_id : null;

        DB::connection('supabase')->transaction(function () use ($batchId) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'kpis',
                'active_batch_id' => $batchId,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null
            ], ['dataset'], ['active_batch_id', 'last_success_at', 'last_run_status', 'last_error_message']);
        });

        if ($lotePrevio) {
            DB::connection('supabase')->table('stats_kpis')
                ->where('batch_id', '!=', $batchId)
                ->where('batch_id', '!=', $lotePrevio)
                ->delete();
        }

        return 1;
    }

    /**
     * 2. stats_warehouses (SNAPSHOT)
     */
    private function syncWarehouses(string $runId): int
    {
        $batchId = (string) Str::uuid();
        $currentYear = (int) date('Y');

        $rows = DB::connection('erp')->select("
            SELECT
                cod_almacen,
                COUNT(*) as orders_count,
                SUM(importe_impuestos) as total_sales
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) = {$currentYear}
                AND tipo_venta IN (2, 4, 5)
                AND ISNULL(anulada, '') <> 'S'
                AND cod_almacen IS NOT NULL AND cod_almacen <> ''
            GROUP BY cod_almacen
        ");

        if (empty($rows)) {
            return 0;
        }

        $dataToInsert = [];
        foreach ($rows as $row) {
            $dataToInsert[] = [
                'batch_id' => $batchId,
                'period_key' => 'year_actual',
                'cod_almacen' => trim($row->cod_almacen),
                'orders_count' => (int)$row->orders_count,
                'total_sales' => (float)$row->total_sales
            ];
        }

        DB::connection('supabase')->table('stats_warehouses')->insert($dataToInsert);

        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'warehouses')->first();
        $lotePrevio = $stateRow ? $stateRow->active_batch_id : null;

        DB::connection('supabase')->transaction(function () use ($batchId) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'warehouses',
                'active_batch_id' => $batchId,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null
            ], ['dataset'], ['active_batch_id', 'last_success_at', 'last_run_status', 'last_error_message']);
        });

        if ($lotePrevio) {
            DB::connection('supabase')->table('stats_warehouses')
                ->where('batch_id', '!=', $batchId)
                ->where('batch_id', '!=', $lotePrevio)
                ->delete();
        }

        return count($dataToInsert);
    }

    /**
     * 3. stats_sellers (SNAPSHOT)
     */
    private function syncSellers(string $runId): int
    {
        $batchId = (string) Str::uuid();
        $currentYear = (int) date('Y');

        $rows = DB::connection('erp')->select("
            SELECT
                cod_vendedor,
                MAX(nombre_vendedor) as nombre_vendedor,
                COUNT(*) as orders_count,
                SUM(importe_impuestos) as total_sales
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) = {$currentYear}
                AND tipo_venta IN (2, 4, 5)
                AND ISNULL(anulada, '') <> 'S'
                AND cod_vendedor IS NOT NULL AND cod_vendedor <> ''
            GROUP BY cod_vendedor
        ");

        if (empty($rows)) {
            return 0;
        }

        $dataToInsert = [];
        foreach ($rows as $row) {
            $dataToInsert[] = [
                'batch_id' => $batchId,
                'period_key' => 'year_actual',
                'cod_vendedor' => trim($row->cod_vendedor),
                'nombre_vendedor' => $row->nombre_vendedor ? trim($row->nombre_vendedor) : null,
                'orders_count' => (int)$row->orders_count,
                'total_sales' => (float)$row->total_sales
            ];
        }

        DB::connection('supabase')->table('stats_sellers')->insert($dataToInsert);

        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'sellers')->first();
        $lotePrevio = $stateRow ? $stateRow->active_batch_id : null;

        DB::connection('supabase')->transaction(function () use ($batchId) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'sellers',
                'active_batch_id' => $batchId,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null
            ], ['dataset'], ['active_batch_id', 'last_success_at', 'last_run_status', 'last_error_message']);
        });

        if ($lotePrevio) {
            DB::connection('supabase')->table('stats_sellers')
                ->where('batch_id', '!=', $batchId)
                ->where('batch_id', '!=', $lotePrevio)
                ->delete();
        }

        return count($dataToInsert);
    }

    /**
     * 4. products_stock (SNAPSHOT)
     */
    private function syncStock(string $runId): int
    {
        ini_set('memory_limit', '512M');
        $batchId = (string) Str::uuid();

        $this->info("Consultando artículos y stock desde SQL Server...");
        $db = DB::connection('erp');
        $pdo = $db->getPdo();
        $stmt = $pdo->prepare("
            SELECT
                a.cod_articulo,
                a.descripcion_web as descripcion,
                a.marca,
                a.cod_familia,
                a.cod_subfamilia,
                ISNULL(s.existencias, 0) as stock_total,
                ISNULL(s.minimos_sum, 0) as stock_minimo,
                ISNULL(a.precio_coste, 0) as precio_coste,
                ISNULL(a.precio_venta_publico, 0) as precio_venta
            FROM articulos a
            INNER JOIN (
                SELECT cod_articulo, SUM(existencias) as existencias, SUM(minimos) as minimos_sum
                FROM stocks
                GROUP BY cod_articulo
                HAVING SUM(existencias) > 0 OR SUM(minimos) > 0
            ) s ON s.cod_articulo = a.cod_articulo
            WHERE (a.fecha_baja IS NULL OR a.fecha_baja > GETDATE()) 
                AND a.cod_articulo IS NOT NULL AND a.cod_articulo <> ''
        ");
        $stmt->execute();

        $chunk = [];
        $insertedCount = 0;

        while ($rowObj = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $chunk[] = [
                'batch_id' => $batchId,
                'cod_articulo' => trim($rowObj->cod_articulo),
                'descripcion' => $rowObj->descripcion ? trim($rowObj->descripcion) : null,
                'marca' => $rowObj->marca ? trim($rowObj->marca) : null,
                'cod_familia' => $rowObj->cod_familia ? trim($rowObj->cod_familia) : null,
                'cod_subfamilia' => $rowObj->cod_subfamilia ? trim($rowObj->cod_subfamilia) : null,
                'stock_total' => (float)($rowObj->stock_total ?? 0),
                'stock_minimo' => (float)($rowObj->stock_minimo ?? 0),
                'precio_coste' => (float)($rowObj->precio_coste ?? 0),
                'precio_venta' => (float)($rowObj->precio_venta ?? 0),
                'updated_at' => now()
            ];

            if (count($chunk) >= 200) {
                DB::connection('supabase')->table('products_stock')->insert($chunk);
                $insertedCount += count($chunk);
                $chunk = [];
            }
        }

        if (count($chunk) > 0) {
            DB::connection('supabase')->table('products_stock')->insert($chunk);
            $insertedCount += count($chunk);
        }

        if ($insertedCount === 0) {
            return 0;
        }

        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'stock')->first();
        $lotePrevio = $stateRow ? $stateRow->active_batch_id : null;

        DB::connection('supabase')->transaction(function () use ($batchId) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'stock',
                'active_batch_id' => $batchId,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null
            ], ['dataset'], ['active_batch_id', 'last_success_at', 'last_run_status', 'last_error_message']);
        });

        if ($lotePrevio) {
            DB::connection('supabase')->table('products_stock')
                ->where('batch_id', '!=', $batchId)
                ->where('batch_id', '!=', $lotePrevio)
                ->delete();
        }

        return $insertedCount;
    }

    /**
     * 5. clients (INCREMENTAL)
     */
    private function syncClients(string $runId): int
    {
        // 1. Procesar primero los borrados físicos
        $this->syncDeletedRecords('clients', 'clientes', 'clients_reporting', 'cod_cliente');

        // 2. Obtener la última sincronización
        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'clients')->first();
        $lastSuccess = $stateRow ? $stateRow->last_success_at : null;

        if ($lastSuccess) {
            $checkTime = date('Ymd H:i:s', strtotime($lastSuccess . ' -5 minutes'));
            $this->info("Consultando clientes modificados desde {$checkTime}...");
            $rows = DB::connection('erp')->select("
                SELECT 
                    cod_cliente, razon_social, cif, poblacion, provincia, telefono, e_mail, limite_credito, cod_vendedor, fecha_modificacion
                FROM clientes
                WHERE fecha_modificacion >= ? OR fecha_hora_alta >= ?
            ", [$checkTime, $checkTime]);
        } else {
            $this->info("Carga inicial completa de clientes...");
            $rows = DB::connection('erp')->select("
                SELECT 
                    cod_cliente, razon_social, cif, poblacion, provincia, telefono, e_mail, limite_credito, cod_vendedor, fecha_modificacion
                FROM clientes
            ");
        }

        if (empty($rows)) {
            $this->info("No hay nuevos clientes modificados.");
            return 0;
        }

        $dataToUpsert = [];
        $syncedAt = now();

        foreach ($rows as $row) {
            $dataToUpsert[] = [
                'cod_cliente' => trim($row->cod_cliente),
                'razon_social' => $row->razon_social ? trim($row->razon_social) : null,
                'cif' => $row->cif ? trim($row->cif) : null,
                'poblacion' => $row->poblacion ? trim($row->poblacion) : null,
                'provincia' => $row->provincia ? trim($row->provincia) : null,
                'telefono' => $row->telefono ? trim($row->telefono) : null,
                'e_mail' => $row->e_mail ? trim($row->e_mail) : null,
                'limite_credito' => (float)($row->limite_credito ?? 0),
                'cod_vendedor' => $row->cod_vendedor ? trim($row->cod_vendedor) : null,
                'total_spent' => 0.0, // Se computa dinámicamente en consultas complejas
                'order_count' => 0,
                'source_modified_at' => $this->parseDateToUtc($row->fecha_modificacion),
                'synced_at' => $syncedAt
            ];
        }

        $processed = 0;
        foreach (array_chunk($dataToUpsert, 100) as $chunk) {
            DB::connection('supabase')->table('clients_reporting')->upsert(
                $chunk,
                ['cod_cliente'],
                ['razon_social', 'cif', 'poblacion', 'provincia', 'telefono', 'e_mail', 'limite_credito', 'cod_vendedor', 'source_modified_at', 'synced_at']
            );
            $processed += count($chunk);
        }

        DB::connection('supabase')->table('sync_state')->upsert([
            'dataset' => 'clients',
            'active_batch_id' => null,
            'last_success_at' => now(),
            'last_run_status' => 'success',
            'last_error_message' => null
        ], ['dataset'], ['last_success_at', 'last_run_status', 'last_error_message']);

        return $processed;
    }

    /**
     * 6. suppliers (INCREMENTAL)
     */
    private function syncSuppliers(string $runId): int
    {
        // 1. Procesar primero los borrados físicos
        $this->syncDeletedRecords('suppliers', 'proveedores', 'suppliers_reporting', 'cod_proveedor');

        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'suppliers')->first();
        $lastSuccess = $stateRow ? $stateRow->last_success_at : null;

        if ($lastSuccess) {
            $checkTime = date('Ymd H:i:s', strtotime($lastSuccess . ' -5 minutes'));
            $rows = DB::connection('erp')->select("
                SELECT 
                    cod_proveedor, razon_social, cif, poblacion, provincia, telefono, e_mail, credito_otorgado, fecha_modificacion
                FROM proveedores
                WHERE fecha_modificacion >= ? OR fecha_hora_alta >= ?
            ", [$checkTime, $checkTime]);
        } else {
            $this->info("Carga inicial completa de proveedores...");
            $rows = DB::connection('erp')->select("
                SELECT 
                    cod_proveedor, razon_social, cif, poblacion, provincia, telefono, e_mail, credito_otorgado, fecha_modificacion
                FROM proveedores
            ");
        }

        if (empty($rows)) {
            return 0;
        }

        $dataToUpsert = [];
        $syncedAt = now();

        foreach ($rows as $row) {
            $dataToUpsert[] = [
                'cod_proveedor' => trim($row->cod_proveedor),
                'razon_social' => $row->razon_social ? trim($row->razon_social) : null,
                'cif' => $row->cif ? trim($row->cif) : null,
                'poblacion' => $row->poblacion ? trim($row->poblacion) : null,
                'provincia' => $row->provincia ? trim($row->provincia) : null,
                'telefono' => $row->telefono ? trim($row->telefono) : null,
                'e_mail' => $row->e_mail ? trim($row->e_mail) : null,
                'credito_otorgado' => (float)($row->credito_otorgado ?? 0),
                'source_modified_at' => $this->parseDateToUtc($row->fecha_modificacion),
                'synced_at' => $syncedAt
            ];
        }

        $processed = 0;
        foreach (array_chunk($dataToUpsert, 100) as $chunk) {
            DB::connection('supabase')->table('suppliers_reporting')->upsert(
                $chunk,
                ['cod_proveedor'],
                ['razon_social', 'cif', 'poblacion', 'provincia', 'telefono', 'e_mail', 'credito_otorgado', 'source_modified_at', 'synced_at']
            );
            $processed += count($chunk);
        }

        DB::connection('supabase')->table('sync_state')->upsert([
            'dataset' => 'suppliers',
            'active_batch_id' => null,
            'last_success_at' => now(),
            'last_run_status' => 'success',
            'last_error_message' => null
        ], ['dataset'], ['last_success_at', 'last_run_status', 'last_error_message']);

        return $processed;
    }

    /**
     * 7. monthly_history (AGREGADO HISTÓRICO 2012-2024)
     */
    private function syncMonthlyHistory(string $runId): int
    {
        $currentYear = (int) date('Y');

        $this->info("Calculando facturación consolidada mensual (2012 a " . ($currentYear - 1) . ") en ERP...");
        
        $revenueRows = DB::connection('erp')->select("
            SELECT 
                YEAR(fecha_venta) as year,
                MONTH(fecha_venta) as month,
                SUM(importe_impuestos) as revenue,
                COUNT(*) as orders_count
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) >= 2012 AND YEAR(fecha_venta) < {$currentYear}
                AND tipo_venta IN (2, 4, 5)
                AND ISNULL(anulada, '') <> 'S'
            GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
        ");

        $costRows = DB::connection('erp')->select("
            SELECT 
                YEAR(v.fecha_venta) as year,
                MONTH(v.fecha_venta) as month,
                SUM(l.precio_coste * l.cantidad) as total_cost,
                SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            WHERE YEAR(v.fecha_venta) >= 2012 AND YEAR(v.fecha_venta) < {$currentYear}
                AND v.tipo_venta IN (2, 4, 5)
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL
                AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                AND l.precio_coste IS NOT NULL
                AND l.precio_coste > 0
                AND l.precio_coste < 100000
                AND l.cantidad > 0
            GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
        ");

        // Agrupar costes
        $costsGrouped = [];
        foreach ($costRows as $c) {
            $key = "{$c->year}-{$c->month}";
            $costsGrouped[$key] = [
                'total_cost' => (float)$c->total_cost,
                'gross_profit' => (float)$c->gross_profit
            ];
        }

        $dataToUpsert = [];
        $syncedAt = now();

        foreach ($revenueRows as $r) {
            $key = "{$r->year}-{$r->month}";
            $costData = $costsGrouped[$key] ?? ['total_cost' => 0.0, 'gross_profit' => (float)$r->revenue];

            $dataToUpsert[] = [
                'year' => (int)$r->year,
                'month' => (int)$r->month,
                'revenue' => (float)$r->revenue,
                'total_cost' => $costData['total_cost'],
                'gross_profit' => $costData['gross_profit'],
                'orders_count' => (int)$r->orders_count,
                'synced_at' => $syncedAt
            ];
        }

        if (empty($dataToUpsert)) {
            return 0;
        }

        $processed = 0;
        foreach (array_chunk($dataToUpsert, 50) as $chunk) {
            DB::connection('supabase')->table('stats_sales_monthly')->upsert(
                $chunk,
                ['year', 'month'],
                ['revenue', 'total_cost', 'gross_profit', 'orders_count', 'synced_at']
            );
            $processed += count($chunk);
        }

        DB::connection('supabase')->table('sync_state')->upsert([
            'dataset' => 'monthly_history',
            'active_batch_id' => null,
            'last_success_at' => now(),
            'last_run_status' => 'success',
            'last_error_message' => null
        ], ['dataset'], ['last_success_at', 'last_run_status', 'last_error_message']);

        return $processed;
    }

    /**
     * 8. sales (INCREMENTAL - Cabeceras y Líneas)
     */
    private function syncSales(string $runId): int
    {
        ini_set('memory_limit', '512M');
        
        $periodOption = $this->option('period');
        $isQuickPeriod = ($periodOption === 'today');
        $isPartialPeriod = in_array($periodOption, ['test_1month', 'today'], true);
        $quickSalesDate = $isQuickPeriod ? now()->toDateString() : null;

        // 1. Procesar borrados físicos primero (omitir en quick sync)
        if (!$isQuickPeriod) {
            $this->syncDeletedRecords('sales', 'hist_ventas_cabecera', 'sales_headers', 'cod_venta');
        }

        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'sales')->first();
        $lastSuccess = $stateRow ? $stateRow->last_success_at : null;

        $currentYear = (int)date('Y');
        $prevYear = $currentYear - 1;

        // Determinar condiciones de rango de fechas de venta
        $querySql = "
            SELECT
                cod_venta, tipo_venta, cod_empresa, cod_caja, cod_almacen, cod_cliente, razon_social, fecha_venta, cod_forma_liquidacion, cod_vendedor, nombre_vendedor, importe as net_amount, importe_impuestos as total_amount, importe_pendiente as pending_amount, anulada, fecha_modificacion
            FROM hist_ventas_cabecera
            WHERE tipo_venta IN (2, 4, 5)
        ";
        $params = [];

        if ($periodOption === 'test_1month') {
            $this->info("Ejecutando prueba controlada de 1 mes de ventas (Julio 2026)...");
            $querySql .= " AND fecha_venta >= '20260701' AND fecha_venta < '20260801'";
        } elseif ($periodOption === 'today') {
            $this->info("Quick sync: sincronizando ventas de {$quickSalesDate}...");
            $querySql .= " AND CAST(fecha_venta AS DATE) = ?";
            $params = [$quickSalesDate];
        } elseif ($periodOption === 'current_month') {
            $start = sprintf('%04d%02d01', $currentYear, (int) date('m'));
            $end = date('Ymd', strtotime("{$currentYear}-" . date('m') . "-01 +1 month"));
            $this->info("Sincronizando ventas del mes actual ({$start} a {$end})...");
            $querySql .= " AND fecha_venta >= ? AND fecha_venta < ?";
            $params = [$start, $end];
        } else if ($lastSuccess) {
            $checkTime = date('Ymd H:i:s', strtotime($lastSuccess . ' -24 hours'));
            $this->info("Sincronización incremental de ventas desde: {$checkTime}...");
            $querySql .= " AND (fecha_modificacion >= ? OR fecha_hora_alta >= ?) AND fecha_venta >= ?";
            $params = [$checkTime, $checkTime, "{$prevYear}0101"];
        } else {
            $this->info("Carga inicial de ventas (Año actual: {$currentYear} + Año anterior: {$prevYear})...");
            $querySql .= " AND fecha_venta >= ?";
            $params = ["{$prevYear}0101"];
        }

        $this->info("Ejecutando consulta de cabeceras mediante cursor en SQL Server...");
        $db = DB::connection('erp');
        $pdo = $db->getPdo();
        $stmt = $pdo->prepare($querySql);
        $stmt->execute($params);

        $syncedAt = now();
        $uniqueMonthsToRecalculate = [];
        $processedHeadersCount = 0;
        $processedLinesCount = 0;
        
        $headerChunk = [];

        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $fechaVentaLocal = $this->parseLocalDate($row->fecha_venta);
            $modifiedAtUtc = $this->parseDateToUtc($row->fecha_modificacion);

            $headerChunk[] = [
                'cod_venta' => trim($row->cod_venta),
                'tipo_venta' => (int)$row->tipo_venta,
                'cod_empresa' => trim($row->cod_empresa),
                'cod_caja' => trim($row->cod_caja),
                'cod_almacen' => $row->cod_almacen ? trim($row->cod_almacen) : null,
                'cod_cliente' => $row->cod_cliente ? trim($row->cod_cliente) : null,
                'razon_social' => $row->razon_social ? trim($row->razon_social) : null,
                'fecha_venta' => $fechaVentaLocal,
                'cod_forma_liquidacion' => $row->cod_forma_liquidacion ? trim($row->cod_forma_liquidacion) : null,
                'cod_vendedor' => $row->cod_vendedor ? trim($row->cod_vendedor) : null,
                'nombre_vendedor' => $row->nombre_vendedor ? trim($row->nombre_vendedor) : null,
                'net_amount' => (float)($row->net_amount ?? 0),
                'total_amount' => (float)($row->total_amount ?? 0),
                'pending_amount' => (float)($row->pending_amount ?? 0),
                'anulada' => trim($row->anulada) === 'S',
                'source_modified_at' => $modifiedAtUtc,
                'synced_at' => $syncedAt
            ];

            // Si la venta pertenece a un año del histórico (< año actual), registrar el mes/año para recálculo
            $ventaYear = (int)date('Y', strtotime($row->fecha_venta));
            if ($ventaYear < $currentYear && $ventaYear >= 2012) {
                $ventaMonth = (int)date('m', strtotime($row->fecha_venta));
                $uniqueMonthsToRecalculate["{$ventaYear}-{$ventaMonth}"] = [
                    'year' => $ventaYear,
                    'month' => $ventaMonth
                ];
            }

            if (count($headerChunk) >= 100) {
                $this->processChunkOfSales($headerChunk, $syncedAt, $processedLinesCount);
                $processedHeadersCount += count($headerChunk);
                $headerChunk = [];
            }
        }

        if (count($headerChunk) > 0) {
            $this->processChunkOfSales($headerChunk, $syncedAt, $processedLinesCount);
            $processedHeadersCount += count($headerChunk);
        }

        $this->info("Sincronización de cabeceras realizada (Total cabeceras: {$processedHeadersCount}, Total líneas: {$processedLinesCount}).");

        // 4. Recalcular históricos de stats_sales_monthly si hubo ventas modificadas de años pasados
        if (!empty($uniqueMonthsToRecalculate)) {
            $this->info("Recalculando " . count($uniqueMonthsToRecalculate) . " meses históricos afectados en Supabase...");
            foreach ($uniqueMonthsToRecalculate as $m) {
                $this->recalculateHistoricalMonth($m['year'], $m['month']);
            }
        }

        // 5. Registrar el sync_state final (solo para sincronizaciones reales, no pruebas parciales)
        if (!$isPartialPeriod) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'sales',
                'active_batch_id' => null,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null
            ], ['dataset'], ['last_success_at', 'last_run_status', 'last_error_message']);
            $this->info("Sync_state actualizado: last_success_at = " . now());
        } else {
            $this->warn("Sync_state NO actualizado: la opción --period={$periodOption} es una sincronización parcial.");
        }

        // 6. Resolver borrados físicos. El quick sync concilia exclusivamente el día
        // consultado; el resto de sincronizaciones conserva el GC global existente.
        if ($isQuickPeriod) {
            $this->cleanupQuickSalesOrphans($quickSalesDate);
        } else {
            $this->cleanupSalesOrphans();
        }

        return $processedHeadersCount;
    }

    /**
     * Procesa e inserta en Supabase un chunk de cabeceras y sus correspondientes líneas
     */
    private function processChunkOfSales(array $headerChunk, $syncedAt, int &$processedLinesCount): void
    {
        // 1. Upsert cabeceras
        DB::connection('supabase')->table('sales_headers')->upsert(
            $headerChunk,
            ['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja'],
            ['cod_almacen', 'cod_cliente', 'razon_social', 'fecha_venta', 'cod_forma_liquidacion', 'cod_vendedor', 'nombre_vendedor', 'net_amount', 'total_amount', 'pending_amount', 'anulada', 'source_modified_at', 'synced_at']
        );

        // 2. Obtener líneas correspondientes de SQL Server
        $orQueries = [];
        $params = [];
        foreach ($headerChunk as $h) {
            $orQueries[] = "(cod_venta = ? AND tipo_venta = ? AND cod_empresa = ? AND cod_caja = ?)";
            $params[] = $h['cod_venta'];
            $params[] = $h['tipo_venta'];
            $params[] = $h['cod_empresa'];
            $params[] = $h['cod_caja'];
        }
        $whereClause = implode(' OR ', $orQueries);

        $erpLines = DB::connection('erp')->select("
            SELECT 
                cod_venta, tipo_venta, cod_empresa, cod_caja, linea, cod_articulo, descripcion, cantidad, precio, precio_coste, importe_impuestos as total_amount, fecha_modificacion
            FROM hist_ventas_linea
            WHERE {$whereClause}
        ", $params);

        if (empty($erpLines)) {
            return;
        }

        // 3. Delete previo agrupado en Supabase
        $deleteQueries = [];
        $deleteParams = [];
        foreach ($headerChunk as $h) {
            $deleteQueries[] = "(cod_venta = ? AND tipo_venta = ? AND cod_empresa = ? AND cod_caja = ?)";
            $deleteParams[] = $h['cod_venta'];
            $deleteParams[] = $h['tipo_venta'];
            $deleteParams[] = $h['cod_empresa'];
            $deleteParams[] = $h['cod_caja'];
        }
        if (!empty($deleteQueries)) {
            $whereClause = implode(' OR ', $deleteQueries);
            DB::connection('supabase')->delete("DELETE FROM sales_lines WHERE {$whereClause}", $deleteParams);
        }

        // 4. Preparar líneas para upsert
        $linesToInsert = [];
        foreach ($erpLines as $line) {
            $linesToInsert[] = [
                'cod_venta' => trim($line->cod_venta),
                'tipo_venta' => (int)$line->tipo_venta,
                'cod_empresa' => trim($line->cod_empresa),
                'cod_caja' => trim($line->cod_caja),
                'linea' => (int)$line->linea,
                'cod_articulo' => $line->cod_articulo ? trim($line->cod_articulo) : null,
                'descripcion' => $line->descripcion ? trim($line->descripcion) : null,
                'cantidad' => (float)($line->cantidad ?? 0),
                'precio' => (float)($line->precio ?? 0),
                'precio_coste' => (float)($line->precio_coste ?? 0),
                'total_amount' => (float)($line->total_amount ?? 0),
                'source_modified_at' => $this->parseDateToUtc($line->fecha_modificacion),
                'synced_at' => $syncedAt
            ];
        }

        // 5. Upsert líneas en Supabase en chunks pequeños para no desbordar
        foreach (array_chunk($linesToInsert, 100) as $lineChunk) {
            DB::connection('supabase')->table('sales_lines')->upsert(
                $lineChunk,
                ['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja', 'linea'],
                ['cod_articulo', 'descripcion', 'cantidad', 'precio', 'precio_coste', 'total_amount', 'source_modified_at', 'synced_at']
                );
            $processedLinesCount += count($lineChunk);
        }
    }

    /**
     * Albaranes de compra del mes actual.
     *
     * El ERP no ofrece una fecha de modificación confirmada para este histórico,
     * así que cada ejecución relee y reconcilia únicamente el mes actual.
     */
    private function syncPurchases(string $runId): int
    {
        if ($this->option('period') !== 'current_month') {
            throw new \InvalidArgumentException('El dataset purchases solo admite --period=current_month.');
        }

        $nowMadrid = now('Europe/Madrid');
        $currentYear = (int) $nowMadrid->format('Y');
        $currentMonth = (int) $nowMadrid->format('m');
        $periodStart = $nowMadrid->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonth();
        $syncedAt = now();
        $processedHeadersCount = 0;
        $processedLinesCount = 0;
        $erpKeys = [];
        $headerChunk = [];

        try {
            $this->info("Sincronizando albaranes de compra de {$currentYear}-" . str_pad((string) $currentMonth, 2, '0', STR_PAD_LEFT) . '...');

            $statement = DB::connection('erp')->getPdo()->prepare("
                SELECT
                    cod_compra, tipo_compra, cod_empresa, cod_proveedor, cod_almacen,
                    nombre_comercial, razon_social, fecha_compra, importe
                FROM hist_compras_cabecera
                WHERE tipo_compra = 2
                    AND YEAR(fecha_compra) = ?
                    AND MONTH(fecha_compra) = ?
                ORDER BY cod_compra, tipo_compra, cod_empresa, cod_proveedor
            ");
            $statement->execute([$currentYear, $currentMonth]);

            while ($erpRow = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $row = $this->normalizePurchaseErpRow($erpRow);
                $header = [
                    'cod_compra' => (int) $row['cod_compra'],
                    'tipo_compra' => (int) $row['tipo_compra'],
                    'cod_empresa' => (int) $row['cod_empresa'],
                    'cod_proveedor' => (int) $row['cod_proveedor'],
                    'cod_almacen' => $row['cod_almacen'] === null ? null : (int) $row['cod_almacen'],
                    'nombre_comercial' => $row['nombre_comercial'] ? trim($row['nombre_comercial']) : null,
                    'razon_social' => $row['razon_social'] ? trim($row['razon_social']) : null,
                    'fecha_compra' => $this->parseLocalDate($row['fecha_compra']),
                    'importe' => (float) ($row['importe'] ?? 0),
                    'source_modified_at' => null,
                    'synced_at' => $syncedAt,
                ];

                $erpKeys[$this->purchaseKey(
                    $header['cod_compra'],
                    $header['tipo_compra'],
                    $header['cod_empresa'],
                    $header['cod_proveedor']
                )] = true;
                $headerChunk[] = $header;

                if (count($headerChunk) >= 100) {
                    $this->processChunkOfPurchases($headerChunk, $syncedAt, $processedLinesCount);
                    $processedHeadersCount += count($headerChunk);
                    $headerChunk = [];
                }
            }

            if (!empty($headerChunk)) {
                $this->processChunkOfPurchases($headerChunk, $syncedAt, $processedLinesCount);
                $processedHeadersCount += count($headerChunk);
            }

            $removedCount = $this->reconcileCurrentMonthPurchases(
                $erpKeys,
                $periodStart->toDateTimeString(),
                $periodEnd->toDateTimeString()
            );

            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'purchases',
                'active_batch_id' => null,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null,
            ], ['dataset'], ['last_success_at', 'last_run_status', 'last_error_message']);

            $this->info("Sincronización de albaranes completada (Cabeceras: {$processedHeadersCount}, Líneas: {$processedLinesCount}, Eliminados: {$removedCount}).");

            return $processedHeadersCount;
        } catch (\Throwable $e) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'purchases',
                'active_batch_id' => null,
                'last_success_at' => null,
                'last_run_status' => 'failed',
                'last_error_message' => substr($e->getMessage(), 0, 1000),
            ], ['dataset'], ['last_run_status', 'last_error_message']);

            throw $e;
        }
    }

    /**
     * Inserta cabeceras en lote y sustituye por conjunto las líneas de sus documentos.
     */
    private function processChunkOfPurchases(array $headerChunk, $syncedAt, int &$processedLinesCount): void
    {
        $orQueries = [];
        $params = [];
        foreach ($headerChunk as $header) {
            $orQueries[] = '(cod_compra = ? AND tipo_compra = ? AND cod_empresa = ? AND cod_proveedor = ?)';
            $params[] = $header['cod_compra'];
            $params[] = $header['tipo_compra'];
            $params[] = $header['cod_empresa'];
            $params[] = $header['cod_proveedor'];
        }

        $erpLines = DB::connection('erp')->select("
            SELECT
                cod_compra, tipo_compra, cod_empresa, cod_proveedor, linea,
                cod_articulo, referencia_proveedor, descripcion, cantidad, tarifa,
                precio_coste, dto1, dto2, dto3, dto4, importe, cod_almacen
            FROM hist_compras_linea
            WHERE " . implode(' OR ', $orQueries), $params);

        $linesToUpsert = [];
        foreach ($erpLines as $erpLine) {
            $line = $this->normalizePurchaseErpRow($erpLine);
            $linesToUpsert[] = [
                'cod_compra' => (int) $line['cod_compra'],
                'tipo_compra' => (int) $line['tipo_compra'],
                'cod_empresa' => (int) $line['cod_empresa'],
                'cod_proveedor' => (int) $line['cod_proveedor'],
                'linea' => (int) $line['linea'],
                'cod_articulo' => $line['cod_articulo'] ? trim($line['cod_articulo']) : null,
                'referencia_proveedor' => $line['referencia_proveedor'] ? trim($line['referencia_proveedor']) : null,
                'descripcion' => $line['descripcion'] ? trim($line['descripcion']) : null,
                'cantidad' => (float) ($line['cantidad'] ?? 0),
                'tarifa' => (float) ($line['tarifa'] ?? 0),
                'precio_coste' => (float) ($line['precio_coste'] ?? 0),
                'dto1' => (float) ($line['dto1'] ?? 0),
                'dto2' => (float) ($line['dto2'] ?? 0),
                'dto3' => (float) ($line['dto3'] ?? 0),
                'dto4' => (float) ($line['dto4'] ?? 0),
                'importe' => (float) ($line['importe'] ?? 0),
                'cod_almacen' => $line['cod_almacen'] === null ? null : (int) $line['cod_almacen'],
                'source_modified_at' => null,
                'synced_at' => $syncedAt,
            ];
        }

        DB::connection('supabase')->transaction(function () use ($headerChunk, $linesToUpsert, &$processedLinesCount) {
            DB::connection('supabase')->table('purchase_headers')->upsert(
                $headerChunk,
                ['cod_compra', 'tipo_compra', 'cod_empresa', 'cod_proveedor'],
                ['cod_almacen', 'nombre_comercial', 'razon_social', 'fecha_compra', 'importe', 'source_modified_at', 'synced_at']
            );

            $linesQuery = DB::connection('supabase')->table('purchase_lines');
            $this->applyPurchaseCompositeKeyFilter($linesQuery, $headerChunk);
            $linesQuery->delete();

            foreach (array_chunk($linesToUpsert, 100) as $lineChunk) {
                DB::connection('supabase')->table('purchase_lines')->upsert(
                    $lineChunk,
                    ['cod_compra', 'tipo_compra', 'cod_empresa', 'cod_proveedor', 'linea'],
                    ['cod_articulo', 'referencia_proveedor', 'descripcion', 'cantidad', 'tarifa', 'precio_coste', 'dto1', 'dto2', 'dto3', 'dto4', 'importe', 'cod_almacen', 'source_modified_at', 'synced_at']
                );
                $processedLinesCount += count($lineChunk);
            }
        });
    }

    /**
     * Normaliza filas usadas por purchases a un array asociativo.
     */
    private function normalizePurchaseErpRow(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if (is_object($row)) {
            return get_object_vars($row);
        }

        throw new \UnexpectedValueException(
            'Fila ERP de purchases con tipo inesperado: ' . get_debug_type($row)
        );
    }

    /**
     * Elimina solo cabeceras del mes actual que ya no existan en el ERP.
     */
    private function reconcileCurrentMonthPurchases(array $erpKeys, string $periodStart, string $periodEnd): int
    {
        $this->info('Conciliando borrados físicos de albaranes del mes actual...');

        $headersToDelete = DB::connection('supabase')->table('purchase_headers')
            ->select('cod_compra', 'tipo_compra', 'cod_empresa', 'cod_proveedor')
            ->where('tipo_compra', 2)
            ->where('fecha_compra', '>=', $periodStart)
            ->where('fecha_compra', '<', $periodEnd)
            ->get()
            ->filter(fn ($header) => !isset($erpKeys[$this->purchaseKey(
                $header->cod_compra,
                $header->tipo_compra,
                $header->cod_empresa,
                $header->cod_proveedor
            )]));

        foreach (array_chunk($headersToDelete->all(), 100) as $headersChunk) {
            DB::connection('supabase')->transaction(function () use ($headersChunk) {
                $linesQuery = DB::connection('supabase')->table('purchase_lines');
                $this->applyPurchaseCompositeKeyFilter($linesQuery, $headersChunk);
                $linesQuery->delete();

                $headersQuery = DB::connection('supabase')->table('purchase_headers');
                $this->applyPurchaseCompositeKeyFilter($headersQuery, $headersChunk);
                $headersQuery->delete();
            });
        }

        $deletedCount = $headersToDelete->count();
        $this->info("Conciliación de albaranes finalizada ({$deletedCount} cabeceras eliminadas).");

        return $deletedCount;
    }

    private function applyPurchaseCompositeKeyFilter($query, array $headers): void
    {
        $query->where(function ($keysQuery) use ($headers) {
            foreach ($headers as $header) {
                $key = $this->normalizePurchaseErpRow($header);
                $keysQuery->orWhere(function ($keyQuery) use ($key) {
                    $keyQuery->where('cod_compra', $key['cod_compra'])
                        ->where('tipo_compra', $key['tipo_compra'])
                        ->where('cod_empresa', $key['cod_empresa'])
                        ->where('cod_proveedor', $key['cod_proveedor']);
                });
            }
        });
    }

    private function purchaseKey($codCompra, $tipoCompra, $codEmpresa, $codProveedor): string
    {
        return implode('-', [
            (int) $codCompra,
            (int) $tipoCompra,
            (int) $codEmpresa,
            (int) $codProveedor,
        ]);
    }

    /**
     * 9. receivables (SNAPSHOT - Vencimientos de clientes / cartera de cobro)
     *
     * Protocolo de seguridad snapshot:
     *  1. Calcular totales esperados en ERP: filas, importe_total,
     *     importe_cobrado, importe_pendiente, impagados y pendientes_normales.
     *  2. Generar batch_id nuevo.
     *  3. Insertar todas las filas en Supabase.
     *  4. Calcular los mismos totales en el nuevo batch y comparar con ERP.
     *  5. SOLO si todas las paridades (filas y monetarias) coinciden dentro de
     *     la tolerancia (1,00 €), actualizar sync_state.active_batch_id.
     *  6. Limpiar batches antiguos.
     *
     * Si cualquier paso intermedio falla, el batch nuevo se borra y el batch
     * anterior sigue activo.
     */
    private function syncReceivables(string $runId): int
    {
        ini_set('memory_limit', '512M');
        $db = DB::connection('erp');

        // --- PASO 1: totales esperados en ERP ----------------------------------
        $this->info("Calculando totales de cartera en ERP...");
        $whereCartera = "
            v.cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
            OR (v.cod_remesa IS NULL AND v.cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC'))
        ";

        $expectedStmt = $db->getPdo()->prepare("
            SELECT
                COUNT(*) as filas,
                COALESCE(SUM(v.importe), 0) as importe_total,
                COALESCE(SUM(v.importe_cobrado), 0) as importe_cobrado_total,
                COALESCE(SUM(v.importe - v.importe_cobrado), 0) as importe_pendiente_total,
                COUNT(CASE WHEN v.cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC') THEN 1 END) as impagados_count,
                COALESCE(SUM(CASE WHEN v.cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
                    THEN (v.importe - v.importe_cobrado) ELSE 0 END), 0) as impagados_pendiente,
                COUNT(CASE WHEN v.cod_remesa IS NULL
                    AND v.cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC') THEN 1 END) as pendientes_normales_count,
                COALESCE(SUM(CASE WHEN v.cod_remesa IS NULL
                    AND v.cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
                    THEN (v.importe - v.importe_cobrado) ELSE 0 END), 0) as pendientes_normales_pendiente
            FROM vencimientos_facturas v
            WHERE {$whereCartera}
        ");
        $expectedStmt->execute();
        $expected = $expectedStmt->fetch(\PDO::FETCH_OBJ);

        $expectedCount = (int) ($expected->filas ?? 0);
        if ($expectedCount === 0) {
            $this->warn("No hay vencimientos pendientes/impagados en ERP. Se omite la carga.");
            return 0;
        }

        $this->info("ERP: {$expectedCount} vencimientos, pendiente total="
            . number_format($expected->importe_pendiente_total, 2) . ", impagados="
            . number_format($expected->impagados_pendiente, 2) . ", pendientes_normales="
            . number_format($expected->pendientes_normales_pendiente, 2));

        // --- PASO 2: crear batch_id nuevo ------------------------------------
        $batchId = (string) Str::uuid();

        // --- PASO 3: insertar filas en Supabase -------------------------------
        $this->info("Consultando vencimientos de clientes desde SQL Server...");
        $stmt = $db->getPdo()->prepare("
            SELECT
                f.cod_almacen,
                v.cod_factura,
                v.tipo_factura,
                v.cod_empresa,
                v.numero,
                v.cod_cliente,
                v.razon_social,
                f.cif,
                COALESCE(f.fecha_factura, v.fecha_vencimiento) as fecha_factura,
                v.fecha_vencimiento,
                v.importe,
                v.importe_cobrado,
                (v.importe - v.importe_cobrado) as importe_pendiente,
                v.cod_forma_liquidacion,
                v.cod_remesa
            FROM vencimientos_facturas v
            LEFT JOIN facturas_ventas_cabecera f
                ON v.cod_factura = f.cod_factura
                AND v.tipo_factura = f.tipo_factura
                AND v.cod_empresa = f.cod_empresa
            WHERE {$whereCartera}
        ");
        $stmt->execute();

        $chunk = [];
        $insertedCount = 0;
        $syncedAt = now();

        while ($rowObj = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $idVencimiento = sprintf(
                '%d-%d-%d-%d',
                (int)$rowObj->cod_empresa,
                (int)$rowObj->cod_factura,
                (int)$rowObj->tipo_factura,
                (int)$rowObj->numero
            );

            $chunk[] = [
                'id_vencimiento' => $idVencimiento,
                'batch_id' => $batchId,
                'cod_empresa' => (int)$rowObj->cod_empresa,
                'cod_factura' => (int)$rowObj->cod_factura,
                'tipo_factura' => (int)$rowObj->tipo_factura,
                'numero' => (int)$rowObj->numero,
                'cod_cliente' => $rowObj->cod_cliente ? (int)$rowObj->cod_cliente : 0,
                'razon_social' => $rowObj->razon_social ? substr(trim($rowObj->razon_social), 0, 150) : null,
                'fecha_factura' => $rowObj->fecha_factura ? substr($rowObj->fecha_factura, 0, 10) : null,
                'fecha_vencimiento' => $rowObj->fecha_vencimiento ? substr($rowObj->fecha_vencimiento, 0, 10) : null,
                'importe_original' => (float)($rowObj->importe ?? 0),
                'importe_cobrado' => (float)($rowObj->importe_cobrado ?? 0),
                'importe_pendiente' => (float)($rowObj->importe_pendiente ?? 0),
                'fecha_devolucion' => null,
                'cod_forma_liquidacion' => $rowObj->cod_forma_liquidacion ? trim($rowObj->cod_forma_liquidacion) : null,
                'nombre_forma_liquidacion' => null,
                'iban' => null,
                'cod_remesa' => $rowObj->cod_remesa ? (int)$rowObj->cod_remesa : null,
                'synced_at' => $syncedAt,
                'cod_almacen' => $rowObj->cod_almacen ? (int)$rowObj->cod_almacen : null,
            ];

            if (count($chunk) >= 500) {
                DB::connection('supabase')->table('receivables')->insert($chunk);
                $insertedCount += count($chunk);
                $chunk = [];
            }
        }

        if (count($chunk) > 0) {
            DB::connection('supabase')->table('receivables')->insert($chunk);
            $insertedCount += count($chunk);
        }

        $this->info("Insertadas {$insertedCount} filas en Supabase para batch {$batchId}.");

        // --- PASO 4: validar paridad ERP vs Supabase --------------------------
        $this->info("Validando paridad de filas y totales entre ERP y Supabase (tolerancia 0,00 €)...");
        $actual = DB::connection('supabase')
            ->selectOne("
                SELECT
                    COUNT(*) as filas,
                    COALESCE(SUM(importe_original), 0) as importe_total,
                    COALESCE(SUM(importe_cobrado), 0) as importe_cobrado_total,
                    COALESCE(SUM(importe_pendiente), 0) as importe_pendiente_total,
                    COUNT(CASE WHEN cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC') THEN 1 END) as impagados_count,
                    COALESCE(SUM(CASE WHEN cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC') THEN importe_pendiente ELSE 0 END), 0) as impagados_pendiente,
                    COUNT(CASE WHEN cod_remesa IS NULL AND cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC') THEN 1 END) as pendientes_normales_count,
                    COALESCE(SUM(CASE WHEN cod_remesa IS NULL AND cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC') THEN importe_pendiente ELSE 0 END), 0) as pendientes_normales_pendiente
                FROM receivables
                WHERE batch_id = ?
            ", [$batchId]);

        // Comparador exacto al céntimo.
        $moneyEqual = fn ($a, $b): bool => round((float) $a, 2) === round((float) $b, 2);

        $paridadOk = true;
        $errores = [];
        $advertencias = [];

        // Validaciones BLOQUEANTES (paridad exacta)
        if ((int)$actual->filas !== $expectedCount) {
            $paridadOk = false;
            $errores[] = "filas: ERP={$expectedCount}, Supabase={$actual->filas}";
        }
        if (!$moneyEqual($actual->importe_pendiente_total, $expected->importe_pendiente_total)) {
            $paridadOk = false;
            $errores[] = "importe_pendiente_total: ERP=" . number_format($expected->importe_pendiente_total, 2) . ", Supabase=" . number_format($actual->importe_pendiente_total, 2);
        }
        if ((int)$actual->impagados_count !== (int)$expected->impagados_count) {
            $paridadOk = false;
            $errores[] = "impagados_count: ERP={$expected->impagados_count}, Supabase={$actual->impagados_count}";
        }
        if (!$moneyEqual($actual->impagados_pendiente, $expected->impagados_pendiente)) {
            $paridadOk = false;
            $errores[] = "impagados_pendiente: ERP=" . number_format($expected->impagados_pendiente, 2) . ", Supabase=" . number_format($actual->impagados_pendiente, 2);
        }
        if ((int)$actual->pendientes_normales_count !== (int)$expected->pendientes_normales_count) {
            $paridadOk = false;
            $errores[] = "pendientes_normales_count: ERP={$expected->pendientes_normales_count}, Supabase={$actual->pendientes_normales_count}";
        }
        if (!$moneyEqual($actual->pendientes_normales_pendiente, $expected->pendientes_normales_pendiente)) {
            $paridadOk = false;
            $errores[] = "pendientes_normales_pendiente: ERP=" . number_format($expected->pendientes_normales_pendiente, 2) . ", Supabase=" . number_format($actual->pendientes_normales_pendiente, 2);
        }

        // Validaciones ADICIONALES (informativas; no bloquean, pero se reportan)
        if (!$moneyEqual($actual->importe_total, $expected->importe_total)) {
            $advertencias[] = "importe_total: ERP=" . number_format($expected->importe_total, 2) . ", Supabase=" . number_format($actual->importe_total, 2);
        }
        if (!$moneyEqual($actual->importe_cobrado_total, $expected->importe_cobrado_total)) {
            $advertencias[] = "importe_cobrado_total: ERP=" . number_format($expected->importe_cobrado_total, 2) . ", Supabase=" . number_format($actual->importe_cobrado_total, 2);
        }

        if (!empty($advertencias)) {
            $this->warn("Advertencias de paridad adicional (no bloqueantes):");
            foreach ($advertencias as $adv) {
                $this->warn("  - {$adv}");
            }
        }

        if (!$paridadOk) {
            $this->error("PARIDAD FALLIDA:");
            foreach ($errores as $err) {
                $this->error("  - {$err}");
            }
            $this->error("El batch {$batchId} no se activa. Se limpia el batch huérfano.");

            DB::connection('supabase')
                ->table('receivables')
                ->where('batch_id', $batchId)
                ->delete();

            throw new \Exception("Paridad receivables incorrecta: " . implode('; ', $errores));
        }

        $this->info("Paridad validada exacta: filas={$actual->filas}, importe_pendiente_total=" . number_format($actual->importe_pendiente_total, 2) . ", "
            . "impagados={$actual->impagados_count}/" . number_format($actual->impagados_pendiente, 2) . ", "
            . "pendientes_normales={$actual->pendientes_normales_count}/" . number_format($actual->pendientes_normales_pendiente, 2) . ".");

        // --- PASO 5: activar el nuevo batch -----------------------------------
        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'receivables')->first();
        $lotePrevio = $stateRow ? $stateRow->active_batch_id : null;

        DB::connection('supabase')->transaction(function () use ($batchId) {
            DB::connection('supabase')->table('sync_state')->upsert([
                'dataset' => 'receivables',
                'active_batch_id' => $batchId,
                'last_success_at' => now(),
                'last_run_status' => 'success',
                'last_error_message' => null
            ], ['dataset'], ['active_batch_id', 'last_success_at', 'last_run_status', 'last_error_message']);
        });

        $this->info("Batch {$batchId} activado como active_batch_id de receivables.");

        // --- PASO 6: limpieza de batches antiguos -----------------------------
        if ($lotePrevio) {
            $deleted = DB::connection('supabase')->table('receivables')
                ->where('batch_id', '!=', $batchId)
                ->where('batch_id', '!=', $lotePrevio)
                ->delete();
            $this->info("Limpieza: se eliminaron {$deleted} batches antiguos de receivables.");
        }

        return $insertedCount;
    }

    /**
     * Recalcula y actualiza un mes específico en stats_sales_monthly
     */
    private function recalculateHistoricalMonth(int $year, int $month): void
    {
        $this->info("Recalculando agregados del mes {$year}-{$month} en el ERP...");

        $erpRevenue = DB::connection('erp')->select("
            SELECT 
                SUM(importe_impuestos) as revenue,
                COUNT(*) as orders_count
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) = ? AND MONTH(fecha_venta) = ?
                AND tipo_venta IN (2, 4, 5)
                AND ISNULL(anulada, '') <> 'S'
        ", [$year, $month])[0] ?? null;

        $erpCost = DB::connection('erp')->select("
            SELECT 
                SUM(l.precio_coste * l.cantidad) as total_cost,
                SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            WHERE YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) = ?
                AND v.tipo_venta IN (2, 4, 5)
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL
                AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                AND l.precio_coste IS NOT NULL
                AND l.precio_coste > 0
                AND l.precio_coste < 100000
                AND l.cantidad > 0
        ", [$year, $month])[0] ?? null;

        $revenue = (float)($erpRevenue->revenue ?? 0);
        $ordersCount = (int)($erpRevenue->orders_count ?? 0);
        $totalCost = (float)($erpCost->total_cost ?? 0);
        $grossProfit = (float)($erpCost->gross_profit ?? 0);

        DB::connection('supabase')->table('stats_sales_monthly')->upsert([
            'year' => $year,
            'month' => $month,
            'revenue' => $revenue,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'orders_count' => $ordersCount,
            'synced_at' => now()
        ], ['year', 'month'], ['revenue', 'total_cost', 'gross_profit', 'orders_count', 'synced_at']);
    }

    /**
     * Reconcilia las ventas del día del quick sync sin recorrer el histórico.
     *
     * Las cabeceras presentes en Supabase para hoy que ya no existen en ERP se
     * eliminan junto con sus líneas. Así se cubren borrados físicos y cambios de
     * fecha sin ejecutar el Garbage Collector global.
     */
    private function cleanupQuickSalesOrphans(string $salesDate): int
    {
        $this->info("Quick sync: conciliando borrados físicos de {$salesDate}...");

        $erpHeaders = DB::connection('erp')->select("
            SELECT cod_venta, tipo_venta, cod_empresa, cod_caja
            FROM hist_ventas_cabecera
            WHERE tipo_venta IN (2, 4, 5)
                AND CAST(fecha_venta AS DATE) = ?
        ", [$salesDate]);

        $erpKeys = [];
        foreach ($erpHeaders as $header) {
            $erpKeys[$this->salesKey(
                $header->cod_venta,
                $header->tipo_venta,
                $header->cod_empresa,
                $header->cod_caja
            )] = true;
        }

        $headersToDelete = DB::connection('supabase')->table('sales_headers')
            ->select('cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja')
            ->whereDate('fecha_venta', $salesDate)
            ->get()
            ->filter(fn ($header) => !isset($erpKeys[$this->salesKey(
                $header->cod_venta,
                $header->tipo_venta,
                $header->cod_empresa,
                $header->cod_caja
            )]));

        foreach (array_chunk($headersToDelete->all(), 100) as $headersChunk) {
            DB::connection('supabase')->transaction(function () use ($headersChunk) {
                $linesQuery = DB::connection('supabase')->table('sales_lines');
                $this->applySalesCompositeKeyFilter($linesQuery, $headersChunk);
                $linesQuery->delete();

                $headersQuery = DB::connection('supabase')->table('sales_headers');
                $this->applySalesCompositeKeyFilter($headersQuery, $headersChunk);
                $headersQuery->delete();
            });
        }

        $deletedCount = $headersToDelete->count();
        $this->info("Quick sync: conciliación diaria finalizada ({$deletedCount} cabeceras eliminadas).");

        return $deletedCount;
    }

    private function applySalesCompositeKeyFilter($query, array $headers): void
    {
        $query->where(function ($keysQuery) use ($headers) {
            foreach ($headers as $header) {
                $keysQuery->orWhere(function ($keyQuery) use ($header) {
                    $keyQuery->where('cod_venta', $header->cod_venta)
                        ->where('tipo_venta', $header->tipo_venta)
                        ->where('cod_empresa', $header->cod_empresa)
                        ->where('cod_caja', $header->cod_caja);
                });
            }
        });
    }

    private function salesKey($codVenta, $tipoVenta, $codEmpresa, $codCaja): string
    {
        return implode('-', [
            trim((string) $codVenta),
            (int) $tipoVenta,
            trim((string) $codEmpresa),
            trim((string) $codCaja),
        ]);
    }

    /**
     * Garbage Collector para buscar y eliminar de Supabase registros borrados físicamente del ERP
     */
    private function cleanupSalesOrphans(): int
    {
        $this->info("Iniciando Garbage Collector de ventas eliminadas físicamente en ERP...");
        
        $offset = 0;
        $limit = 300;
        $keysToDelete = [];

        while (true) {
            $headers = DB::connection('supabase')->table('sales_headers')
                ->select('cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja')
                ->orderBy('cod_venta')
                ->orderBy('tipo_venta')
                ->orderBy('cod_empresa')
                ->orderBy('cod_caja')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($headers->isEmpty()) {
                break;
            }

            $orQueries = [];
            $params = [];

            foreach ($headers as $h) {
                $orQueries[] = "(cod_venta = ? AND tipo_venta = ? AND cod_empresa = ? AND cod_caja = ?)";
                $params[] = trim($h->cod_venta);
                $params[] = (int)$h->tipo_venta;
                $params[] = trim($h->cod_empresa);
                $params[] = trim($h->cod_caja);
            }

            $whereClause = implode(' OR ', $orQueries);

            // Consultar cuáles existen en SQL Server
            $existing = DB::connection('erp')->select("
                SELECT cod_venta, tipo_venta, cod_empresa, cod_caja
                FROM hist_ventas_cabecera
                WHERE {$whereClause}
            ", $params);

            $existingMap = [];
            foreach ($existing as $e) {
                $key = trim($e->cod_venta) . '-' . $e->tipo_venta . '-' . trim($e->cod_empresa) . '-' . trim($e->cod_caja);
                $existingMap[$key] = true;
            }

            foreach ($headers as $h) {
                $key = trim($h->cod_venta) . '-' . $h->tipo_venta . '-' . trim($h->cod_empresa) . '-' . trim($h->cod_caja);
                if (!isset($existingMap[$key])) {
                    $keysToDelete[] = [
                        'cod_venta' => $h->cod_venta,
                        'tipo_venta' => $h->tipo_venta,
                        'cod_empresa' => $h->cod_empresa,
                        'cod_caja' => $h->cod_caja
                    ];
                }
            }

            $offset += $limit;
        }

        $deletedCount = 0;
        if (!empty($keysToDelete)) {
            $this->warn("Detectados " . count($keysToDelete) . " registros huérfanos/eliminados en ERP. Procediendo al borrado en Supabase...");
            
            foreach ($keysToDelete as $k) {
                // Borrar líneas
                DB::connection('supabase')->table('sales_lines')
                    ->where('cod_venta', $k['cod_venta'])
                    ->where('tipo_venta', $k['tipo_venta'])
                    ->where('cod_empresa', $k['cod_empresa'])
                    ->where('cod_caja', $k['cod_caja'])
                    ->delete();

                // Borrar cabecera
                DB::connection('supabase')->table('sales_headers')
                    ->where('cod_venta', $k['cod_venta'])
                    ->where('tipo_venta', $k['tipo_venta'])
                    ->where('cod_empresa', $k['cod_empresa'])
                    ->where('cod_caja', $k['cod_caja'])
                    ->delete();
                    
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("✓ Garbage Collector finalizado. Se eliminaron {$deletedCount} cabeceras y sus correspondientes líneas.");
        } else {
            $this->info("✓ Garbage Collector finalizado. No se detectaron discrepancias.");
        }

        return $deletedCount;
    }

    /**
     * Sincroniza agregados históricos unificados de 2012-2024 en Supabase
     */
    private function syncHistorical(string $runId): int
    {
        $this->info("Iniciando carga de agregados históricos unificados (2012 - 2024)...");
        
        $totalProcessed = 0;

        // 1. stats_historical_kpis
        $this->info("- Sincronizando stats_historical_kpis...");
        $kpisRevenues = DB::connection('erp')->select("
            SELECT
                YEAR(v.fecha_venta) as year,
                MONTH(v.fecha_venta) as month,
                SUM(v.importe_impuestos) as revenue,
                COUNT(*) as orders_count,
                SUM(v.importe_pendiente) as pending_amount,
                COUNT(DISTINCT v.cod_cliente) as unique_clients
            FROM hist_ventas_cabecera v
            WHERE YEAR(v.fecha_venta) >= 2012 AND YEAR(v.fecha_venta) < 2025
                AND ISNULL(v.anulada, '') <> 'S'
                AND v.tipo_venta IN (2,4,5)
            GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
        ");

        $kpisCosts = DB::connection('erp')->select("
            SELECT 
                YEAR(v.fecha_venta) as year,
                MONTH(v.fecha_venta) as month,
                SUM(l.precio_coste * l.cantidad) as total_cost,
                SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            WHERE YEAR(v.fecha_venta) >= 2012 AND YEAR(v.fecha_venta) < 2025
                AND v.tipo_venta IN (2, 4, 5)
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL
                AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                AND l.precio_coste IS NOT NULL
                AND l.precio_coste > 0
                AND l.precio_coste < 100000
                AND l.cantidad > 0
            GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
        ");

        // Agrupar costes
        $costsGrouped = [];
        foreach ($kpisCosts as $c) {
            $key = "{$c->year}-{$c->month}";
            $costsGrouped[$key] = [
                'total_cost' => (float)$c->total_cost,
                'gross_profit' => (float)$c->gross_profit
            ];
        }

        $kpisChunk = [];
        foreach ($kpisRevenues as $row) {
            $key = "{$row->year}-{$row->month}";
            $costData = $costsGrouped[$key] ?? ['total_cost' => 0.0, 'gross_profit' => (float)$row->revenue];
            
            $kpisChunk[] = [
                'year' => (int)$row->year,
                'month' => (int)$row->month,
                'revenue' => (float)$row->revenue,
                'total_cost' => $costData['total_cost'],
                'gross_profit' => $costData['gross_profit'],
                'orders_count' => (int)$row->orders_count,
                'pending_amount' => (float)$row->pending_amount,
                'unique_clients' => (int)$row->unique_clients,
                'synced_at' => now()
            ];
        }

        if (!empty($kpisChunk)) {
            foreach (array_chunk($kpisChunk, 2000) as $chunk) {
                DB::connection('supabase')->table('stats_historical_kpis')->upsert($chunk, ['year', 'month']);
            }
            $totalProcessed += count($kpisChunk);
        }
        unset($kpisRevenues, $kpisCosts, $costsGrouped, $kpisChunk);

        // 2. stats_historical_sellers
        $this->info("- Sincronizando stats_historical_sellers...");
        $sellers = DB::connection('erp')->select("
            SELECT
                YEAR(v.fecha_venta) as year,
                MONTH(v.fecha_venta) as month,
                v.cod_vendedor,
                MAX(v.nombre_vendedor) as nombre_vendedor,
                COUNT(*) as orders_count,
                SUM(v.importe_impuestos) as total_sales
            FROM hist_ventas_cabecera v
            WHERE YEAR(v.fecha_venta) >= 2012 AND YEAR(v.fecha_venta) < 2025
                AND ISNULL(v.anulada, '') <> 'S'
                AND v.tipo_venta IN (2,4,5)
            GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta), v.cod_vendedor
        ");
        
        $sellersChunk = [];
        foreach ($sellers as $row) {
            $sellersChunk[] = [
                'year' => (int)$row->year,
                'month' => (int)$row->month,
                'cod_vendedor' => trim($row->cod_vendedor),
                'nombre_vendedor' => $row->nombre_vendedor ? trim($row->nombre_vendedor) : null,
                'orders_count' => (int)$row->orders_count,
                'total_sales' => (float)$row->total_sales,
                'synced_at' => now()
            ];
        }
        if (!empty($sellersChunk)) {
            foreach (array_chunk($sellersChunk, 2000) as $chunk) {
                DB::connection('supabase')->table('stats_historical_sellers')->upsert($chunk, ['year', 'month', 'cod_vendedor']);
            }
            $totalProcessed += count($sellersChunk);
        }
        unset($sellers, $sellersChunk);

        // 3. stats_historical_warehouses
        $this->info("- Sincronizando stats_historical_warehouses...");
        $warehouses = DB::connection('erp')->select("
            SELECT
                YEAR(v.fecha_venta) as year,
                MONTH(v.fecha_venta) as month,
                v.cod_almacen,
                COUNT(*) as orders_count,
                SUM(v.importe_impuestos) as total_sales
            FROM hist_ventas_cabecera v
            WHERE YEAR(v.fecha_venta) >= 2012 AND YEAR(v.fecha_venta) < 2025
                AND ISNULL(v.anulada, '') <> 'S'
                AND v.tipo_venta IN (2,4,5)
                AND v.cod_almacen IS NOT NULL AND v.cod_almacen <> ''
            GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta), v.cod_almacen
        ");
        
        $warehousesChunk = [];
        foreach ($warehouses as $row) {
            $warehousesChunk[] = [
                'year' => (int)$row->year,
                'month' => (int)$row->month,
                'cod_almacen' => trim($row->cod_almacen),
                'orders_count' => (int)$row->orders_count,
                'total_sales' => (float)$row->total_sales,
                'synced_at' => now()
            ];
        }
        if (!empty($warehousesChunk)) {
            foreach (array_chunk($warehousesChunk, 2000) as $chunk) {
                DB::connection('supabase')->table('stats_historical_warehouses')->upsert($chunk, ['year', 'month', 'cod_almacen']);
            }
            $totalProcessed += count($warehousesChunk);
        }
        unset($warehouses, $warehousesChunk);

        // 4. stats_historical_families
        $this->info("- Sincronizando stats_historical_families...");
        $families = DB::connection('erp')->select("
            SELECT
                YEAR(v.fecha_venta) as year,
                MONTH(v.fecha_venta) as month,
                a.cod_familia,
                MAX(fa.descripcion) as family_name,
                SUM(l.importe_impuestos) as total
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            INNER JOIN articulos a ON a.cod_articulo = l.cod_articulo
            LEFT JOIN familias fa ON fa.cod_familia = a.cod_familia
            WHERE YEAR(v.fecha_venta) >= 2012 AND YEAR(v.fecha_venta) < 2025
                AND ISNULL(v.anulada, '') <> 'S'
                AND v.tipo_venta IN (2,4,5)
            GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta), a.cod_familia
        ");
        
        $familiesChunk = [];
        foreach ($families as $row) {
            $familiesChunk[] = [
                'year' => (int)$row->year,
                'month' => (int)$row->month,
                'cod_familia' => trim($row->cod_familia),
                'family_name' => $row->family_name ? trim($row->family_name) : null,
                'total' => (float)$row->total,
                'synced_at' => now()
            ];
        }
        if (!empty($familiesChunk)) {
            foreach (array_chunk($familiesChunk, 2000) as $chunk) {
                DB::connection('supabase')->table('stats_historical_families')->upsert($chunk, ['year', 'month', 'cod_familia']);
            }
            $totalProcessed += count($familiesChunk);
        }
        unset($families, $familiesChunk);

        // 5. stats_historical_clients
        $this->info("- Sincronizando stats_historical_clients...");
        for ($y = 2012; $y <= 2024; $y++) {
            $this->info("  * Procesando clientes de año: {$y}...");
            $clients = DB::connection('erp')->select("
                SELECT
                    YEAR(v.fecha_venta) as year,
                    MONTH(v.fecha_venta) as month,
                    v.cod_cliente,
                    MAX(c.razon_social) as razon_social,
                    MAX(c.poblacion) as poblacion,
                    MAX(c.provincia) as provincia,
                    SUM(v.importe_impuestos) as total_spent,
                    COUNT(*) as orders_count,
                    (
                        SELECT TOP 1 v2.cod_vendedor
                        FROM hist_ventas_cabecera v2
                        WHERE v2.cod_cliente = v.cod_cliente
                            AND YEAR(v2.fecha_venta) = ? AND MONTH(v2.fecha_venta) = MONTH(v.fecha_venta)
                            AND ISNULL(v2.anulada, '') <> 'S'
                            AND v2.tipo_venta IN (2,4,5)
                        GROUP BY v2.cod_vendedor
                        ORDER BY SUM(v2.importe_impuestos) DESC
                    ) as vendedor_principal
                FROM hist_ventas_cabecera v
                LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                WHERE YEAR(v.fecha_venta) = ?
                    AND ISNULL(v.anulada, '') <> 'S'
                    AND v.tipo_venta IN (2,4,5)
                GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta), v.cod_cliente
            ", [$y, $y]);

            $clientsChunk = [];
            foreach ($clients as $row) {
                $clientsChunk[] = [
                    'year' => (int)$row->year,
                    'month' => (int)$row->month,
                    'cod_cliente' => trim($row->cod_cliente),
                    'razon_social' => $row->razon_social ? trim($row->razon_social) : null,
                    'poblacion' => $row->poblacion ? trim($row->poblacion) : null,
                    'provincia' => $row->provincia ? trim($row->provincia) : null,
                    'total_spent' => (float)$row->total_spent,
                    'orders_count' => (int)$row->orders_count,
                    'vendedor_principal' => $row->vendedor_principal ? trim($row->vendedor_principal) : null,
                    'synced_at' => now()
                ];

                if (count($clientsChunk) >= 3000) {
                    DB::connection('supabase')->table('stats_historical_clients')->upsert($clientsChunk, ['year', 'month', 'cod_cliente']);
                    $totalProcessed += count($clientsChunk);
                    $clientsChunk = [];
                }
            }

            if (!empty($clientsChunk)) {
                DB::connection('supabase')->table('stats_historical_clients')->upsert($clientsChunk, ['year', 'month', 'cod_cliente']);
                $totalProcessed += count($clientsChunk);
            }
            unset($clients, $clientsChunk);
        }

        // 6. stats_historical_products
        $this->info("- Sincronizando stats_historical_products...");
        for ($y = 2012; $y <= 2024; $y++) {
            $this->info("  * Procesando productos de año: {$y}...");
            $products = DB::connection('erp')->select("
                SELECT
                    YEAR(v.fecha_venta) as year,
                    MONTH(v.fecha_venta) as month,
                    l.cod_articulo,
                    MAX(a.descripcion_web) as descripcion,
                    SUM(l.cantidad) as total_qty,
                    SUM(l.importe_impuestos) as total_revenue,
                    MAX(a.cod_familia) as cod_family
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera v
                    ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                    AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                LEFT JOIN articulos a ON a.cod_articulo = l.cod_articulo
                WHERE YEAR(v.fecha_venta) = ?
                    AND ISNULL(v.anulada, '') <> 'S'
                    AND v.tipo_venta IN (2,4,5)
                    AND l.cod_articulo IS NOT NULL
                GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta), l.cod_articulo
            ", [$y]);

            $productsChunk = [];
            foreach ($products as $row) {
                $productsChunk[] = [
                    'year' => (int)$row->year,
                    'month' => (int)$row->month,
                    'cod_articulo' => trim($row->cod_articulo),
                    'descripcion' => $row->descripcion ? trim($row->descripcion) : null,
                    'total_qty' => (float)$row->total_qty,
                    'total_revenue' => (float)$row->total_revenue,
                    'cod_family' => $row->cod_family ? trim($row->cod_family) : null,
                    'synced_at' => now()
                ];

                if (count($productsChunk) >= 3000) {
                    DB::connection('supabase')->table('stats_historical_products')->upsert($productsChunk, ['year', 'month', 'cod_articulo']);
                    $totalProcessed += count($productsChunk);
                    $productsChunk = [];
                }
            }

            if (!empty($productsChunk)) {
                DB::connection('supabase')->table('stats_historical_products')->upsert($productsChunk, ['year', 'month', 'cod_articulo']);
                $totalProcessed += count($productsChunk);
            }
            unset($products, $productsChunk);
        }

        $this->info("✓ Sincronización histórica completada. Filas insertadas: {$totalProcessed}");
        return $totalProcessed;
    }
}
