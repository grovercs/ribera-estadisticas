@extends('layouts.app')

@section('title', 'Clientes - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Clientes</h1>
            <p class="text-sm text-[#747878] mt-1">Maestro de clientes importado desde el ERP.</p>
        </div>
        <div class="text-sm text-[#747878]">
            Total clientes: <span class="font-semibold text-[#191c1e]">{{ $clients->total() }}</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <form method="GET" action="{{ route('clients') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <label for="search" class="block text-xs font-semibold text-[#747878] uppercase mb-1">Buscar</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                    placeholder="Código, razón social, CIF, población..."
                    class="w-full rounded-lg border border-[#e1e2e6] bg-white px-3 py-2 text-sm text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
            </div>

            <div>
                <label for="poblacion" class="block text-xs font-semibold text-[#747878] uppercase mb-1">Población</label>
                <select id="poblacion" name="poblacion"
                    class="w-full rounded-lg border border-[#e1e2e6] bg-white px-3 py-2 text-sm text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="">Todas</option>
                    @foreach($poblaciones as $p)
                        <option value="{{ $p }}" {{ request('poblacion') == $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="provincia" class="block text-xs font-semibold text-[#747878] uppercase mb-1">Provincia</label>
                <select id="provincia" name="provincia"
                    class="w-full rounded-lg border border-[#e1e2e6] bg-white px-3 py-2 text-sm text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="">Todas</option>
                    @foreach($provincias as $pr)
                        <option value="{{ $pr }}" {{ request('provincia') == $pr ? 'selected' : '' }}>{{ $pr }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="vendedor" class="block text-xs font-semibold text-[#747878] uppercase mb-1">Vendedor</label>
                <select id="vendedor" name="vendedor"
                    class="w-full rounded-lg border border-[#e1e2e6] bg-white px-3 py-2 text-sm text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="">Todos</option>
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->cod_vendedor }}" {{ request('vendedor') == $seller->cod_vendedor ? 'selected' : '' }}>{{ $seller->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-5 flex gap-2">
                <button type="submit"
                    class="inline-flex items-center rounded-lg bg-[#206393] px-4 py-2 text-sm font-semibold text-white hover:bg-[#184b70] transition-colors">
                    Filtrar
                </button>
                <a href="{{ route('clients') }}"
                    class="inline-flex items-center rounded-lg border border-[#e1e2e6] bg-white px-4 py-2 text-sm font-semibold text-[#191c1e] hover:bg-[#f8f9fc] transition-colors">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Clients Table -->
    <div class="glass-card rounded-xl p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Razón Social</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nombre Comercial</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">CIF</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Población</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Provincia</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Teléfono</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Email</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vendedor</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Límite Crédito</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pedidos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $client->cod_cliente }}</td>
                            <td class="py-3 px-4 font-medium">{{ $client->razon_social }}</td>
                            <td class="py-3 px-4">{{ $client->nombre_comercial ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878] font-mono text-xs">{{ $client->cif ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $client->poblacion ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $client->provincia ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $client->telefono ?? '-' }}</td>
                            <td class="py-3 px-4 text-xs">{{ $client->e_mail ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $client->vendedor ?? '-' }}</td>
                            <td class="py-3 px-4 text-right font-semibold">{{ number_format($client->limite_credito ?? 0, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($client->total_spent, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right">{{ number_format($client->order_count, 0, ',', '.') }}</td>
                        </tr>
                        @if($client->fecha_alta)
                            <tr class="border-b border-[#f2f3f7]">
                                <td colspan="12" class="px-4 pb-3 text-xs text-[#747878]">
                                    Fecha alta: {{ \Carbon\Carbon::parse($client->fecha_alta)->format('d/m/Y') }}
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="12" class="py-8 px-4 text-center text-[#747878]">No se encontraron clientes con los filtros aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $clients->links() }}
        </div>
    </div>
@endsection
