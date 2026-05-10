<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialController extends Controller
{
    public function index(Request $request)
    {
        // Obtener filtros de rango de fechas
        $yearFrom = $request->input('year_from', null);
        $yearTo = $request->input('year_to', null);
        $monthFrom = $request->input('month_from', 'all');
        $monthTo = $request->input('month_to', 'all');

        // Obtener años disponibles para el default
        $availableYears = DB::connection('erp')->select("
            SELECT DISTINCT YEAR(fecha_venta) as year
            FROM hist_ventas_cabecera
            WHERE fecha_venta IS NOT NULL
            ORDER BY year ASC
        ");
        $yearRange = array_column($availableYears, 'year');
        $minYear = !empty($yearRange) ? (int)$yearRange[0] : 2012;
        $maxYear = !empty($yearRange) ? (int)end($yearRange) : date('Y');

        // Por defecto, usar el último año disponible si no se especifica filtro
        if ($yearFrom === null) {
            $yearFrom = $maxYear;
        }
        if ($yearTo === null) {
            $yearTo = $yearFrom;
        }

        $cacheKey = 'financial_data_' . $yearFrom . '_' . $yearTo . '_' . $monthFrom . '_' . $monthTo;

        $data = cache()->remember($cacheKey, now()->addMinutes(10), function () use ($yearFrom, $yearTo, $monthFrom, $monthTo, $minYear, $maxYear, $yearRange) {
            try {
                $erp = DB::connection('erp');

                // Construir cláusula WHERE para filtros
                // Filtrar artículos corruptos: ALMACEN, FERRETERIA, etc. y precios absurdos
                $whereClause = "WHERE ISNULL(v.anulada, '') <> 'S'
                    AND l.cod_articulo IS NOT NULL
                    AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                    AND l.precio_coste IS NOT NULL
                    AND l.precio_coste > 0
                    AND l.precio_coste < 100000
                    AND l.cantidad > 0";
                $params = [];

                if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
                    $whereClause .= ' AND YEAR(v.fecha_venta) >= ?';
                    $params[] = $yearFrom;
                }
                if ($yearTo !== 'all' && is_numeric($yearTo)) {
                    $whereClause .= ' AND YEAR(v.fecha_venta) <= ?';
                    $params[] = $yearTo;
                }
                if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
                    $whereClause .= ' AND (YEAR(v.fecha_venta) > ? OR (YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) >= ?))';
                    $params[] = $yearFrom;
                    $params[] = $yearFrom;
                    $params[] = $monthFrom;
                }
                if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
                    $whereClause .= ' AND (YEAR(v.fecha_venta) < ? OR (YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) <= ?))';
                    $params[] = $yearTo;
                    $params[] = $yearTo;
                    $params[] = $monthTo;
                }

                // === KPIs PRINCIPALES ===
                $kpis = $erp->select("
                    SELECT
                        SUM(l.importe_impuestos) as revenue,
                        SUM(l.precio_coste * l.cantidad) as total_cost,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit,
                        COUNT(DISTINCT v.cod_venta) as total_orders,
                        COUNT(DISTINCT v.cod_cliente) as unique_clients
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    $whereClause
                ", $params)[0];

                $revenue = (float)($kpis->revenue ?? 0);
                $totalCost = (float)($kpis->total_cost ?? 0);
                $grossProfit = (float)($kpis->gross_profit ?? 0);
                $marginRate = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
                $avgTicket = $revenue / ($kpis->total_orders ?? 1);
                $revenuePerClient = $revenue / ($kpis->unique_clients ?? 1);

                // === MARGEN POR FAMILIA (Top 15) ===
                $marginByFamily = $erp->select("
                    SELECT TOP 15
                        f.cod_familia,
                        MAX(f.descripcion) as familia,
                        SUM(l.importe_impuestos) as revenue,
                        SUM(l.precio_coste * l.cantidad) as total_cost,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit,
                        COUNT(DISTINCT v.cod_venta) as orders
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    $whereClause
                    GROUP BY f.cod_familia
                    ORDER BY gross_profit DESC
                ", $params);

                // Añadir cálculo de margen %
                foreach ($marginByFamily as &$family) {
                    $family->margin_rate = $family->revenue > 0
                        ? (($family->revenue - $family->total_cost) / $family->revenue) * 100
                        : 0;
                }

                // === MARGEN POR SUBFAMILIA (Top 20) ===
                $marginBySubfamily = $erp->select("
                    SELECT TOP 20
                        f.cod_familia,
                        MAX(fa.descripcion) as familia,
                        a.cod_subfamilia,
                        MAX(s.descripcion) as subfamilia,
                        SUM(l.importe_impuestos) as revenue,
                        SUM(l.precio_coste * l.cantidad) as total_cost,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit,
                        COUNT(DISTINCT v.cod_venta) as orders
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    INNER JOIN subfamilias s ON a.cod_subfamilia = s.cod_subfamilia AND s.cod_familia = a.cod_familia
                    INNER JOIN familias fa ON f.cod_familia = fa.cod_familia
                    $whereClause
                        AND a.cod_subfamilia IS NOT NULL
                    GROUP BY f.cod_familia, a.cod_subfamilia
                    ORDER BY gross_profit DESC
                ", $params);

                foreach ($marginBySubfamily as &$subfamily) {
                    $subfamily->margin_rate = $subfamily->revenue > 0
                        ? (($subfamily->revenue - $subfamily->total_cost) / $subfamily->revenue) * 100
                        : 0;
                }

                // === PRODUCTOS ESTRELLA (Alta rotación + Buen margen) ===
                $starProducts = $erp->select("
                    SELECT TOP 20
                        l.cod_articulo,
                        MAX(l.descripcion) as descripcion,
                        MAX(f.descripcion) as familia,
                        SUM(l.cantidad) as total_qty,
                        SUM(l.importe_impuestos) as revenue,
                        SUM(l.precio_coste * l.cantidad) as total_cost,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit,
                        COUNT(DISTINCT v.cod_venta) as orders,
                        MAX(l.precio_coste) as unit_cost,
                        MAX(l.precio) as unit_price
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                    INNER JOIN familias f ON a.cod_familia = f.cod_familia
                    $whereClause
                    GROUP BY l.cod_articulo
                    HAVING SUM(l.cantidad) > 10
                    ORDER BY gross_profit DESC
                ", $params);

                foreach ($starProducts as &$product) {
                    $product->margin_rate = $product->revenue > 0
                        ? (($product->revenue - $product->total_cost) / $product->revenue) * 100
                        : 0;
                    $product->margin_per_unit = $product->unit_price - $product->unit_cost;
                }

                // === EVOLUCIÓN MENSUAL DE MÁRGENES ===
                $monthlyMargin = $erp->select("
                    SELECT
                        YEAR(v.fecha_venta) as year,
                        MONTH(v.fecha_venta) as month,
                        SUM(l.importe_impuestos) as revenue,
                        SUM(l.precio_coste * l.cantidad) as total_cost,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    $whereClause
                    GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
                    ORDER BY year ASC, month ASC
                ", $params);

                $formattedMonthlyMargin = [];
                foreach ($monthlyMargin as $row) {
                    $monthKey = sprintf('%04d-%02d', $row->year, $row->month);
                    $formattedMonthlyMargin[$monthKey] = [
                        'revenue' => (float)$row->revenue,
                        'cost' => (float)$row->total_cost,
                        'profit' => (float)$row->gross_profit,
                        'margin_rate' => $row->revenue > 0 ? (($row->revenue - $row->total_cost) / $row->revenue) * 100 : 0
                    ];
                }

                // === CLIENTES TOP por RENTABILIDAD (no solo facturación) ===
                $topClientsByProfit = $erp->select("
                    SELECT TOP 15
                        v.cod_cliente,
                        MAX(c.razon_social) as razon_social,
                        MAX(c.poblacion) as poblacion,
                        SUM(l.importe_impuestos) as revenue,
                        SUM(l.precio_coste * l.cantidad) as total_cost,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit,
                        COUNT(DISTINCT v.cod_venta) as orders
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                    $whereClause
                    GROUP BY v.cod_cliente
                    ORDER BY gross_profit DESC
                ", $params);

                foreach ($topClientsByProfit as &$client) {
                    $client->margin_rate = $client->revenue > 0
                        ? (($client->revenue - $client->total_cost) / $client->revenue) * 100
                        : 0;
                    $client->avg_order_value = $client->orders > 0 ? $client->revenue / $client->orders : 0;
                }

                return [
                    'kpis' => [
                        'revenue' => $revenue,
                        'total_cost' => $totalCost,
                        'gross_profit' => $grossProfit,
                        'margin_rate' => $marginRate,
                        'total_orders' => (int)($kpis->total_orders ?? 0),
                        'unique_clients' => (int)($kpis->unique_clients ?? 0),
                        'avg_ticket' => $avgTicket,
                        'revenue_per_client' => $revenuePerClient,
                    ],
                    'marginByFamily' => $marginByFamily,
                    'marginBySubfamily' => $marginBySubfamily,
                    'starProducts' => $starProducts,
                    'monthlyMargin' => $formattedMonthlyMargin,
                    'topClientsByProfit' => $topClientsByProfit,
                    'yearRange' => $yearRange,
                    'minYear' => $minYear,
                    'maxYear' => $maxYear,
                    'selectedYearFrom' => $yearFrom,
                    'selectedYearTo' => $yearTo,
                    'selectedMonthFrom' => $monthFrom,
                    'selectedMonthTo' => $monthTo,
                    'source' => 'ERP SQL Server',
                ];

            } catch (\Exception $e) {
                \Log::error('Financial Dashboard Error: ' . $e->getMessage());
                return [
                    'kpis' => ['revenue' => 0, 'total_cost' => 0, 'gross_profit' => 0, 'margin_rate' => 0, 'total_orders' => 0, 'unique_clients' => 0, 'avg_ticket' => 0, 'revenue_per_client' => 0],
                    'marginByFamily' => [],
                    'marginBySubfamily' => [],
                    'starProducts' => [],
                    'monthlyMargin' => [],
                    'topClientsByProfit' => [],
                    'yearRange' => $yearRange ?? range(2012, date('Y')),
                    'minYear' => $minYear ?? 2012,
                    'maxYear' => $maxYear ?? date('Y'),
                    'selectedYearFrom' => $yearFrom,
                    'selectedYearTo' => $yearTo,
                    'selectedMonthFrom' => $monthFrom,
                    'selectedMonthTo' => $monthTo,
                    'error' => $e->getMessage(),
                ];
            }
        });

        return view('financial.index', $data);
    }
}
