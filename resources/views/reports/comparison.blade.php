@extends('layouts.app')

@section('title', 'Comparativa Histórica - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Comparativa Histórica ERP</h1>
            <p class="text-sm text-[#747878] mt-1">Consulta bajo demanda desde SQL Server. Sin importar datos locales.</p>
        </div>
    </div>

    <div class="glass-card rounded-xl p-5">
        <form method="GET" action="{{ route('reports.comparison') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Año 1</label>
                <select name="year1" class="border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    @for($y = 2012; $y <= 2020; $y++)
                        <option value="{{ $y }}" {{ $year1 == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Año 2</label>
                <select name="year2" class="border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    @for($y = 2012; $y <= 2020; $y++)
                        <option value="{{ $y }}" {{ $year2 == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <button type="submit" name="compare" value="1" class="px-4 py-2 bg-[#206393] text-white rounded-lg text-sm font-semibold hover:bg-[#1a4f78] transition-colors">
                Comparar
            </button>
        </form>
    </div>

    @if($results)
        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach([$year1, $year2] as $year)
                <div class="glass-card rounded-xl p-5">
                    <h2 class="text-xl font-semibold text-[#191c1e] mb-4">{{ $year }}</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs font-semibold text-[#747878] uppercase">Ventas Totales</div>
                            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($results['kpis'][$year]->total_sales ?? 0, 0, ',', '.') }} €</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-[#747878] uppercase">Operaciones</div>
                            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($results['kpis'][$year]->total_orders ?? 0, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-[#747878] uppercase">Ticket Medio</div>
                            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($results['kpis'][$year]->avg_ticket ?? 0, 2, ',', '.') }} €</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-[#747878] uppercase">Clientes Únicos</div>
                            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($results['kpis'][$year]->unique_clients ?? 0, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Gráfico mensual -->
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Evolución Mensual</h2>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>

        <!-- Top Clientes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach([$year1, $year2] as $year)
                <div class="glass-card rounded-xl p-5">
                    <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Clientes {{ $year }}</h2>
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
                                @foreach($results['topClients'][$year] as $client)
                                    <tr class="border-b border-[#f2f3f7]">
                                        <td class="py-2 px-3 font-medium">{{ $client->razon_social ?? 'N/A' }}</td>
                                        <td class="py-2 px-3 text-right text-[#206393] font-semibold">{{ number_format($client->total_spent, 2, ',', '.') }} €</td>
                                        <td class="py-2 px-3 text-right">{{ number_format($client->order_count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Top Productos -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach([$year1, $year2] as $year)
                <div class="glass-card rounded-xl p-5">
                    <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Productos {{ $year }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#e1e2e6]">
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Código</th>
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Descripción</th>
                                    <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['topProducts'][$year] as $product)
                                    <tr class="border-b border-[#f2f3f7]">
                                        <td class="py-2 px-3 font-mono text-[#206393]">{{ $product->cod_articulo }}</td>
                                        <td class="py-2 px-3">{{ $product->descripcion }}</td>
                                        <td class="py-2 px-3 text-right font-semibold text-[#191c1e]">{{ number_format($product->total_revenue, 2, ',', '.') }} €</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Top Familias -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach([$year1, $year2] as $year)
                <div class="glass-card rounded-xl p-5">
                    <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Familias {{ $year }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#e1e2e6]">
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                                    <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['byFamily'][$year] as $family)
                                    <tr class="border-b border-[#f2f3f7]">
                                        <td class="py-2 px-3 font-medium">{{ $family->familia }}</td>
                                        <td class="py-2 px-3 text-right font-semibold text-[#191c1e]">{{ number_format($family->total_revenue, 2, ',', '.') }} €</td>
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
            <span class="material-symbols-outlined text-4xl mb-2">query_stats</span>
            <p>Selecciona dos años y pulsa <strong>Comparar</strong> para consultar el ERP en tiempo real.</p>
            <p class="text-xs mt-1">Los datos se leen directamente de SQL Server sin almacenarse localmente.</p>
        </div>
    @endif
@endsection

@push('scripts')
@if($results)
<script>
    const ctx = document.getElementById('comparisonChart').getContext('2d');
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

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '{{ $year1 }}',
                    data: dataYear1,
                    borderColor: '#747878',
                    backgroundColor: 'rgba(116, 120, 120, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#747878',
                },
                {
                    label: '{{ $year2 }}',
                    data: dataYear2,
                    borderColor: '#206393',
                    backgroundColor: 'rgba(32, 99, 147, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#206393',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true }
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
@endif
@endpush
