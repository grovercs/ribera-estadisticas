<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RiberaSyncToSupabase extends Command
{
    protected $signature = 'ribera:sync-to-supabase 
                            {dataset? : Dataset específico (kpis, warehouses, sellers, stock, clients, suppliers, monthly_history, sales, historical)}
                            {--period= : Período personalizado para ventas. Opciones: test_1month, full}';

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
            'historical'
        ];

        if ($dataset && !in_array($dataset, $availableDatasets, true)) {
            $this->error("Dataset no reconocido. Disponibles: " . implode(', ', $availableDatasets));
            return self::INVALID;
        }

        $datasetsToSync = $dataset ? [$dataset] : $availableDatasets;

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
     * Helper para parsear fechas locales de Madrid y convertirlas a UTC para Supabase
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
        
        // 1. Procesar borrados físicos primero
        $this->syncDeletedRecords('sales', 'hist_ventas_cabecera', 'sales_headers', 'cod_venta');

        $periodOption = $this->option('period');
        $stateRow = DB::connection('supabase')->table('sync_state')->where('dataset', 'sales')->first();
        $lastSuccess = $stateRow ? $stateRow->last_success_at : null;

        $currentYear = (int)date('Y');
        $prevYear = $currentYear - 1;

        // Determinar condiciones de rango de fechas de venta
        $querySql = "
            SELECT 
                cod_venta, tipo_venta, cod_empresa, cod_caja, cod_almacen, cod_cliente, razon_social, fecha_venta, cod_forma_liquidacion, cod_vendedor, nombre_vendedor, importe_impuestos as total_amount, importe_pendiente as pending_amount, anulada, fecha_modificacion
            FROM hist_ventas_cabecera
            WHERE tipo_venta IN (2, 4, 5)
        ";
        $params = [];

        if ($periodOption === 'test_1month') {
            $this->info("Ejecutando prueba controlada de 1 mes de ventas (Julio 2026)...");
            $querySql .= " AND fecha_venta >= '20260701' AND fecha_venta < '20260801'";
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
            $fechaVentaUtc = $this->parseDateToUtc($row->fecha_venta);
            $modifiedAtUtc = $this->parseDateToUtc($row->fecha_modificacion);

            $headerChunk[] = [
                'cod_venta' => trim($row->cod_venta),
                'tipo_venta' => (int)$row->tipo_venta,
                'cod_empresa' => trim($row->cod_empresa),
                'cod_caja' => trim($row->cod_caja),
                'cod_almacen' => $row->cod_almacen ? trim($row->cod_almacen) : null,
                'cod_cliente' => $row->cod_cliente ? trim($row->cod_cliente) : null,
                'razon_social' => $row->razon_social ? trim($row->razon_social) : null,
                'fecha_venta' => $fechaVentaUtc,
                'cod_forma_liquidacion' => $row->cod_forma_liquidacion ? trim($row->cod_forma_liquidacion) : null,
                'cod_vendedor' => $row->cod_vendedor ? trim($row->cod_vendedor) : null,
                'nombre_vendedor' => $row->nombre_vendedor ? trim($row->nombre_vendedor) : null,
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

        // 5. Registrar el sync_state final
        DB::connection('supabase')->table('sync_state')->upsert([
            'dataset' => 'sales',
            'active_batch_id' => null,
            'last_success_at' => now(),
            'last_run_status' => 'success',
            'last_error_message' => null
        ], ['dataset'], ['last_success_at', 'last_run_status', 'last_error_message']);

        // 6. Ejecutar Garbage Collector de borrados físicos no registrados
        $this->cleanupSalesOrphans();

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
            ['cod_almacen', 'cod_cliente', 'razon_social', 'fecha_venta', 'cod_forma_liquidacion', 'cod_vendedor', 'nombre_vendedor', 'total_amount', 'pending_amount', 'anulada', 'source_modified_at', 'synced_at']
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
