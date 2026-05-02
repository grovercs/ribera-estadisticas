@extends('layouts.app')

@section('title', 'Clientes - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Inteligencia de Clientes</h1>
            <p class="text-sm text-[#747878] mt-1">Análisis de comportamiento y ranking desde datos ERP.</p>
        </div>
        <div class="text-sm text-[#747878]">
            Total clientes únicos: <span class="font-semibold text-[#191c1e]">{{ $clients->total() }}</span>
        </div>
    </div>

    <!-- Top Clients -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top 10 Clientes por Gasto</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">#</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">CIF</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ventas</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Gasto Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topClients as $index => $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-bold text-[#747878]">{{ $index + 1 }}</td>
                            <td class="py-3 px-4 font-medium">{{ $client->razon_social }}</td>
                            <td class="py-3 px-4 text-[#747878] font-mono text-xs">{{ $client->cif ?? '-' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($client->order_count) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($client->total_spent, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- All Clients -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Todos los Clientes</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nombre</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">CIF</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ventas</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total Facturado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $client->cod_cliente }}</td>
                            <td class="py-3 px-4 font-medium">{{ $client->razon_social }}</td>
                            <td class="py-3 px-4 text-[#747878] font-mono text-xs">{{ $client->cif ?? '-' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($client->order_count) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#191c1e]">{{ number_format($client->total_spent, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $clients->links() }}
        </div>
    </div>
@endsection
