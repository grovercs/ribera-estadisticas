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
            ->join('erp_products', 'erp_sale_lines.cod_articulo', '=', 'erp_products.cod_articulo')
            ->leftJoin('erp_stocks', 'erp_sale_lines.cod_articulo', '=', 'erp_stocks.cod_articulo')
            ->select(
                'erp_sale_lines.cod_articulo',
                'erp_sale_lines.descripcion',
                'erp_products.marca',
                'erp_products.cod_familia',
                'erp_products.cod_subfamilia',
                DB::raw('SUM(erp_sale_lines.cantidad) as total_qty'),
                DB::raw('SUM(erp_sale_lines.importe_impuestos) as total_revenue'),
                DB::raw('SUM(erp_stocks.existencias) as stock_total')
            )
            ->whereNotNull('erp_sale_lines.cod_articulo')
            ->groupBy('erp_sale_lines.cod_articulo', 'erp_sale_lines.descripcion', 'erp_products.marca', 'erp_products.cod_familia', 'erp_products.cod_subfamilia')
            ->orderByDesc('total_revenue')
            ->take(10)
            ->get();

        return view('dashboard.index', compact(
            'totalSales', 'totalOrders', 'avgTicket', 'pendingAmount',
            'salesByMonth', 'alerts', 'topClients', 'topProducts'
        ));
    }
}
