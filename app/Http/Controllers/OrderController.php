<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['client', 'items.product'])
            ->orderBy('order_date', 'desc')
            ->paginate(20);

        return view('sales.index', compact('orders'));
    }
}
