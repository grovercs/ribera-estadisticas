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

        // Top clientes por gasto (desde ventas ERP)
        $topClients = ErpSale::select('razon_social', DB::raw('SUM(importe_impuestos) as total_spent'))
            ->groupBy('razon_social')
            ->orderByDesc('total_spent')
            ->take(5)
            ->get();

        // Top productos por facturacion (desde lineas ERP)
        $topProducts = DB::table('erp_sale_lines')
            ->select('cod_articulo', 'descripcion', DB::raw('SUM(cantidad) as total_qty'), DB::raw('SUM(importe_impuestos) as total_revenue'))
            ->whereNotNull('cod_articulo')
            ->groupBy('cod_articulo', 'descripcion')
            ->orderByDesc('total_revenue')
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'totalSales', 'totalOrders', 'avgTicket', 'pendingAmount',
            'salesByMonth', 'alerts', 'topClients', 'topProducts'
        ));
    }
}
