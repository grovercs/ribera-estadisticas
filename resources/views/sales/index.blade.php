@extends('layouts.app')

@section('title', 'Ventas - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Módulo de Ventas</h1>
            <p class="text-sm text-[#747878] mt-1">Historial de órdenes y transacciones.</p>
        </div>
    </div>

    <div class="glass-card rounded-xl p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Orden</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Fecha</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Estado</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pago</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $order->order_number }}</td>
                            <td class="py-3 px-4">{{ $order->client?->name ?? 'N/A' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $order->order_date->format('d/m/Y') }}</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($order->status)
                                        @case('delivered') bg-green-100 text-green-800 @break
                                        @case('shipped') bg-blue-100 text-blue-800 @break
                                        @case('processing') bg-yellow-100 text-yellow-800 @break
                                        @case('pending') bg-gray-100 text-gray-800 @break
                                        @case('cancelled') bg-red-100 text-red-800 @break
                                    @endswitch"
                                >
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($order->payment_status)
                                        @case('paid') bg-green-100 text-green-800 @break
                                        @case('partial') bg-yellow-100 text-yellow-800 @break
                                        @case('overdue') bg-red-100 text-red-800 @break
                                        @default bg-gray-100 text-gray-800 @break
                                    @endswitch"
                                >
                                    {{ ucfirst($order->payment_status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-[#191c1e]">${{ number_format($order->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </div>
@endsection
