<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getSubfamilies(Request $request)
    {
        $family = $request->input('family');

        try {
            $subfamilies = DB::connection('erp')
                ->table('subfamilias')
                ->where('cod_familia', $family)
                ->select('cod_subfamilia', 'descripcion')
                ->orderBy('descripcion')
                ->get();
        } catch (\Exception $e) {
            $subfamilies = DB::table('erp_subfamilies')
                ->where('cod_familia', $family)
                ->select('cod_subfamilia', 'descripcion')
                ->orderBy('descripcion')
                ->get();
        }

        return response()->json($subfamilies);
    }

    public function comparison(Request $request)
    {
        // Obtener años disponibles desde SQL Server
        try {
            $availableYears = DB::connection('erp')
                ->select("
                    SELECT DISTINCT YEAR(fecha_venta) as year
                    FROM hist_ventas_cabecera
                    WHERE fecha_venta IS NOT NULL
                    ORDER BY year DESC
                ");
            $years = array_column($availableYears, 'year');
            $minYear = !empty($years) ? min($years) : 2012;
            $maxYear = !empty($years) ? max($years) : date('Y');
        } catch (\Exception $e) {
            $minYear = 2012;
            $maxYear = 2020;
        }

        $year1 = $request->input('year1', $maxYear - 1);
        $year2 = $request->input('year2', $maxYear);

        // Ajustar si los años seleccionados no existen
        if ($year1 < $minYear) $year1 = $minYear;
        if ($year1 > $maxYear) $year1 = $maxYear;
        if ($year2 < $minYear) $year2 = $minYear;
        if ($year2 > $maxYear) $year2 = $maxYear;

        $selectedFamily = $request->input('family', '');
        $selectedSubfamily = $request->input('subfamily', '');

        // Cargar familias desde SQL Server
        try {
            $allFamilies = DB::connection('erp')
                ->table('familias')
                ->select('cod_familia', 'descripcion')
                ->orderBy('descripcion')
                ->get();
        } catch (\Exception $e) {
            $allFamilies = DB::table('erp_families')
                ->select('cod_familia', 'descripcion')
                ->orderBy('descripcion')
                ->get();
        }

        // Cargar subfamilias si hay familia seleccionada
        $subfamilies = [];
        if ($selectedFamily) {
            try {
                $subfamilies = DB::connection('erp')
                    ->table('subfamilias')
                    ->where('cod_familia', $selectedFamily)
                    ->select('cod_subfamilia', 'descripcion')
                    ->orderBy('descripcion')
                    ->get();
            } catch (\Exception $e) {
                $subfamilies = DB::table('erp_subfamilies')
                    ->where('cod_familia', $selectedFamily)
                    ->select('cod_subfamilia', 'descripcion')
                    ->orderBy('descripcion')
                    ->get();
            }
        }

        $results = null;

        if ($request->has('compare')) {
            $results = $this->queryErpComparison($year1, $year2, $selectedFamily, $selectedSubfamily);
        }

        return view('reports.comparison', compact(
            'year1', 'year2', 'results', 'allFamilies', 'subfamilies',
            'selectedFamily', 'selectedSubfamily', 'minYear', 'maxYear'
        ));
    }

    private function queryErpComparison(int $year1, int $year2, ?string $family = null, ?string $subfamily = null): array
    {
        $erp = DB::connection('erp');

        // Construir condiciones de filtro
        $familyCondition = '';
        $params = [];

        if ($family && $subfamily) {
            $familyCondition = "
                INNER JOIN hist_ventas_linea lf ON v.cod_venta = lf.cod_venta
                    AND v.tipo_venta = lf.tipo_venta AND v.cod_empresa = lf.cod_empresa AND v.cod_caja = lf.cod_caja
                INNER JOIN articulos a ON lf.cod_articulo = a.cod_articulo
                WHERE a.cod_familia = ? AND a.cod_subfamilia = ?";
            $params = [$family, $subfamily];
        } elseif ($family) {
            $familyCondition = "
                INNER JOIN hist_ventas_linea lf ON v.cod_venta = lf.cod_venta
                    AND v.tipo_venta = lf.tipo_venta AND v.cod_empresa = lf.cod_empresa AND v.cod_caja = lf.cod_caja
                INNER JOIN articulos a ON lf.cod_articulo = a.cod_articulo
                WHERE a.cod_familia = ?";
            $params = [$family];
        }

        // KPIs por año
        $kpis = [];
        foreach ([$year1, $year2] as $year) {
            if ($family) {
                $kpiParams = array_merge($params, [$year]);
                $kpi = $erp->select("
                    SELECT
                        COUNT(DISTINCT v.cod_venta) as total_orders,
                        SUM(v.importe_impuestos) as total_sales,
                        AVG(v.importe_impuestos) as avg_ticket,
                        COUNT(DISTINCT v.cod_cliente) as unique_clients
                    FROM hist_ventas_cabecera v
                    $familyCondition
                        AND YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                ", $kpiParams)[0];
            } else {
                $kpi = $erp->select("
                    SELECT
                        COUNT(DISTINCT v.cod_venta) as total_orders,
                        SUM(v.importe_impuestos) as total_sales,
                        AVG(v.importe_impuestos) as avg_ticket,
                        COUNT(DISTINCT v.cod_cliente) as unique_clients
                    FROM hist_ventas_cabecera v
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                ", [$year])[0];
            }

            $kpis[$year] = $kpi;
        }

        // Cálculo de crecimientos y métricas financieras
        $growth = [
            'sales_growth' => $kpis[$year1]->total_sales > 0
                ? (($kpis[$year2]->total_sales - $kpis[$year1]->total_sales) / $kpis[$year1]->total_sales) * 100
                : 0,
            'ticket_growth' => $kpis[$year1]->avg_ticket > 0
                ? (($kpis[$year2]->avg_ticket - $kpis[$year1]->avg_ticket) / $kpis[$year1]->avg_ticket) * 100
                : 0,
            'clients_growth' => $kpis[$year1]->unique_clients > 0
                ? (($kpis[$year2]->unique_clients - $kpis[$year1]->unique_clients) / $kpis[$year1]->unique_clients) * 100
                : 0,
            'orders_growth' => $kpis[$year1]->total_orders > 0
                ? (($kpis[$year2]->total_orders - $kpis[$year1]->total_orders) / $kpis[$year1]->total_orders) * 100
                : 0,
        ];

        // Métricas financieras adicionales
        $financialMetrics = [
            'concentration_top10' => [], // % ventas top 10 productos
            'avg_order_value' => [
                $year1 => $kpis[$year1]->avg_ticket ?? 0,
                $year2 => $kpis[$year2]->avg_ticket ?? 0,
            ],
            'client_activity' => [], // clientes activos por año
        ];

        // Top 10 clientes por año
        $topClients = [];
        foreach ([$year1, $year2] as $year) {
            if ($family) {
                $clientParams = array_merge($params, [$year]);
                $topClients[$year] = $erp->select("
                    SELECT TOP 10
                        v.cod_cliente,
                        MAX(c.razon_social) as razon_social,
                        SUM(v.importe_impuestos) as total_spent,
                        COUNT(DISTINCT v.cod_venta) as order_count
                    FROM hist_ventas_cabecera v
                    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                    $familyCondition
                        AND YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_cliente
                    ORDER BY total_spent DESC
                ", $clientParams);
            } else {
                $topClients[$year] = $erp->select("
                    SELECT TOP 10
                        v.cod_cliente,
                        MAX(c.razon_social) as razon_social,
                        SUM(v.importe_impuestos) as total_spent,
                        COUNT(DISTINCT v.cod_venta) as order_count
                    FROM hist_ventas_cabecera v
                    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_cliente
                    ORDER BY total_spent DESC
                ", [$year]);
            }
        }

        // Top 20 productos por año con métricas de rotación
        $topProducts = [];
        foreach ([$year1, $year2] as $year) {
            if ($family) {
                $prodParams = array_merge($params, [$year]);
                $topProducts[$year] = $erp->select("
                    SELECT TOP 20
                        l.cod_articulo,
                        MAX(l.descripcion) as descripcion,
                        MAX(f.descripcion) as familia,
                        SUM(l.cantidad) as total_qty,
                        SUM(l.importe_impuestos) as total_revenue,
                        COUNT(DISTINCT v.cod_venta) as order_count
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                        AND l.cod_articulo IS NOT NULL
                    GROUP BY l.cod_articulo
                    ORDER BY total_revenue DESC
                ", $prodParams);
            } else {
                $topProducts[$year] = $erp->select("
                    SELECT TOP 20
                        l.cod_articulo,
                        MAX(l.descripcion) as descripcion,
                        MAX(f.descripcion) as familia,
                        SUM(l.cantidad) as total_qty,
                        SUM(l.importe_impuestos) as total_revenue,
                        COUNT(DISTINCT v.cod_venta) as order_count
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                        AND l.cod_articulo IS NOT NULL
                    GROUP BY l.cod_articulo
                    ORDER BY total_revenue DESC
                ", [$year]);
            }
        }

        // Productos combinados para comparación con métricas financieras
        $topProductsCombined = [];
        $allCodes = array_unique(array_merge(
            array_column($topProducts[$year1], 'cod_articulo'),
            array_column($topProducts[$year2], 'cod_articulo')
        ));

        $productsById = [];
        foreach ($topProducts[$year1] as $p) {
            $productsById[$p->cod_articulo] = ['year1' => $p];
        }
        foreach ($topProducts[$year2] as $p) {
            if (!isset($productsById[$p->cod_articulo])) {
                $productsById[$p->cod_articulo] = [];
            }
            $productsById[$p->cod_articulo]['year2'] = $p;
        }

        foreach ($allCodes as $code) {
            $p1 = $productsById[$code]['year1'] ?? null;
            $p2 = $productsById[$code]['year2'] ?? null;

            if (!$p1 && !$p2) continue;

            $year1Rev = $p1 ? (float)$p1->total_revenue : 0;
            $year2Rev = $p2 ? (float)$p2->total_revenue : 0;
            $growthRate = $year1Rev > 0 ? (($year2Rev - $year1Rev) / $year1Rev) * 100 : ($year2Rev > 0 ? 100 : 0);

            // Calcular ticket medio por producto
            $year1Ticket = $p1 && $p1->order_count > 0 ? $year1Rev / $p1->order_count : 0;
            $year2Ticket = $p2 && $p2->order_count > 0 ? $year2Rev / $p2->order_count : 0;

            $topProductsCombined[] = (object) [
                'cod_articulo' => $p2?->cod_articulo ?? $p1?->cod_articulo,
                'descripcion' => $p2?->descripcion ?? $p1?->descripcion ?? 'N/A',
                'familia' => $p2?->familia ?? $p1?->familia ?? 'N/A',
                'year1_revenue' => $year1Rev,
                'year2_revenue' => $year2Rev,
                'year1_qty' => $p1 ? (float)$p1->total_qty : 0,
                'year2_qty' => $p2 ? (float)$p2->total_qty : 0,
                'year1_orders' => $p1 ? (int)$p1->order_count : 0,
                'year2_orders' => $p2 ? (int)$p2->order_count : 0,
                'growth' => $growthRate,
                'year1_avg_ticket' => $year1Ticket,
                'year2_avg_ticket' => $year2Ticket,
            ];
        }

        usort($topProductsCombined, fn($a, $b) => $b->year2_revenue <=> $a->year2_revenue);
        $topProductsCombined = array_slice($topProductsCombined, 0, 20);

        // Evolución mensual
        $monthly = $erp->select("
            SELECT
                YEAR(fecha_venta) as year,
                MONTH(fecha_venta) as month,
                SUM(importe_impuestos) as total
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) IN (?, ?)
                AND ISNULL(anulada, '') <> 'S'
            GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
            ORDER BY year, month
        ", [$year1, $year2]);

        // Ventas por familia (Top 15)
        $byFamily = [];
        foreach ([$year1, $year2] as $year) {
            if ($family && $subfamily) {
                // Filtrar por familia y subfamilia específica
                $byFamily[$year] = $erp->select("
                    SELECT TOP 15
                        f.cod_familia,
                        MAX(f.descripcion) as familia,
                        SUM(l.importe_impuestos) as total_revenue
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    WHERE YEAR(v.fecha_venta) = ?
                        AND a.cod_familia = ?
                        AND a.cod_subfamilia = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY f.cod_familia
                    ORDER BY total_revenue DESC
                ", [$year, $family, $subfamily]);
            } elseif ($family) {
                // Filtrar solo por familia - mostrar subfamilias de esa familia
                $byFamily[$year] = $erp->select("
                    SELECT TOP 15
                        f.cod_familia,
                        MAX(f.descripcion) as familia,
                        SUM(l.importe_impuestos) as total_revenue
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    WHERE YEAR(v.fecha_venta) = ?
                        AND a.cod_familia = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY f.cod_familia
                    ORDER BY total_revenue DESC
                ", [$year, $family]);
            } else {
                // Sin filtro - mostrar todas las familias
                $byFamily[$year] = $erp->select("
                    SELECT TOP 15
                        f.cod_familia,
                        MAX(f.descripcion) as familia,
                        SUM(l.importe_impuestos) as total_revenue
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY f.cod_familia
                    ORDER BY total_revenue DESC
                ", [$year]);
            }
        }

        // Calcular concentración top 10
        foreach ([$year1, $year2] as $year) {
            if ($family) {
                $totalParams = array_merge($params, [$year]);
                $total = $erp->select("
                    SELECT SUM(l.importe_impuestos) as total
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                ", $totalParams)[0]->total ?? 0;
            } else {
                $total = $erp->select("
                    SELECT SUM(l.importe_impuestos) as total
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ?
                        AND ISNULL(v.anulada, '') <> 'S'
                ", [$year])[0]->total ?? 0;
            }

            $top10Revenue = array_sum(array_slice(array_column($topProducts[$year], 'total_revenue'), 0, 10));
            $financialMetrics['concentration_top10'][$year] = $total > 0 ? ($top10Revenue / $total) * 100 : 0;
        }

        return compact('kpis', 'growth', 'financialMetrics', 'topClients', 'topProducts', 'topProductsCombined', 'monthly', 'byFamily');
    }
}
