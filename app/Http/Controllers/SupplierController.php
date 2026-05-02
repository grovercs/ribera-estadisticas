<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('erp_suppliers')
            ->select('cod_proveedor', 'nombre_comercial', 'razon_social', 'cif', 'poblacion', 'provincia', 'telefono', 'e_mail', 'credito_otorgado');

        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', $search)
                  ->orWhere('nombre_comercial', 'like', $search)
                  ->orWhere('cif', 'like', $search)
                  ->orWhere('poblacion', 'like', $search)
                  ->orWhere('cod_proveedor', 'like', $search);
            });
        }

        $suppliers = $query->orderBy('razon_social')->paginate(25)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }
}
