<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Alert;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('stock_quantity', 'asc')->paginate(20);
        $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'min_stock')->count();
        $alerts = Alert::where('type', 'low_stock')->where('status', 'active')->get();

        return view('stock.index', compact('products', 'lowStockCount', 'alerts'));
    }
}
