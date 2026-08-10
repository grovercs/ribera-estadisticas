@extends('layouts.app')

@section('title', 'Dashboard - Ribera Estadísticas')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
            <div>
                <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Resumen</h1>
                <p class="text-sm text-[#747878] mt-1">Datos reales desde el ERP. Fuente: {{ $source ?? 'ERP' }}</p>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap gap-2 items-center">
                    <div class="flex items-center gap-1">
                        <label class="text-xs font-semibold text-[#747878] uppercase">Desde</label>
                        <select name="year_from" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm">
                            <option value="all" {{ $selectedYearFrom === 'all' ? 'selected' : '' }}>Todo</option>
                            @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                                <option value="{{ $year }}" {{ $selectedYearFrom == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                        <select name="month_from" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm">
                            <option value="all" {{ $selectedMonthFrom === 'all' ? 'selected' : '' }}>Todos</option>
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" {{ $selectedMonthFrom == $m ? 'selected' : '' }}>{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <span class="text-[#747878]">→</span>

                    <div class="flex items-center gap-1">
                        <label class="text-xs font-semibold text-[#747878] uppercase">Hasta</label>
                        <select name="year_to" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm">
                            <option value="all" {{ $selectedYearTo === 'all' ? 'selected' : '' }}>Todo</option>
                            @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                                <option value="{{ $year }}" {{ $selectedYearTo == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                        <select name="month_to" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm">
                            <option value="all" {{ $selectedMonthTo === 'all' ? 'selected' : '' }}>Todos</option>
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" {{ $selectedMonthTo == $m ? 'selected' : '' }}>{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="p-1.5 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078]" title="Aplicar">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                    </button>

                    @if($selectedYearFrom !== 'all' || $selectedYearTo !== 'all' || $selectedMonthFrom !== 'all' || $selectedMonthTo !== 'all')
                        <a href="{{ route('dashboard') }}" class="p-1.5 bg-[#747878] text-white rounded-lg hover:bg-[#5a5d5f]" title="Limpiar">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- Alertas compactas --}}
        @if($alerts->count() > 0)
            <div class="bg-[#fff8e1] border border-[#f9a825] rounded-xl p-4 flex items-start gap-3 shadow-sm mb-6">
                <span class="material-symbols-outlined text-[#f57f17] mt-0.5">warning</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-[#4c3f00]">Alertas operacionales</h3>
                        <span class="text-xs text-[#f57f17] font-medium">{{ $alerts->count() }} activas</span>
                    </div>
                    <ul class="text-sm text-[#544600] mt-1 space-y-1 list-disc list-inside">
                        @foreach($alerts->take(2) as $alert)
                            <li><strong>{{ $alert['title'] }}</strong> — {{ Str::limit($alert['description'], 80) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="glass-card rounded-xl p-5">
                <p class="text-xs font-semibold text-[#747878] uppercase">Ventas totales</p>
                <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($totalSales, 0, ',', '.') }} €</p>
                <p class="text-xs text-[#206393] mt-1">{{ number_format($totalOrders) }} operaciones</p>
            </div>
            <div class="glass-card rounded-xl p-5">
                <p class="text-xs font-semibold text-[#747878] uppercase">Ticket medio</p>
                <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($avgTicket, 2, ',', '.') }} €</p>
            </div>
            <div class="glass-card rounded-xl p-5">
                <p class="text-xs font-semibold text-[#747878] uppercase">Pendiente cobro</p>
                <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($pendingAmount, 0, ',', '.') }} €</p>
            </div>
            <div class="glass-card rounded-xl p-5">
                <p class="text-xs font-semibold text-[#747878] uppercase">Clientes únicos</p>
                <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($uniqueClients, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Gráficos --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="glass-card rounded-xl p-5 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-[#191c1e]">Evolución de ventas</h2>
                    @if($selectedYearFrom !== 'all' || $selectedYearTo !== 'all')
                        <span class="px-2 py-1 bg-[#206393]/10 text-[#206393] rounded-full text-xs font-semibold">Filtro activo</span>
                    @endif
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="glass-card rounded-xl p-5">
                <h2 class="text-lg font-semibold text-[#191c1e] mb-4">Ventas por almacén</h2>
                <div style="position: relative; height: 260px;">
                    <canvas id="warehouseChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Tablas: top clientes y top productos --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="glass-card rounded-xl p-5">
                <h2 class="text-lg font-semibold text-[#191c1e] mb-4">Top clientes</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-[#747878] text-xs uppercase border-b border-[#e1e2e6]">
                            <tr>
                                <th class="text-left py-2 px-3">Cliente</th>
                                <th class="text-left py-2 px-3">Vendedor</th>
                                <th class="text-right py-2 px-3">Facturación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f2f3f7]">
                            @foreach($topClients as $client)
                                <tr class="hover:bg-[#f8f9fc]">
                                    <td class="py-2 px-3">
                                        <div class="font-medium text-[#191c1e]">{{ $client['razon_social'] ?? 'N/A' }}</div>
                                        @if(!empty($client['poblacion']))
                                            <div class="text-xs text-[#747878]">{{ $client['poblacion'] }}{{ !empty($client['provincia']) ? ', ' . $client['provincia'] : '' }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-[#747878]">{{ $client['vendedor_principal'] ?? '-' }}</td>
                                    <td class="py-2 px-3 text-right font-semibold text-[#206393]">{{ number_format($client['total_spent'], 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-card rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-[#191c1e]">Top productos</h2>
                        <p class="text-xs text-[#747878] mt-1">Más vendidos en el período. Stock actual en rojo si está agotado.</p>
                    </div>
                    @php
                        $dashQuery = request()->query();
                        unset($dashQuery['page']);
                    @endphp
                    <a href="{{ route('dashboard', array_merge($dashQuery, ['hide_no_stock' => $hideNoStock ? 0 : 1])) }}"
                       class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $hideNoStock ? 'bg-[#206393] text-white border-[#206393]' : 'bg-white text-[#747878] border-[#e1e2e6]' }} hover:shadow-sm transition">
                        {{ $hideNoStock ? 'Mostrar también sin stock' : 'Ocultar sin stock' }}
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-[#747878] text-xs uppercase border-b border-[#e1e2e6]">
                            <tr>
                                <th class="text-left py-2 px-3">Artículo</th>
                                <th class="text-right py-2 px-3">Stock</th>
                                <th class="text-right py-2 px-3">Cantidad</th>
                                <th class="text-right py-2 px-3">Facturación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f2f3f7]">
                            @foreach($topProducts as $product)
                                <tr class="hover:bg-[#f8f9fc]">
                                    <td class="py-2 px-3">
                                        <div class="font-mono text-[#206393]">{{ $product['cod_articulo'] }}</div>
                                        <div class="text-xs text-[#747878] truncate max-w-[200px]" title="{{ $product['descripcion'] ?? '' }}">{{ $product['descripcion'] ?? '-' }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right {{ ($product['stock_total'] ?? 0) <= 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($product['stock_total'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format($product['total_qty'], 0, ',', '.') }}</td>
                                    <td class="py-2 px-3 text-right font-semibold text-[#206393]">{{ number_format($product['total_revenue'], 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Resúmenes inferiores --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="glass-card rounded-xl p-5">
                <h2 class="text-lg font-semibold text-[#191c1e] mb-4">Top familias</h2>
                <div style="position: relative; height: 220px;">
                    <canvas id="familyChart"></canvas>
                </div>
                <div id="familyLegend" class="mt-4">
                    {{-- Leyenda generada por JS --}}
                </div>
            </div>

            <div class="glass-card rounded-xl p-5">
                <h2 class="text-lg font-semibold text-[#191c1e] mb-4">Top vendedores</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-[#747878] text-xs uppercase border-b border-[#e1e2e6]">
                            <tr>
                                <th class="text-left py-2 px-3">Vendedor</th>
                                <th class="text-right py-2 px-3">Operaciones</th>
                                <th class="text-right py-2 px-3">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f2f3f7]">
                            @foreach($topSellers as $seller)
                                <tr class="hover:bg-[#f8f9fc]">
                                    <td class="py-2 px-3">{{ $seller->nombre_vendedor ?: $seller->cod_vendedor }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format($seller->orders, 0, ',', '.') }}</td>
                                    <td class="py-2 px-3 text-right font-semibold text-[#206393]">{{ number_format($seller->total, 0, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const salesData = @json($salesByMonth);
    const prevSalesData = @json($prevSalesByMonth);
    const labels = Object.keys(salesData).sort();
    const prevLabels = Object.keys(prevSalesData).sort();
    const allLabels = Array.from(new Set([...labels, ...prevLabels])).sort();

    const currentDataset = allLabels.map(l => salesData[l] || 0);
    const prevDataset = allLabels.map(l => prevSalesData[l] || null);

    new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: allLabels.map(l => {
                const [year, month] = l.split('-');
                return `${month}/${year}`;
            }),
            datasets: [
                {
                    label: 'Ventas actuales (€)',
                    data: currentDataset,
                    borderColor: '#206393',
                    backgroundColor: 'rgba(32, 99, 147, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                },
                {
                    label: 'Año anterior (€)',
                    data: prevDataset,
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => value.toLocaleString('es-ES') + ' €' }
                }
            }
        }
    });

    const warehouseLabels = @json(array_map(fn($x) => $x->cod_almacen, $salesByWarehouse));
    const warehouseTotals = @json(array_map(fn($x) => $x->total, $salesByWarehouse));
    new Chart(document.getElementById('warehouseChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: warehouseLabels,
            datasets: [{
                data: warehouseTotals,
                backgroundColor: ['#206393', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });

    // Normalizar datos de familias a números para evitar NaN
    const familyDataRaw = (@json($salesByFamily)).map(item => ({
        ...item,
        total: Number(item.total) || 0
    }));
    const familyLabels = familyDataRaw.map(x => x.family_name || ('Fam. ' + x.cod_familia));
    const familyTotals = familyDataRaw.map(x => x.total);
    const familyColors = ['#206393', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1'];
    const familyGrandTotal = familyTotals.reduce((a, b) => a + b, 0);

    new Chart(document.getElementById('familyChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: familyLabels,
            datasets: [{
                label: 'Ventas (€)',
                data: familyTotals,
                backgroundColor: familyLabels.map((_, i) => familyColors[i % familyColors.length]),
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.raw) || 0;
                            const total = Number(context.dataset.data.reduce((a, b) => a + b, 0)) || 0;
                            const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            return context.label + ': ' + value.toLocaleString('es-ES') + ' € (' + pct + '%)';
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true },
                y: { ticks: { callback: function(value) { const label = this.getLabelForValue(value); return label.length > 18 ? label.substr(0, 18) + '...' : label; } } }
            }
        }
    });

    // Leyenda personalizada con colores, importe y porcentaje
    const familyLegend = document.getElementById('familyLegend');
    let familyLegendHtml = '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">';
    familyDataRaw.forEach((item, i) => {
        const pct = familyGrandTotal > 0 ? ((item.total / familyGrandTotal) * 100).toFixed(1) : '0.0';
        const color = familyColors[i % familyColors.length];
        const label = item.family_name || ('Fam. ' + item.cod_familia);
        familyLegendHtml += `
            <div class="flex items-center gap-2 p-2 rounded-lg hover:bg-[#f8f9fc]">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: ${color}"></span>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-[#191c1e] truncate" title="${label}">${label}</div>
                    <div class="text-xs text-[#747878]">${item.total.toLocaleString('es-ES')} € · ${pct}%</div>
                </div>
            </div>
        `;
    });
    familyLegendHtml += '</div>';
    if (familyLegend) familyLegend.innerHTML = familyLegendHtml;
</script>
@endpush
