<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sortBy = $request->input('sort', 'revenue');

        // Filtros de fecha: por defecto año en curso completo
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $yearFrom = $request->input('year_from', $currentYear);
        $yearTo = $request->input('year_to', $currentYear);
        $monthFrom = $request->input('month_from', 1);
        $monthTo = $request->input('month_to', $currentMonth);

        // Normalizar
        $yearFrom = is_numeric($yearFrom) ? (int) $yearFrom : $currentYear;
        $yearTo = is_numeric($yearTo) ? (int) $yearTo : $currentYear;
        $monthFrom = is_numeric($monthFrom) ? (int) $monthFrom : 1;
        $monthTo = is_numeric($monthTo) ? (int) $monthTo : $currentMonth;

        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        // Obtener años disponibles desde ERP
        $availableYears = [];
        $minYear = $currentYear - 3;
        $maxYear = $currentYear;
        try {
            $rows = DB::connection('erp')->select("
                SELECT DISTINCT YEAR(fecha_venta) as year
                FROM hist_ventas_cabecera
                WHERE fecha_venta IS NOT NULL
                ORDER BY year ASC
            ");
            $availableYears = array_column($rows, 'year');
            if (!empty($availableYears)) {
                $minYear = (int) min($availableYears);
                $maxYear = (int) max($availableYears);
            }
        } catch (\Exception $e) {
            \Log::warning('FamilyController: no se pudieron obtener años del ERP: ' . $e->getMessage());
        }

        $yearRange = range($minYear, $maxYear);

        // Maestros de familias desde MySQL local
        $query = DB::table('erp_families')->select('cod_familia', 'descripcion');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                  ->orWhere('cod_familia', 'like', "%{$search}%");
            });
        }
        $families = $query->orderBy('cod_familia')->get();
        $familyCodes = $families->pluck('cod_familia')->toArray();

        if (empty($familyCodes)) {
            return view('families.index', [
                'metrics' => collect(),
                'totalProducts' => 0,
                'totalStock' => 0,
                'totalRevenue' => 0,
                'totalRevenuePrev' => 0,
                'totalRevenueGrowth' => 0,
                'totalSubfamilies' => 0,
                'topFamilies' => collect(),
                'search' => $search,
                'sortBy' => $sortBy,
                'yearFrom' => $yearFrom,
                'yearTo' => $yearTo,
                'monthFrom' => $monthFrom,
                'monthTo' => $monthTo,
                'minYear' => $minYear,
                'maxYear' => $maxYear,
                'yearRange' => $yearRange,
            ]);
        }

        // Métricas de maestros desde MySQL local
        $productsSubq = DB::table('erp_products')
            ->select('cod_familia', DB::raw('COUNT(*) as product_count'))
            ->groupBy('cod_familia');

        $stockSubq = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->select('p.cod_familia', DB::raw('SUM(s.existencias) as stock_total'))
            ->groupBy('p.cod_familia');

        $subfamilySubq = DB::table('erp_subfamilies')
            ->select('cod_familia', DB::raw('COUNT(*) as subfamily_count'))
            ->groupBy('cod_familia');

        $localMetrics = DB::table('erp_families as f')
            ->select('f.cod_familia', 'f.descripcion',
                DB::raw('COALESCE(pc.product_count, 0) as product_count'),
                DB::raw('COALESCE(sc.stock_total, 0) as stock_total'),
                DB::raw('COALESCE(sf.subfamily_count, 0) as subfamily_count')
            )
            ->leftJoinSub($productsSubq, 'pc', 'f.cod_familia', '=', 'pc.cod_familia')
            ->leftJoinSub($stockSubq, 'sc', 'f.cod_familia', '=', 'sc.cod_familia')
            ->leftJoinSub($subfamilySubq, 'sf', 'f.cod_familia', '=', 'sf.cod_familia')
            ->whereIn('f.cod_familia', $familyCodes)
            ->orderBy('f.cod_familia')
            ->get()
            ->keyBy('cod_familia');

        // Facturación desde ERP SQL Server en tiempo real
        $revenueCurrent = [];
        $revenuePrev = [];
        try {
            [$revenueCurrent, $revenuePrev] = $this->fetchRevenueFromErp($familyCodes, $yearFrom, $yearTo, $monthFrom, $monthTo);
        } catch (\Exception $e) {
            \Log::error('FamilyController: error al consultar facturación ERP: ' . $e->getMessage());
        }

        // Combinar métricas
        $metrics = [];
        foreach ($families as $family) {
            $local = $localMetrics[$family->cod_familia] ?? null;
            $revCurrent = (float) ($revenueCurrent[$family->cod_familia] ?? 0);
            $revPrev = (float) ($revenuePrev[$family->cod_familia] ?? 0);
            $growth = $revPrev > 0 ? (($revCurrent - $revPrev) / $revPrev) * 100 : ($revCurrent > 0 ? 100 : 0);

            $metrics[] = (object) [
                'cod_familia' => $family->cod_familia,
                'descripcion' => $family->descripcion ?: 'Sin descripción',
                'product_count' => (int) ($local->product_count ?? 0),
                'stock_total' => (float) ($local->stock_total ?? 0),
                'subfamily_count' => (int) ($local->subfamily_count ?? 0),
                'total_revenue' => $revCurrent,
                'total_revenue_prev' => $revPrev,
                'growth' => $growth,
            ];
        }

        $metrics = collect($metrics);

        // Totales
        $totalProducts = $metrics->sum('product_count');
        $totalStock = $metrics->sum('stock_total');
        $totalRevenue = $metrics->sum('total_revenue');
        $totalRevenuePrev = $metrics->sum('total_revenue_prev');
        $totalRevenueGrowth = $totalRevenuePrev > 0
            ? (($totalRevenue - $totalRevenuePrev) / $totalRevenuePrev) * 100
            : ($totalRevenue > 0 ? 100 : 0);
        $totalSubfamilies = $metrics->sum('subfamily_count');

        // Ordenar
        if ($sortBy === 'revenue') {
            $metrics = $metrics->sortByDesc('total_revenue');
        } elseif ($sortBy === 'products') {
            $metrics = $metrics->sortByDesc('product_count');
        } elseif ($sortBy === 'stock') {
            $metrics = $metrics->sortByDesc('stock_total');
        } elseif ($sortBy === 'growth') {
            $metrics = $metrics->sortByDesc('growth');
        }

        $topFamilies = $metrics->sortByDesc('total_revenue')->take(10);

        return view('families.index', compact(
            'metrics', 'totalProducts', 'totalStock', 'totalRevenue', 'totalRevenuePrev',
            'totalRevenueGrowth', 'totalSubfamilies', 'topFamilies', 'search', 'sortBy',
            'yearFrom', 'yearTo', 'monthFrom', 'monthTo', 'minYear', 'maxYear', 'yearRange'
        ));
    }

    /**
     * Obtiene la facturación por familia desde el ERP para el período actual y el anterior.
     *
     * @param string[] $familyCodes
     * @param int      $yearFrom
     * @param int      $yearTo
     * @param int      $monthFrom
     * @param int      $monthTo
     *
     * @return array{0: array<string, float>, 1: array<string, float>}
     */
    private function fetchRevenueFromErp(array $familyCodes, int $yearFrom, int $yearTo, int $monthFrom, int $monthTo): array
    {
        $erp = DB::connection('erp');
        $placeholders = implode(',', array_fill(0, count($familyCodes), '?'));

        // Período actual
        $whereCurrent = $this->buildPeriodWhere($yearFrom, $yearTo, $monthFrom, $monthTo);
        $paramsCurrent = array_merge(
            [$yearFrom, $yearTo, $yearFrom, $yearFrom, $monthFrom, $yearTo, $yearTo, $monthTo],
            $familyCodes
        );

        $rowsCurrent = $erp->select("
            SELECT a.cod_familia, SUM(l.importe_impuestos) as total_revenue
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
            WHERE {$whereCurrent}
                AND a.cod_familia IN ({$placeholders})
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL
            GROUP BY a.cod_familia
        ", $paramsCurrent);

        // Período anterior: mismo rango de meses/años desplazado N años atrás
        $yearSpan = $yearTo - $yearFrom;
        $prevYearFrom = $yearFrom - $yearSpan - 1;
        $prevYearTo = $yearTo - $yearSpan - 1;

        $wherePrev = $this->buildPeriodWhere($prevYearFrom, $prevYearTo, $monthFrom, $monthTo);
        $paramsPrev = array_merge(
            [$prevYearFrom, $prevYearTo, $prevYearFrom, $prevYearFrom, $monthFrom, $prevYearTo, $prevYearTo, $monthTo],
            $familyCodes
        );

        $rowsPrev = $erp->select("
            SELECT a.cod_familia, SUM(l.importe_impuestos) as total_revenue
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
            WHERE {$wherePrev}
                AND a.cod_familia IN ({$placeholders})
                AND ISNULL(v.anulada, '') <> 'S'
                AND l.cod_articulo IS NOT NULL
            GROUP BY a.cod_familia
        ", $paramsPrev);

        $current = [];
        foreach ($rowsCurrent as $r) {
            $current[$r->cod_familia] = (float) $r->total_revenue;
        }

        $prev = [];
        foreach ($rowsPrev as $r) {
            $prev[$r->cod_familia] = (float) $r->total_revenue;
        }

        return [$current, $prev];
    }

    /**
     * Construye la cláusula WHERE para filtrar por rango de año/mes.
     */
    private function buildPeriodWhere(int $yearFrom, int $yearTo, int $monthFrom, int $monthTo): string
    {
        return "YEAR(v.fecha_venta) BETWEEN ? AND ?
            AND (
                (YEAR(v.fecha_venta) > ? OR (YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) >= ?))
                AND
                (YEAR(v.fecha_venta) < ? OR (YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) <= ?))
            )";
    }

    public function show(string $cod_familia, Request $request)
    {
        $family = DB::table('erp_families')
            ->where('cod_familia', $cod_familia)
            ->first();

        if (! $family) {
            abort(404);
        }

        $subfamilies = DB::table('erp_subfamilies')
            ->where('cod_familia', $cod_familia)
            ->select('cod_subfamilia', 'descripcion')
            ->orderBy('descripcion')
            ->get();

        $subfamilyCodes = $subfamilies->pluck('cod_subfamilia')->toArray();

        // Métricas por subfamilia
        $metrics = DB::table('erp_products')
            ->where('cod_familia', $cod_familia)
            ->whereIn('cod_subfamilia', $subfamilyCodes)
            ->select('cod_subfamilia', DB::raw('COUNT(*) as product_count'))
            ->groupBy('cod_subfamilia')
            ->get()
            ->keyBy('cod_subfamilia');

        $stockMetrics = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->where('p.cod_familia', $cod_familia)
            ->whereIn('p.cod_subfamilia', $subfamilyCodes)
            ->select('p.cod_subfamilia', DB::raw('SUM(s.existencias) as stock_total'))
            ->groupBy('p.cod_subfamilia')
            ->get()
            ->keyBy('cod_subfamilia');

        $revenueMetrics = DB::table('erp_sale_lines as sl')
            ->join('erp_products as p', 'sl.cod_articulo', '=', 'p.cod_articulo')
            ->where('p.cod_familia', $cod_familia)
            ->whereIn('p.cod_subfamilia', $subfamilyCodes)
            ->select('p.cod_subfamilia', DB::raw('SUM(sl.importe_impuestos) as total_revenue'), DB::raw('SUM(sl.cantidad) as total_qty'))
            ->groupBy('p.cod_subfamilia')
            ->get()
            ->keyBy('cod_subfamilia');

        // Totales de la familia
        $familyTotalProducts = 0;
        $familyTotalStock = 0;
        $familyTotalRevenue = 0;

        foreach ($subfamilies as $sub) {
            $sub->product_count = $metrics[$sub->cod_subfamilia]->product_count ?? 0;
            $sub->stock_total = $stockMetrics[$sub->cod_subfamilia]->stock_total ?? 0;
            $sub->total_revenue = $revenueMetrics[$sub->cod_subfamilia]->total_revenue ?? 0;
            $sub->total_qty = $revenueMetrics[$sub->cod_subfamilia]->total_qty ?? 0;

            $familyTotalProducts += $sub->product_count;
            $familyTotalStock += $sub->stock_total;
            $familyTotalRevenue += $sub->total_revenue;
        }

        // Productos paginados y ordenados por facturación
        $page = $request->input('page', 1);
        $perPage = 25;

        // Obtener IDs de productos primero para paginación manual
        $allProducts = DB::table('erp_products')
            ->where('cod_familia', $cod_familia)
            ->select('cod_articulo')
            ->get()
            ->pluck('cod_articulo')
            ->toArray();

        $totalProducts = count($allProducts);
        $totalPages = ceil($totalProducts / $perPage);
        $offset = ($page - 1) * $perPage;
        $productCodesPage = array_slice($allProducts, $offset, $perPage);

        // Obtener detalles de productos de la página
        $products = DB::table('erp_products')
            ->whereIn('cod_articulo', $productCodesPage)
            ->select('cod_articulo', 'marca', 'cod_subfamilia')
            ->orderBy('cod_articulo')
            ->get();

        $productStocks = DB::table('erp_stocks')
            ->whereIn('cod_articulo', $productCodesPage)
            ->select('cod_articulo', DB::raw('SUM(existencias) as stock_total'))
            ->groupBy('cod_articulo')
            ->get()
            ->keyBy('cod_articulo');

        $productRevenues = DB::table('erp_sale_lines')
            ->whereIn('cod_articulo', $productCodesPage)
            ->select('cod_articulo', DB::raw('SUM(importe_impuestos) as total_revenue'), DB::raw('SUM(cantidad) as total_qty'))
            ->groupBy('cod_articulo')
            ->get()
            ->keyBy('cod_articulo');

        foreach ($products as $product) {
            $product->stock_total = $productStocks[$product->cod_articulo]->stock_total ?? 0;
            $product->total_revenue = $productRevenues[$product->cod_articulo]->total_revenue ?? 0;
            $product->total_qty = $productRevenues[$product->cod_articulo]->total_qty ?? 0;
        }

        // Subfamilia con más facturación
        $topSubfamily = $subfamilies->sortByDesc('total_revenue')->first();

        return view('families.show', compact(
            'family', 'subfamilies', 'products', 'page', 'totalPages', 'totalProducts',
            'familyTotalProducts', 'familyTotalStock', 'familyTotalRevenue', 'topSubfamily'
        ));
    }
}
