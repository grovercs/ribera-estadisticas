<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::withCount('orders')
            ->withSum('orders', 'total')
            ->orderBy('total_spent', 'desc')
            ->paginate(20);

        $topClients = Client::orderBy('total_spent', 'desc')->take(10)->get();

        return view('clients.index', compact('clients', 'topClients'));
    }
}
