<?php

namespace App\Http\Controllers;

use App\Models\ErpSale;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = ErpSale::withCount('saleLines')
            ->orderBy('fecha_venta', 'desc')
            ->paginate(20);

        return view('sales.index', compact('orders'));
    }
}
