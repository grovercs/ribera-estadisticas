<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyController extends Controller
{
    public function index()
    {
        $families = DB::table('erp_families')
            ->select('cod_familia', 'descripcion')
            ->orderBy('cod_familia')
            ->get();

        $familyCodes = $families->pluck('cod_familia')->toArray();

        // Métricas por familia
        $metrics = DB::table('erp_products')
            ->whereIn('cod_familia', $familyCodes)
            ->select('cod_familia', DB::raw('COUNT(*) as product_count'))
            ->groupBy('cod_familia')
            ->get()
            ->keyBy('cod_familia');

        $stockMetrics = DB::table('erp_stocks as s')
            ->join('erp_products as p', 's.cod_articulo', '=', 'p.cod_articulo')
            ->whereIn('p.cod_familia', $familyCodes)
            ->select('p.cod_familia', DB::raw('SUM(s.existencias) as stock_total'))
            ->groupBy('p.cod_familia')
            ->get()
            ->keyBy('cod_familia');

        $revenueMetrics = DB::table('erp_sale_lines as sl')
            ->join('erp_products as p', 'sl.cod_articulo', '=', 'p.cod_articulo')
            ->whereIn('p.cod_familia', $familyCodes)
            ->select('p.cod_familia', DB::raw('SUM(sl.importe_impuestos) as total_revenue'))
            ->groupBy('p.cod_familia')
            ->get()
            ->keyBy('cod_familia');

        $subfamilyCounts = DB::table('erp_subfamilies')
            ->whereIn('cod_familia', $familyCodes)
            ->select('cod_familia', DB::raw('COUNT(*) as subfamily_count'))
            ->groupBy('cod_familia')
            ->get()
            ->keyBy('cod_familia');

        foreach ($families as $family) {
            $family->product_count = $metrics[$family->cod_familia]->product_count ?? 0;
            $family->stock_total = $stockMetrics[$family->cod_familia]->stock_total ?? 0;
            $family->total_revenue = $revenueMetrics[$family->cod_familia]->total_revenue ?? 0;
            $family->subfamily_count = $subfamilyCounts[$family->cod_familia]->subfamily_count ?? 0;
        }

        return view('families.index', compact('families'));
    }

    public function show(string $cod_familia)
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
            ->orderBy('cod_subfamilia')
            ->get();

        $subfamilyCodes = $subfamilies->pluck('cod_subfamilia')->toArray();

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
            ->select('p.cod_subfamilia', DB::raw('SUM(sl.importe_impuestos) as total_revenue'))
            ->groupBy('p.cod_subfamilia')
            ->get()
            ->keyBy('cod_subfamilia');

        foreach ($subfamilies as $sub) {
            $sub->product_count = $metrics[$sub->cod_subfamilia]->product_count ?? 0;
            $sub->stock_total = $stockMetrics[$sub->cod_subfamilia]->stock_total ?? 0;
            $sub->total_revenue = $revenueMetrics[$sub->cod_subfamilia]->total_revenue ?? 0;
        }

        // Productos de la familia (top 20 por facturación)
        $products = DB::table('erp_products')
            ->where('cod_familia', $cod_familia)
            ->orderBy('cod_articulo')
            ->select('cod_articulo', 'marca', 'cod_subfamilia')
            ->take(20)
            ->get();

        $productCodes = $products->pluck('cod_articulo')->toArray();

        $productStocks = DB::table('erp_stocks')
            ->whereIn('cod_articulo', $productCodes)
            ->select('cod_articulo', DB::raw('SUM(existencias) as stock_total'))
            ->groupBy('cod_articulo')
            ->get()
            ->keyBy('cod_articulo');

        $productRevenues = DB::table('erp_sale_lines')
            ->whereIn('cod_articulo', $productCodes)
            ->select('cod_articulo', DB::raw('SUM(importe_impuestos) as total_revenue'), DB::raw('SUM(cantidad) as total_qty'))
            ->groupBy('cod_articulo')
            ->get()
            ->keyBy('cod_articulo');

        foreach ($products as $product) {
            $product->stock_total = $productStocks[$product->cod_articulo]->stock_total ?? 0;
            $product->total_revenue = $productRevenues[$product->cod_articulo]->total_revenue ?? 0;
            $product->total_qty = $productRevenues[$product->cod_articulo]->total_qty ?? 0;
        }

        return view('families.show', compact('family', 'subfamilies', 'products'));
    }
}
