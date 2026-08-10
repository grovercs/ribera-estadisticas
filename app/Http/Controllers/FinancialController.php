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

                // === ANALÍTICA DE COMPRAS: índice de precio (Laspeyres) + PPV ===
                // Periodo A = rango seleccionado. Periodo B = mismo rango desplazado
                // 'shift' años atrás (comparación homóloga para variación de precio).
                $curYear = (int)date('Y'); $curMonth = (int)date('n');
                $pYf = is_numeric($yearFrom) ? (int)$yearFrom : (int)$maxYear;
                $pYt = is_numeric($yearTo) ? (int)$yearTo : $pYf;
                $pMf = (is_numeric($monthFrom) && $monthFrom !== 'all') ? (int)$monthFrom : 1;
                $pMt = (is_numeric($monthTo) && $monthTo !== 'all') ? (int)$monthTo : 12;
                // Si el rango llega al año en curso, recortar al mes actual (evita meses
                // vacíos y comparaciones PPV injustas parcial vs año completo).
                $endMonth = ($pYt == $curYear) ? min($pMt, $curMonth) : $pMt;

                // Base del índice = ene-mar del año inicial (cesta fija de 3 meses).
                $baseStart = sprintf('%04d0101', $pYf);
                $baseEnd   = sprintf('%04d0401', $pYf);
                $idxStart  = sprintf('%04d%02d01', $pYf, $pMf);
                $endDt = \DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $pYt, $endMonth));
                $endDt->modify('first day of next month');
                $idxEnd = $endDt->format('Ymd');

                $shift = $pYt - $pYf + 1;
                $ppvStartB = sprintf('%04d%02d01', $pYf - $shift, $pMf);
                $endB = clone $endDt; $endB->modify("-$shift years");
                $ppvEndB = $endB->format('Ymd');

                // JOIN común de compras (excluye pseudocódigos de sección como en ventas).
                $comprasJoin = "FROM hist_compras_linea l
                    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
                    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0
                      AND l.cod_articulo NOT IN ('ALMACEN','FERRETERIA','SANITARIOS','COCINAS','MARMOLES')";

                // KPIs de compra del rango A
                $comprasKpi = $erp->select("SELECT COUNT(*) lineas,
                    COUNT(DISTINCT l.cod_articulo) arts, COUNT(DISTINCT l.cod_proveedor) provs,
                    SUM(CAST(l.importe AS float)) importe, SUM(CAST(l.cantidad AS float)) cantidad
                    $comprasJoin AND c.fecha_compra>=? AND c.fecha_compra<?",
                    [$idxStart, $idxEnd])[0];

                // Índice Laspeyres de precio de compra (cesta fija, carry base 100,
                // capeo p/p0 a [0.1, 10] para notas de abono / cambios de unidad).
                $indexRows = $erp->select("
                    WITH base AS (
                        SELECT l.cod_articulo,
                            SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) AS p0,
                            SUM(CAST(l.cantidad AS float)) AS q0
                        $comprasJoin AND c.fecha_compra>=? AND c.fecha_compra<?
                        GROUP BY l.cod_articulo
                        HAVING SUM(CAST(l.cantidad AS float)) > 10
                    ),
                    tot AS (SELECT SUM(p0*q0) AS tbc FROM base),
                    mensual AS (
                        SELECT l.cod_articulo, YEAR(c.fecha_compra) y, MONTH(c.fecha_compra) m,
                            SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) AS p
                        $comprasJoin AND c.fecha_compra>=? AND c.fecha_compra<?
                        GROUP BY l.cod_articulo, YEAR(c.fecha_compra), MONTH(c.fecha_compra)
                    )
                    SELECT m.y, m.m,
                        100 * (1 + (SUM(CASE WHEN m.p/b.p0 > 10 THEN b.p0*10 WHEN m.p/b.p0 < 0.1 THEN b.p0*0.1 ELSE m.p END * b.q0)
                                    - SUM(b.p0 * b.q0)) / (SELECT tbc FROM tot)) AS indice,
                        COUNT(*) comprados
                    FROM mensual m JOIN base b ON m.cod_articulo = b.cod_articulo
                    GROUP BY m.y, m.m
                    ORDER BY m.y, m.m",
                    [$baseStart, $baseEnd, $idxStart, $idxEnd]);

                $purchaseIndex = [];
                $indexBaseArticles = 0;
                foreach ($indexRows as $r) {
                    $key = sprintf('%04d-%02d', $r->y, $r->m);
                    $purchaseIndex[$key] = round((float)$r->indice, 1);
                }
                $indexBaseLabel = count($indexRows) > 0 ? sprintf('ene–mar %d', $pYf) : null;

                // PPV: variación de precio de compra por artículo (A vs B), top subidas/bajadas.
                $ppvSql = "WITH ra AS (
                        SELECT l.cod_articulo, MAX(l.descripcion) descripcion,
                            SUM(CAST(l.cantidad AS float)) qty, SUM(CAST(l.importe AS float)) imp
                        $comprasJoin AND c.fecha_compra>=? AND c.fecha_compra<?
                        GROUP BY l.cod_articulo
                    ), rb AS (
                        SELECT l.cod_articulo,
                            SUM(CAST(l.cantidad AS float)) qty, SUM(CAST(l.importe AS float)) imp
                        $comprasJoin AND c.fecha_compra>=? AND c.fecha_compra<?
                        GROUP BY l.cod_articulo
                    )
                    SELECT TOP 15 a.cod_articulo cod, MAX(a.descripcion) descripcion, MAX(fam.descripcion) familia,
                        a.qty qtyA, b.qty qtyB, a.imp/NULLIF(a.qty,0) pA, b.imp/NULLIF(b.qty,0) pB
                    FROM ra a JOIN rb b ON a.cod_articulo=b.cod_articulo
                    LEFT JOIN articulos art ON a.cod_articulo=art.cod_articulo
                    LEFT JOIN familias fam ON art.cod_familia=fam.cod_familia
                    WHERE a.qty>? AND b.qty>?
                      AND (a.imp/NULLIF(a.qty,0))/NULLIF(b.imp/NULLIF(b.qty,0),0) BETWEEN 0.2 AND 5
                    GROUP BY a.cod_articulo, a.descripcion, a.qty, b.qty, a.imp, b.imp
                    ORDER BY (a.imp/NULLIF(a.qty,0))/NULLIF(b.imp/NULLIF(b.qty,0),0) DESC";
                $ppvParams = [$idxStart, $idxEnd, $ppvStartB, $ppvEndB, 20, 20];
                $ppvUp = $erp->select($ppvSql, $ppvParams);
                $ppvDown = $erp->select(
                    str_replace('ORDER BY (a.imp/NULLIF(a.qty,0))/NULLIF(b.imp/NULLIF(b.qty,0),0) DESC',
                                'ORDER BY (a.imp/NULLIF(a.qty,0))/NULLIF(b.imp/NULLIF(b.qty,0),0) ASC', $ppvSql),
                    $ppvParams);

                $fmtPpv = function ($rows) {
                    $out = [];
                    foreach ($rows as $r) {
                        $pA = (float)$r->pA; $pB = (float)$r->pB;
                        if ($pA == 0 || $pB == 0 || $r->pA === null || $r->pB === null) continue;
                        $out[] = [
                            'cod' => $r->cod,
                            'desc' => trim($r->descripcion ?? ''),
                            'familia' => trim($r->familia ?? ''),
                            'pA' => $pA,
                            'pB' => $pB,
                            'var' => ($pA / $pB - 1) * 100,
                            'qtyA' => (float)$r->qtyA,
                            'qtyB' => (float)$r->qtyB,
                        ];
                    }
                    return $out;
                };
                $ppvIncreases = $fmtPpv($ppvUp);
                $ppvDecreases = $fmtPpv($ppvDown);

                $purchaseData = [
                    'kpi' => [
                        'lineas' => (int)($comprasKpi->lineas ?? 0),
                        'articulos' => (int)($comprasKpi->arts ?? 0),
                        'proveedores' => (int)($comprasKpi->provs ?? 0),
                        'importe' => (float)($comprasKpi->importe ?? 0),
                        'cantidad' => (float)($comprasKpi->cantidad ?? 0),
                    ],
                    'index' => $purchaseIndex,
                    'indexBaseLabel' => $indexBaseLabel,
                    'periodoA' => sprintf('%02d/%04d – %02d/%04d', $pMf, $pYf, $endMonth, $pYt),
                    'periodoB' => sprintf('%02d/%04d – %02d/%04d', $pMf, $pYf - $shift, $endMonth, $pYt - $shift),
                    'ppvIncreases' => $ppvIncreases,
                    'ppvDecreases' => $ppvDecreases,
                ];

                // Convertir resultados stdClass a array antes de cachear: el caché file
                // de Laravel serializa/deserializa, y los stdClass quedan como
                // __PHP_Incomplete_Class al leerlos en otro proceso (acceso ->prop falla).
                $toArr = function ($rows) { return array_map(fn($r) => (array) $r, $rows); };
                $marginByFamily = $toArr($marginByFamily);
                $marginBySubfamily = $toArr($marginBySubfamily);
                $starProducts = $toArr($starProducts);
                $topClientsByProfit = $toArr($topClientsByProfit);

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
                    'purchase' => $purchaseData,
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
                    'purchase' => [
                        'kpi' => ['lineas'=>0,'articulos'=>0,'proveedores'=>0,'importe'=>0,'cantidad'=>0],
                        'index' => [], 'indexBaseLabel' => null, 'periodoA' => '', 'periodoB' => '',
                        'ppvIncreases' => [], 'ppvDecreases' => [],
                    ],
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

    // ===== Helpers compartidos por las páginas de detalle =====

    /**
     * Parsea los filtros year/month (mismos que el index) y construye la
     * cláusula WHERE para las consultas de ventas sobre hist_ventas_*.
     */
    private function filtros(Request $request): array
    {
        $yearFrom = $request->input('year_from', null);
        $yearTo = $request->input('year_to', null);
        $monthFrom = $request->input('month_from', 'all');
        $monthTo = $request->input('month_to', 'all');

        $availableYears = DB::connection('erp')->select("
            SELECT DISTINCT YEAR(fecha_venta) as year
            FROM hist_ventas_cabecera WHERE fecha_venta IS NOT NULL ORDER BY year ASC
        ");
        $yearRange = array_column($availableYears, 'year');
        $minYear = !empty($yearRange) ? (int)$yearRange[0] : 2012;
        $maxYear = !empty($yearRange) ? (int)end($yearRange) : (int)date('Y');
        if ($yearFrom === null) $yearFrom = $maxYear;
        if ($yearTo === null) $yearTo = $yearFrom;

        $whereClause = "WHERE ISNULL(v.anulada, '') <> 'S'
            AND l.cod_articulo IS NOT NULL
            AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
            AND l.precio_coste IS NOT NULL AND l.precio_coste > 0 AND l.precio_coste < 100000
            AND l.cantidad > 0";
        $params = [];
        if ($yearFrom !== 'all' && is_numeric($yearFrom)) {
            $whereClause .= ' AND YEAR(v.fecha_venta) >= ?'; $params[] = $yearFrom;
        }
        if ($yearTo !== 'all' && is_numeric($yearTo)) {
            $whereClause .= ' AND YEAR(v.fecha_venta) <= ?'; $params[] = $yearTo;
        }
        if ($yearFrom !== 'all' && is_numeric($yearFrom) && $monthFrom !== 'all' && is_numeric($monthFrom)) {
            $whereClause .= ' AND (YEAR(v.fecha_venta) > ? OR (YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) >= ?))';
            $params[] = $yearFrom; $params[] = $yearFrom; $params[] = $monthFrom;
        }
        if ($yearTo !== 'all' && is_numeric($yearTo) && $monthTo !== 'all' && is_numeric($monthTo)) {
            $whereClause .= ' AND (YEAR(v.fecha_venta) < ? OR (YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) <= ?))';
            $params[] = $yearTo; $params[] = $yearTo; $params[] = $monthTo;
        }

        $qParams = http_build_query(array_filter([
            'year_from' => $yearFrom, 'year_to' => $yearTo,
            'month_from' => $monthFrom, 'month_to' => $monthTo,
        ], fn($v) => $v !== null && $v !== ''));

        return [
            'yearFrom' => $yearFrom, 'yearTo' => $yearTo, 'monthFrom' => $monthFrom, 'monthTo' => $monthTo,
            'yearRange' => $yearRange, 'minYear' => $minYear, 'maxYear' => $maxYear,
            'selectedYearFrom' => $yearFrom, 'selectedYearTo' => $yearTo,
            'selectedMonthFrom' => $monthFrom, 'selectedMonthTo' => $monthTo,
            'whereClause' => $whereClause, 'params' => $params, 'qParams' => $qParams,
        ];
    }

    /** Rangos de fechas para compras (periodo A y B desplazado), como en el index. */
    private function rangosCompra(array $f): array
    {
        $curYear = (int)date('Y'); $curMonth = (int)date('n');
        $pYf = is_numeric($f['yearFrom']) ? (int)$f['yearFrom'] : (int)$f['maxYear'];
        $pYt = is_numeric($f['yearTo']) ? (int)$f['yearTo'] : $pYf;
        $pMf = is_numeric($f['monthFrom']) ? (int)$f['monthFrom'] : 1;
        $pMt = is_numeric($f['monthTo']) ? (int)$f['monthTo'] : 12;
        $endMonth = ($pYt == $curYear) ? min($pMt, $curMonth) : $pMt;

        $idxStart = sprintf('%04d%02d01', $pYf, $pMf);
        $endDt = \DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $pYt, $endMonth));
        $endDt->modify('first day of next month');
        $idxEnd = $endDt->format('Ymd');
        $shift = $pYt - $pYf + 1;
        $ppvStartB = sprintf('%04d%02d01', $pYf - $shift, $pMf);
        $endB = clone $endDt; $endB->modify("-$shift years");
        $ppvEndB = $endB->format('Ymd');
        $periodoA = sprintf('%02d/%04d – %02d/%04d', $pMf, $pYf, $endMonth, $pYt);
        $periodoB = sprintf('%02d/%04d – %02d/%04d', $pMf, $pYf - $shift, $endMonth, $pYt - $shift);
        return compact('idxStart', 'idxEnd', 'ppvStartB', 'ppvEndB', 'periodoA', 'periodoB');
    }

    private function paginar(int $total, int $page, int $perPage): array
    {
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        return ['page' => $page, 'totalPages' => $totalPages, 'total' => $total,
            'offset' => ($page - 1) * $perPage, 'perPage' => $perPage];
    }

    private function dirValid(?string $dir): string
    {
        return $dir === 'asc' ? 'asc' : 'desc';
    }

    /** Datos comunes para la vista de detalle (form de filtros + estado). */
    private function baseVista(array $f, array $extra): array
    {
        return array_merge([
            'yearRange' => $f['yearRange'], 'minYear' => $f['minYear'], 'maxYear' => $f['maxYear'],
            'selectedYearFrom' => $f['selectedYearFrom'], 'selectedYearTo' => $f['selectedYearTo'],
            'selectedMonthFrom' => $f['selectedMonthFrom'], 'selectedMonthTo' => $f['selectedMonthTo'],
            'qParams' => $f['qParams'],
        ], $extra);
    }

    // ===== Detalle: Familias =====
    public function detalleFamilias(Request $request)
    {
        $f = $this->filtros($request);
        $erp = DB::connection('erp');
        $search = trim((string)$request->input('search', ''));
        $sort = (string)$request->input('sort', 'gross_profit');
        $dir = $this->dirValid($request->input('dir'));
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 25;

        $sortMap = [
            'revenue' => 'SUM(l.importe_impuestos)',
            'total_cost' => 'SUM(l.precio_coste*l.cantidad)',
            'gross_profit' => 'SUM(l.importe_impuestos-(l.precio_coste*l.cantidad))',
            'margin_rate' => '(SUM(l.importe_impuestos)-SUM(l.precio_coste*l.cantidad))*1.0/NULLIF(SUM(l.importe_impuestos),0)',
            'orders' => 'COUNT(DISTINCT v.cod_venta)',
            'familia' => 'MAX(f.descripcion)',
        ];
        $orderExpr = $sortMap[$sort] ?? $sortMap['gross_profit'];
        $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';

        $base = "FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v ON l.cod_venta=v.cod_venta AND l.tipo_venta=v.tipo_venta AND l.cod_empresa=v.cod_empresa AND l.cod_caja=v.cod_caja
            LEFT JOIN articulos a ON l.cod_articulo=a.cod_articulo
            LEFT JOIN familias f ON a.cod_familia=f.cod_familia
            {$f['whereClause']} AND a.cod_familia IS NOT NULL";
        $having = $search !== '' ? " HAVING MAX(f.descripcion) LIKE ?" : " HAVING 1=1";

        $countSql = "SELECT COUNT(*) AS cnt FROM (SELECT a.cod_familia {$base} GROUP BY a.cod_familia {$having}) x";
        $countParams = array_merge($f['params'], $search !== '' ? ["%{$search}%"] : []);
        $total = (int)$erp->select($countSql, $countParams)[0]->cnt;

        $dataSql = "SELECT a.cod_familia cod_familia, MAX(f.descripcion) familia,
                SUM(l.importe_impuestos) revenue, SUM(l.precio_coste*l.cantidad) total_cost,
                SUM(l.importe_impuestos-(l.precio_coste*l.cantidad)) gross_profit,
                COUNT(DISTINCT v.cod_venta) orders
            {$base} GROUP BY a.cod_familia {$having}
            ORDER BY {$orderExpr} {$orderDir} OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $dataParams = array_merge($f['params'], $search !== '' ? ["%{$search}%"] : []);
        $pag = $this->paginar($total, $page, $perPage);
        $dataParams[] = $pag['offset'];
        $dataParams[] = $pag['perPage'];

        $rows = array_map(function ($r) {
            $r = (array)$r;
            $r['margin_rate'] = $r['revenue'] > 0 ? (($r['revenue'] - $r['total_cost']) / $r['revenue']) * 100 : 0;
            return $r;
        }, $erp->select($dataSql, $dataParams));

        return view('financial.detalle', $this->baseVista($f, [
            'titulo' => 'Rentabilidad por Familia',
            'seccion' => 'familias',
            'columnas' => [
                ['key' => 'familia', 'label' => 'Familia', 'align' => 'left', 'sortable' => true, 'type' => 'text'],
                ['key' => 'revenue', 'label' => 'Facturación', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'total_cost', 'label' => 'Coste', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'gross_profit', 'label' => 'Beneficio', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'margin_rate', 'label' => 'Margen %', 'sortable' => true, 'type' => 'pctbadge'],
                ['key' => 'orders', 'label' => 'Pedidos', 'sortable' => true, 'type' => 'int'],
            ],
            'rows' => $rows, 'sort' => $sort, 'dir' => $dir, 'search' => $search,
            'page' => $pag['page'], 'totalPages' => $pag['totalPages'], 'total' => $pag['total'],
        ]));
    }

    // ===== Detalle: Productos =====
    public function detalleProductos(Request $request)
    {
        $f = $this->filtros($request);
        $erp = DB::connection('erp');
        $search = trim((string)$request->input('search', ''));
        $sort = (string)$request->input('sort', 'gross_profit');
        $dir = $this->dirValid($request->input('dir'));
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 25;

        $sortMap = [
            'total_qty' => 'SUM(l.cantidad)',
            'revenue' => 'SUM(l.importe_impuestos)',
            'gross_profit' => 'SUM(l.importe_impuestos-(l.precio_coste*l.cantidad))',
            'margin_rate' => '(SUM(l.importe_impuestos)-SUM(l.precio_coste*l.cantidad))*1.0/NULLIF(SUM(l.importe_impuestos),0)',
            'margin_per_unit' => 'MAX(l.precio)-MAX(l.precio_coste)',
            'descripcion' => 'MAX(l.descripcion)',
        ];
        $orderExpr = $sortMap[$sort] ?? $sortMap['gross_profit'];
        $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';

        $base = "FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v ON l.cod_venta=v.cod_venta AND l.tipo_venta=v.tipo_venta AND l.cod_empresa=v.cod_empresa AND l.cod_caja=v.cod_caja
            LEFT JOIN articulos a ON l.cod_articulo=a.cod_articulo
            LEFT JOIN familias fa ON a.cod_familia=fa.cod_familia
            {$f['whereClause']}";
        $having = " HAVING SUM(l.cantidad) > 10";
        if ($search !== '') $having .= " AND (MAX(l.descripcion) LIKE ? OR l.cod_articulo LIKE ?)";

        $countSql = "SELECT COUNT(*) AS cnt FROM (SELECT l.cod_articulo {$base} GROUP BY l.cod_articulo {$having}) x";
        $sp = $search !== '' ? ["%{$search}%", "%{$search}%"] : [];
        $total = (int)$erp->select($countSql, array_merge($f['params'], $sp))[0]->cnt;

        $dataSql = "SELECT l.cod_articulo cod_articulo, MAX(l.descripcion) descripcion, MAX(fa.descripcion) familia,
                SUM(l.cantidad) total_qty, SUM(l.importe_impuestos) revenue,
                SUM(l.precio_coste*l.cantidad) total_cost,
                SUM(l.importe_impuestos-(l.precio_coste*l.cantidad)) gross_profit,
                COUNT(DISTINCT v.cod_venta) orders,
                MAX(l.precio_coste) unit_cost, MAX(l.precio) unit_price
            {$base} GROUP BY l.cod_articulo {$having}
            ORDER BY {$orderExpr} {$orderDir} OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $pag = $this->paginar($total, $page, $perPage);
        $dataParams = array_merge($f['params'], $sp, [$pag['offset'], $pag['perPage']]);

        $rows = array_map(function ($r) {
            $r = (array)$r;
            $r['margin_rate'] = $r['revenue'] > 0 ? (($r['revenue'] - $r['total_cost']) / $r['revenue']) * 100 : 0;
            $r['margin_per_unit'] = (float)$r['unit_price'] - (float)$r['unit_cost'];
            return $r;
        }, $erp->select($dataSql, $dataParams));

        return view('financial.detalle', $this->baseVista($f, [
            'titulo' => 'Productos por Rentabilidad',
            'seccion' => 'productos',
            'columnas' => [
                ['key' => 'descripcion', 'label' => 'Producto', 'align' => 'left', 'sortable' => true, 'type' => 'product'],
                ['key' => 'total_qty', 'label' => 'Und. Vendidas', 'sortable' => true, 'type' => 'int'],
                ['key' => 'revenue', 'label' => 'Facturación', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'gross_profit', 'label' => 'Beneficio', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'margin_rate', 'label' => 'Margen %', 'sortable' => true, 'type' => 'pctbadge'],
                ['key' => 'margin_per_unit', 'label' => 'Margen/Und', 'sortable' => true, 'type' => 'euro2'],
            ],
            'rows' => $rows, 'sort' => $sort, 'dir' => $dir, 'search' => $search,
            'page' => $pag['page'], 'totalPages' => $pag['totalPages'], 'total' => $pag['total'],
        ]));
    }

    // ===== Detalle: Clientes =====
    public function detalleClientes(Request $request)
    {
        $f = $this->filtros($request);
        $erp = DB::connection('erp');
        $search = trim((string)$request->input('search', ''));
        $sort = (string)$request->input('sort', 'gross_profit');
        $dir = $this->dirValid($request->input('dir'));
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 25;

        $sortMap = [
            'revenue' => 'SUM(l.importe_impuestos)',
            'gross_profit' => 'SUM(l.importe_impuestos-(l.precio_coste*l.cantidad))',
            'margin_rate' => '(SUM(l.importe_impuestos)-SUM(l.precio_coste*l.cantidad))*1.0/NULLIF(SUM(l.importe_impuestos),0)',
            'orders' => 'COUNT(DISTINCT v.cod_venta)',
            'avg_order_value' => 'SUM(l.importe_impuestos)*1.0/NULLIF(COUNT(DISTINCT v.cod_venta),0)',
            'razon_social' => 'MAX(c.razon_social)',
        ];
        $orderExpr = $sortMap[$sort] ?? $sortMap['gross_profit'];
        $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';

        $base = "FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v ON l.cod_venta=v.cod_venta AND l.tipo_venta=v.tipo_venta AND l.cod_empresa=v.cod_empresa AND l.cod_caja=v.cod_caja
            LEFT JOIN clientes c ON v.cod_cliente=c.cod_cliente
            {$f['whereClause']}";
        $having = " HAVING 1=1";
        if ($search !== '') $having .= " AND (MAX(c.razon_social) LIKE ? OR MAX(c.poblacion) LIKE ?)";

        $countSql = "SELECT COUNT(*) AS cnt FROM (SELECT v.cod_cliente {$base} GROUP BY v.cod_cliente {$having}) x";
        $sp = $search !== '' ? ["%{$search}%", "%{$search}%"] : [];
        $total = (int)$erp->select($countSql, array_merge($f['params'], $sp))[0]->cnt;

        $dataSql = "SELECT v.cod_cliente cod_cliente, MAX(c.razon_social) razon_social, MAX(c.poblacion) poblacion,
                SUM(l.importe_impuestos) revenue, SUM(l.precio_coste*l.cantidad) total_cost,
                SUM(l.importe_impuestos-(l.precio_coste*l.cantidad)) gross_profit,
                COUNT(DISTINCT v.cod_venta) orders
            {$base} GROUP BY v.cod_cliente {$having}
            ORDER BY {$orderExpr} {$orderDir} OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $pag = $this->paginar($total, $page, $perPage);
        $dataParams = array_merge($f['params'], $sp, [$pag['offset'], $pag['perPage']]);

        $rows = array_map(function ($r) {
            $r = (array)$r;
            $r['margin_rate'] = $r['revenue'] > 0 ? (($r['revenue'] - $r['total_cost']) / $r['revenue']) * 100 : 0;
            $r['avg_order_value'] = $r['orders'] > 0 ? $r['revenue'] / $r['orders'] : 0;
            return $r;
        }, $erp->select($dataSql, $dataParams));

        return view('financial.detalle', $this->baseVista($f, [
            'titulo' => 'Clientes por Rentabilidad',
            'seccion' => 'clientes',
            'columnas' => [
                ['key' => 'razon_social', 'label' => 'Cliente', 'align' => 'left', 'sortable' => true, 'type' => 'text'],
                ['key' => 'poblacion', 'label' => 'Población', 'align' => 'left', 'sortable' => false, 'type' => 'text'],
                ['key' => 'revenue', 'label' => 'Facturación', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'gross_profit', 'label' => 'Beneficio', 'sortable' => true, 'type' => 'euro'],
                ['key' => 'margin_rate', 'label' => 'Margen %', 'sortable' => true, 'type' => 'pctbadge'],
                ['key' => 'orders', 'label' => 'Pedidos', 'sortable' => true, 'type' => 'int'],
                ['key' => 'avg_order_value', 'label' => 'Ticket Medio', 'sortable' => true, 'type' => 'euro2'],
            ],
            'rows' => $rows, 'sort' => $sort, 'dir' => $dir, 'search' => $search,
            'page' => $pag['page'], 'totalPages' => $pag['totalPages'], 'total' => $pag['total'],
        ]));
    }

    // ===== Detalle: Variación de precio de compra (PPV) =====
    public function detallePpv(Request $request)
    {
        $f = $this->filtros($request);
        $r = $this->rangosCompra($f);
        $erp = DB::connection('erp');
        $search = trim((string)$request->input('search', ''));
        $sort = (string)$request->input('sort', 'var');
        $dir = $this->dirValid($request->input('dir'));
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 25;

        $comprasJoin = "FROM hist_compras_linea l
            JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
            WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0
              AND l.cod_articulo NOT IN ('ALMACEN','FERRETERIA','SANITARIOS','COCINAS','MARMOLES')";

        $cte = "WITH ra AS (
                SELECT l.cod_articulo, MAX(l.descripcion) descripcion,
                    SUM(CAST(l.cantidad AS float)) qty, SUM(CAST(l.importe AS float)) imp
                {$comprasJoin} AND c.fecha_compra>=? AND c.fecha_compra<? GROUP BY l.cod_articulo
            ), rb AS (
                SELECT l.cod_articulo,
                    SUM(CAST(l.cantidad AS float)) qty, SUM(CAST(l.importe AS float)) imp
                {$comprasJoin} AND c.fecha_compra>=? AND c.fecha_compra<? GROUP BY l.cod_articulo
            )";
        $inner = "SELECT a.cod_articulo cod, MAX(a.descripcion) descripcion, MAX(fam.descripcion) familia,
                a.qty qtyA, b.qty qtyB, a.imp/NULLIF(a.qty,0) pA, b.imp/NULLIF(b.qty,0) pB,
                (a.imp/NULLIF(a.qty,0))/NULLIF(b.imp/NULLIF(b.qty,0),0) ratio
            FROM ra a JOIN rb b ON a.cod_articulo=b.cod_articulo
            LEFT JOIN articulos art ON a.cod_articulo=art.cod_articulo
            LEFT JOIN familias fam ON art.cod_familia=fam.cod_familia
            WHERE a.qty>20 AND b.qty>20
              AND (a.imp/NULLIF(a.qty,0))/NULLIF(b.imp/NULLIF(b.qty,0),0) BETWEEN 0.2 AND 5";
        if ($search !== '') $inner .= " AND (a.descripcion LIKE ? OR a.cod_articulo LIKE ?)";
        $inner .= " GROUP BY a.cod_articulo, a.descripcion, a.qty, b.qty, a.imp, b.imp";

        $baseParams = [$r['idxStart'], $r['idxEnd'], $r['ppvStartB'], $r['ppvEndB']];
        $sp = $search !== '' ? ["%{$search}%", "%{$search}%"] : [];

        $countSql = "{$cte} SELECT COUNT(*) AS cnt FROM ({$inner}) x";
        $total = (int)$erp->select($countSql, array_merge($baseParams, $sp))[0]->cnt;

        $sortMap = ['var' => 'ratio', 'pA' => 'pA', 'pB' => 'pB', 'qtyA' => 'qtyA', 'qtyB' => 'qtyB', 'descripcion' => 'descripcion'];
        $orderExpr = $sortMap[$sort] ?? 'ratio';
        $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';
        $pag = $this->paginar($total, $page, $perPage);

        $dataSql = "{$cte} {$inner} ORDER BY {$orderExpr} {$orderDir} OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $dataParams = array_merge($baseParams, $sp, [$pag['offset'], $pag['perPage']]);

        $rows = array_map(function ($x) {
            $x = (array)$x;
            $pA = (float)$x['pA']; $pB = (float)$x['pB'];
            $x['pA'] = $pA; $x['pB'] = $pB;
            $x['var'] = ($pA > 0 && $pB > 0) ? ($pA / $pB - 1) * 100 : 0;
            $x['qtyA'] = (float)$x['qtyA']; $x['qtyB'] = (float)$x['qtyB'];
            return $x;
        }, $erp->select($dataSql, $dataParams));

        return view('financial.detalle', $this->baseVista($f, [
            'titulo' => 'Variación de precio de compra (PPV)',
            'seccion' => 'ppv',
            'subtitulo' => "{$r['periodoA']} vs {$r['periodoB']}",
            'columnas' => [
                ['key' => 'descripcion', 'label' => 'Artículo', 'align' => 'left', 'sortable' => true, 'type' => 'product'],
                ['key' => 'familia', 'label' => 'Familia', 'align' => 'left', 'sortable' => false, 'type' => 'text'],
                ['key' => 'pB', 'label' => 'P. anterior', 'sortable' => true, 'type' => 'price'],
                ['key' => 'pA', 'label' => 'P. actual', 'sortable' => true, 'type' => 'price'],
                ['key' => 'var', 'label' => 'Var.', 'sortable' => true, 'type' => 'varbadge'],
                ['key' => 'qtyA', 'label' => 'Und. act.', 'sortable' => true, 'type' => 'int'],
                ['key' => 'qtyB', 'label' => 'Und. ant.', 'sortable' => false, 'type' => 'int'],
            ],
            'rows' => $rows, 'sort' => $sort, 'dir' => $dir, 'search' => $search,
            'page' => $pag['page'], 'totalPages' => $pag['totalPages'], 'total' => $pag['total'],
        ]));
    }

    // ===== Datos mensuales de evolución (AJAX para el filtro del gráfico) =====
    public function evolucionData(Request $request)
    {
        $f = $this->filtros($request);
        $cacheKey = 'financial_evol_' . $f['yearFrom'] . '_' . $f['yearTo'] . '_' . $f['monthFrom'] . '_' . $f['monthTo'];
        $payload = cache()->remember($cacheKey, now()->addMinutes(10), function () use ($f) {
            $erp = DB::connection('erp');
            $rows = $erp->select("
                SELECT YEAR(v.fecha_venta) as year, MONTH(v.fecha_venta) as month,
                    SUM(l.importe_impuestos) as revenue,
                    SUM(l.precio_coste * l.cantidad) as total_cost,
                    SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as gross_profit
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera v
                    ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                    AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                {$f['whereClause']}
                GROUP BY YEAR(v.fecha_venta), MONTH(v.fecha_venta)
                ORDER BY year ASC, month ASC
            ", $f['params']);

            $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            $labels = $revenue = $cost = $profit = $margin = [];
            foreach ($rows as $r) {
                $rev = (float)$r->revenue; $c = (float)$r->total_cost; $p = (float)$r->gross_profit;
                $labels[] = $meses[(int)$r->month - 1] . '/' . $r->year;
                $revenue[] = $rev; $cost[] = $c; $profit[] = $p;
                $margin[] = $rev > 0 ? (($rev - $c) / $rev) * 100 : 0;
            }
            return compact('labels', 'revenue', 'cost', 'profit', 'margin');
        });
        return response()->json($payload);
    }
}
