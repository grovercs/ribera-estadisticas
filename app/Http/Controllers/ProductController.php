<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('erp_products as p')
            ->leftJoin('erp_families as f', 'p.cod_familia', '=', 'f.cod_familia')
            ->leftJoin('erp_subfamilies as sf', 'p.cod_subfamilia', '=', 'sf.cod_subfamilia')
            ->select(
                'p.cod_articulo',
                'p.marca',
                'f.descripcion as familia',
                'sf.descripcion as subfamilia',
                'p.precio_coste',
                'p.precio_venta_publico'
            );

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('p.cod_articulo', 'like', "%{$search}%")
                  ->orWhere('p.marca', 'like', "%{$search}%")
                  ->orWhere('f.descripcion', 'like', "%{$search}%")
                  ->orWhere('sf.descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('familia')) {
            $query->where('p.cod_familia', $request->input('familia'));
        }

        if ($request->filled('subfamilia')) {
            $query->where('p.cod_subfamilia', $request->input('subfamilia'));
        }

        $order = $request->input('order', 'revenue');
        if ($order === 'coste') {
            $query->orderByDesc('p.precio_coste');
        } elseif ($order === 'pvp') {
            $query->orderByDesc('p.precio_venta_publico');
        }

        $products = $query->paginate(20)->withQueryString();

        $codes = $products->pluck('cod_articulo')->toArray();

        $stocks = collect();
        if (!empty($codes)) {
            $stocks = DB::table('erp_stocks')
                ->select('cod_articulo', DB::raw('SUM(existencias) as stock_total'))
                ->whereIn('cod_articulo', $codes)
                ->groupBy('cod_articulo')
                ->get()
                ->keyBy('cod_articulo');
        }

        $sales = collect();
        if (!empty($codes)) {
            $sales = DB::table('erp_sale_lines')
                ->select('cod_articulo', DB::raw('SUM(cantidad) as total_qty'), DB::raw('SUM(importe_impuestos) as total_revenue'))
                ->whereIn('cod_articulo', $codes)
                ->groupBy('cod_articulo')
                ->get()
                ->keyBy('cod_articulo');
        }

        foreach ($products as $product) {
            $product->stock_total = $stocks[$product->cod_articulo]->stock_total ?? 0;
            $product->total_qty = $sales[$product->cod_articulo]->total_qty ?? 0;
            $product->total_revenue = $sales[$product->cod_articulo]->total_revenue ?? 0;
        }

        $productItems = collect($products->items());

        if ($request->boolean('stock_bajo')) {
            $productItems = $productItems->filter(fn($p) => $p->stock_total <= 0)->values();
        }

        if ($order === 'stock') {
            $productItems = $productItems->sortByDesc('stock_total')->values();
        } elseif ($order === 'revenue') {
            $productItems = $productItems->sortByDesc('total_revenue')->values();
        }

        $topProduct = $productItems->first();

        $families = DB::table('erp_families')->select('cod_familia', 'descripcion')->orderBy('descripcion')->get();
        $subfamilies = DB::table('erp_subfamilies')->select('cod_subfamilia', 'descripcion')->orderBy('descripcion')->get();

        $alerts = Alert::where('type', 'low_stock')->where('status', 'active')->get();

        return view('stock.index', compact('products', 'productItems', 'topProduct', 'alerts', 'families', 'subfamilies'));
    }
}
