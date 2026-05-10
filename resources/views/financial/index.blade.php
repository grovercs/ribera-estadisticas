@extends('layouts.app')

@section('title', 'Análisis Financiero - Ribera Estadísticas')

@section('content')
    <!-- Header con Filtros -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Análisis Financiero</h1>
            <p class="text-sm text-[#747878] mt-1">Márgenes, rentabilidad y salud del negocio.</p>
        </div>
        <div class="flex gap-2 items-center">
            <!-- Filtros de Rango de Fechas -->
            <form method="GET" action="{{ route('financial') }}" class="flex gap-2 items-center flex-wrap">
                <div class="flex items-center gap-1">
                    <label for="year_from" class="text-xs font-semibold text-[#747878] uppercase">Desde:</label>
                    <select name="year_from" id="year_from"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ (isset($selectedYearFrom) && $selectedYearFrom === 'all') ? 'selected' : '' }}>Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}" {{ (!isset($selectedYearFrom) || $selectedYearFrom == $year) ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month_from" id="month_from"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ !isset($selectedMonthFrom) || $selectedMonthFrom === 'all' ? 'selected' : '' }}>Todos</option>
                        @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                            <option value="{{ $num }}" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == $num) ? 'selected' : '' }}>{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="text-[#747878]">→</span>
                <div class="flex items-center gap-1">
                    <label for="year_to" class="text-xs font-semibold text-[#747878] uppercase">Hasta:</label>
                    <select name="year_to" id="year_to"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ (isset($selectedYearTo) && $selectedYearTo === 'all') ? 'selected' : '' }}>Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}" {{ (!isset($selectedYearTo) || $selectedYearTo == $year) ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month_to" id="month_to"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ !isset($selectedMonthTo) || $selectedMonthTo === 'all' ? 'selected' : '' }}>Todos</option>
                        @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                            <option value="{{ $num }}" {{ (isset($selectedMonthTo) && $selectedMonthTo == $num) ? 'selected' : '' }}>{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="p-1.5 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors">
                    <span class="material-symbols-outlined text-[18px]">search</span>
                </button>
                @if(isset($selectedYearFrom) && $selectedYearFrom !== $maxYear)
                    <a href="{{ route('financial') }}" class="p-1.5 bg-[#747878] text-white rounded-lg hover:bg-[#5a5d5f] transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </a>
                @endif
            </form>
        </div>
    </div>

    @if(isset($error))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 font-semibold">Error: {{ $error }}</p>
        </div>
    @endif

    <!-- KPIs Financieros Principales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Ventas Netas -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#206393]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ventas Netas</span>
                <span class="material-symbols-outlined text-[#206393]">payments</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['revenue'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Ejercicio {{ $selectedYearFrom }}</div>
        </div>

        <!-- Coste Ventas -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#dc3545]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Coste Ventas</span>
                <span class="material-symbols-outlined text-[#dc3545]">inventory</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['total_cost'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Coste mercancía vendida</div>
        </div>

        <!-- Beneficio Bruto -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#28a745]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Beneficio Bruto</span>
                <span class="material-symbols-outlined text-[#28a745]">trending_up</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['gross_profit'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Margen bruto (ventas - coste)</div>
        </div>

        <!-- Margen % -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#ffc107]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Margen Bruto %</span>
                <span class="material-symbols-outlined text-[#ffc107]">pie_chart</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['margin_rate'], 1, ',', '.') }} %</div>
            <div class="text-xs text-[#747878]">Rentabilidad sobre ventas</div>
        </div>
    </div>

    <!-- KPIs Secundarios -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <!-- Pedidos -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Pedidos</span>
                <span class="material-symbols-outlined text-[#747878]">shopping_cart</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['total_orders'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878]">Operaciones realizadas</div>
        </div>

        <!-- Clientes Únicos -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Clientes Activos</span>
                <span class="material-symbols-outlined text-[#747878]">groups</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['unique_clients'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878]">Clientes que compraron</div>
        </div>

        <!-- Ticket Medio -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ticket Medio</span>
                <span class="material-symbols-outlined text-[#747878]">receipt_long</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['avg_ticket'], 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Por operación</div>
        </div>

        <!-- Facturación por Cliente -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Por Cliente</span>
                <span class="material-symbols-outlined text-[#747878]">person</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['revenue_per_client'], 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Facturación media/cliente</div>
        </div>
    </div>

    <!-- Evolución Mensual de Márgenes -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Evolución Mensual: Facturación vs Beneficio Bruto</h2>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="monthlyMarginChart"></canvas>
        </div>
    </div>

    <!-- Margen por Familia -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Rentabilidad por Familia de Productos</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Coste</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Beneficio</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Margen %</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pedidos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($marginByFamily as $family)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $family->familia }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($family->revenue, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($family->total_cost, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($family->gross_profit, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $family->margin_rate >= 40 ? 'bg-green-100 text-green-700' : ($family->margin_rate >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($family->margin_rate, 1) }}%
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($family->orders, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Productos Estrella -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">
            <span class="material-symbols-outlined text-[#ffc107] vertical-align-middle" style="font-variation-settings: 'FILL' 1;">star</span>
            Productos Estrella (Alta Rotación + Buen Margen)
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Und. Vendidas</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Beneficio</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Margen %</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Margen/Und</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($starProducts as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-medium text-[#191c1e]">{{ $product->descripcion }}</div>
                                <div class="text-xs text-[#747878] font-mono">{{ $product->cod_articulo }}</div>
                            </td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->familia }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($product->total_qty, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($product->revenue, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($product->gross_profit, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $product->margin_rate >= 40 ? 'bg-green-100 text-green-700' : ($product->margin_rate >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($product->margin_rate, 1) }}%
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($product->margin_per_unit, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Clientes Top por Rentabilidad -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Clientes Más Rentables</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Población</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Beneficio</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Margen %</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Pedidos</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ticket Medio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topClientsByProfit as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $client->razon_social }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->poblacion ?? '-' }}</td>
                            <td class="py-3 px-4 text-right text-[#206393]">{{ number_format($client->revenue, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($client->gross_profit, 0, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $client->margin_rate >= 40 ? 'bg-green-100 text-green-700' : ($client->margin_rate >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($client->margin_rate, 1) }}%
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($client->orders, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($client->avg_order_value, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const ctx = document.getElementById('monthlyMarginChart').getContext('2d');
    const monthlyData = @json($monthlyMargin);
    const labels = Object.keys(monthlyData);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(l => {
                const [year, month] = l.split('-');
                const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                return months[parseInt(month)-1] + '/' + year;
            }),
            datasets: [
                {
                    label: 'Facturación (€)',
                    data: labels.map(l => monthlyData[l].revenue),
                    borderColor: '#206393',
                    backgroundColor: 'rgba(32, 99, 147, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Beneficio Bruto (€)',
                    data: labels.map(l => monthlyData[l].profit),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Margen %',
                    data: labels.map(l => monthlyData[l].margin_rate),
                    borderColor: '#ffc107',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('es-ES') + ' €';
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    max: 100,
                    grid: { display: false },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
@endpush
