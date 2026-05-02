<?php

namespace App\Http\Controllers;

use App\Models\ErpSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index()
    {
        // Top 10 clientes por gasto total (desde ventas ERP)
        $topClients = ErpSale::select(
                'cod_cliente',
                'razon_social',
                'cif',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(importe_impuestos) as total_spent')
            )
            ->whereNotNull('cod_cliente')
            ->groupBy('cod_cliente', 'razon_social', 'cif')
            ->orderByDesc('total_spent')
            ->take(10)
            ->get();

        // Todos los clientes únicos (paginados)
        $clients = ErpSale::select(
                'cod_cliente',
                'razon_social',
                'cif',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(importe_impuestos) as total_spent')
            )
            ->whereNotNull('cod_cliente')
            ->groupBy('cod_cliente', 'razon_social', 'cif')
            ->orderByDesc('total_spent')
            ->paginate(20);

        return view('clients.index', compact('clients', 'topClients'));
    }
}
