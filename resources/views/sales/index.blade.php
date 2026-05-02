@extends('layouts.app')

@section('title', 'Ventas - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Módulo de Ventas</h1>
            <p class="text-sm text-[#747878] mt-1">Historial de ventas reales del ERP.</p>
        </div>
        <div class="text-sm text-[#747878]">
            Total registros: <span class="font-semibold text-[#191c1e]">{{ number_format($orders->total()) }}</span>
        </div>
    </div>

    <div class="glass-card rounded-xl p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Venta</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Fecha</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Tipo</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pago</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Líneas</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $order->cod_venta }}</td>
                            <td class="py-3 px-4">{{ $order->razon_social ?? 'N/A' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $order->fecha_venta ? \Carbon\Carbon::parse($order->fecha_venta)->format('d/m/Y') : '-' }}</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($order->tipo_venta)
                                        @case(0) bg-gray-100 text-gray-800 @break
                                        @case(1) bg-blue-100 text-blue-800 @break
                                        @case(2) bg-yellow-100 text-yellow-800 @break
                                        @case(4) bg-purple-100 text-purple-800 @break
                                        @case(5) bg-green-100 text-green-800 @break
                                        @default bg-gray-100 text-gray-800 @break
                                    @endswitch"
                                >
                                    @switch($order->tipo_venta)
                                        @case(0) Ticket @break
                                        @case(1) Albarán @break
                                        @case(2) Factura @break
                                        @case(4) Devolución @break
                                        @case(5) Pedido @break
                                        @default Tipo {{ $order->tipo_venta }} @break
                                    @endswitch
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($order->importe_pendiente > 0) bg-red-100 text-red-800
                                    @elseif($order->importe_cobrado > 0) bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800 @endif"
                                >
                                    @if($order->importe_pendiente > 0) Pendiente
                                    @elseif($order->importe_cobrado > 0) Cobrado
                                    @else Sin cobro @endif
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ $order->sale_lines_count }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#191c1e]">{{ number_format($order->importe_impuestos, 2, ',', '.') }} €</td>
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
