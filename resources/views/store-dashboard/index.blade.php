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
                    <option value="hoy" {{ $periodo === 'hoy' ? 'selected' : '' }}>Último Día con Ventas</option>
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

    @push('styles')
    <style>
        /* KPI Cards premium */
        .kpi-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px 22px 14px;
            border: 1px solid #e8eaed;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: box-shadow .2s, transform .2s;
            position: relative;
            overflow: hidden;
        }
        .kpi-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.10); transform: translateY(-2px); }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--kpi-accent, #206393);
            border-radius: 16px 16px 0 0;
        }
        .kpi-badge-up   { background: #dcfce7; color: #16a34a; }
        .kpi-badge-down { background: #fee2e2; color: #dc2626; }
        .kpi-badge-flat { background: #f1f5f9; color: #64748b; }
        .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
            animation: pulse-ring 1.5s ease-out infinite;
            display: inline-block;
        }
        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
            70%  { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
            100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }
        /* Responsive charts */
        .apexcharts-canvas { max-width: 100% !important; }
    </style>
    @endpush

    @php
        /* ── Cálculos de tendencia para las KPI cards ── */
        $ventasYearImp    = $totales['ventas_year']['importe'] ?? 0;
        $ventasYearAntImp = $totales['ventas_year_ant_periodo']['importe'] ?? 0;
        $ventasPct = $ventasYearAntImp > 0
            ? (($ventasYearImp - $ventasYearAntImp) / $ventasYearAntImp) * 100 : 0;

        $margenPct    = $totales['margen_porcentaje'] ?? 0;
        $margenImport = $totales['margen'] ?? 0;

        $impagadosImp = $impagados['impagados_importe'] ?? 0;
        $impagadosCnt = $impagados['impagados_count'] ?? 0;

        $ticketM    = $ticketMedio ?? 0;
        $ticketMAnt = $ticketMedioAnt ?? 0;
        $ticketPct  = $ticketMAnt > 0 ? (($ticketM - $ticketMAnt) / $ticketMAnt) * 100 : 0;

        // Color semáforo margen
        $margenColor = $margenPct >= 28 ? '#16a34a' : ($margenPct >= 20 ? '#ca8a04' : '#dc2626');
        $margenBg    = $margenPct >= 28 ? '#dcfce7'  : ($margenPct >= 20 ? '#fef9c3'  : '#fee2e2');

        $fmtEur = fn($v) => number_format($v, 2, ',', '.');
        $fmtPct = fn($v) => ($v >= 0 ? '+' : '') . number_format($v, 1) . '%';
    @endphp

    <!-- ═══ KPI CARDS ═══ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

        {{-- Card 1: Ventas del Año --}}
        <div class="kpi-card" style="--kpi-accent:#206393">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <p class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-1">Ventas Año {{ $year }}</p>
                    <p class="text-2xl font-black text-[#191c1e] leading-tight">{{ $fmtEur($ventasYearImp) }} €</p>
                </div>
                <div class="w-9 h-9 rounded-xl bg-[#eff6ff] flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[#206393] text-[20px]">payments</span>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold
                    {{ $ventasPct >= 0 ? 'kpi-badge-up' : 'kpi-badge-down' }}">
                    {{ $ventasPct >= 0 ? '▲' : '▼' }} {{ $fmtPct($ventasPct) }}
                </span>
                <span class="text-xs text-[#747878]">vs {{ $year-1 }} mismo periodo</span>
            </div>
            <div id="spark-ventas" class="-mx-2"></div>
            <p class="text-xs text-[#9ca3af] mt-1">{{ number_format($totales['ventas_year']['tickets'] ?? 0, 0, ',', '.') }} tickets · últ. 14 días</p>
        </div>

        {{-- Card 2: Margen Comercial --}}
        <div class="kpi-card" style="--kpi-accent:{{ $margenColor }}">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <p class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-1">Margen Comercial</p>
                    <p class="text-2xl font-black leading-tight" style="color:{{ $margenColor }}">{{ number_format($margenPct, 2) }}%</p>
                </div>
                <span class="px-2.5 py-1 rounded-xl text-xs font-bold" style="background:{{ $margenBg }};color:{{ $margenColor }}">
                    {{ $margenPct >= 28 ? '✓ Bueno' : ($margenPct >= 20 ? '⚠ Justo' : '✗ Bajo') }}
                </span>
            </div>
            <p class="text-sm font-semibold text-[#374151] mb-3">{{ $fmtEur($margenImport) }} €</p>
            <div id="spark-margen" class="-mx-2"></div>
            <div class="flex items-center gap-1.5 mt-1">
                <span class="text-xs text-[#9ca3af]">Venta: {{ $fmtEur($totales['margen_venta'] ?? 0) }} € · Coste: {{ $fmtEur($totales['margen_coste'] ?? 0) }} €</span>
            </div>
        </div>

        {{-- Card 3: Impagados --}}
        <div class="kpi-card" style="--kpi-accent:#dc2626">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <p class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-1 flex items-center gap-1.5">
                        Impagados
                        @if($impagadosCnt > 0)
                            <span class="pulse-dot"></span>
                        @endif
                    </p>
                    <p class="text-2xl font-black text-[#191c1e] leading-tight">{{ $fmtEur($impagadosImp) }} €</p>
                </div>
                <div class="w-9 h-9 rounded-xl bg-[#fef2f2] flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[#dc2626] text-[20px]">warning</span>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-[#fee2e2] text-[#dc2626]">
                    {{ $impagadosCnt }} facturas
                </span>
                <span class="text-xs text-[#747878]">sin cobrar</span>
            </div>
            <div class="border-t border-[#f3f4f6] pt-2">
                <div class="flex justify-between text-xs text-[#6b7280]">
                    <span>Pendientes cobro</span>
                    <span class="font-semibold text-[#374151]">{{ $fmtEur($impagados['pendientes_importe'] ?? 0) }} €</span>
                </div>
                <div class="flex justify-between text-xs text-[#6b7280] mt-0.5">
                    <span>Nº pendientes</span>
                    <span class="font-semibold text-[#374151]">{{ $impagados['pendientes_count'] ?? 0 }} docs.</span>
                </div>
            </div>
        </div>

        {{-- Card 4: Ticket Medio --}}
        <div class="kpi-card" style="--kpi-accent:#7c3aed">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <p class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-1">Ticket Medio</p>
                    <p class="text-2xl font-black text-[#191c1e] leading-tight">{{ $fmtEur($ticketM) }} €</p>
                </div>
                <div class="w-9 h-9 rounded-xl bg-[#f5f3ff] flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[#7c3aed] text-[20px]">receipt</span>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold
                    {{ $ticketPct >= 0 ? 'kpi-badge-up' : 'kpi-badge-down' }}">
                    {{ $ticketPct >= 0 ? '▲' : '▼' }} {{ $fmtPct($ticketPct) }}
                </span>
                <span class="text-xs text-[#747878]">vs {{ $year-1 }}</span>
            </div>
            <div id="spark-ticket" class="-mx-2"></div>
            <p class="text-xs text-[#9ca3af] mt-1">Año anterior: {{ $fmtEur($ticketMAnt) }} €/ticket</p>
        </div>

    </div>
    @php $spData = $sparklines ?? ['labels'=>[],'pont'=>[],'vielha'=>[],'total'=>[]]; @endphp
    <!-- ═══ SPARKLINES JS (pequeños, dentro de cards) ═══ -->
    @push('scripts')
    <script>
    (function() {
        const sp = @json($spData);
        const spTotal  = sp.total  ?? [];
        const spVielha = sp.vielha ?? [];
        const spPont   = sp.pont   ?? [];
        const spLabels = sp.labels ?? [];
        const C = { blue:'#206393', green:'#16a34a', red:'#ef4444', purple:'#7c3aed', amber:'#f59e0b' };

        function miniSparkline(elId, data, color, label) {
            const el = document.getElementById(elId);
            if (!el || !data.length) return;
            new ApexCharts(el, {
                chart: { type:'area', height:55, sparkline:{enabled:true},
                         animations:{enabled:true,easing:'easeinout',speed:500} },
                series: [{ name: label, data }],
                stroke: { curve:'smooth', width:2 },
                fill: { type:'gradient', gradient:{ shadeIntensity:1, opacityFrom:.4, opacityTo:0, stops:[0,100] } },
                colors: [color],
                tooltip: {
                    fixed:{enabled:false},
                    x:{show:true, formatter: (_,{dataPointIndex}) => spLabels[dataPointIndex] ?? ''},
                    y:{ formatter: v => new Intl.NumberFormat('es-ES',{minimumFractionDigits:2}).format(v)+' €' },
                    marker:{show:false},
                },
            }).render();
        }

        miniSparkline('spark-ventas', spTotal,  C.blue,   'Ventas totales');
        miniSparkline('spark-margen', spVielha, C.green,  'Vielha');
        miniSparkline('spark-ticket', spPont,   C.purple, 'Pont de Suert');
    })();
    </script>
    @endpush


    <!-- GRÁFICOS APEXCHARTS -->
    @php
        $vielhaYear   = round($tiendas[2]['ventas']['year']['importe'] ?? 0, 2);
        $pontYear     = round($tiendas[1]['ventas']['year']['importe'] ?? 0, 2);
        $vielhaYearAnt = round($tiendas[2]['ventas']['year_anterior']['importe'] ?? 0, 2);
        $pontYearAnt  = round($tiendas[1]['ventas']['year_anterior']['importe'] ?? 0, 2);
        $vielhaQuince = round($tiendas[2]['ventas']['quincena']['importe'] ?? 0, 2);
        $pontQuince   = round($tiendas[1]['ventas']['quincena']['importe'] ?? 0, 2);
        $vielhaAyer   = round($tiendas[2]['ventas']['ayer']['importe'] ?? 0, 2);
        $pontAyer     = round($tiendas[1]['ventas']['ayer']['importe'] ?? 0, 2);

        $vielhaVenta  = round($tiendas[2]['margenes']['venta'] ?? 0, 2);
        $pontVenta    = round($tiendas[1]['margenes']['venta'] ?? 0, 2);
        $vielhaCoste  = round($tiendas[2]['margenes']['coste'] ?? 0, 2);
        $pontCoste    = round($tiendas[1]['margenes']['coste'] ?? 0, 2);
        $vielhaMargenPct = round($tiendas[2]['margenes']['margen_porcentaje'] ?? 0, 2);
        $pontMargenPct   = round($tiendas[1]['margenes']['margen_porcentaje'] ?? 0, 2);

        $yearLabel    = $year;
        $yearAntLabel = $year - 1;
    @endphp


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">


        {{-- GRÁFICO 1: Ventas por tienda y periodo --}}
        <div class="glass-card rounded-xl p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-[#191c1e] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#206393] text-[20px]">bar_chart</span>
                    Ventas por Tienda
                </h2>
                <span class="text-xs text-[#747878]">Importe sin IVA (€)</span>
            </div>
            <div id="chart-ventas-tienda"></div>
        </div>

        {{-- GRÁFICO 2: Donut distribución ventas anuales --}}
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-[#191c1e] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#28a745] text-[20px]">donut_large</span>
                    Ventas {{ $yearLabel }}
                </h2>
                <span class="text-xs text-[#747878]">Por tienda</span>
            </div>
            <div id="chart-donut-ventas"></div>
        </div>

    </div>

    {{-- GRÁFICO 3: Márgenes año actual --}}
    <div class="glass-card rounded-xl p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-[#191c1e] flex items-center gap-2">
                <span class="material-symbols-outlined text-[#28a745] text-[20px]">analytics</span>
                Márgenes Comerciales {{ $yearLabel }} — Venta vs Coste
            </h2>
            <span class="text-xs text-[#747878]">% margen sobre venta sin IVA</span>
        </div>
        {{-- Gauge RadialBar --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-0">
            <div>
                <p class="text-center text-xs font-semibold text-[#747878] uppercase tracking-wide mb-1">Vielha</p>
                <div id="gauge-vielha"></div>
                <div class="text-center -mt-2">
                    <p class="text-xs text-[#6b7280]">Venta: <span class="font-semibold text-[#191c1e]">{{ number_format($vielhaVenta,2,',','.') }} €</span></p>
                    <p class="text-xs text-[#6b7280]">Coste: <span class="font-semibold text-[#191c1e]">{{ number_format($vielhaCoste,2,',','.') }} €</span></p>
                    <p class="text-xs text-[#6b7280]">Margen: <span class="font-bold" style="color:{{ $vielhaMargenPct >= 28 ? '#16a34a' : ($vielhaMargenPct >= 20 ? '#ca8a04' : '#dc2626') }}">{{ number_format($vielhaVenta-$vielhaCoste,2,',','.') }} €</span></p>
                </div>
            </div>
            <div>
                <p class="text-center text-xs font-semibold text-[#747878] uppercase tracking-wide mb-1">Pont de Suert</p>
                <div id="gauge-pont"></div>
                <div class="text-center -mt-2">
                    <p class="text-xs text-[#6b7280]">Venta: <span class="font-semibold text-[#191c1e]">{{ number_format($pontVenta,2,',','.') }} €</span></p>
                    <p class="text-xs text-[#6b7280]">Coste: <span class="font-semibold text-[#191c1e]">{{ number_format($pontCoste,2,',','.') }} €</span></p>
                    <p class="text-xs text-[#6b7280]">Margen: <span class="font-bold" style="color:{{ $pontMargenPct >= 28 ? '#16a34a' : ($pontMargenPct >= 20 ? '#ca8a04' : '#dc2626') }}">{{ number_format($pontVenta-$pontCoste,2,',','.') }} €</span></p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        const palette = {
            vielha:    '#206393',
            pont:      '#28a745',
            vielhaLt:  'rgba(32,99,147,0.15)',
            pontLt:    'rgba(40,167,69,0.15)',
            coste:     '#dc3545',
            pct:       '#ffc107',
            gray:      '#747878',
        };
        const fontFamily = 'Inter, sans-serif';

        const baseOpts = {
            chart: { fontFamily, toolbar: { show: false }, animations: { enabled: true, easing: 'easeinout', speed: 600 } },
            tooltip: { theme: 'light' },
            grid: { borderColor: '#e1e2e6', strokeDashArray: 4, padding: { left: 8, right: 8 } },
            legend: { fontFamily, fontSize: '12px', labels: { colors: '#747878' } },
        };

        /* ─── GRÁFICO 1: Barras agrupadas Ventas por periodo ─── */
        new ApexCharts(document.getElementById('chart-ventas-tienda'), {
            ...baseOpts,
            chart: { ...baseOpts.chart, type: 'bar', height: 280 },
            series: [
                { name: 'Vielha',        data: [{{ $vielhaAyer }}, {{ $vielhaQuince }}, {{ $vielhaYear }}, {{ $vielhaYearAnt }}] },
                { name: 'Pont de Suert', data: [{{ $pontAyer }},   {{ $pontQuince }},   {{ $pontYear }},   {{ $pontYearAnt }}] },
            ],
            xaxis: {
                categories: ['Penúlt. Día ({{ isset($penultimoDiaVentas) ? \Carbon\Carbon::parse($penultimoDiaVentas)->format("d/m") : "Ayer" }})', 'Quincena', 'Año {{ $yearLabel }}', 'Año {{ $yearAntLabel }}'],
                labels: { style: { colors: palette.gray, fontSize: '12px', fontFamily } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { colors: palette.gray, fontSize: '11px', fontFamily },
                    formatter: v => v >= 1000 ? (v/1000).toFixed(0)+'k €' : v.toFixed(0)+' €'
                }
            },
            colors: [palette.vielha, palette.pont],
            plotOptions: { bar: { borderRadius: 5, columnWidth: '55%', dataLabels: { position: 'top' } } },
            dataLabels: {
                enabled: true,
                formatter: v => v >= 1000 ? (v/1000).toFixed(1)+'k' : v.toFixed(0),
                style: { fontSize: '10px', fontFamily, fontWeight: 600, colors: ['#444'] },
                offsetY: -18,
            },
            states: { hover: { filter: { type: 'lighten', value: 0.1 } } },
        }).render();

        /* ─── GRÁFICO 2: Donut ventas anuales por tienda ─── */
        new ApexCharts(document.getElementById('chart-donut-ventas'), {
            ...baseOpts,
            chart: { ...baseOpts.chart, type: 'donut', height: 280 },
            series: [{{ $vielhaYear }}, {{ $pontYear }}],
            labels: ['Vielha', 'Pont de Suert'],
            colors: [palette.vielha, palette.pont],
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true, label: 'Total',
                                fontSize: '13px', fontFamily, fontWeight: 700, color: '#191c1e',
                                formatter: w => {
                                    const t = w.globals.seriesTotals.reduce((a,b) => a+b, 0);
                                    return t >= 1000 ? (t/1000).toFixed(1)+'k €' : t.toFixed(0)+' €';
                                }
                            },
                            value: {
                                show: true, fontSize: '18px', fontFamily, fontWeight: 700, color: '#191c1e',
                                formatter: v => v >= 1000 ? (v/1000).toFixed(1)+'k €' : Number(v).toFixed(0)+' €'
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            legend: { position: 'bottom', fontFamily, fontSize: '12px' },
        }).render();

        /* ─── GRÁFICO 3: RadialBar Gauges Márgen por tienda ─── */
        function makeGauge(elId, pct, color, label) {
            const el = document.getElementById(elId);
            if (!el) return;
            new ApexCharts(el, {
                chart: { type:'radialBar', height:240, sparkline:{enabled:true},
                         animations:{enabled:true,easing:'easeinout',speed:900} },
                series: [Math.min(Math.max(pct,0),100)],
                colors: [color],
                plotOptions: {
                    radialBar: {
                        startAngle: -130, endAngle: 130,
                        hollow: { size:'62%', background:'#f8fafc' },
                        track: { background:'#e8eaed', strokeWidth:'97%' },
                        dataLabels: {
                            name: { show:true, color:'#9ca3af', fontSize:'11px', fontFamily, offsetY:20 },
                            value: { offsetY:-10, fontSize:'26px', fontFamily, fontWeight:800, color, formatter:v=>v.toFixed(1)+'%' },
                        }
                    }
                },
                labels: [label],
                fill: { type:'gradient', gradient:{ shade:'dark', type:'horizontal', shadeIntensity:.5,
                    gradientToColors:[pct>=28?'#4ade80':pct>=20?'#fbbf24':'#f87171'], stops:[0,100] } },
                stroke: { lineCap:'round' },
            }).render();
        }
        makeGauge('gauge-vielha', {{ $vielhaMargenPct }},
            '{{ $vielhaMargenPct >= 28 ? "#16a34a" : ($vielhaMargenPct >= 20 ? "#ca8a04" : "#dc2626") }}',
            '{{ number_format($vielhaMargenPct,1) }}% margen');
        makeGauge('gauge-pont', {{ $pontMargenPct }},
            '{{ $pontMargenPct >= 28 ? "#16a34a" : ($pontMargenPct >= 20 ? "#ca8a04" : "#dc2626") }}',
            '{{ number_format($pontMargenPct,1) }}% margen');
    })();
    </script>
    @endpush

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
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Último Día ({{ isset($ultimoDiaVentas) ? \Carbon\Carbon::parse($ultimoDiaVentas)->format('d/m') : 'Hoy' }})</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['ventas']['hoy']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['ventas']['hoy']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['ventas']['hoy']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['ventas']['hoy']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'hoy') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getImporte($tiendas, 'hoy'), 2, ',', '.') }} €</td>
                    </tr>
                    <!-- Ayer -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Penúltimo Día ({{ isset($penultimoDiaVentas) ? \Carbon\Carbon::parse($penultimoDiaVentas)->format('d/m') : 'Ayer' }})</td>
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
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors font-bold">
                        <td class="py-3 px-4 text-[#191c1e]">Anteriores</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[2]['ventas']['anteriores']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['ventas']['anteriores']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right">{{ $tiendas[1]['ventas']['anteriores']['tickets'] ?? 0 }}</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['ventas']['anteriores']['importe'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold">{{ $getTickets($tiendas, 'anteriores') }}</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($getImporte($tiendas, 'anteriores'), 2, ',', '.') }} €</td>
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
            Impagados / Pendientes
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Concepto</th>
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
                        $getImpagadosTickets = fn($t, $k) => ($t[2][$k]['tickets'] ?? 0) + ($t[1][$k]['tickets'] ?? 0);
                        $getImpagadosImporte = fn($t, $k) => ($t[2][$k]['importe'] ?? 0) + ($t[1][$k]['importe'] ?? 0);
                    @endphp
                    <!-- Impagados -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Impagados</td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="abrirDetalle('impagados', 2, 'Vielha')" class="hover:underline text-[#206393] font-semibold focus:outline-none">
                                {{ $tiendas[2]['impagados']['tickets'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right text-[#dc3545] font-semibold">
                            <button onclick="abrirDetalle('impagados', 2, 'Vielha')" class="hover:underline text-[#dc3545] font-semibold focus:outline-none">
                                {{ number_format($tiendas[2]['impagados']['importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="abrirDetalle('impagados', 1, 'Pont de Suert')" class="hover:underline text-[#206393] font-semibold focus:outline-none">
                                {{ $tiendas[1]['impagados']['tickets'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right text-[#dc3545] font-semibold">
                            <button onclick="abrirDetalle('impagados', 1, 'Pont de Suert')" class="hover:underline text-[#dc3545] font-semibold focus:outline-none">
                                {{ number_format($tiendas[1]['impagados']['importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold">
                            <button onclick="abrirDetalle('impagados', 'all', 'Total')" class="hover:underline text-[#191c1e] font-bold focus:outline-none">
                                {{ $getImpagadosTickets($tiendas, 'impagados') }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-[#dc3545]">
                            <button onclick="abrirDetalle('impagados', 'all', 'Total')" class="hover:underline text-[#dc3545] font-bold focus:outline-none">
                                {{ number_format($getImpagadosImporte($tiendas, 'impagados'), 2, ',', '.') }} €
                            </button>
                        </td>
                    </tr>
                    <!-- Impagados devueltos (extra, definicion previa del panel) -->
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#747878]">
                            Impagados devueltos
                            <span class="block text-[10px] uppercase tracking-wide text-[#a0a4a8]">extra</span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="abrirDetalle('impagados_devueltos', 2, 'Vielha')" class="hover:underline text-[#747878] font-semibold focus:outline-none">
                                {{ $tiendas[2]['impagados_devueltos']['tickets'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right text-[#747878]">
                            <button onclick="abrirDetalle('impagados_devueltos', 2, 'Vielha')" class="hover:underline text-[#747878] font-semibold focus:outline-none">
                                {{ number_format($tiendas[2]['impagados_devueltos']['importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="abrirDetalle('impagados_devueltos', 1, 'Pont de Suert')" class="hover:underline text-[#747878] font-semibold focus:outline-none">
                                {{ $tiendas[1]['impagados_devueltos']['tickets'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right text-[#747878]">
                            <button onclick="abrirDetalle('impagados_devueltos', 1, 'Pont de Suert')" class="hover:underline text-[#747878] font-semibold focus:outline-none">
                                {{ number_format($tiendas[1]['impagados_devueltos']['importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">
                            <button onclick="abrirDetalle('impagados_devueltos', 'all', 'Total')" class="hover:underline text-[#747878] font-bold focus:outline-none">
                                {{ $getImpagadosTickets($tiendas, 'impagados_devueltos') }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">
                            <button onclick="abrirDetalle('impagados_devueltos', 'all', 'Total')" class="hover:underline text-[#747878] font-bold focus:outline-none">
                                {{ number_format($getImpagadosImporte($tiendas, 'impagados_devueltos'), 2, ',', '.') }} €
                            </button>
                        </td>
                    </tr>
                    <!-- Pendientes -->
                    <tr class="hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Pendientes</td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="abrirDetalle('pendientes', 2, 'Vielha')" class="hover:underline text-[#206393] font-semibold focus:outline-none">
                                {{ $tiendas[2]['pendientes']['tickets'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right text-[#747878]">
                            <button onclick="abrirDetalle('pendientes', 2, 'Vielha')" class="hover:underline text-[#747878] font-semibold focus:outline-none">
                                {{ number_format($tiendas[2]['pendientes']['importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="abrirDetalle('pendientes', 1, 'Pont de Suert')" class="hover:underline text-[#206393] font-semibold focus:outline-none">
                                {{ $tiendas[1]['pendientes']['tickets'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right text-[#747878]">
                            <button onclick="abrirDetalle('pendientes', 1, 'Pont de Suert')" class="hover:underline text-[#747878] font-semibold focus:outline-none">
                                {{ number_format($tiendas[1]['pendientes']['importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold">
                            <button onclick="abrirDetalle('pendientes', 'all', 'Total')" class="hover:underline text-[#191c1e] font-bold focus:outline-none">
                                {{ $impagados['pendientes_count'] ?? 0 }}
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-[#747878]">
                            <button onclick="abrirDetalle('pendientes', 'all', 'Total')" class="hover:underline text-[#747878] font-bold focus:outline-none">
                                {{ number_format($impagados['pendientes_importe'] ?? 0, 2, ',', '.') }} €
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>


    <!-- MÁRGENES COMERCIALES -->
    <div class="glass-card rounded-xl p-5 mb-6 overflow-hidden">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#28a745]">analytics</span>
            Márgenes Comerciales
        </h2>

        {{-- ÚLTIMO DÍA CON VENTAS --}}
        @php
            $fechaHoyLabel = isset($ultimoDiaVentas)
                ? \Carbon\Carbon::parse($ultimoDiaVentas)->locale('es')->isoFormat('dddd D [de] MMMM')
                : 'Último día';
            $esHoy = isset($ultimoDiaVentas) && $ultimoDiaVentas === now()->toDateString();
        @endphp
        <p class="text-xs font-semibold text-[#747878] uppercase tracking-wide mb-2">
            {{ $esHoy ? 'Hoy' : 'Último día con ventas' }}
            <span class="normal-case font-normal text-[#206393] ml-1">— {{ $fechaHoyLabel }}</span>
        </p>
        <div class="overflow-x-auto mb-5">
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
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[2]['margenes_hoy']['venta'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($tiendas[1]['margenes_hoy']['venta'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#206393]">{{ number_format($totales['margen_hoy_venta'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Coste</td>
                        <td class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($tiendas[2]['margenes_hoy']['coste'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($tiendas[1]['margenes_hoy']['coste'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#dc3545]">{{ number_format($totales['margen_hoy_coste'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Resultado</td>
                        <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tiendas[2]['margenes_hoy']['margen'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tiendas[1]['margenes_hoy']['margen'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#28a745]">{{ number_format($totales['margen_hoy'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="bg-[#f0f4f8]">
                        <td class="py-3 px-4 font-semibold text-[#191c1e]">% Margen</td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ ($tiendas[2]['margenes_hoy']['margen_porcentaje'] ?? 0) >= 35 ? 'bg-green-100 text-green-700' : (($tiendas[2]['margenes_hoy']['margen_porcentaje'] ?? 0) >= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($tiendas[2]['margenes_hoy']['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ ($tiendas[1]['margenes_hoy']['margen_porcentaje'] ?? 0) >= 35 ? 'bg-green-100 text-green-700' : (($tiendas[1]['margenes_hoy']['margen_porcentaje'] ?? 0) >= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($tiendas[1]['margenes_hoy']['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-bold
                                {{ ($totales['margen_hoy_porcentaje'] ?? 0) >= 35 ? 'bg-green-100 text-green-700' : (($totales['margen_hoy_porcentaje'] ?? 0) >= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($totales['margen_hoy_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- AÑO ACTUAL --}}
        <p class="text-xs font-semibold text-[#747878] uppercase tracking-wide mb-2">Año {{ $year }}</p>
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
                        <td class="py-3 px-4 font-medium text-[#191c1e]">Resultado</td>
                        <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tiendas[2]['margenes']['margen'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($tiendas[1]['margenes']['margen'] ?? 0, 2, ',', '.') }} €</td>
                        <td class="py-3 px-4 text-right font-bold text-[#28a745]">{{ number_format($totales['margen'] ?? 0, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="bg-[#f0f4f8]">
                        <td class="py-3 px-4 font-semibold text-[#191c1e]">% Margen</td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ ($tiendas[2]['margenes']['margen_porcentaje'] ?? 0) >= 25 ? 'bg-green-100 text-green-700' : (($tiendas[2]['margenes']['margen_porcentaje'] ?? 0) >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($tiendas[2]['margenes']['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ ($tiendas[1]['margenes']['margen_porcentaje'] ?? 0) >= 25 ? 'bg-green-100 text-green-700' : (($tiendas[1]['margenes']['margen_porcentaje'] ?? 0) >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($tiendas[1]['margenes']['margen_porcentaje'] ?? 0, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <span class="px-2 py-1 rounded-full text-xs font-bold
                                {{ ($totales['margen_porcentaje'] ?? 0) >= 25 ? 'bg-green-100 text-green-700' : (($totales['margen_porcentaje'] ?? 0) >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
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
                    @php
                        $fcPeriodos = [
                            'mes_actual'            => 'Mes Actual',
                            'mes_anterior'          => 'Mes Anterior',
                            'year_actual'           => "Año {$year}",
                            'year_anterior_periodo' => 'Año Anterior (mismo periodo)',
                            'year_anterior'         => "Año " . ($year - 1),
                        ];
                    @endphp
                    @foreach ($fcPeriodos as $fcKey => $fcLabel)
                        @php
                            $fcRow = $facturasCompras[$fcKey] ?? ['count' => 0, 'importe' => 0];
                            $isYearActual = $fcKey === 'year_actual';
                            $isAnterior = in_array($fcKey, ['year_anterior_periodo', 'year_anterior']);
                            $labelCls = $isAnterior ? 'text-[#747878]' : 'text-[#191c1e]';
                            $labelWeight = $isYearActual ? 'font-semibold' : 'font-medium';
                            $countCls = 'text-[#747878]';
                            $impCls = $isAnterior ? 'text-[#747878]' : 'text-[#206393] ' . ($isYearActual ? 'font-semibold' : 'font-semibold');
                            $rowBg = $isYearActual ? ' bg-[#f0f4f8]' : '';
                        @endphp
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors{{ $rowBg }}">
                            <td class="py-3 px-4 {{ $labelWeight }} {{ $labelCls }}">
                                <button onclick="abrirDetalleFacturasCompras('{{ $fcKey }}', '{{ $fcLabel }}')" class="hover:underline focus:outline-none text-left {{ $labelWeight }} {{ $labelCls }}">
                                    {{ $fcLabel }}
                                </button>
                            </td>
                            <td class="py-3 px-4 text-right {{ $countCls }}">
                                <button onclick="abrirDetalleFacturasCompras('{{ $fcKey }}', '{{ $fcLabel }}')" class="hover:underline focus:outline-none {{ $countCls }}">{{ $fcRow['count'] ?? 0 }}</button>
                            </td>
                            <td class="py-3 px-4 text-right {{ $impCls }}">
                                <button onclick="abrirDetalleFacturasCompras('{{ $fcKey }}', '{{ $fcLabel }}')" class="hover:underline focus:outline-none {{ $impCls }}">{{ number_format($fcRow['importe'] ?? 0, 2, ',', '.') }} €</button>
                            </td>
                        </tr>
                    @endforeach
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
                        $pagosOrden = ['Mes Actual', 'Mes Siguiente', 'En 2 meses', 'En 3 meses'];
                        $pagosMap = [];
                        foreach($pagosPendientes ?? [] as $p) { $pagosMap[$p['periodo']] = $p['importe']; }
                    @endphp
                    @foreach($pagosOrden as $periodo)
                        @if(isset($pagosMap[$periodo]))
                            <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                                <td class="py-3 px-4 font-medium text-[#191c1e]">
                                    <button onclick="abrirDetallePagos('{{ $periodo }}')" class="hover:underline text-[#191c1e] font-medium text-left focus:outline-none">
                                        {{ $periodo }}
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-right text-[#ffc107] font-semibold">
                                    <button onclick="abrirDetallePagos('{{ $periodo }}')" class="hover:underline text-[#ffc107] font-semibold focus:outline-none">
                                        {{ number_format($pagosMap[$periodo], 2, ',', '.') }} €
                                    </button>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    <tr class="border-t-2 border-[#e1e2e6] bg-[#f8f9fc]">
                        <td class="py-3 px-4 font-bold text-[#191c1e]">
                            <button onclick="abrirDetallePagos('all')" class="hover:underline text-[#191c1e] font-bold focus:outline-none">
                                Total pagos pendientes
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-[#ffc107]">
                            <button onclick="abrirDetallePagos('all')" class="hover:underline text-[#ffc107] font-bold focus:outline-none">
                                {{ number_format(array_sum(array_column($pagosPendientes ?? [], 'importe')), 2, ',', '.') }} €
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Detalle de Impagados / Pendientes -->
    <div id="modal-detalle" class="fixed inset-0 bg-[#191c1e]/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[85vh] flex flex-col overflow-hidden transform transition-all scale-95 opacity-0 duration-300" id="modal-card">
            <!-- Header -->
            <div class="bg-[#f8f9fc] border-b border-[#e1e2e6] px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-[#191c1e] flex items-center gap-2" id="modal-title">
                    <span class="material-symbols-outlined text-[#206393]">info</span>
                    Detalle
                </h3>
                <button onclick="cerrarModal()" class="text-[#747878] hover:text-[#191c1e] focus:outline-none transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Buscador -->
            <div class="px-6 py-3 border-b border-[#f2f3f7] bg-[#fafafa]">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <span class="material-symbols-outlined text-sm">search</span>
                    </span>
                    <input type="text" id="modal-search" oninput="filtrarDetalle()" placeholder="Buscar..." class="w-full pl-9 pr-4 py-2 border border-[#e1e2e6] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#206393] bg-white text-[#191c1e]">
                </div>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto flex-1 bg-white">
                <div id="modal-loading" class="flex flex-col items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-[#206393] mb-4"></div>
                    <p class="text-sm text-[#747878]">Cargando datos desde el ERP...</p>
                </div>

                <div id="modal-error" class="hidden text-center py-8 text-red-500">
                    <span class="material-symbols-outlined text-4xl mb-2">error</span>
                    <p id="modal-error-msg">Ha ocurrido un error al cargar los datos.</p>
                </div>

                <div id="modal-empty" class="hidden text-center py-12 text-[#747878]">
                    <span class="material-symbols-outlined text-4xl mb-2">inventory_2</span>
                    <p>No se encontraron registros para esta selección.</p>
                </div>

                <div id="modal-table-container" class="hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#e1e2e6] text-[#747878] font-semibold text-left" id="modal-thead-row">
                                <th class="py-2 px-3">Tienda</th>
                                <th class="py-2 px-3">Factura</th>
                                <th class="py-2 px-3">Cliente</th>
                                <th class="py-2 px-3">CIF/DNI</th>
                                <th class="py-2 px-3 text-center">Vencimiento</th>
                                <th class="py-2 px-3 text-right">Imp. Pendiente</th>
                            </tr>
                        </thead>
                        <tbody id="modal-table-body">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-[#f8f9fc] border-t border-[#e1e2e6] px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="text-sm font-semibold text-[#191c1e] flex gap-4" id="modal-totals">
                    <span>Total Registros: <strong id="modal-total-count">0</strong></span>
                    <span>Total Importe: <strong class="text-[#206393]" id="modal-total-sum">0,00 €</strong></span>
                </div>
                <button onclick="cerrarModal()" class="px-4 py-2 bg-[#e1e2e6] hover:bg-[#d0d2d6] text-[#191c1e] font-semibold rounded-lg text-sm transition-colors focus:outline-none">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <script>
        let allDetailData = [];
        let modalMode = 'clientes'; // 'clientes' o 'pagos'

        function abrirDetalle(tipo, tienda, tiendaNombre) {
            modalMode = 'clientes';
            const modal = document.getElementById('modal-detalle');
            const card = document.getElementById('modal-card');
            const titleEl = document.getElementById('modal-title');
            const searchInput = document.getElementById('modal-search');
            
            // Set correct header columns
            document.getElementById('modal-thead-row').innerHTML = `
                <th class="py-2 px-3">Tienda</th>
                <th class="py-2 px-3">Factura</th>
                <th class="py-2 px-3">Cliente</th>
                <th class="py-2 px-3">CIF/DNI</th>
                <th class="py-2 px-3 text-center">Vencimiento</th>
                <th class="py-2 px-3 text-right">Imp. Pendiente</th>
            `;

            // Set placeholder
            searchInput.placeholder = 'Buscar por cliente, CIF, factura...';

            // Set title
            const tipoLabel = tipo === 'impagados' ? 'Impagados' : (tipo === 'impagados_devueltos' ? 'Impagados Devueltos' : 'Facturas Pendientes');
            const tipoIcon = tipo === 'impagados_devueltos' ? 'undo' : (tipo === 'impagados' ? 'warning' : 'receipt');
            const tipoColor = tipo === 'pendientes' ? '[#206393]' : (tipo === 'impagados_devueltos' ? '[#747878]' : '[#dc3545]');
            titleEl.innerHTML = `<span class="material-symbols-outlined text-${tipoColor}">${tipoIcon}</span> Detalle de ${tipoLabel} — ${tiendaNombre}`;
            
            // Reset state
            searchInput.value = '';
            document.getElementById('modal-loading').classList.remove('hidden');
            document.getElementById('modal-table-container').classList.add('hidden');
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('modal-empty').classList.add('hidden');
            
            // Show modal container
            modal.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Fetch data
            fetch(`{{ route('store-dashboard.detalle-impagados') }}?tipo=${tipo}&tienda=${tienda}`)
                .then(response => response.json())
                .then(res => {
                    document.getElementById('modal-loading').classList.add('hidden');
                    if (res.success) {
                        allDetailData = res.data;
                        renderTable(allDetailData);
                    } else {
                        showError(res.error || 'Error al obtener datos');
                    }
                })
                .catch(err => {
                    document.getElementById('modal-loading').classList.add('hidden');
                    showError(err.message || 'Error de conexión');
                });
        }

        function abrirDetallePagos(periodo) {
            modalMode = 'pagos';
            const modal = document.getElementById('modal-detalle');
            const card = document.getElementById('modal-card');
            const titleEl = document.getElementById('modal-title');
            const searchInput = document.getElementById('modal-search');
            
            // Set correct header columns
            document.getElementById('modal-thead-row').innerHTML = `
                <th class="py-2 px-3">Factura</th>
                <th class="py-2 px-3">Proveedor</th>
                <th class="py-2 px-3">CIF/DNI</th>
                <th class="py-2 px-3 text-center">Vencimiento</th>
                <th class="py-2 px-3 text-right">Importe</th>
            `;

            // Set placeholder
            searchInput.placeholder = 'Buscar por proveedor, CIF, factura...';

            // Set title
            titleEl.innerHTML = `<span class="material-symbols-outlined text-[#ffc107]">account_balance_wallet</span> Detalle de Pagos Pendientes — ${periodo}`;
            
            // Reset state
            searchInput.value = '';
            document.getElementById('modal-loading').classList.remove('hidden');
            document.getElementById('modal-table-container').classList.add('hidden');
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('modal-empty').classList.add('hidden');
            
            // Show modal container
            modal.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Fetch data
            fetch(`{{ route('store-dashboard.detalle-pagos') }}?periodo=${encodeURIComponent(periodo)}`)
                .then(response => response.json())
                .then(res => {
                    document.getElementById('modal-loading').classList.add('hidden');
                    if (res.success) {
                        allDetailData = res.data;
                        renderTable(allDetailData);
                    } else {
                        showError(res.error || 'Error al obtener datos');
                    }
                })
                .catch(err => {
                    document.getElementById('modal-loading').classList.add('hidden');
                    showError(err.message || 'Error de conexión');
                });
        }

        function abrirDetalleFacturasCompras(periodo, etiqueta) {
            modalMode = 'compras';
            const modal = document.getElementById('modal-detalle');
            const card = document.getElementById('modal-card');
            const titleEl = document.getElementById('modal-title');
            const searchInput = document.getElementById('modal-search');

            // Set correct header columns
            document.getElementById('modal-thead-row').innerHTML = `
                <th class="py-2 px-3">Factura</th>
                <th class="py-2 px-3">Proveedor</th>
                <th class="py-2 px-3">CIF/DNI</th>
                <th class="py-2 px-3 text-center">Fecha</th>
                <th class="py-2 px-3 text-right">Base</th>
                <th class="py-2 px-3 text-right">IVA</th>
                <th class="py-2 px-3 text-right">Total</th>
            `;

            // Set placeholder
            searchInput.placeholder = 'Buscar por proveedor, CIF, factura...';

            // Set title
            titleEl.innerHTML = `<span class="material-symbols-outlined text-[#206393]">receipt_long</span> Detalle de Facturas de Compras y Gastos — ${etiqueta || periodo}`;

            // Reset state
            searchInput.value = '';
            document.getElementById('modal-loading').classList.remove('hidden');
            document.getElementById('modal-table-container').classList.add('hidden');
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('modal-empty').classList.add('hidden');

            // Show modal container
            modal.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Fetch data
            const year = new URLSearchParams(window.location.search).get('year') || '';
            fetch(`{{ route('store-dashboard.detalle-facturas-compras') }}?periodo=${encodeURIComponent(periodo)}&year=${encodeURIComponent(year)}`)
                .then(response => response.json())
                .then(res => {
                    document.getElementById('modal-loading').classList.add('hidden');
                    if (res.success) {
                        allDetailData = res.data;
                        renderTable(allDetailData);
                    } else {
                        showError(res.error || 'Error al obtener datos');
                    }
                })
                .catch(err => {
                    document.getElementById('modal-loading').classList.add('hidden');
                    showError(err.message || 'Error de conexión');
                });
        }

        function renderTable(data) {
            const tbody = document.getElementById('modal-table-body');
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                document.getElementById('modal-table-container').classList.add('hidden');
                document.getElementById('modal-empty').classList.remove('hidden');
                document.getElementById('modal-total-count').textContent = '0';
                document.getElementById('modal-total-sum').textContent = '0,00 €';
                return;
            }

            document.getElementById('modal-empty').classList.add('hidden');
            document.getElementById('modal-table-container').classList.remove('hidden');

            let totalSum = 0;
            data.forEach(item => {
                const itemImporte = modalMode === 'clientes' ? item.importe_pendiente : (modalMode === 'compras' ? item.total : item.importe);
                totalSum += itemImporte;

                // Parse date to check if expired/overdue compared to today
                let isExpired = false;
                if (item.fecha_vencimiento && item.fecha_vencimiento !== 'N/A') {
                    const parts = item.fecha_vencimiento.split('/');
                    const vencDate = new Date(parts[2], parts[1] - 1, parts[0]);
                    isExpired = vencDate < new Date();
                }

                const tr = document.createElement('tr');
                tr.className = 'border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors';

                if (modalMode === 'compras') {
                    const fmt = v => (v || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    tr.innerHTML = `
                        <td class="py-2.5 px-3 text-[#206393] font-semibold">${item.factura}</td>
                        <td class="py-2.5 px-3">
                            <div class="font-medium text-[#191c1e]">${item.proveedor}</div>
                            <div class="text-xs text-[#747878]">Prov: ${item.cod_proveedor}</div>
                        </td>
                        <td class="py-2.5 px-3 text-[#747878]">${item.cif}</td>
                        <td class="py-2.5 px-3 text-center text-[#747878]">${item.fecha}</td>
                        <td class="py-2.5 px-3 text-right text-[#191c1e]">${fmt(item.base)}</td>
                        <td class="py-2.5 px-3 text-right text-[#747878]">${fmt(item.cuota)}</td>
                        <td class="py-2.5 px-3 text-right font-bold text-[#191c1e]">${fmt(item.total)} €</td>
                    `;
                } else if (modalMode === 'clientes') {
                    tr.innerHTML = `
                        <td class="py-2.5 px-3 font-medium text-[#191c1e]">${item.tienda}</td>
                        <td class="py-2.5 px-3 text-[#206393] font-semibold">${item.factura}</td>
                        <td class="py-2.5 px-3">
                            <div class="font-medium text-[#191c1e]">${item.razon_social}</div>
                            <div class="text-xs text-[#747878]">Cliente: ${item.cod_cliente}</div>
                        </td>
                        <td class="py-2.5 px-3 text-[#747878]">${item.cif}</td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold ${isExpired ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">
                                ${item.fecha_vencimiento}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold text-[#191c1e]">${itemImporte.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                    `;
                } else {
                    tr.innerHTML = `
                        <td class="py-2.5 px-3 text-[#206393] font-semibold">${item.factura}</td>
                        <td class="py-2.5 px-3">
                            <div class="font-medium text-[#191c1e]">${item.proveedor}</div>
                            <div class="text-xs text-[#747878]">Prov: ${item.cod_proveedor}</div>
                        </td>
                        <td class="py-2.5 px-3 text-[#747878]">${item.cif}</td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold ${isExpired ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">
                                ${item.fecha_vencimiento}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold text-[#191c1e]">${itemImporte.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                    `;
                }
                tbody.appendChild(tr);
            });

            document.getElementById('modal-total-count').textContent = data.length;
            document.getElementById('modal-total-sum').textContent = totalSum.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        }

        function filtrarDetalle() {
            const query = document.getElementById('modal-search').value.toLowerCase().trim();
            if (!query) {
                renderTable(allDetailData);
                return;
            }

            const filtered = allDetailData.filter(item => {
                let searchStr = '';
                if (modalMode === 'clientes') {
                    searchStr = `${item.razon_social} ${item.cod_cliente} ${item.cif} ${item.factura} ${item.tienda}`.toLowerCase();
                } else if (modalMode === 'compras') {
                    searchStr = `${item.proveedor} ${item.cod_proveedor} ${item.cif} ${item.factura} ${item.fecha}`.toLowerCase();
                } else {
                    searchStr = `${item.proveedor} ${item.cod_proveedor} ${item.cif} ${item.factura}`.toLowerCase();
                }
                return searchStr.includes(query);
            });
            renderTable(filtered);
        }

        function showError(msg) {
            document.getElementById('modal-error-msg').textContent = msg;
            document.getElementById('modal-error').classList.remove('hidden');
            document.getElementById('modal-table-container').classList.add('hidden');
            document.getElementById('modal-empty').classList.add('hidden');
        }

        function cerrarModal() {
            const modal = document.getElementById('modal-detalle');
            const card = document.getElementById('modal-card');
            
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Close on ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
@endsection
