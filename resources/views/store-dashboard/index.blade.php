@extends('layouts.app')

@section('title', 'Cuadro de Mando por Tiendas - Ribera Estadísticas')

@section('content')
    <!-- Header con Filtros -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Cuadro de Mando por Tiendas</h1>
            <p class="text-sm text-[#747878] mt-1">Seguimiento diario de ventas, márgenes y pagos por tienda.</p>
        </div>
        <div class="flex gap-2 items-center">
            <form method="GET" action="{{ route('store-dashboard') }}" class="flex gap-2 items-center">
                <select name="periodo" onchange="this.form.submit()"
                    class="px-3 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="hoy" {{ $periodo === 'hoy' ? 'selected' : '' }}>Hoy</option>
                    <option value="quincena" {{ $periodo === 'quincena' ? 'selected' : '' }}>Quincena Actual</option>
                    <option value="year" {{ $periodo === 'year' ? 'selected' : '' }}>Año {{ $year }}</option>
                </select>
                <select name="year" onchange="this.form.submit()"
                    class="px-3 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    @foreach(range(date('Y'), 2019) as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
                <select name="anio_ant" onchange="this.form.submit()"
                    class="px-3 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]"
                    title="Años hacia atrás para 'Anteriores'">
                    <option value="1" {{ $anioAnteriores === '1' ? 'selected' : '' }}>Ant. 1 año</option>
                    <option value="2" {{ $anioAnteriores === '2' ? 'selected' : '' }}>Ant. 2 años</option>
                    <option value="3" {{ $anioAnteriores === '3' ? 'selected' : '' }}>Ant. 3 años</option>
                    <option value="5" {{ $anioAnteriores === '5' ? 'selected' : '' }}>Ant. 5 años</option>
                    <option value="10" {{ $anioAnteriores === '10' ? 'selected' : '' }}>Ant. 10 años</option>
                    <option value="todos" {{ $anioAnteriores === 'todos' ? 'selected' : '' }}>Todo el histórico</option>
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
    <div class="flex items-center gap-4 mb-6">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[#206393]">calendar_today</span>
            <span class="text-sm font-semibold text-[#747878]">Periodo: <span class="text-[#191c1e]">{{ $fechaTexto }}</span></span>
        </div>
        @if(isset($ultima_actualizacion))
            <div class="flex items-center gap-2 text-xs text-[#747878]">
                <span class="material-symbols-outlined text-[16px]">update</span>
                <span>Actualizado: {{ date('H:i:s', strtotime($ultima_actualizacion)) }}</span>
            </div>
        @endif
    </div>

    <!-- RESUMEN DE IMPORTES - TARJETAS PRINCIPALES -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Ventas Totales -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-[#206393]">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wide">Ventas Totales</span>
                <span class="material-symbols-outlined text-[#206393]">payments</span>
            </div>
            <div class="text-2xl font-bold text-[#191c1e]">{{ number_format($totales['ventas_year']['importe'] ?? 0, 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Tickets: {{ number_format($totales['ventas_year']['tickets'] ?? 0, 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878]">Quincena: {{ number_format($totales['ventas_quincena']['importe'] ?? 0, 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Ayer: {{ number_format($totales['ventas_ayer']['importe'] ?? 0, 2, ',', '.') }} €</div>
        </div>

        <!-- Márgenes -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-[#28a745]">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wide">Margen Total</span>
                <span class="material-symbols-outlined text-[#28a745]">trending_up</span>
            </div>
            <div class="text-2xl font-bold text-[#191c1e]">{{ number_format($totales['margen'] ?? 0, 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Venta: {{ number_format($totales['margen_venta'] ?? 0, 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Coste: {{ number_format($totales['margen_coste'] ?? 0, 2, ',', '.') }} €</div>
            <div class="text-xs font-semibold text-[#28a745]">{{ number_format($totales['margen_porcentaje'] ?? 0, 2) }}% margen</div>
        </div>

        <!-- Impagados -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-[#dc3545]">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wide">Impagados</span>
                <span class="material-symbols-outlined text-[#dc3545]">warning</span>
            </div>
            <div class="text-2xl font-bold text-[#191c1e]">{{ number_format($impagados['impagados_importe'] ?? 0, 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">{{ $impagados['impagados_count'] ?? 0 }} facturas impagadas</div>
            <div class="text-xs text-[#747878]">{{ $impagados['pendientes_count'] ?? 0 }} pendientes de cobro</div>
            <div class="text-xs text-[#dc3545]">{{ number_format($impagados['pendientes_importe'] ?? 0, 2, ',', '.') }} € pendientes</div>
        </div>

        <!-- Pagos Pendientes -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-[#ffc107]">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wide">Pagos Pendientes</span>
                <span class="material-symbols-outlined text-[#ffc107]">account_balance_wallet</span>
            </div>
            <div class="text-2xl font-bold text-[#191c1e]">{{ number_format(array_sum(array_column($pagosPendientes ?? [], 'importe')), 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Por vencimiento</div>
            @foreach($pagosPendientes ?? [] as $pago)
                <div class="text-xs text-[#747878]">{{ $pago['periodo'] }}: {{ number_format($pago['importe'], 2, ',', '.') }} €</div>
            @endforeach
        </div>
    </div>

    <!-- VENTAS POR PERIODO -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#206393]">shopping_cart</span>
            Ventas por Periodo
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Periodo</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vielha Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vielha Importe</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pont de Suert Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pont de Suert Importe</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $getTickets = fn($t, $k) => ($t[2]['ventas'][$k]['tickets'] ?? 0) + ($t[1]['ventas'][$k]['tickets'] ?? 0);
                        $getImporte = fn($t, $k) => ($t[2]['ventas'][$k]['importe'] ?? 0) + ($t[1]['ventas'][$k]['importe'] ?? 0);
                    @endphp
                    <!-- Hoy -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Hoy</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['ventas']['hoy']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['ventas']['hoy']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['ventas']['hoy']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['ventas']['hoy']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'hoy') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getImporte($tiendas, 'hoy'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Ayer -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Ayer</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['ventas']['ayer']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['ventas']['ayer']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['ventas']['ayer']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['ventas']['ayer']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'ayer') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getImporte($tiendas, 'ayer'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Quincena Actual -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Quincena Actual</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['ventas']['quincena']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['ventas']['quincena']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['ventas']['quincena']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['ventas']['quincena']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'quincena') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getImporte($tiendas, 'quincena'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Anteriores -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Anteriores</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['ventas']['anteriores']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[2]['ventas']['anteriores']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['ventas']['anteriores']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[1]['ventas']['anteriores']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'anteriores') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ number_format($getImporte($tiendas, 'anteriores'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Año Actual -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors bg-[#f0f4f8]">
                        <td class="py-3 px-4 font-semibold text-[#191c1e]">Año {{ $year }}</td>
                        <td class="py-3 px-4 text-right font-semibold">{{ $tiendas[2]['ventas']['year']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($tiendas[2]['ventas']['year']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-semibold">{{ $tiendas[1]['ventas']['year']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($tiendas[1]['ventas']['year']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'year') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getImporte($tiendas, 'year'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Año Anterior -->
                    <tr class="hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#747878]">Año {{ $year - 1 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $tiendas[2]['ventas']['year_anterior']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[2]['ventas']['year_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $tiendas[1]['ventas']['year_anterior']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[1]['ventas']['year_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ $getTickets($tiendas, 'year_anterior') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ number_format($getImporte($tiendas, 'year_anterior'), 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FACTURAS DE VENTA -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#206393]">receipt</span>
            Facturas de Venta
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Periodo</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vielha Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vielha Importe</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pont de Suert Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pont de Suert Importe</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $getFacturasTickets = fn($t, $k) => ($t[2]['facturas'][$k]['tickets'] ?? 0) + ($t[1]['facturas'][$k]['tickets'] ?? 0);
                        $getFacturasImporte = fn($t, $k) => ($t[2]['facturas'][$k]['importe'] ?? 0) + ($t[1]['facturas'][$k]['importe'] ?? 0);
                    @endphp
                    <!-- Quincena Actual -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Quincena Actual</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['facturas']['quincena']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['facturas']['quincena']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['facturas']['quincena']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['facturas']['quincena']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getFacturasTickets($tiendas, 'quincena') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getFacturasImporte($tiendas, 'quincena'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Quincena Anterior -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Quincena Anterior</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['facturas']['quincena_anterior']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['facturas']['quincena_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['facturas']['quincena_anterior']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['facturas']['quincena_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getFacturasTickets($tiendas, 'quincena_anterior') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getFacturasImporte($tiendas, 'quincena_anterior'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Año Actual -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors bg-[#f0f4f8]">
                        <td class="py-3 px-4 font-semibold text-[#191c1e]">Año {{ $year }}</td>
                        <td class="py-3 px-4 text-right font-semibold">{{ $tiendas[2]['facturas']['year']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($tiendas[2]['facturas']['year']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-semibold">{{ $tiendas[1]['facturas']['year']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($tiendas[1]['facturas']['year']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getFacturasTickets($tiendas, 'year') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getFacturasImporte($tiendas, 'year'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Año Anterior Mismo Periodo -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#747878]">Año Anterior (mismo periodo)</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $tiendas[2]['facturas']['year_ant_periodo']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[2]['facturas']['year_ant_periodo']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $tiendas[1]['facturas']['year_ant_periodo']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[1]['facturas']['year_ant_periodo']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ $getFacturasTickets($tiendas, 'year_ant_periodo') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ number_format($getFacturasImporte($tiendas, 'year_ant_periodo'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Año Anterior -->
                    <tr class="hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#747878]">Año {{ $year - 1 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $tiendas[2]['facturas']['year_anterior']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[2]['facturas']['year_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $tiendas[1]['facturas']['year_anterior']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($tiendas[1]['facturas']['year_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ $getFacturasTickets($tiendas, 'year_anterior') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">{{ number_format($getFacturasImporte($tiendas, 'year_anterior'), 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- IMPAGADOS -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#dc3545]">warning</span>
            Impagados
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Concepto</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nº</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Impagados</td>
                        <td class="py-3 px-4 text-right">{{ $impagados['impagados_count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#dc3545] font-semibold">{{ number_format($impagados['impagados_importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Pendientes</td>
                        <td class="py-3 px-4 text-right">{{ $impagados['pendientes_count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($impagados['pendientes_importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MÁRGENES COMERCIALES -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#28a745]">analytics</span>
            Márgenes Comerciales (Año {{ $year }})
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Concepto</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vielha</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pont de Suert</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Venta</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['margenes']['venta'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['margenes']['venta'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($totales['margen_venta'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Coste</td>
                        <td class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($tiendas[2]['margenes']['coste'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($tiendas[1]['margenes']['coste'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#dc3545]">{{ number_format($totales['margen_coste'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Margen</td>
                        <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tiendas[2]['margenes']['margen'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tiendas[1]['margenes']['margen'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#28a745]">{{ number_format($totales['margen'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="bg-[#f0f4f8]">
                        <td class="py-3 px-4 font-semibold text-[#191c1e]">% Margen</td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ ($tiendas[2]['margenes']['margen_porcentaje'] ?? 0) >= 40 ? 'bg-green-100 text-green-700' : (($tiendas[2]['margenes']['margen_porcentaje'] ?? 0) >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($tiendas[2]['margenes']['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ ($tiendas[1]['margenes']['margen_porcentaje'] ?? 0) >= 40 ? 'bg-green-100 text-green-700' : (($tiendas[1]['margenes']['margen_porcentaje'] ?? 0) >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($tiendas[1]['margenes']['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-bold
                                {{ ($totales['margen_porcentaje'] ?? 0) >= 40 ? 'bg-green-100 text-green-700' : (($totales['margen_porcentaje'] ?? 0) >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($totales['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ALBARANES DE COMPRA MES -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#206393]">local_shipping</span>
            Albaranes de Compra (Mes Actual)
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Tienda</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nº Albaranes</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Vielha</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['albaranes']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($tiendas[2]['albaranes']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Pont de Suert</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['albaranes']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($tiendas[1]['albaranes']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-t-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <td class="py-3 px-4 font-bold text-[#191c1e]">TOTAL</td>
                        <td class="py-3 px-4 text-right font-bold">{{ ($tiendas[2]['albaranes']['count'] ?? 0) + ($tiendas[1]['albaranes']['count'] ?? 0) }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($totales['albaranes_mes'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FACTURAS DE COMPRAS Y GASTOS -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#206393]">receipt_long</span>
            Facturas de Compras y Gastos
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Periodo</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nº Facturas</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Mes Actual</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $facturasCompras['mes_actual']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($facturasCompras['mes_actual']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Mes Anterior</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $facturasCompras['mes_anterior']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($facturasCompras['mes_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors bg-[#f0f4f8]">
                        <td class="py-3 px-4 font-semibold text-[#191c1e]">Año {{ $year }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $facturasCompras['year_actual']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($facturasCompras['year_actual']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#747878]">Año Anterior (mismo periodo)</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $facturasCompras['year_anterior_periodo']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($facturasCompras['year_anterior_periodo']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#747878]">Año {{ $year - 1 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ $facturasCompras['year_anterior']['count'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($facturasCompras['year_anterior']['importe'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGOS PENDIENTES -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#ffc107]">account_balance_wallet</span>
            Pagos Pendientes
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Periodo</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $pagosOrden = ['Mes Actual', 'Mes Siguiente', 'En 2 meses', 'En 3 meses', 'Mas de 3 meses'];
                        $pagosMap = [];
                        foreach($pagosPendientes ?? [] as $p) { $pagosMap[$p['periodo']] = $p['importe']; }
                    @endphp
                    @foreach($pagosOrden as $periodo)
                        @if(isset($pagosMap[$periodo]))
                            <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                                <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $periodo }}</td>
                                <td class="py-3 px-4 text-right text-[#ffc107] font-semibold">{{ number_format($pagosMap[$periodo], 2, ',', '.') }} €</td>
                            </tr>
                        @endif
                    @endforeach
                    <tr class="border-t-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <td class="py-3 px-4 font-bold text-[#191c1e]">Total pagos pendientes</td>
                        <td class="py-3 px-4 text-right font-bold text-[#ffc107]">{{ number_format(array_sum(array_column($pagosPendientes ?? [], 'importe')), 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
