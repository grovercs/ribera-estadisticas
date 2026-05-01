@extends('layouts.app')

@section('title', 'Clientes - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Inteligencia de Clientes</h1>
            <p class="text-sm text-[#747878] mt-1">Análisis de comportamiento y ranking.</p>
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
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Empresa</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Órdenes</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Gasto Total</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Límite Crédito</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topClients as $index => $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-bold text-[#747878]">{{ $index + 1 }}</td>
                            <td class="py-3 px-4 font-medium">{{ $client->name }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->company ?? '-' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($client->order_count) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#206393]">${{ number_format($client->total_spent, 2) }}</td>
                            <td class="py-3 px-4 text-right">${{ number_format($client->credit_limit, 2) }}</td>
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
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nombre</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Email</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ciudad</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Tipo</th>
                        <th class="text-center py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium">{{ $client->name }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->email }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->city }}</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $client->type === 'business' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}"
                                >
                                    {{ $client->type === 'business' ? 'Empresa' : 'Individual' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}"
                                >
                                    {{ $client->status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
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
