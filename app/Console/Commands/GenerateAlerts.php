<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateAlerts extends Command
{
    protected $signature = 'app:generate-alerts';

    protected $description = 'Genera alertas automáticas basadas en datos reales del ERP';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        // Archivar alertas activas anteriores
        DB::table('alerts')->where('status', 'active')->update([
            'status' => 'resolved',
            'resolved_at' => $now,
        ]);

        // 1. Productos sin stock (top 50)
        $this->info('Detectando productos sin stock...');
        $stockAgg = DB::table('erp_stocks')
            ->select('cod_articulo', DB::raw('SUM(existencias) as stock_total'))
            ->groupBy('cod_articulo')
            ->havingRaw('SUM(existencias) <= 0');

        $outOfStock = DB::table('erp_products as p')
            ->joinSub($stockAgg, 'agg', 'p.cod_articulo', '=', 'agg.cod_articulo')
            ->select('p.cod_articulo', 'p.marca', 'agg.stock_total')
            ->orderBy('p.cod_articulo')
            ->limit(50)
            ->get();

        $inserts = [];
        foreach ($outOfStock as $item) {
            $inserts[] = [
                'type' => 'low_stock',
                'title' => 'Sin stock: ' . ($item->marca ?? $item->cod_articulo),
                'description' => 'El artículo ' . $item->cod_articulo . ' tiene ' . $item->stock_total . ' unidades en stock.',
                'severity' => 'critical',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (! empty($inserts)) {
            DB::table('alerts')->insert($inserts);
            $count += count($inserts);
        }

        // 2. Clientes sin compras en 6 meses (top 50)
        $this->info('Detectando clientes dormidos...');
        $sixMonthsAgo = $now->copy()->subMonths(6)->format('Y-m-d');

        $dormantClients = DB::table('erp_clients as c')
            ->leftJoin('erp_sales as s', function ($join) use ($sixMonthsAgo) {
                $join->on('c.cod_cliente', '=', 's.cod_cliente')
                      ->where('s.fecha_venta', '>=', $sixMonthsAgo);
            })
            ->whereNull('s.cod_cliente')
            ->select('c.cod_cliente', 'c.razon_social')
            ->orderBy('c.cod_cliente')
            ->limit(50)
            ->get();

        $inserts = [];
        foreach ($dormantClients as $client) {
            $inserts[] = [
                'type' => 'overdue_payment',
                'title' => 'Cliente dormido: ' . ($client->razon_social ?? $client->cod_cliente),
                'description' => 'No ha realizado compras en los últimos 6 meses.',
                'severity' => 'warning',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (! empty($inserts)) {
            DB::table('alerts')->insert($inserts);
            $count += count($inserts);
        }

        // 3. Productos con mucho stock y pocas ventas (rotación baja, top 50)
        $this->info('Detectando productos con rotación baja...');
        $oneYearAgo = $now->copy()->subYear()->format('Y-m-d');

        $salesAgg = DB::table('erp_sale_lines')
            ->select('cod_articulo', DB::raw('SUM(cantidad) as total_qty'))
            ->where('created_at', '>=', $oneYearAgo)
            ->groupBy('cod_articulo');

        $lowRotation = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->leftJoinSub($salesAgg, 'sl', 's.cod_articulo', '=', 'sl.cod_articulo')
            ->select('s.cod_articulo', 'p.marca', DB::raw('SUM(s.existencias) as stock_total'), DB::raw('MAX(COALESCE(sl.total_qty, 0)) as sold_qty'))
            ->groupBy('s.cod_articulo', 'p.marca')
            ->havingRaw('SUM(s.existencias) > 100')
            ->havingRaw('MAX(COALESCE(sl.total_qty, 0)) < 10')
            ->orderBy('s.cod_articulo')
            ->limit(50)
            ->get();

        $inserts = [];
        foreach ($lowRotation as $item) {
            $inserts[] = [
                'type' => 'delayed_shipment',
                'title' => 'Rotación baja: ' . ($item->marca ?? $item->cod_articulo),
                'description' => 'Stock: ' . $item->stock_total . ' uds. Vendidas último año: ' . $item->sold_qty . ' uds.',
                'severity' => 'info',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (! empty($inserts)) {
            DB::table('alerts')->insert($inserts);
            $count += count($inserts);
        }

        $this->info("Generadas {$count} alertas.");
        return self::SUCCESS;
    }
}
