<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const DEFAULT_YEAR_RANGE = 3;

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

        // Rango de años: por defecto los últimos 3 años completos
        $defaultYearFrom = max($minYear, $maxYear - self::DEFAULT_YEAR_RANGE + 1);
        $yearFrom = (int) $request->input('year_from', $defaultYearFrom);
        $yearTo = (int) $request->input('year_to', $maxYear);

        // Validar límites
        $yearFrom = max($minYear, min($maxYear, $yearFrom));
        $yearTo = max($minYear, min($maxYear, $yearTo));
        if ($yearFrom > $yearTo) {
            $yearFrom = $yearTo;
        }

        $selectedYears = range($yearFrom, $yearTo);

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
            $results = $this->queryErpComparison($selectedYears, $selectedFamily, $selectedSubfamily);
        }

        return view('reports.comparison', compact(
            'selectedYears', 'yearFrom', 'yearTo', 'results', 'allFamilies', 'subfamilies',
            'selectedFamily', 'selectedSubfamily', 'minYear', 'maxYear'
        ));
    }

    private function queryErpComparison(array $years, ?string $family = null, ?string $subfamily = null): array
    {
        $erp = DB::connection('erp');
        $stats = new StatisticsService();

        // Filtros para queries de cabecera (ventas/clientes) y de líneas (productos/familias)
        [$headerJoins, $headerWhere, $headerParams] = $this->buildFamilyFilter(true, $family, $subfamily);
        [$lineWhere, $lineFilterParams] = $this->buildLineFilter($family, $subfamily);

        // KPIs por año
        $kpis = [];
        foreach ($years as $year) {
            $params = array_merge([$year], $headerParams);
            $kpis[$year] = $erp->select("
                SELECT
                    COUNT(DISTINCT v.cod_venta) as total_orders,
                    SUM(v.importe_impuestos) as total_sales,
                    AVG(v.importe_impuestos) as avg_ticket,
                    COUNT(DISTINCT v.cod_cliente) as unique_clients
                FROM hist_ventas_cabecera v
                {$headerJoins}
                WHERE YEAR(v.fecha_venta) = ?
                    AND ISNULL(v.anulada, '') <> 'S'
                    {$headerWhere}
            ", $params)[0];
        }

        // Crecimiento año a año
        $growth = [];
        $previous = null;
        foreach ($years as $year) {
            if ($previous === null) {
                $growth[$year] = [
                    'sales_growth' => 0.0,
                    'ticket_growth' => 0.0,
                    'clients_growth' => 0.0,
                    'orders_growth' => 0.0,
                ];
            } else {
                $growth[$year] = [
                    'sales_growth' => $kpis[$previous]->total_sales > 0
                        ? (($kpis[$year]->total_sales - $kpis[$previous]->total_sales) / $kpis[$previous]->total_sales) * 100
                        : 0,
                    'ticket_growth' => $kpis[$previous]->avg_ticket > 0
                        ? (($kpis[$year]->avg_ticket - $kpis[$previous]->avg_ticket) / $kpis[$previous]->avg_ticket) * 100
                        : 0,
                    'clients_growth' => $kpis[$previous]->unique_clients > 0
                        ? (($kpis[$year]->unique_clients - $kpis[$previous]->unique_clients) / $kpis[$previous]->unique_clients) * 100
                        : 0,
                    'orders_growth' => $kpis[$previous]->total_orders > 0
                        ? (($kpis[$year]->total_orders - $kpis[$previous]->total_orders) / $kpis[$previous]->total_orders) * 100
                        : 0,
                ];
            }
            $previous = $year;
        }

        // Top 10 clientes por año
        $topClients = [];
        foreach ($years as $year) {
            $params = array_merge([$year], $headerParams);
            $topClients[$year] = $erp->select("
                SELECT TOP 10
                    v.cod_cliente,
                    MAX(c.razon_social) as razon_social,
                    SUM(v.importe_impuestos) as total_spent,
                    COUNT(DISTINCT v.cod_venta) as order_count
                FROM hist_ventas_cabecera v
                LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                {$headerJoins}
                WHERE YEAR(v.fecha_venta) = ?
                    AND ISNULL(v.anulada, '') <> 'S'
                    {$headerWhere}
                GROUP BY v.cod_cliente
                ORDER BY total_spent DESC
            ", $params);
        }

        // Top 20 productos por año
        $topProducts = [];
        foreach ($years as $year) {
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
                {$lineWhere}
                GROUP BY l.cod_articulo
                ORDER BY total_revenue DESC
            ", array_merge([$year], $lineFilterParams));
        }

        // Productos combinados para comparación
        $topProductsCombined = $this->combineTopProducts($topProducts, $years);

        // Evolución mensual (con filtro de familia si aplica)
        $monthly = $this->fetchMonthlyEvolution($erp, $years, $family, $subfamily);

        // Ventas por familia comparativa multiaño
        $byFamily = $this->fetchByFamily($erp, $years, $family, $subfamily, $stats);

        // Calcular concentración top 10 por año
        $concentrationTop10 = [];
        foreach ($years as $year) {
            $total = $erp->select("
                SELECT SUM(l.importe_impuestos) as total
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera v
                    ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                    AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                {$lineWhere}
            ", array_merge([$year], $lineFilterParams))[0]->total ?? 0;

            $top10Revenue = array_sum(array_slice(array_column($topProducts[$year], 'total_revenue'), 0, 10));
            $concentrationTop10[$year] = $total > 0 ? ($top10Revenue / $total) * 100 : 0;
        }

        // Métricas financieras y estadísticas
        $totalSalesByYear = [];
        $avgTicketByYear = [];
        foreach ($years as $year) {
            $totalSalesByYear[$year] = (float) ($kpis[$year]->total_sales ?? 0);
            $avgTicketByYear[$year] = (float) ($kpis[$year]->avg_ticket ?? 0);
        }

        $firstYear = reset($years);
        $lastYear = end($years);
        $periods = count($years) - 1;

        $cagrSales = $stats->cagr($totalSalesByYear[$firstYear], $totalSalesByYear[$lastYear], max(1, $periods));
        $volatilitySales = $stats->coefficientOfVariation(array_values($totalSalesByYear));
        $salesTrend = $stats->linearRegression($totalSalesByYear);
        $salesForecast = $stats->forecast($totalSalesByYear, 1);
        $salesOutliers = $stats->zScoreOutliers($totalSalesByYear);

        // Formatear evolución mensual para análisis estacional
        $monthlyByYear = [];
        foreach ($monthly as $row) {
            $monthlyByYear[(int)$row->year][(int)$row->month] = (float)$row->total;
        }
        $seasonality = $stats->seasonalityIndex($monthlyByYear);

        // Top movers por producto (CAGR entre primer y último año, con mínimo de presencia)
        $topMoversProducts = $this->calculateTopMovers($topProductsCombined, $years, $stats);

        // Top movers por familia (CAGR entre primer y último año)
        $topMoversFamilies = $this->calculateFamilyMovers($byFamily, $years, $stats);

        $financialMetrics = [
            'total_sales_by_year' => $totalSalesByYear,
            'avg_ticket_by_year' => $avgTicketByYear,
            'concentration_top10' => $concentrationTop10,
            'cagr' => $cagrSales,
            'volatility' => $volatilitySales,
            'trend' => $salesTrend,
            'forecast' => $salesForecast,
            'outliers' => $salesOutliers,
            'seasonality' => $seasonality,
            'top_movers_products' => $topMoversProducts,
            'top_movers_families' => $topMoversFamilies,
        ];

        return compact('kpis', 'growth', 'financialMetrics', 'topClients', 'topProducts', 'topProductsCombined', 'monthly', 'byFamily');
    }

    /**
     * Construye los joins/where/params para filtrar por familia/subfamilia.
     *
     * @param bool        $forHeaderQuery true si la query principal es sobre hist_ventas_cabecera
     * @param string|null $family
     * @param string|null $subfamily
     *
     * @return array{0: string, 1: string, 2: array}
     */
    private function buildFamilyFilter(bool $forHeaderQuery, ?string $family, ?string $subfamily): array
    {
        if (!$family) {
            return ['', '', []];
        }

        if ($forHeaderQuery) {
            $joins = "
                INNER JOIN hist_ventas_linea lf ON v.cod_venta = lf.cod_venta
                    AND v.tipo_venta = lf.tipo_venta AND v.cod_empresa = lf.cod_empresa AND v.cod_caja = lf.cod_caja
                INNER JOIN articulos af ON lf.cod_articulo = af.cod_articulo";
            $where = " AND af.cod_familia = ?";
            $params = [$family];
            if ($subfamily) {
                $where .= " AND af.cod_subfamilia = ?";
                $params[] = $subfamily;
            }
        } else {
            $joins = '';
            $where = " AND a.cod_familia = ?";
            $params = [$family];
            if ($subfamily) {
                $where .= " AND a.cod_subfamilia = ?";
                $params[] = $subfamily;
            }
        }

        return [$joins, $where, $params];
    }

    /**
     * Construye la cláusula WHERE dinámica para queries de líneas.
     *
     * @return array{0: string, 1: array}
     */
    private function buildLineFilter(?string $family, ?string $subfamily): array
    {
        $where = "WHERE YEAR(v.fecha_venta) = ?
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL";
        $params = [];

        if ($family && $subfamily) {
            $where .= " AND a.cod_familia = ? AND a.cod_subfamilia = ?";
            $params = [$family, $subfamily];
        } elseif ($family) {
            $where .= " AND a.cod_familia = ?";
            $params = [$family];
        }

        return [$where, $params];
    }

    /**
     * Combina los Top 20 productos de cada año en una sola lista con métricas multiaño.
     *
     * @param array<int, array> $topProducts
     * @param int[]             $years
     *
     * @return array<object>
     */
    private function combineTopProducts(array $topProducts, array $years): array
    {
        $allCodes = [];
        foreach ($years as $year) {
            $allCodes = array_merge($allCodes, array_column($topProducts[$year], 'cod_articulo'));
        }
        $allCodes = array_unique($allCodes);

        $productsById = [];
        foreach ($years as $year) {
            foreach ($topProducts[$year] as $p) {
                if (!isset($productsById[$p->cod_articulo])) {
                    $productsById[$p->cod_articulo] = [];
                }
                $productsById[$p->cod_articulo][$year] = $p;
            }
        }

        $lastYear = end($years);
        $firstYear = reset($years);
        $combined = [];

        foreach ($allCodes as $code) {
            $data = $productsById[$code] ?? [];
            if (empty($data)) {
                continue;
            }

            $first = $data[$firstYear] ?? null;
            $last = $data[$lastYear] ?? null;
            $firstRev = $first ? (float)$first->total_revenue : 0;
            $lastRev = $last ? (float)$last->total_revenue : 0;

            $yearRevenues = [];
            $yearQtys = [];
            $yearOrders = [];
            foreach ($years as $year) {
                $p = $data[$year] ?? null;
                $yearRevenues[$year] = $p ? (float)$p->total_revenue : 0;
                $yearQtys[$year] = $p ? (float)$p->total_qty : 0;
                $yearOrders[$year] = $p ? (int)$p->order_count : 0;
            }

            // Crecimiento simple primer -> último año
            $growthRate = $firstRev > 0
                ? (($lastRev - $firstRev) / $firstRev) * 100
                : ($lastRev > 0 ? 100 : 0);

            // Ticket medio del último año
            $lastTicket = $last && $last->order_count > 0
                ? $lastRev / $last->order_count
                : 0;

            $any = $last ?? $first ?? reset($data);

            $combined[] = (object) [
                'cod_articulo' => $code,
                'descripcion' => $any->descripcion ?? 'N/A',
                'familia' => $any->familia ?? 'N/A',
                'year_revenues' => $yearRevenues,
                'year_qtys' => $yearQtys,
                'year_orders' => $yearOrders,
                'first_revenue' => $firstRev,
                'last_revenue' => $lastRev,
                'growth' => $growthRate,
                'last_avg_ticket' => $lastTicket,
                'last_orders' => $yearOrders[$lastYear],
                'presence_years' => count(array_filter($yearRevenues, fn($v) => $v > 0)),
            ];
        }

        usort($combined, fn($a, $b) => $b->last_revenue <=> $a->last_revenue);

        return array_slice($combined, 0, 20);
    }

    /**
     * Evolución mensual, opcionalmente filtrada por familia/subfamilia.
     *
     * @return array<object>
     */
    private function fetchMonthlyEvolution($erp, array $years, ?string $family, ?string $subfamily): array
    {
        if ($family) {
            [$lineWhere, $lineFilterParams] = $this->buildLineFilter($family, $subfamily);
            $where = str_replace('YEAR(v.fecha_venta) = ?', 'YEAR(v.fecha_venta) IN (' . implode(',', array_fill(0, count($years), '?')) . ')', $lineWhere);
            $params = array_merge($years, $lineFilterParams);

            return $erp->select("
                SELECT
                    YEAR(v.fecha_venta) as year,
                    MONTH(v.fecha_venta) as month,
                    SUM(l.importe_impuestos) as total
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera v
                    ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                    AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                {$where}
                GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
                ORDER BY year, month
            ", $params);
        }

        return $erp->select("
            SELECT
                YEAR(fecha_venta) as year,
                MONTH(fecha_venta) as month,
                SUM(importe_impuestos) as total
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND ISNULL(anulada, '') <> 'S'
            GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
            ORDER BY year, month
        ", $years);
    }

    /**
     * Ventas por familia con columnas por año.
     *
     * @return array<array>
     */
    private function fetchByFamily($erp, array $years, ?string $family, ?string $subfamily, StatisticsService $stats): array
    {
        [$lineWhere, $lineFilterParams] = $this->buildLineFilter($family, $subfamily);
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));

        // Sustituir el placeholder único de año por el rango IN
        $where = str_replace('YEAR(v.fecha_venta) = ?', 'YEAR(v.fecha_venta) IN (' . $yearPlaceholders . ')', $lineWhere);
        $params = array_merge($years, $lineFilterParams);

        $caseColumns = [];
        foreach ($years as $year) {
            $caseColumns[] = "SUM(CASE WHEN YEAR(v.fecha_venta) = ? THEN l.importe_impuestos ELSE 0 END) as y_{$year}";
        }
        $caseColumnsSql = implode(",\n                ", $caseColumns);

        // Params: CASE años + WHERE años + filtros
        $allParams = array_merge($years, $params);

        $rows = $erp->select("
            SELECT TOP 15
                f.cod_familia,
                MAX(f.descripcion) as familia,
                {$caseColumnsSql}
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
            INNER JOIN familias f ON a.cod_familia = f.cod_familia
            {$where}
            GROUP BY f.cod_familia
            ORDER BY y_" . end($years) . " DESC
        ", $allParams);

        $lastYear = end($years);
        $firstYear = reset($years);
        $byFamily = [];

        foreach ($rows as $r) {
            $yearRevenues = [];
            foreach ($years as $year) {
                $prop = "y_{$year}";
                $yearRevenues[$year] = (float)($r->$prop ?? 0);
            }

            $y1 = $yearRevenues[$firstYear];
            $y2 = $yearRevenues[$lastYear];

            $periods = count($years) - 1;
            $cagr = $y1 > 0 ? $stats->cagr($y1, $y2, max(1, $periods)) : 0;

            $byFamily[] = [
                'cod_familia' => $r->cod_familia,
                'familia' => $r->familia,
                'year_revenues' => $yearRevenues,
                'y1_revenue' => $y1,
                'y2_revenue' => $y2,
                'growth' => $y1 > 0 ? (($y2 - $y1) / $y1) * 100 : ($y2 > 0 ? 100 : 0),
                'cagr' => $cagr,
            ];
        }

        return $byFamily;
    }

    /**
     * Calcula los productos con mayor crecimiento absoluto y relativo.
     *
     * @return array<array>
     */
    private function calculateTopMovers(array $topProductsCombined, array $years, StatisticsService $stats): array
    {
        $firstYear = reset($years);
        $lastYear = end($years);
        $periods = count($years) - 1;

        $movers = [];
        foreach ($topProductsCombined as $p) {
            $firstRev = $p->year_revenues[$firstYear] ?? 0;
            $lastRev = $p->year_revenues[$lastYear] ?? 0;

            if ($firstRev <= 0 || $lastRev <= 0 || $p->presence_years < 2) {
                continue;
            }

            $cagr = $stats->cagr($firstRev, $lastRev, max(1, $periods));
            $absoluteGrowth = $lastRev - $firstRev;

            $movers[] = [
                'cod_articulo' => $p->cod_articulo,
                'descripcion' => $p->descripcion,
                'familia' => $p->familia,
                'first_revenue' => $firstRev,
                'last_revenue' => $lastRev,
                'absolute_growth' => $absoluteGrowth,
                'cagr' => $cagr,
            ];
        }

        // Top 5 crecimiento relativo
        usort($movers, fn($a, $b) => $b['cagr'] <=> $a['cagr']);
        $topGrowth = array_slice($movers, 0, 5);

        // Top 5 decaimiento relativo
        usort($movers, fn($a, $b) => $a['cagr'] <=> $b['cagr']);
        $topDecline = array_slice($movers, 0, 5);

        return [
            'growth' => $topGrowth,
            'decline' => $topDecline,
        ];
    }

    /**
     * Calcula las familias con mayor crecimiento.
     *
     * @return array<array>
     */
    private function calculateFamilyMovers(array $byFamily, array $years, StatisticsService $stats): array
    {
        $firstYear = reset($years);
        $lastYear = end($years);
        $periods = count($years) - 1;

        $movers = [];
        foreach ($byFamily as $f) {
            $firstRev = $f['year_revenues'][$firstYear] ?? 0;
            $lastRev = $f['year_revenues'][$lastYear] ?? 0;

            if ($firstRev <= 0 || $lastRev <= 0) {
                continue;
            }

            $cagr = $stats->cagr($firstRev, $lastRev, max(1, $periods));
            $absoluteGrowth = $lastRev - $firstRev;

            $movers[] = [
                'cod_familia' => $f['cod_familia'],
                'familia' => $f['familia'],
                'first_revenue' => $firstRev,
                'last_revenue' => $lastRev,
                'absolute_growth' => $absoluteGrowth,
                'cagr' => $cagr,
            ];
        }

        usort($movers, fn($a, $b) => $b['cagr'] <=> $a['cagr']);
        $topGrowth = array_slice($movers, 0, 5);

        usort($movers, fn($a, $b) => $a['cagr'] <=> $b['cagr']);
        $topDecline = array_slice($movers, 0, 5);

        return [
            'growth' => $topGrowth,
            'decline' => $topDecline,
        ];
    }
}
