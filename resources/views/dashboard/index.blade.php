@extends('layouts.app')

@section('title', 'Dashboard - Ribera Estadísticas')

@section('content')
    <!-- Header con Filtro de Años -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Resumen</h1>
            <p class="text-sm text-[#747878] mt-1">Datos reales importados desde el ERP.</p>
        </div>
        <div class="flex gap-2 items-center">
            <!-- Filtros de Rango de Fechas -->
            <form method="GET" action="{{ route('dashboard') }}" class="flex gap-2 items-center flex-wrap">
                <!-- Año Desde -->
                <div class="flex items-center gap-1">
                    <label for="year_from" class="text-xs font-semibold text-[#747878] uppercase">Desde:</label>
                    <select
                        name="year_from"
                        id="year_from"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393] cursor-pointer"
                    >
                        <option value="all" {{ !isset($selectedYearFrom) || $selectedYearFrom === 'all' ? 'selected' : '' }}>Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}" {{ (isset($selectedYearFrom) && $selectedYearFrom == $year) ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select
                        name="month_from"
                        id="month_from"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393] cursor-pointer"
                    >
                        <option value="all" {{ !isset($selectedMonthFrom) || $selectedMonthFrom === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="1" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '1') ? 'selected' : '' }}>Ene</option>
                        <option value="2" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '2') ? 'selected' : '' }}>Feb</option>
                        <option value="3" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '3') ? 'selected' : '' }}>Mar</option>
                        <option value="4" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '4') ? 'selected' : '' }}>Abr</option>
                        <option value="5" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '5') ? 'selected' : '' }}>May</option>
                        <option value="6" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '6') ? 'selected' : '' }}>Jun</option>
                        <option value="7" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '7') ? 'selected' : '' }}>Jul</option>
                        <option value="8" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '8') ? 'selected' : '' }}>Ago</option>
                        <option value="9" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '9') ? 'selected' : '' }}>Sep</option>
                        <option value="10" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '10') ? 'selected' : '' }}>Oct</option>
                        <option value="11" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '11') ? 'selected' : '' }}>Nov</option>
                        <option value="12" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == '12') ? 'selected' : '' }}>Dic</option>
                    </select>
                </div>

                <span class="text-[#747878]">→</span>

                <!-- Año Hasta -->
                <div class="flex items-center gap-1">
                    <label for="year_to" class="text-xs font-semibold text-[#747878] uppercase">Hasta:</label>
                    <select
                        name="year_to"
                        id="year_to"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393] cursor-pointer"
                    >
                        <option value="all" {{ !isset($selectedYearTo) || $selectedYearTo === 'all' ? 'selected' : '' }}>Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}" {{ (isset($selectedYearTo) && $selectedYearTo == $year) ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select
                        name="month_to"
                        id="month_to"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393] cursor-pointer"
                    >
                        <option value="all" {{ !isset($selectedMonthTo) || $selectedMonthTo === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="1" {{ (isset($selectedMonthTo) && $selectedMonthTo == '1') ? 'selected' : '' }}>Ene</option>
                        <option value="2" {{ (isset($selectedMonthTo) && $selectedMonthTo == '2') ? 'selected' : '' }}>Feb</option>
                        <option value="3" {{ (isset($selectedMonthTo) && $selectedMonthTo == '3') ? 'selected' : '' }}>Mar</option>
                        <option value="4" {{ (isset($selectedMonthTo) && $selectedMonthTo == '4') ? 'selected' : '' }}>Abr</option>
                        <option value="5" {{ (isset($selectedMonthTo) && $selectedMonthTo == '5') ? 'selected' : '' }}>May</option>
                        <option value="6" {{ (isset($selectedMonthTo) && $selectedMonthTo == '6') ? 'selected' : '' }}>Jun</option>
                        <option value="7" {{ (isset($selectedMonthTo) && $selectedMonthTo == '7') ? 'selected' : '' }}>Jul</option>
                        <option value="8" {{ (isset($selectedMonthTo) && $selectedMonthTo == '8') ? 'selected' : '' }}>Ago</option>
                        <option value="9" {{ (isset($selectedMonthTo) && $selectedMonthTo == '9') ? 'selected' : '' }}>Sep</option>
                        <option value="10" {{ (isset($selectedMonthTo) && $selectedMonthTo == '10') ? 'selected' : '' }}>Oct</option>
                        <option value="11" {{ (isset($selectedMonthTo) && $selectedMonthTo == '11') ? 'selected' : '' }}>Nov</option>
                        <option value="12" {{ (isset($selectedMonthTo) && $selectedMonthTo == '12') ? 'selected' : '' }}>Dic</option>
                    </select>
                </div>

                <!-- Botón Aplicar -->
                <button
                    type="submit"
                    class="p-1.5 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors"
                    title="Aplicar filtros"
                >
                    <span class="material-symbols-outlined text-[18px]">search</span>
                </button>

                <!-- Botón Limpiar -->
                @if((isset($selectedYearFrom) && $selectedYearFrom !== 'all') || (isset($selectedYearTo) && $selectedYearTo !== 'all') || (isset($selectedMonthFrom) && $selectedMonthFrom !== 'all') || (isset($selectedMonthTo) && $selectedMonthTo !== 'all'))
                    <a
                        href="{{ route('dashboard') }}"
                        class="p-1.5 bg-[#747878] text-white rounded-lg hover:bg-[#5a5d5f] transition-colors"
                        title="Limpiar filtros"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </a>
                @endif
            </form>
            <span class="px-3 py-1.5 bg-[#206393] text-white rounded-lg text-xs font-semibold">
                {{ number_format($totalOrders) }} ventas
            </span>
        </div>
    </div>

    <!-- Alertas -->
    @if($alerts->count() > 0)
        <div class="bg-[#ffe16d] border border-[#705d00] rounded-xl p-4 flex items-start gap-4 shadow-sm">
            <div class="mt-0.5 text-[#4c3f00]">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">warning</span>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-[#4c3f00] mb-1">Alertas Operacionales</h3>
                <ul class="text-sm text-[#544600] space-y-1 list-disc list-inside">
                    @foreach($alerts->take(2) as $alert)
                        <li><strong>{{ $alert['title'] }}</strong>: {{ $alert['description'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ventas Totales</span>
                <span class="material-symbols-outlined text-[#747878]">payments</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ number_format($totalSales, 0, ',', '.') }} €</div>
                <div class="flex items-center gap-1 text-[#206393]">
                    <span class="material-symbols-outlined text-[16px]">trending_up</span>
                    <span class="text-sm font-semibold">{{ number_format($totalOrders) }} operaciones</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ticket Medio</span>
                <span class="material-symbols-outlined text-[#747878]">receipt_long</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ number_format($avgTicket, 2, ',', '.') }} €</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">Por operación</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between border-l-4 border-l-[#206393]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Pendiente Cobro</span>
                <span class="material-symbols-outlined text-[#206393]">account_balance_wallet</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ number_format($pendingAmount, 0, ',', '.') }} €</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">Importe pendiente</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Clientes Únicos</span>
                <span class="material-symbols-outlined text-[#747878]">groups</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ \App\Models\ErpSale::distinct('cod_cliente')->count('cod_cliente') }}</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">En el período</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="glass-card rounded-xl p-5">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-semibold text-[#191c1e]">
                    Evolución de Ventas
                    @if((isset($selectedYearFrom) && $selectedYearFrom !== 'all') || (isset($selectedYearTo) && $selectedYearTo !== 'all'))
                        <span class="text-[#206393]">
                            @if($selectedYearFrom !== 'all' && $selectedYearTo !== 'all')
                                {{ $selectedYearFrom }} @if($selectedMonthFrom !== 'all')/{{ \Carbon\Carbon::create()->month((int)$selectedMonthFrom)->format('M') }} @endif
                                -
                                {{ $selectedYearTo }} @if($selectedMonthTo !== 'all')/{{ \Carbon\Carbon::create()->month((int)$selectedMonthTo)->format('M') }} @endif
                            @elseif($selectedYearFrom !== 'all')
                                Desde {{ $selectedYearFrom }} @if($selectedMonthFrom !== 'all')/{{ \Carbon\Carbon::create()->month((int)$selectedMonthFrom)->format('M') }} @endif
                            @elseif($selectedYearTo !== 'all')
                                Hasta {{ $selectedYearTo }} @if($selectedMonthTo !== 'all')/{{ \Carbon\Carbon::create()->month((int)$selectedMonthTo)->format('M') }} @endif
                            @endif
                        </span>
                    @endif
                </h2>
                <p class="text-sm text-[#747878]">
                    @if($selectedYearFrom !== 'all' && $selectedYearTo !== 'all')
                        Ventas entre {{ \Carbon\Carbon::create()->month((int)$selectedMonthFrom)->format('F') }}/{{ $selectedYearFrom }} y {{ \Carbon\Carbon::create()->month((int)$selectedMonthTo)->format('F') }}/{{ $selectedYearTo }}
                    @elseif($selectedYearFrom !== 'all')
                        Ventas desde {{ \Carbon\Carbon::create()->month((int)$selectedMonthFrom)->format('F') }}/{{ $selectedYearFrom }}
                    @elseif($selectedYearTo !== 'all')
                        Ventas hasta {{ \Carbon\Carbon::create()->month((int)$selectedMonthTo)->format('F') }}/{{ $selectedYearTo }}
                    @else
                        Volumen mensual importado desde ERP ({{ $minYear ?? '2012' }} - {{ $maxYear ?? date('Y') }})
                    @endif
                </p>
            </div>
            @if((isset($selectedYearFrom) && $selectedYearFrom !== 'all') || (isset($selectedYearTo) && $selectedYearTo !== 'all') || (isset($selectedMonthFrom) && $selectedMonthFrom !== 'all') || (isset($selectedMonthTo) && $selectedMonthTo !== 'all'))
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-[#206393]/10 text-[#206393] rounded-full text-xs font-semibold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">filter_list</span>
                        Filtro activo
                    </span>
                </div>
            @endif
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Top Clients -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Clientes por Facturación</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Población</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Provincia</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vendedor</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topClients as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $client['razon_social'] }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client['poblacion'] ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client['provincia'] ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ '-' }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($client['total_spent'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Productos por Facturación</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Marca</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cantidad</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topProducts as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $product['cod_articulo'] }}</td>
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $product['descripcion'] }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product['marca'] ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product['cod_familia'] ?? '-' }}{{ $product['cod_subfamilia'] ?? '' ? ' / ' . ($product['cod_subfamilia'] ?? '') : '' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product['stock_total'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product['total_qty'], 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($product['total_revenue'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesData = @json($salesByMonth);
    const labels = Object.keys(salesData);
    const data = Object.values(salesData);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(l => {
                const [year, month] = l.split('-');
                return `${month}/${year}`;
            }),
            datasets: [{
                label: 'Ventas (€)',
                data: data,
                borderColor: '#206393',
                backgroundColor: 'rgba(32, 99, 147, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#206393',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
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
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
@endpush
