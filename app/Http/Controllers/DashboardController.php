<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\ErpSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // KPIs desde datos reales del ERP
        $totalSales = ErpSale::sum('importe_impuestos');
        $totalOrders = ErpSale::count();
        $avgTicket = ErpSale::avg('importe_impuestos') ?? 0;
        $pendingAmount = ErpSale::sum('importe_pendiente');

        // Ventas por mes (últimos 12 meses) – usamos fecha_venta
        $salesByMonth = ErpSale::where('fecha_venta', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(fecha_venta, "%Y-%m") as month, SUM(importe_impuestos) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Si no hay datos reales de los últimos 12 meses, mostramos todo el rango disponible
        if (empty($salesByMonth)) {
            $salesByMonth = ErpSale::selectRaw('DATE_FORMAT(fecha_venta, "%Y-%m") as month, SUM(importe_impuestos) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        // Alertas activas (mantenemos las de la app)
        $alerts = Alert::where('status', 'active')
            ->with(['product', 'client'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Top clientes por gasto (desde ventas ERP, enriquecido con maestros)
        $topClients = ErpSale::query()
            ->join('erp_clients', 'erp_sales.cod_cliente', '=', 'erp_clients.cod_cliente')
            ->leftJoin('erp_sellers', 'erp_clients.cod_vendedor', '=', 'erp_sellers.cod_vendedor')
            ->select(
                'erp_clients.razon_social',
                'erp_clients.poblacion',
                'erp_clients.provincia',
                'erp_sellers.nombre as vendedor',
                DB::raw('SUM(erp_sales.importe_impuestos) as total_spent')
            )
            ->groupBy('erp_sales.cod_cliente', 'erp_clients.razon_social', 'erp_clients.poblacion', 'erp_clients.provincia', 'erp_sellers.nombre')
            ->orderByDesc('total_spent')
            ->take(10)
            ->get();

        // Top productos por facturacion (desde lineas ERP, enriquecido con maestros)
        $topProducts = DB::table('erp_sale_lines')
            ->select('cod_articulo', 'descripcion', DB::raw('SUM(cantidad) as total_qty'), DB::raw('SUM(importe_impuestos) as total_revenue'))
            ->whereNotNull('cod_articulo')
            ->groupBy('cod_articulo', 'descripcion')
            ->orderByDesc('total_revenue')
            ->take(10)
            ->get();

        $productCodes = $topProducts->pluck('cod_articulo')->toArray();

        $productMasters = DB::table('erp_products')
            ->whereIn('cod_articulo', $productCodes)
            ->select('cod_articulo', 'marca', 'cod_familia', 'cod_subfamilia')
            ->get()
            ->keyBy('cod_articulo');

        $productStocks = DB::table('erp_stocks')
            ->whereIn('cod_articulo', $productCodes)
            ->select('cod_articulo', DB::raw('SUM(existencias) as stock_total'))
            ->groupBy('cod_articulo')
            ->get()
            ->keyBy('cod_articulo');

        foreach ($topProducts as $product) {
            $product->marca = $productMasters[$product->cod_articulo]->marca ?? null;
            $product->cod_familia = $productMasters[$product->cod_articulo]->cod_familia ?? null;
            $product->cod_subfamilia = $productMasters[$product->cod_articulo]->cod_subfamilia ?? null;
            $product->stock_total = $productStocks[$product->cod_articulo]->stock_total ?? 0;
        }

        return view('dashboard.index', compact(
            'totalSales', 'totalOrders', 'avgTicket', 'pendingAmount',
            'salesByMonth', 'alerts', 'topClients', 'topProducts'
        ));
    }
}
