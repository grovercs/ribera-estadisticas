@extends('layouts.app')

@section('title', 'Cuadro de Mando por Tiendas - Ribera Estadísticas')

@section('content')
    <!-- Header con Filtros -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Cuadro de Mando por Tiendas</h1>
            <p class="text-sm text-[#747878] mt-1">Seguimiento diario de ventas y márgenes por tienda.</p>
        </div>
        <div class="flex gap-2 items-center">
            <form method="GET" action="{{ route('store-dashboard') }}" class="flex gap-2 items-center">
                <select name="periodo" onchange="this.form.submit()"
                    class="px-3 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="hoy" {{ $periodo === 'hoy' ? 'selected' : '' }}>Hoy</option>
                    <option value="ayer" {{ $periodo === 'ayer' ? 'selected' : '' }}>Ayer</option>
                    <option value="quincena" {{ $periodo === 'quincena' ? 'selected' : '' }}>Últimos 15 días</option>
                    <option value="mes" {{ $periodo === 'mes' ? 'selected' : '' }}>Mes Actual</option>
                    <option value="year" {{ $periodo === 'year' ? 'selected' : '' }}>Año {{ date('Y') }}</option>
                </select>
                <select name="year" onchange="this.form.submit()"
                    class="px-3 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    @foreach(range(date('Y'), 2019) as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <button onclick="window.location.reload()" class="p-1.5 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors" title="Actualizar">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
            </button>
        </div>
    </div>

    @if(isset($error))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 font-semibold">Error: {{ $error }}</p>
        </div>
    @endif

    <!-- Periodo seleccionado + Ultima actualizacion -->
    <div class="flex items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[#206393]">calendar_today</span>
            <span class="text-sm font-semibold text-[#747878]">Periodo: <span class="text-[#191c1e]">{{ $fechaTexto }}</span></span>
        </div>
        @if(isset($ultima_actualizacion))
            <div class="flex items-center gap-2 text-xs text-[#747878]">
                <span class="material-symbols-outlined text-[16px]">update</span>
                <span>Actualizado: {{ $ultima_actualizacion->format('H:i:s') }}</span>
            </div>
        @endif
    </div>

    <!-- Tabla Principal de Ventas -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Ventas por Tienda</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Tienda</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cantidad</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Importe</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pedidos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ventasPorTienda as $tienda)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">
                                {{ $tienda->nombre_almacen }}
                                @if($tienda->cod_almacen == 1)
                                    <span class="ml-2 px-2 py-0.5 bg-[#206393] text-white text-xs rounded">Vielha</span>
                                @elseif($tienda->cod_almacen == 2)
                                    <span class="ml-2 px-2 py-0.5 bg-[#206393] text-white text-xs rounded">Pont de Suert</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($tienda->cantidad, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-bold">{{ number_format($tienda->importe, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tienda->pedidos, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-[#747878]">
                                @if($periodo === 'hoy')
                                    <span class="material-symbols-outlined align-middle mr-2">info</span>
                                    No hay ventas registradas hoy todavía.
                                    <br><span class="text-xs">Las ventas del día se van registrando a medida que se procesan los tickets.</span>
                                @else
                                    No hay datos disponibles para este periodo
                                @endif
                            </td>
                        </tr>
                    @endforelse
                    @if(count($ventasPorTienda) > 0)
                        <tr class="border-t-2 border-[#e1e2e6] bg-[#f8f9fc]">
                            <td class="py-3 px-4 font-bold text-[#191c1e]">TOTAL</td>
                            <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($total['cantidad'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($total['importe'], 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ number_format($total['pedidos'], 0, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Márgenes por Tienda -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Márgenes por Tienda</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Tienda</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Venta</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Coste</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Margen</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">% Margen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($margenesPorTienda as $tienda)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $tienda->nombre_almacen }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($tienda->venta, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($tienda->coste, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tienda->margen, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $tienda->margen_porcentaje >= 40 ? 'bg-green-100 text-green-700' : ($tienda->margen_porcentaje >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($tienda->margen_porcentaje, 2) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-[#747878]">No hay datos de márgenes para este periodo</td>
                        </tr>
                    @endforelse
                    @if(count($margenesPorTienda) > 0)
                        <tr class="border-t-2 border-[#e1e2e6] bg-[#f8f9fc]">
                            <td class="py-3 px-4 font-bold text-[#191c1e]">TOTAL</td>
                            <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($total['venta'], 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right font-bold text-[#dc3545]">{{ number_format($total['coste'], 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right font-bold text-[#28a745]">{{ number_format($total['margen'], 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-bold
                                    {{ $total['margen_porcentaje'] >= 40 ? 'bg-green-100 text-green-700' : ($total['margen_porcentaje'] >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($total['margen_porcentaje'], 2) }}%
                                </span>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagos Pendientes -->
    @if(count($pagosPendientes) > 0)
        <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
            <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Pagos Pendientes por Vencimiento</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-[#e1e2e6]">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Periodo</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Importe Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pagosPendientes as $pago)
                            <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                                <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $pago->periodo }}</td>
                                <td class="py-3 px-4 text-right text-[#dc3545] font-semibold">{{ number_format($pago->importe, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
