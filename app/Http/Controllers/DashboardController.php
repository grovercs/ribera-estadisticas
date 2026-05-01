<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Client;
use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // KPIs
        $totalSales = Order::where('status', '!=', 'cancelled')->sum('total');
        $totalOrders = Order::where('status', '!=', 'cancelled')->count();
        $avgMargin = Order::where('status', '!=', 'cancelled')->avg('total') ?? 0;
        $activeShipments = Order::where('status', 'shipped')->count();

        // Ventas por mes (últimos 12 meses)
        $salesByMonth = Order::where('status', '!=', 'cancelled')
            ->where('order_date', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month, SUM(total) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Alertas activas
        $alerts = Alert::where('status', 'active')
            ->with(['product', 'order', 'client'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Productos más vendidos
        $topProducts = \DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.name', \DB::raw('SUM(order_items.quantity) as total_qty'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'totalSales', 'totalOrders', 'avgMargin', 'activeShipments',
            'salesByMonth', 'alerts', 'topProducts'
        ));
    }
}
