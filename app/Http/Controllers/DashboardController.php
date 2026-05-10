<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Obtener filtros de rango de fechas
        // Por defecto mostrar solo el año en curso
        $currentYear = date('Y');
        $yearFrom = $request->input('year_from', $currentYear);
        $yearTo = $request->input('year_to', $currentYear);
        $monthFrom = $request->input('month_from', '1');
        $monthTo = $request->input('month_to', date('m'));

        $cacheKey = 'dashboard_data_v2_' . $yearFrom . '_' . $yearTo . '_' . $monthFrom . '_' . $monthTo;

        // Cargar datos desde caché o ERP
        $cachedData = cache()->remember($cacheKey, now()->addMinutes(5), function () use ($yearFrom, $yearTo, $monthFrom, $monthTo) {
            // Intentar usar SQL Server (ERP) directamente
            try {
                $erp = DB::connection('erp');

                // Obtener años disponibles
                $availableYears = $erp->select("
                    SELECT DISTINCT YEAR(fecha_venta) as year
                    FROM hist_ventas_cabecera
                    WHERE fecha_venta IS NOT NULL
                    ORDER BY year ASC
                ");
                $minYear = !empty($availableYears) ? (int)$availableYears[0]->year : 2012;
                $maxYear = !empty($availableYears) ? (int)end($availableYears)->year : date('Y');
                $yearRange = array_column($availableYears, 'year');

                // Construir cláusula WHERE para KPIs y ventas por mes
                $whereClause = "WHERE ISNULL(anulada, '') <> ?";
                $params = ['S'];

                // Filtro año desde
                if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
                    $whereClause .= ' AND YEAR(fecha_venta) >= ?';
                    $params[] = $yearFrom;
                }

                // Filtro año hasta
                if ($yearTo !== 'all' && is_numeric($yearTo)) {
                    $whereClause .= ' AND YEAR(fecha_venta) <= ?';
                    $params[] = $yearTo;
                }

                // Filtro mes desde (solo si hay año desde)
                if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
                    $whereClause .= ' AND (YEAR(fecha_venta) > ? OR (YEAR(fecha_venta) = ? AND MONTH(fecha_venta) >= ?))';
                    $params[] = $yearFrom;
                    $params[] = $yearFrom;
                    $params[] = $monthFrom;
                }

                // Filtro mes hasta (solo si hay año hasta)
                if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
                    $whereClause .= ' AND (YEAR(fecha_venta) < ? OR (YEAR(fecha_venta) = ? AND MONTH(fecha_venta) <= ?))';
                    $params[] = $yearTo;
                    $params[] = $yearTo;
                    $params[] = $monthTo;
                }

                // KPIs desde SQL Server (con filtro de rango)
                $kpis = $erp->select("
                    SELECT
                        SUM(importe_impuestos) as total_sales,
                        COUNT(*) as total_orders,
                        AVG(importe_impuestos) as avg_ticket,
                        SUM(importe_pendiente) as pending_amount
                    FROM hist_ventas_cabecera
                    $whereClause
                ", $params)[0];

                // Ventas por mes con filtro de rango de fechas
                $salesByMonth = $erp->select("
                    SELECT
                        YEAR(fecha_venta) as year,
                        MONTH(fecha_venta) as month,
                        SUM(importe_impuestos) as total
                    FROM hist_ventas_cabecera
                    $whereClause
                    GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
                    ORDER BY year ASC, month ASC
                ", $params);

                // Formatear para el gráfico
                $formattedSales = [];
                foreach ($salesByMonth as $row) {
                    $monthKey = sprintf('%04d-%02d', $row->year, $row->month);
                    $formattedSales[$monthKey] = (float)$row->total;
                }

                // Top clientes desde SQL Server (con filtro de rango)
                $topClients = array_map(function($c) {
                    return (array) $c;
                }, $erp->select("
                    SELECT TOP 10
                        v.cod_cliente,
                        MAX(c.razon_social) as razon_social,
                        MAX(c.poblacion) as poblacion,
                        MAX(c.provincia) as provincia,
                        SUM(v.importe_impuestos) as total_spent
                    FROM hist_ventas_cabecera v
                    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                    $whereClause
                    GROUP BY v.cod_cliente
                    ORDER BY total_spent DESC
                ", $params));

                // Top productos desde SQL Server (con filtro de rango)
                $topProducts = array_map(function($p) {
                    return (array) $p;
                }, $erp->select("
                    SELECT TOP 10
                        l.cod_articulo,
                        MAX(l.descripcion) as descripcion,
                        SUM(l.cantidad) as total_qty,
                        SUM(l.importe_impuestos) as total_revenue
                    FROM hist_ventas_linea l
                    INNER JOIN hist_ventas_cabecera v
                        ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    $whereClause
                        AND l.cod_articulo IS NOT NULL
                    GROUP BY l.cod_articulo
                    ORDER BY total_revenue DESC
                ", $params));

                // Obtener familias desde SQL Server
                $productCodes = array_column($topProducts, 'cod_articulo');
                $families = $erp->select("
                    SELECT a.cod_articulo, a.cod_familia, a.cod_subfamilia
                    FROM articulos a
                    WHERE a.cod_articulo IN ('" . implode("','", $productCodes) . "')
                ");
                $familyInfoById = [];
                foreach ($families as $f) {
                    $familyInfoById[$f->cod_articulo] = [
                        'cod_familia' => $f->cod_familia,
                        'cod_subfamilia' => $f->cod_subfamilia
                    ];
                }

                // Añadir información extra a productos
                foreach ($topProducts as &$product) {
                    $info = $familyInfoById[$product['cod_articulo']] ?? null;
                    $product['cod_familia'] = $info['cod_familia'] ?? '-';
                    $product['cod_subfamilia'] = $info['cod_subfamilia'] ?? null;
                    $product['stock_total'] = 0;
                }

                return [
                    'totalSales' => (float)($kpis->total_sales ?? 0),
                    'totalOrders' => (int)($kpis->total_orders ?? 0),
                    'avgTicket' => (float)($kpis->avg_ticket ?? 0),
                    'pendingAmount' => (float)($kpis->pending_amount ?? 0),
                    'salesByMonth' => $formattedSales,
                    'topClients' => $topClients,
                    'topProducts' => $topProducts,
                    'minYear' => $minYear,
                    'maxYear' => $maxYear,
                    'yearRange' => $yearRange,
                    'selectedYearFrom' => $yearFrom,
                    'selectedYearTo' => $yearTo,
                    'selectedMonthFrom' => $monthFrom,
                    'selectedMonthTo' => $monthTo,
                    'source' => 'ERP SQL Server',
                ];

            } catch (\Exception $e) {
                // Log del error para debug
                \Log::error('Dashboard SQL Server Error: ' . $e->getMessage());
                // Fallback a MySQL local si SQL Server falla
                return $this->getDashboardFromMySQL($yearFrom, $yearTo, $monthFrom, $monthTo);
            }
        });

        // Cargar alertas fuera de la caché (datos locales MySQL)
        $alerts = Alert::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($alert) {
                return [
                    'title' => $alert->title,
                    'description' => $alert->description,
                    'status' => $alert->status,
                    'type' => $alert->type,
                ];
            });

        // Fusionar datos
        $data = array_merge($cachedData, ['alerts' => $alerts]);

        return view('dashboard.index', $data);
    }

    private function getDashboardFromMySQL(?string $yearFrom = 'all', ?string $yearTo = 'all', ?string $monthFrom = 'all', ?string $monthTo = 'all'): array
    {
        // Obtener años disponibles desde MySQL
        $yearRange = DB::table('erp_sales')
            ->selectRaw('DISTINCT YEAR(fecha_venta) as year')
            ->orderBy('year')
            ->pluck('year')
            ->toArray();
        $minYear = !empty($yearRange) ? (int)min($yearRange) : 2012;
        $maxYear = !empty($yearRange) ? (int)max($yearRange) : date('Y');

        // KPIs desde MySQL local (con filtro de rango)
        $kpiQuery = DB::table('erp_sales')
            ->selectRaw('SUM(importe_impuestos) as total_sales, COUNT(*) as total_orders, AVG(importe_impuestos) as avg_ticket, SUM(importe_pendiente) as pending_amount');

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

        $totalSales = $kpis->total_sales ?? 0;
        $totalOrders = $kpis->total_orders ?? 0;
        $avgTicket = $kpis->avg_ticket ?? 0;
        $pendingAmount = $kpis->pending_amount ?? 0;

        // Ventas por mes con filtro de rango de fechas
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

        $salesByMonth = $salesQuery
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Alertas
        $alerts = Alert::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($alert) {
                return [
                    'title' => $alert->title,
                    'description' => $alert->description,
                    'status' => $alert->status,
                    'type' => $alert->type,
                    'created_at' => $alert->created_at,
                ];
            });

        // Top clientes (con filtro de rango)
        $clientQuery = DB::table('erp_sales')
            ->join('erp_clients', 'erp_sales.cod_cliente', '=', 'erp_clients.cod_cliente')
            ->select(
                'erp_clients.razon_social',
                'erp_clients.poblacion',
                'erp_clients.provincia',
                DB::raw('SUM(erp_sales.importe_impuestos) as total_spent')
            );

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

        // Top productos (con filtro de rango)
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
            ->select('erp_products.cod_articulo', 'erp_products.marca', 'erp_products.cod_familia')
            ->whereIn('erp_products.cod_articulo', $productCodes)
            ->get()
            ->keyBy('cod_articulo');

        foreach ($topProducts as &$product) {
            $info = $productInfo[$product['cod_articulo']] ?? null;
            $product['descripcion'] = $info ? $info->marca : 'N/A';
            $product['cod_familia'] = $info->cod_familia ?? null;
            $product['stock_total'] = 0;
        }

        // Definir filtros para la vista (en español)
        $selectedYearFrom = $yearFrom;
        $selectedMonthFrom = $monthFrom;
        $selectedYearTo = $yearTo;
        $selectedMonthTo = $monthTo;

        return [
            'totalSales' => $totalSales,
            'totalOrders' => $totalOrders,
            'avgTicket' => $avgTicket,
            'pendingAmount' => $pendingAmount,
            'salesByMonth' => $salesByMonth,
            'alerts' => $alerts,
            'topClients' => $topClients,
            'topProducts' => $topProducts,
            'minYear' => $minYear,
            'maxYear' => $maxYear,
            'yearRange' => $yearRange,
            'selectedYearFrom' => $selectedYearFrom,
            'selectedMonthFrom' => $selectedMonthFrom,
            'selectedYearTo' => $selectedYearTo,
            'selectedMonthTo' => $selectedMonthTo,
            'source' => 'SQL Server Producción',
        ];
    }
}
