<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyController extends Controller
{
    public function index(Request $request)
    {
        // Sin caché por ahora - problemas de serialización
        $search = $request->input('search');
        $sortBy = $request->input('sort', 'revenue');

        // Obtener familias
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
                'metrics' => [], 'totalProducts' => 0, 'totalStock' => 0, 'totalRevenue' => 0,
                'totalSubfamilies' => 0, 'topFamilies' => [], 'search' => $search, 'sortBy' => $sortBy,
            ]);
        }

        // Subquery pre-agregada para productos
        $productsSubq = DB::table('erp_products')
            ->select('cod_familia', DB::raw('COUNT(*) as product_count'))
            ->groupBy('cod_familia');

        // Subquery pre-agregada para stocks
        $stockSubq = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->select('p.cod_familia', DB::raw('SUM(s.existencias) as stock_total'))
            ->groupBy('p.cod_familia');

        // Subquery para revenue
        $revenueSubq = DB::table('erp_sale_lines as sl')
            ->join('erp_sales as s', function ($join) {
                $join->on('sl.cod_venta', '=', 's.cod_venta')
                    ->on('sl.tipo_venta', '=', 's.tipo_venta')
                    ->on('sl.cod_empresa', '=', 's.cod_empresa')
                    ->on('sl.cod_caja', '=', 's.cod_caja');
            })
            ->join('erp_products as p', 'sl.cod_articulo', '=', 'p.cod_articulo')
            ->where(function ($q) {
                $q->whereNull('s.anulada')->orWhere('s.anulada', '')->orWhere('s.anulada', 'N');
            })
            ->select('p.cod_familia', DB::raw('SUM(sl.importe_impuestos) as total_revenue'))
            ->groupBy('p.cod_familia');

        // Subquery para subfamilias
        $subfamilySubq = DB::table('erp_subfamilies')
            ->select('cod_familia', DB::raw('COUNT(*) as subfamily_count'))
            ->groupBy('cod_familia');

        // Join con subqueries
        $metrics = DB::table('erp_families as f')
            ->select('f.cod_familia', 'f.descripcion',
                DB::raw('COALESCE(pc.product_count, 0) as product_count'),
                DB::raw('COALESCE(sc.stock_total, 0) as stock_total'),
                DB::raw('COALESCE(rc.total_revenue, 0) as total_revenue'),
                DB::raw('COALESCE(sf.subfamily_count, 0) as subfamily_count')
            )
            ->leftJoinSub($productsSubq, 'pc', 'f.cod_familia', '=', 'pc.cod_familia')
            ->leftJoinSub($stockSubq, 'sc', 'f.cod_familia', '=', 'sc.cod_familia')
            ->leftJoinSub($revenueSubq, 'rc', 'f.cod_familia', '=', 'rc.cod_familia')
            ->leftJoinSub($subfamilySubq, 'sf', 'f.cod_familia', '=', 'sf.cod_familia')
            ->whereIn('f.cod_familia', $familyCodes)
            ->orderBy('f.cod_familia')
            ->get();

        // Totales
        $totalProducts = $metrics->sum('product_count');
        $totalStock = $metrics->sum('stock_total');
        $totalRevenue = $metrics->sum('total_revenue');
        $totalSubfamilies = $metrics->sum('subfamily_count');

        // Ordenar
        if ($sortBy === 'revenue') $metrics = $metrics->sortByDesc('total_revenue');
        elseif ($sortBy === 'products') $metrics = $metrics->sortByDesc('product_count');
        elseif ($sortBy === 'stock') $metrics = $metrics->sortByDesc('stock_total');

        $topFamilies = $metrics->sortByDesc('total_revenue')->take(10);

        return view('families.index', compact('metrics', 'totalProducts', 'totalStock', 'totalRevenue', 'totalSubfamilies', 'topFamilies', 'search', 'sortBy'));
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
