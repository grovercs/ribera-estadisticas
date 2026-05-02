<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $salesSubquery = DB::table('erp_sales')
            ->select('cod_cliente', DB::raw('SUM(importe_impuestos) as total_spent'), DB::raw('COUNT(*) as order_count'))
            ->groupBy('cod_cliente');

        $query = DB::table('erp_clients as c')
            ->select([
                'c.cod_cliente',
                'c.razon_social',
                'c.nombre_comercial',
                'c.cif',
                'c.poblacion',
                'c.provincia',
                'c.telefono',
                'c.e_mail',
                'c.fecha_alta',
                'c.limite_credito',
                's.nombre as vendedor',
                DB::raw('COALESCE(sales.total_spent, 0) as total_spent'),
                DB::raw('COALESCE(sales.order_count, 0) as order_count'),
            ])
            ->leftJoin('erp_sellers as s', 'c.cod_vendedor', '=', 's.cod_vendedor')
            ->leftJoinSub($salesSubquery, 'sales', function ($join) {
                $join->on('c.cod_cliente', '=', 'sales.cod_cliente');
            });

        // Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('c.razon_social', 'like', "%{$search}%")
                    ->orWhere('c.nombre_comercial', 'like', "%{$search}%")
                    ->orWhere('c.cif', 'like', "%{$search}%")
                    ->orWhere('c.poblacion', 'like', "%{$search}%")
                    ->orWhere('c.cod_cliente', 'like', "%{$search}%");
            });
        }

        if ($request->filled('poblacion')) {
            $query->where('c.poblacion', $request->input('poblacion'));
        }

        if ($request->filled('provincia')) {
            $query->where('c.provincia', $request->input('provincia'));
        }

        if ($request->filled('vendedor')) {
            $query->where('c.cod_vendedor', $request->input('vendedor'));
        }

        $clients = $query->orderBy('c.razon_social')->paginate(20)->withQueryString();

        // Filter lists for dropdowns
        $poblaciones = DB::table('erp_clients')
            ->whereNotNull('poblacion')
            ->where('poblacion', '!=', '')
            ->distinct()
            ->orderBy('poblacion')
            ->pluck('poblacion');

        $provincias = DB::table('erp_clients')
            ->whereNotNull('provincia')
            ->where('provincia', '!=', '')
            ->distinct()
            ->orderBy('provincia')
            ->pluck('provincia');

        $sellers = DB::table('erp_sellers')
            ->select('cod_vendedor', 'nombre')
            ->orderBy('nombre')
            ->get();

        return view('clients.index', compact('clients', 'poblaciones', 'provincias', 'sellers'));
    }
}
