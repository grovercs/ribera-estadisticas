<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateAlerts extends Command
{
    protected $signature = 'alerts:generate {--limit=50 : Límite de alertas por tipo}';

    protected $description = 'Genera alertas automáticas basadas en datos reales del ERP';

    public function handle(): int
    {
        $now = now();
        $count = 0;
        $limit = (int) $this->option('limit');

        // Archivar alertas activas anteriores (mantener histórico)
        DB::table('alerts')
            ->where('status', 'active')
            ->update([
                'status' => 'resolved',
                'resolved_at' => $now,
            ]);

        $this->info('Archivadas alertas anteriores. Generando nuevas...');

        // 1. Productos con stock bajo o negativo (crítico)
        $this->info('  [1/4] Detectando productos con stock bajo...');
        $count += $this->generateLowStockAlerts($now, $limit);

        // 2. Clientes dormidos (sin compras en 6 meses)
        $this->info('  [2/4] Detectando clientes dormidos...');
        $count += $this->generateDormantClientsAlerts($now, $limit);

        // 3. Productos con rotación baja (stock parado > 1 año)
        $this->info('  [3/4] Detectando productos con rotación baja...');
        $count += $this->generateLowRotationAlerts($now, $limit);

        // 4. Caída de ventas vs año anterior (por familia)
        $this->info('  [4/4] Detectando caída de ventas...');
        $count += $this->generateSalesDropAlerts($now, $limit);

        $this->info("✓ Total: {$count} alertas generadas.");
        return self::SUCCESS;
    }

    private function generateLowStockAlerts(Carbon $now, int $limit): int
    {
        // Productos con stock <= mínimo o negativo
        $alerts = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->leftJoin('erp_families as f', 'p.cod_familia', '=', 'f.cod_familia')
            ->select(
                's.cod_articulo',
                'p.marca',
                'f.descripcion as familia',
                DB::raw('SUM(s.existencias) as stock_actual'),
                DB::raw('MAX(s.minimos) as stock_minimo')
            )
            ->groupBy('s.cod_articulo', 'p.marca', 'f.descripcion')
            ->havingRaw('SUM(s.existencias) <= MAX(s.minimos)')
            ->orderBy('stock_actual')
            ->limit($limit)
            ->get();

        $inserts = [];
        foreach ($alerts as $item) {
            $severity = $item->stock_actual <= 0 ? 'critical' : 'warning';
            $inserts[] = [
                'type' => 'low_stock',
                'product_id' => null, // No hay relación directa con products
                'title' => "Stock bajo: {$item->marca} ({$item->cod_articulo})",
                'description' => "Familia: {$item->familia}. Stock: {$item->stock_actual} uds (mínimo: {$item->stock_minimo}).",
                'severity' => $severity,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($inserts)) {
            DB::table('alerts')->insert($inserts);
        }

        return count($inserts);
    }

    private function generateDormantClientsAlerts(Carbon $now, int $limit): int
    {
        // Clientes sin compras en últimos 6 meses PERO que sí compraron antes
        $sixMonthsAgo = $now->copy()->subMonths(6)->format('Y-m-d');
        $oneYearAgo = $now->copy()->subYear()->format('Y-m-d');

        $oneYearAgoDate = $now->copy()->subYear()->format('Y-m-d');

        $alerts = DB::table('erp_clients as c')
            // Clientes que SÍ tuvieron ventas históricas (último año)
            ->join('erp_sales as sh', function ($join) use ($oneYearAgo, $sixMonthsAgo) {
                $join->on('c.cod_cliente', '=', 'sh.cod_cliente')
                    ->where('sh.fecha_venta', '>=', $oneYearAgo)
                    ->where('sh.fecha_venta', '<', $sixMonthsAgo)
                    ->where('sh.anulada', 'N');
            })
            // Pero NO tuvieron ventas recientes (últimos 6 meses)
            ->leftJoin('erp_sales as sr', function ($join) use ($sixMonthsAgo) {
                $join->on('c.cod_cliente', '=', 'sr.cod_cliente')
                    ->where('sr.fecha_venta', '>=', $sixMonthsAgo)
                    ->where('sr.anulada', 'N');
            })
            ->whereNull('sr.cod_venta')
            // Excluir clientes genéricos/comodín
            ->whereNotIn('c.cod_cliente', [0, 1, 2, 3, 5, 10]) // Clientes VARIOS, CONTADO, etc.
            ->where(function ($q) use ($oneYearAgoDate) {
                $q->whereNull('c.fecha_baja')
                  ->orWhere('c.fecha_baja', '>=', $oneYearAgoDate);
            })
            ->select('c.cod_cliente', 'c.razon_social', 'c.poblacion', 'c.provincia')
            ->distinct()
            ->orderBy('c.razon_social')
            ->limit($limit)
            ->get();

        $inserts = [];
        foreach ($alerts as $client) {
            $inserts[] = [
                'type' => 'high_demand',
                'client_id' => null,
                'title' => "Cliente dormido: {$client->razon_social}",
                'description' => "Sin compras desde hace 6+ meses (última compra: +6 meses). Ubicación: {$client->poblacion} ({$client->provincia}).",
                'severity' => 'warning',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($inserts)) {
            DB::table('alerts')->insert($inserts);
        }

        return count($inserts);
    }

    private function generateLowRotationAlerts(Carbon $now, int $limit): int
    {
        // Productos con stock alto y ventas mínimas en último año
        $oneYearAgo = $now->copy()->subYear()->format('Y-m-d');

        $ventasAgg = DB::table('erp_sale_lines as sl')
            ->join('erp_sales as s', function ($join) {
                $join->on('sl.cod_venta', '=', 's.cod_venta')
                    ->on('sl.tipo_venta', '=', 's.tipo_venta')
                    ->on('sl.cod_empresa', '=', 's.cod_empresa')
                    ->on('sl.cod_caja', '=', 's.cod_caja');
            })
            ->where('s.fecha_venta', '>=', $oneYearAgo)
            ->select('sl.cod_articulo', DB::raw('SUM(sl.cantidad) as total_vendido'))
            ->groupBy('sl.cod_articulo');

        $alerts = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->leftJoin('erp_families as f', 'p.cod_familia', '=', 'f.cod_familia')
            ->leftJoinSub($ventasAgg, 'v', 's.cod_articulo', '=', 'v.cod_articulo')
            ->select(
                's.cod_articulo',
                'p.marca',
                'f.descripcion as familia',
                DB::raw('SUM(s.existencias) as stock_total'),
                DB::raw('COALESCE(SUM(v.total_vendido), 0) as vendido_anio')
            )
            ->groupBy('s.cod_articulo', 'p.marca', 'f.descripcion')
            ->havingRaw('SUM(s.existencias) > 50')
            ->havingRaw('COALESCE(SUM(v.total_vendido), 0) < 20')
            ->orderByDesc('stock_total')
            ->limit($limit)
            ->get();

        $inserts = [];
        foreach ($alerts as $item) {
            $inserts[] = [
                'type' => 'delayed_shipment',
                'product_id' => null,
                'title' => "Rotación baja: {$item->marca} ({$item->cod_articulo})",
                'description' => "Familia: {$item->familia}. Stock: {$item->stock_total} uds. Vendido último año: {$item->vendido_anio} uds.",
                'severity' => 'info',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($inserts)) {
            DB::table('alerts')->insert($inserts);
        }

        return count($inserts);
    }

    private function generateSalesDropAlerts(Carbon $now, int $limit): int
    {
        // Familias con caída de ventas: último mes vs mes anterior
        // (adaptado para bases de datos con pocos meses de histórico)
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth()->format('Y-m-d');
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth()->format('Y-m-d');
        $thisMonthStart = $now->copy()->startOfMonth()->format('Y-m-d');

        // Verificar si hay datos suficientes
        $minDate = DB::table('erp_sales')->min('fecha_venta');
        if (!$minDate || strtotime($minDate) > strtotime($lastMonthStart)) {
            // No hay datos del mes anterior completo, saltar esta alerta
            return 0;
        }

        $ventasUltimoMes = DB::table('erp_sales as s')
            ->join('erp_sale_lines as sl', function ($join) {
                $join->on('sl.cod_venta', '=', 's.cod_venta')
                    ->on('sl.tipo_venta', '=', 's.tipo_venta')
                    ->on('sl.cod_empresa', '=', 's.cod_empresa')
                    ->on('sl.cod_caja', '=', 's.cod_caja');
            })
            ->join('erp_products as p', 'sl.cod_articulo', '=', 'p.cod_articulo')
            ->whereBetween('s.fecha_venta', [$lastMonthStart, $lastMonthEnd])
            ->where('s.anulada', 'N')
            ->select('p.cod_familia', DB::raw('SUM(sl.importe_impuestos) as total_ultimo'))
            ->groupBy('p.cod_familia');

        $ventasEsteMes = DB::table('erp_sales as s')
            ->join('erp_sale_lines as sl', function ($join) {
                $join->on('sl.cod_venta', '=', 's.cod_venta')
                    ->on('sl.tipo_venta', '=', 's.tipo_venta')
                    ->on('sl.cod_empresa', '=', 's.cod_empresa')
                    ->on('sl.cod_caja', '=', 's.cod_caja');
            })
            ->join('erp_products as p', 'sl.cod_articulo', '=', 'p.cod_articulo')
            ->where('s.fecha_venta', '>=', $thisMonthStart)
            ->where('s.anulada', 'N')
            ->select('p.cod_familia', DB::raw('SUM(sl.importe_impuestos) as total_actual'))
            ->groupBy('p.cod_familia');

        // Comparar: familias que vendieron el mes pasado y tienen >50% caída este mes
        // (ajustado proporcionalmente por días del mes)
        $diasMesActual = max(1, (int) $now->day);
        $diasMesCompleto = $now->copy()->startOfMonth()->daysInMonth;
        $factorAjuste = $diasMesCompleto / $diasMesActual;

        $alerts = DB::table('erp_families as f')
            ->joinSub($ventasUltimoMes, 'ult', 'f.cod_familia', '=', 'ult.cod_familia')
            ->leftJoinSub($ventasEsteMes, 'act', 'f.cod_familia', '=', 'act.cod_familia')
            ->select(
                'f.cod_familia',
                'f.descripcion',
                DB::raw('ult.total_ultimo as ventas_ultimo_mes'),
                DB::raw('COALESCE(act.total_actual, 0) as ventas_actual'),
                DB::raw('COALESCE(act.total_actual, 0) * ' . $factorAjuste . ' as ventas_actual_proyectada'),
                DB::raw('CASE WHEN ult.total_ultimo > 0 THEN ((ult.total_ultimo - COALESCE(act.total_actual, 0) * ' . $factorAjuste . ') / ult.total_ultimo * 100) ELSE 0 END as caida_pct')
            )
            ->whereRaw('COALESCE(act.total_actual, 0) * ' . $factorAjuste . ' < ult.total_ultimo * 0.5') // Caída > 50%
            ->orderByDesc('caida_pct')
            ->limit($limit)
            ->get();

        $inserts = [];
        foreach ($alerts as $item) {
            $inserts[] = [
                'type' => 'high_demand',
                'product_id' => null,
                'title' => "Caída ventas familia: {$item->descripcion}",
                'description' => "Mes anterior: €{$item->ventas_ultimo_mes}. Mes actual (proyectado): €" . number_format($item->ventas_actual_proyectada, 2) . ". Caída estimada: " . number_format($item->caida_pct, 1) . "%.",
                'severity' => 'critical',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($inserts)) {
            DB::table('alerts')->insert($inserts);
        }

        return count($inserts);
    }
}
