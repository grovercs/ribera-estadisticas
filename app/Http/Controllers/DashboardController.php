<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $yearFrom = $request->input('year_from', $currentYear);
        $yearTo = $request->input('year_to', $currentYear);
        $monthFrom = $request->input('month_from', '1');
        $monthTo = $request->input('month_to', (string) $currentMonth);
        $hideNoStock = $request->boolean('hide_no_stock');

        // Normalizar "all"
        $yearFrom = is_numeric($yearFrom) ? (int) $yearFrom : 'all';
        $yearTo = is_numeric($yearTo) ? (int) $yearTo : 'all';
        $monthFrom = is_numeric($monthFrom) ? (int) $monthFrom : 'all';
        $monthTo = is_numeric($monthTo) ? (int) $monthTo : 'all';

        if ($yearFrom !== 'all' && $yearTo !== 'all' && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $cacheKey = 'dashboard_data_v3_' . (($yearFrom === 'all' ? 'all' : $yearFrom) . '_' . ($yearTo === 'all' ? 'all' : $yearTo) . '_' . ($monthFrom === 'all' ? 'all' : $monthFrom) . '_' . ($monthTo === 'all' ? 'all' : $monthTo) . '_' . ($hideNoStock ? '1' : '0'));

        $cachedData = cache()->remember($cacheKey, now()->addMinutes(5), function () use ($yearFrom, $yearTo, $monthFrom, $monthTo, $hideNoStock) {
            try {
                $erp = DB::connection('erp');

                // Años disponibles
                $availableYears = $erp->select("
                    SELECT DISTINCT YEAR(fecha_venta) as year
                    FROM hist_ventas_cabecera
                    WHERE fecha_venta IS NOT NULL
                    ORDER BY year ASC
                ");
                $minYear = !empty($availableYears) ? (int) $availableYears[0]->year : 2012;
                $maxYear = !empty($availableYears) ? (int) end($availableYears)->year : date('Y');
                $yearRange = array_column($availableYears, 'year');

                [$whereClause, $params] = $this->buildPeriodWhere($yearFrom, $yearTo, $monthFrom, $monthTo, 'v');
                [$whereClauseV2, $paramsV2] = $this->buildPeriodWhere($yearFrom, $yearTo, $monthFrom, $monthTo, 'v2');

                // KPIs
                $kpis = $erp->select("
                    SELECT
                        SUM(v.importe_impuestos) as total_sales,
                        COUNT(*) as total_orders,
                        AVG(v.importe_impuestos) as avg_ticket,
                        SUM(v.importe_pendiente) as pending_amount,
                        COUNT(DISTINCT v.cod_cliente) as unique_clients
                    FROM hist_ventas_cabecera v
                    WHERE $whereClause
                ", $params)[0];

                // Ventas por mes (período actual)
                $salesByMonth = $erp->select("
                    SELECT
                        YEAR(v.fecha_venta) as year,
                        MONTH(v.fecha_venta) as month,
                        SUM(v.importe_impuestos) as total
                    FROM hist_ventas_cabecera v
                    WHERE $whereClause
                    GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
                    ORDER BY year ASC, month ASC
                ", $params);
                $formattedSales = [];
                foreach ($salesByMonth as $row) {
                    $monthKey = sprintf('%04d-%02d', $row->year, $row->month);
                    $formattedSales[$monthKey] = (float) $row->total;
                }

                // Ventas por mes del año anterior para comparativa
                $prevYearFrom = $yearFrom !== 'all' ? $yearFrom - 1 : 'all';
                $prevYearTo = $yearTo !== 'all' ? $yearTo - 1 : 'all';
                [$prevWhereClause, $prevParams] = $this->buildPeriodWhere($prevYearFrom, $prevYearTo, $monthFrom, $monthTo, 'v');
                $prevSalesByMonth = $erp->select("
                    SELECT
                        YEAR(v.fecha_venta) as year,
                        MONTH(v.fecha_venta) as month,
                        SUM(v.importe_impuestos) as total
                    FROM hist_ventas_cabecera v
                    WHERE $prevWhereClause
                    GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
                    ORDER BY year ASC, month ASC
                ", $prevParams);
                $formattedPrevSales = [];
                foreach ($prevSalesByMonth as $row) {
                    $monthKey = sprintf('%04d-%02d', $row->year + 1, $row->month);
                    $formattedPrevSales[$monthKey] = (float) $row->total;
                }

                // Top clientes + vendedor principal
                $topClients = array_map(fn($c) => (array) $c, $erp->select("
                    SELECT TOP 10
                        v.cod_cliente,
                        MAX(c.razon_social) as razon_social,
                        MAX(c.poblacion) as poblacion,
                        MAX(c.provincia) as provincia,
                        SUM(v.importe_impuestos) as total_spent,
                        (
                            SELECT TOP 1 v2.cod_vendedor
                            FROM hist_ventas_cabecera v2
                            WHERE v2.cod_cliente = v.cod_cliente
                                AND $whereClauseV2
                            GROUP BY v2.cod_vendedor
                            ORDER BY SUM(v2.importe_impuestos) DESC
                        ) as vendedor_principal
                    FROM hist_ventas_cabecera v
                    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                    WHERE $whereClause
                    GROUP BY v.cod_cliente
                    ORDER BY total_spent DESC
                ", array_merge($params, $paramsV2)));

                // Top productos con datos del maestro y stock
                $stockFilterParam = $hideNoStock ? 1 : 0;
                $topProducts = array_map(fn($p) => (array) $p, $erp->select("
                    WITH sales_cte AS (
                        SELECT
                            l.cod_articulo,
                            MAX(l.descripcion) as line_descripcion,
                            SUM(l.cantidad) as total_qty,
                            SUM(l.importe_impuestos) as total_revenue
                        FROM hist_ventas_linea l
                        INNER JOIN hist_ventas_cabecera v
                            ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                            AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                        WHERE $whereClause
                            AND l.cod_articulo IS NOT NULL
                        GROUP BY l.cod_articulo
                    ),
                    stock_cte AS (
                        SELECT cod_articulo, SUM(existencias) as stock_total
                        FROM stocks
                        GROUP BY cod_articulo
                    )
                    SELECT TOP 10
                        s.cod_articulo,
                        MAX(a.marca) as marca,
                        MAX(COALESCE(a.descripcion_web, s.line_descripcion, '')) as descripcion,
                        MAX(a.cod_familia) as cod_familia,
                        MAX(a.cod_subfamilia) as cod_subfamilia,
                        s.total_qty,
                        s.total_revenue,
                        ISNULL(st.stock_total, 0) as stock_total
                    FROM sales_cte s
                    LEFT JOIN articulos a ON a.cod_articulo = s.cod_articulo
                    LEFT JOIN stock_cte st ON st.cod_articulo = s.cod_articulo
                    WHERE s.total_revenue > 0
                        AND (ISNULL(st.stock_total, 0) > 0 OR ? = 0)
                    GROUP BY s.cod_articulo, s.total_qty, s.total_revenue, st.stock_total
                    ORDER BY s.total_revenue DESC
                ", array_merge($params, [$stockFilterParam])));

                // Resúmenes
                $salesByFamily = array_map(function ($f) {
                    $arr = (array) $f;
                    $arr['total'] = (float) ($arr['total'] ?? 0);
                    return $arr;
                }, $erp->select("
                    WITH line_sales AS (
                        SELECT
                            l.cod_articulo,
                            SUM(l.importe_impuestos) as total_revenue
                        FROM hist_ventas_linea l
                        INNER JOIN hist_ventas_cabecera v
                            ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                            AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                        WHERE $whereClause
                            AND l.cod_articulo IS NOT NULL
                        GROUP BY l.cod_articulo
                    )
                    SELECT TOP 10
                        a.cod_familia,
                        MAX(fa.descripcion) as family_name,
                        SUM(ls.total_revenue) as total
                    FROM line_sales ls
                    INNER JOIN articulos a ON a.cod_articulo = ls.cod_articulo
                    LEFT JOIN familias fa ON fa.cod_familia = a.cod_familia
                    GROUP BY a.cod_familia
                    ORDER BY total DESC
                ", $params));

                $salesByWarehouse = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(*) as orders,
                        SUM(v.importe_impuestos) as total
                    FROM hist_ventas_cabecera v
                    WHERE $whereClause
                    GROUP BY v.cod_almacen
                    ORDER BY total DESC
                ", $params);

                $topSellers = $erp->select("
                    SELECT TOP 10
                        v.cod_vendedor,
                        MAX(v.nombre_vendedor) as nombre_vendedor,
                        COUNT(*) as orders,
                        SUM(v.importe_impuestos) as total
                    FROM hist_ventas_cabecera v
                    WHERE $whereClause
                    GROUP BY v.cod_vendedor
                    ORDER BY total DESC
                ", $params);

                return [
                    'totalSales' => (float) ($kpis->total_sales ?? 0),
                    'totalOrders' => (int) ($kpis->total_orders ?? 0),
                    'avgTicket' => (float) ($kpis->avg_ticket ?? 0),
                    'pendingAmount' => (float) ($kpis->pending_amount ?? 0),
                    'uniqueClients' => (int) ($kpis->unique_clients ?? 0),
                    'salesByMonth' => $formattedSales,
                    'prevSalesByMonth' => $formattedPrevSales,
                    'topClients' => $topClients,
                    'topProducts' => $topProducts,
                    'salesByFamily' => $salesByFamily,
                    'salesByWarehouse' => $salesByWarehouse,
                    'topSellers' => $topSellers,
                    'minYear' => $minYear,
                    'maxYear' => $maxYear,
                    'yearRange' => $yearRange,
                    'selectedYearFrom' => $yearFrom,
                    'selectedMonthFrom' => $monthFrom,
                    'selectedYearTo' => $yearTo,
                    'selectedMonthTo' => $monthTo,
                    'hideNoStock' => $hideNoStock,
                    'source' => 'ERP SQL Server',
                ];
            } catch (\Exception $e) {
                \Log::error('Dashboard SQL Server Error: ' . $e->getMessage());
                return $this->getDashboardFromMySQL($yearFrom, $yearTo, $monthFrom, $monthTo);
            }
        });

        // Alertas fuera de caché
        $alerts = Alert::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($alert) {
                return [
                    'title' => $alert->title,
                    'description' => $alert->description,
                    'status' => $alert->status,
                    'type' => $alert->type,
                ];
            });

        $data = array_merge($cachedData, ['alerts' => $alerts]);

        return view('dashboard.index', $data);
    }

    private function buildPeriodWhere($yearFrom, $yearTo, $monthFrom, $monthTo, string $alias): array
    {
        $where = ["ISNULL({$alias}.anulada, '') <> 'S'"];
        $params = [];

        if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
            $where[] = "YEAR({$alias}.fecha_venta) >= ?";
            $params[] = $yearFrom;
        }

        if ($yearTo !== 'all' && is_numeric($yearTo)) {
            $where[] = "YEAR({$alias}.fecha_venta) <= ?";
            $params[] = $yearTo;
        }

        if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
            $where[] = "(YEAR({$alias}.fecha_venta) > ? OR (YEAR({$alias}.fecha_venta) = ? AND MONTH({$alias}.fecha_venta) >= ?))";
            $params[] = $yearFrom;
            $params[] = $yearFrom;
            $params[] = $monthFrom;
        }

        if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
            $where[] = "(YEAR({$alias}.fecha_venta) < ? OR (YEAR({$alias}.fecha_venta) = ? AND MONTH({$alias}.fecha_venta) <= ?))";
            $params[] = $yearTo;
            $params[] = $yearTo;
            $params[] = $monthTo;
        }

        return [implode(' AND ', $where), $params];
    }

    private function getDashboardFromMySQL($yearFrom, $yearTo, $monthFrom, $monthTo): array
    {
        $yearRange = DB::table('erp_sales')
            ->selectRaw('DISTINCT YEAR(fecha_venta) as year')
            ->orderBy('year')
            ->pluck('year')
            ->toArray();
        $minYear = !empty($yearRange) ? (int) min($yearRange) : 2012;
        $maxYear = !empty($yearRange) ? (int) max($yearRange) : date('Y');

        $kpiQuery = DB::table('erp_sales')
            ->selectRaw('SUM(importe_impuestos) as total_sales, COUNT(*) as total_orders, AVG(importe_impuestos) as avg_ticket, SUM(importe_pendiente) as pending_amount, COUNT(DISTINCT cod_cliente) as unique_clients');

        if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
            $kpiQuery->whereRaw('YEAR(fecha_venta) >= ?', [$yearFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo)) {
            $kpiQuery->whereRaw('YEAR(fecha_venta) <= ?', [$yearTo]);
        }
        if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
            $kpiQuery->whereRaw('(YEAR(fecha_venta) > ? OR (YEAR(fecha_venta) = ? AND MONTH(fecha_venta) >= ?))', [$yearFrom, $yearFrom, $monthFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
            $kpiQuery->whereRaw('(YEAR(fecha_venta) < ? OR (YEAR(fecha_venta) = ? AND MONTH(fecha_venta) <= ?))', [$yearTo, $yearTo, $monthTo]);
        }

        $kpis = $kpiQuery->first();

        $salesQuery = DB::table('erp_sales')
            ->selectRaw('DATE_FORMAT(fecha_venta, "%Y-%m") as month, SUM(importe_impuestos) as total');
        if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
            $salesQuery->whereRaw('YEAR(fecha_venta) >= ?', [$yearFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo)) {
            $salesQuery->whereRaw('YEAR(fecha_venta) <= ?', [$yearTo]);
        }
        if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
            $salesQuery->whereRaw('(YEAR(fecha_venta) > ? OR (YEAR(fecha_venta) = ? AND MONTH(fecha_venta) >= ?))', [$yearFrom, $yearFrom, $monthFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
            $salesQuery->whereRaw('(YEAR(fecha_venta) < ? OR (YEAR(fecha_venta) = ? AND MONTH(fecha_venta) <= ?))', [$yearTo, $yearTo, $monthTo]);
        }
        $salesByMonth = $salesQuery->groupBy('month')->orderBy('month')->pluck('total', 'month')->toArray();

        $clientQuery = DB::table('erp_sales')
            ->join('erp_clients', 'erp_sales.cod_cliente', '=', 'erp_clients.cod_cliente')
            ->select('erp_clients.razon_social', 'erp_clients.poblacion', 'erp_clients.provincia', DB::raw('SUM(erp_sales.importe_impuestos) as total_spent'));

        if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
            $clientQuery->whereRaw('YEAR(erp_sales.fecha_venta) >= ?', [$yearFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo)) {
            $clientQuery->whereRaw('YEAR(erp_sales.fecha_venta) <= ?', [$yearTo]);
        }
        if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
            $clientQuery->whereRaw('(YEAR(erp_sales.fecha_venta) > ? OR (YEAR(erp_sales.fecha_venta) = ? AND MONTH(erp_sales.fecha_venta) >= ?))', [$yearFrom, $yearFrom, $monthFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
            $clientQuery->whereRaw('(YEAR(erp_sales.fecha_venta) < ? OR (YEAR(erp_sales.fecha_venta) = ? AND MONTH(erp_sales.fecha_venta) <= ?))', [$yearTo, $yearTo, $monthTo]);
        }

        $topClients = $clientQuery
            ->groupBy('erp_clients.cod_cliente', 'erp_clients.razon_social', 'erp_clients.poblacion', 'erp_clients.provincia')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(fn($c) => (array) $c)
            ->toArray();

        $productQuery = DB::table('erp_sale_lines')
            ->join('erp_sales', 'erp_sale_lines.cod_venta', '=', 'erp_sales.cod_venta')
            ->select('erp_sale_lines.cod_articulo', DB::raw('SUM(erp_sale_lines.cantidad) as total_qty'), DB::raw('SUM(erp_sale_lines.importe_impuestos) as total_revenue'))
            ->whereNotNull('erp_sale_lines.cod_articulo');

        if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
            $productQuery->whereRaw('YEAR(erp_sales.fecha_venta) >= ?', [$yearFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo)) {
            $productQuery->whereRaw('YEAR(erp_sales.fecha_venta) <= ?', [$yearTo]);
        }
        if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
            $productQuery->whereRaw('(YEAR(erp_sales.fecha_venta) > ? OR (YEAR(erp_sales.fecha_venta) = ? AND MONTH(erp_sales.fecha_venta) >= ?))', [$yearFrom, $yearFrom, $monthFrom]);
        }
        if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
            $productQuery->whereRaw('(YEAR(erp_sales.fecha_venta) < ? OR (YEAR(erp_sales.fecha_venta) = ? AND MONTH(erp_sales.fecha_venta) <= ?))', [$yearTo, $yearTo, $monthTo]);
        }

        $topProducts = $productQuery
            ->groupBy('erp_sale_lines.cod_articulo')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn($p) => (array) $p)
            ->toArray();

        $productCodes = array_column($topProducts, 'cod_articulo');
        $productInfo = DB::table('erp_products')
            ->select('cod_articulo', 'marca', 'cod_familia')
            ->whereIn('cod_articulo', $productCodes)
            ->get()
            ->keyBy('cod_articulo');

        foreach ($topProducts as &$product) {
            $info = $productInfo[$product['cod_articulo']] ?? null;
            $product['descripcion'] = $info->marca ?? 'N/A';
            $product['cod_familia'] = $info->cod_familia ?? null;
            $product['cod_subfamilia'] = null;
            $product['stock_total'] = 0;
        }

        return [
            'totalSales' => (float) ($kpis->total_sales ?? 0),
            'totalOrders' => (int) ($kpis->total_orders ?? 0),
            'avgTicket' => (float) ($kpis->avg_ticket ?? 0),
            'pendingAmount' => (float) ($kpis->pending_amount ?? 0),
            'uniqueClients' => (int) ($kpis->unique_clients ?? 0),
            'salesByMonth' => $salesByMonth,
            'prevSalesByMonth' => [],
            'topClients' => $topClients,
            'topProducts' => $topProducts,
            'salesByFamily' => [],
            'salesByWarehouse' => [],
            'topSellers' => [],
            'minYear' => $minYear,
            'maxYear' => $maxYear,
            'yearRange' => $yearRange,
            'selectedYearFrom' => $yearFrom,
            'selectedMonthFrom' => $monthFrom,
            'selectedYearTo' => $yearTo,
            'selectedMonthTo' => $monthTo,
            'hideNoStock' => false,
            'source' => 'MySQL local',
        ];
    }
}
