@extends('layouts.app')

@section('title', 'Comparación Avanzada - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Comparación Avanzada</h1>
            <p class="text-sm text-[#747878] mt-1">Análisis financiero interactivo con métricas de gestión</p>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <form method="GET" action="{{ route('reports.comparison') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Año Inicio</label>
                <select name="year1" class="w-full border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    @for($y = $minYear; $y <= $maxYear; $y++)
                        <option value="{{ $y }}" {{ $year1 == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Año Fin</label>
                <select name="year2" class="w-full border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    @for($y = $minYear; $y <= $maxYear; $y++)
                        <option value="{{ $y }}" {{ $year2 == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Familia</label>
                <select name="family" class="w-full border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    <option value="">Todas</option>
                    @foreach($allFamilies as $family)
                        <option value="{{ $family->cod_familia }}" {{ $selectedFamily == $family->cod_familia ? 'selected' : '' }}>
                            {{ Str::limit($family->descripcion ?: $family->cod_familia, 40) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Subfamilia</label>
                <select name="subfamily" id="subfamilySelect" class="w-full border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]" {{ !$selectedFamily ? 'disabled' : '' }}>
                    <option value="">Todas</option>
                    @if($selectedFamily && isset($subfamilies))
                        @foreach($subfamilies as $sub)
                            <option value="{{ $sub->cod_subfamilia }}" {{ $selectedSubfamily == $sub->cod_subfamilia ? 'selected' : '' }}>
                                {{ Str::limit($sub->descripcion ?: $sub->cod_subfamilia, 40) }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" name="compare" value="1" class="w-full px-6 py-2 bg-[#206393] text-white rounded-lg text-sm font-semibold hover:bg-[#1a4f78] transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">filter_list</span>
                    Analizar
                </button>
            </div>
        </form>
    </div>

    @if($results)
        <!-- KPIs Financieros -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="glass-card rounded-xl p-4 border-l-4 border-[#206393]">
                <div class="text-xs font-semibold text-[#747878] uppercase">Ventas {{ $year1 }}</div>
                <div class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($results['kpis'][$year1]->total_sales ?? 0, 0, ',', '.') }} €</div>
                <div class="text-xs text-[#747878] mt-1">{{ number_format($results['kpis'][$year1]->total_orders ?? 0, 0) }} ops</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-[#206393]">
                <div class="text-xs font-semibold text-[#747878] uppercase">Ventas {{ $year2 }}</div>
                <div class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($results['kpis'][$year2]->total_sales ?? 0, 0, ',', '.') }} €</div>
                <div class="text-xs text-[#747878] mt-1">{{ number_format($results['kpis'][$year2]->total_orders ?? 0, 0) }} ops</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-{{ $results['growth']['sales_growth'] >= 0 ? 'green' : 'red' }}-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Δ Ventas</div>
                <div class="text-xl font-bold mt-1 {{ $results['growth']['sales_growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $results['growth']['sales_growth'] >= 0 ? '+' : '' }}{{ number_format($results['growth']['sales_growth'], 1) }}%
                </div>
                <div class="text-xs text-[#747878] mt-1">vs {{ $year1 }}</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-{{ $results['growth']['ticket_growth'] >= 0 ? 'green' : 'red' }}-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Δ Ticket</div>
                <div class="text-xl font-bold mt-1 {{ $results['growth']['ticket_growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $results['growth']['ticket_growth'] >= 0 ? '+' : '' }}{{ number_format($results['growth']['ticket_growth'], 1) }}%
                </div>
                <div class="text-xs text-[#747878] mt-1">{{ number_format($results['kpis'][$year2]->avg_ticket ?? 0, 2) }} €</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-{{ $results['growth']['clients_growth'] >= 0 ? 'green' : 'red' }}-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Δ Clientes</div>
                <div class="text-xl font-bold mt-1 {{ $results['growth']['clients_growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $results['growth']['clients_growth'] >= 0 ? '+' : '' }}{{ number_format($results['growth']['clients_growth'], 1) }}%
                </div>
                <div class="text-xs text-[#747878] mt-1">{{ number_format($results['kpis'][$year2]->unique_clients ?? 0, 0) }} activos</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-purple-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Concentración</div>
                <div class="text-xl font-bold mt-1 text-purple-600">{{ number_format($results['financialMetrics']['concentration_top10'][$year2] ?? 0, 1) }}%</div>
                <div class="text-xs text-[#747878] mt-1">Top 10 productos</div>
            </div>
        </div>

        <!-- Gráfico de Evolución Mensual -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Evolución de Ventas</h2>
                <div class="flex gap-2">
                    <button onclick="setChartType('bar')" class="px-3 py-1 text-xs border rounded hover:bg-gray-50">Barras</button>
                    <button onclick="setChartType('line')" class="px-3 py-1 text-xs border rounded hover:bg-gray-50">Líneas</button>
                </div>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Gráfico por Familias -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Ranking por Familias</h2>
                <span class="text-xs text-[#747878]">Top 15 - Ordenado por facturación</span>
            </div>
            <div style="position: relative; height: 400px;">
                <canvas id="familyChart"></canvas>
            </div>
        </div>

        <!-- Tabla Detallada de Productos -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Análisis de Productos</h2>
                <div class="flex gap-2 text-xs">
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded">Crecimiento positivo</span>
                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded">Crecimiento negativo</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-[#e1e2e6]">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">#</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">{{ $year1 }}</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">{{ $year2 }}</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Δ</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ticket {{ $year2 }}</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ops {{ $year2 }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results['topProductsCombined'] ?? [] as $index => $product)
                            <tr class="border-b border-[#f2f3f7] hover:bg-gray-50">
                                <td class="py-3 px-4 text-[#747878]">{{ $index + 1 }}</td>
                                <td class="py-3 px-4 font-medium">
                                    <div class="truncate max-w-xs" title="{{ $product->descripcion }}">
                                        {{ Str::limit($product->descripcion, 40) }}
                                    </div>
                                    <div class="text-xs text-[#747878]">{{ $product->cod_articulo }}</div>
                                </td>
                                <td class="py-3 px-4 text-xs text-[#747878]">{{ $product->familia ?? 'N/A' }}</td>
                                <td class="py-3 px-4 text-right text-[#747878]">
                                    {{ number_format($product->year1_revenue ?? 0, 0, ',', '.') }} €
                                    <div class="text-xs">{{ number_format($product->year1_qty ?? 0, 0) }} uds</div>
                                </td>
                                <td class="py-3 px-4 text-right font-semibold text-[#206393]">
                                    {{ number_format($product->year2_revenue ?? 0, 0, ',', '.') }} €
                                    <div class="text-xs">{{ number_format($product->year2_qty ?? 0, 0) }} uds</div>
                                </td>
                                <td class="py-3 px-4 text-right {{ $product->growth >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                    {{ $product->growth >= 0 ? '+' : '' }}{{ number_format($product->growth, 1) }}%
                                </td>
                                <td class="py-3 px-4 text-right text-[#747878]">
                                    {{ number_format($product->year2_avg_ticket ?? 0, 2, ',', '.') }} €
                                </td>
                                <td class="py-3 px-4 text-right text-[#747878]">
                                    {{ number_format($product->year2_orders ?? 0, 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gráfico Comparativo Top 10 Productos -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Comparativa Top 10 Productos</h2>
                <span class="text-xs text-[#747878]">Facturación por año</span>
            </div>
            <div style="position: relative; height: 350px;">
                <canvas id="productChart"></canvas>
            </div>
        </div>

        <!-- Top Clientes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            @foreach([$year1, $year2] as $year)
                <div class="glass-card rounded-xl p-5">
                    <h3 class="text-lg font-semibold text-[#191c1e] mb-3">Top 5 Clientes {{ $year }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#e1e2e6]">
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                                    <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Ventas</th>
                                    <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Ops</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($results['topClients'][$year] ?? [], 0, 5) as $client)
                                    <tr class="border-b border-[#f2f3f7]">
                                        <td class="py-2 px-3 font-medium">{{ Str::limit($client->razon_social ?? 'N/A', 25) }}</td>
                                        <td class="py-2 px-3 text-right text-[#206393] font-semibold">{{ number_format($client->total_spent ?? 0, 0, ',', '.') }} €</td>
                                        <td class="py-2 px-3 text-right">{{ number_format($client->order_count ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="glass-card rounded-xl p-8 text-center text-[#747878]">
            <span class="material-symbols-outlined text-4xl mb-2">insights</span>
            <p class="text-lg font-medium">Selecciona los filtros y pulsa <strong>Analizar</strong></p>
            <p class="text-xs mt-1">Análisis financiero comparativo entre dos períodos</p>
        </div>
    @endif
@endsection

@push('scripts')
<script>
// Carga dinámica de subfamilias al seleccionar familia
document.addEventListener('DOMContentLoaded', function() {
    const familySelect = document.querySelector('select[name="family"]');
    const subfamilySelect = document.getElementById('subfamilySelect');

    if (familySelect && subfamilySelect) {
        familySelect.addEventListener('change', function() {
            const familyCode = this.value;

            if (!familyCode) {
                subfamilySelect.innerHTML = '<option value="">Todas</option>';
                subfamilySelect.disabled = true;
                return;
            }

            fetch(`{{ route('api.subfamilies') }}?family=${familyCode}`)
                .then(r => r.json())
                .then(data => {
                    subfamilySelect.innerHTML = '<option value="">Todas</option>' +
                        data.map(s => `<option value="${s.cod_subfamilia}">${s.descripcion?.substring(0, 40) || s.cod_subfamilia}</option>`).join('');
                    subfamilySelect.disabled = false;
                });
        });
    }
});
</script>
@if($results)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartType = 'bar';

function setChartType(type) {
    chartType = type;
    salesChart.config.type = type;
    salesChart.update();
}

// Datos para gráfico de evolución mensual
const monthlyData = @json($results['monthly']);
const labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const dataYear1 = new Array(12).fill(0);
const dataYear2 = new Array(12).fill(0);

monthlyData.forEach(item => {
    const monthIndex = item.month - 1;
    if (item.year == {{ $year1 }}) {
        dataYear1[monthIndex] = parseFloat(item.total);
    } else if (item.year == {{ $year2 }}) {
        dataYear2[monthIndex] = parseFloat(item.total);
    }
});

// Gráfico de Ventas
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: '{{ $year1 }}',
                data: dataYear1,
                backgroundColor: 'rgba(116, 120, 120, 0.7)',
                borderColor: '#747878',
                borderWidth: 1
            },
            {
                label: '{{ $year2 }}',
                data: dataYear2,
                backgroundColor: 'rgba(32, 99, 147, 0.7)',
                borderColor: '#206393',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'k €';
                    }
                }
            },
            x: { grid: { display: false } }
        }
    }
});

// Gráfico de Familias
const familyCtx = document.getElementById('familyChart').getContext('2d');
const familyData = @json($results['byFamily'][$year2] ?? []);

new Chart(familyCtx, {
    type: 'bar',
    data: {
        labels: familyData.map(f => (f.familia || 'N/A').substring(0, 25)),
        datasets: [{
            label: 'Facturación {{ $year2 }}',
            data: familyData.map(f => parseFloat(f.total_revenue)),
            backgroundColor: familyData.map((_, i) => {
                const opacity = 1 - (i / Math.max(familyData.length, 1)) * 0.6;
                return `rgba(32, 99, 147, ${opacity})`;
            }),
            borderColor: '#206393',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'k €';
                    }
                }
            },
            y: { grid: { display: false } }
        }
    }
});

// Gráfico de Productos
const productCtx = document.getElementById('productChart').getContext('2d');
const productData = @json($results['topProductsCombined'] ?? []);
const top10Products = productData.slice(0, 10);

new Chart(productCtx, {
    type: 'bar',
    data: {
        labels: top10Products.map(p => p.cod_articulo),
        datasets: [
            {
                label: '{{ $year1 }}',
                data: top10Products.map(p => parseFloat(p.year1_revenue || 0)),
                backgroundColor: 'rgba(116, 120, 120, 0.7)',
                borderColor: '#747878',
                borderWidth: 1
            },
            {
                label: '{{ $year2 }}',
                data: top10Products.map(p => parseFloat(p.year2_revenue || 0)),
                backgroundColor: 'rgba(32, 99, 147, 0.7)',
                borderColor: '#206393',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
                    },
                    title: function(context) {
                        const idx = context[0].dataIndex;
                        return top10Products[idx].descripcion?.substring(0, 50);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'k €';
                    }
                }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
@endif
@endpush
