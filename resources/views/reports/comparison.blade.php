@extends('layouts.app')

@section('title', 'Comparación Avanzada - Ribera Estadísticas')

@section('content')
    @php
        $firstYear = reset($selectedYears);
        $lastYear = end($selectedYears);
        $nextYear = $lastYear + 1;
    @endphp

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Comparación Avanzada</h1>
            <p class="text-sm text-[#747878] mt-1">Análisis de tendencias y comparativa histórica</p>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <form method="GET" action="{{ route('reports.comparison') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Año Inicio</label>
                <select name="year_from" class="w-full border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    @for($y = $minYear; $y <= $maxYear; $y++)
                        <option value="{{ $y }}" {{ $yearFrom == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Año Fin</label>
                <select name="year_to" class="w-full border border-[#e1e2e6] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-[#206393]">
                    @for($y = $minYear; $y <= $maxYear; $y++)
                        <option value="{{ $y }}" {{ $yearTo == $y ? 'selected' : '' }}>{{ $y }}</option>
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
                <div class="text-xs font-semibold text-[#747878] uppercase">Ventas {{ $lastYear }}</div>
                <div class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($results['kpis'][$lastYear]->total_sales ?? 0, 0, ',', '.') }} €</div>
                <div class="text-xs text-[#747878] mt-1">{{ number_format($results['kpis'][$lastYear]->total_orders ?? 0, 0) }} ops</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-{{ ($results['growth'][$lastYear]['sales_growth'] ?? 0) >= 0 ? 'green' : 'red' }}-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Δ Ventas {{ $lastYear }}</div>
                <div class="text-xl font-bold mt-1 {{ ($results['growth'][$lastYear]['sales_growth'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ ($results['growth'][$lastYear]['sales_growth'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($results['growth'][$lastYear]['sales_growth'] ?? 0, 1) }}%
                </div>
                <div class="text-xs text-[#747878] mt-1">vs {{ $lastYear - 1 }}</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-purple-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">CAGR {{ $firstYear }}-{{ $lastYear }}</div>
                <div class="text-xl font-bold mt-1 text-purple-600">{{ number_format($results['financialMetrics']['cagr'] ?? 0, 1) }}%</div>
                <div class="text-xs text-[#747878] mt-1">Crecimiento anual compuesto</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-orange-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Volatilidad</div>
                <div class="text-xl font-bold mt-1 text-orange-600">{{ number_format($results['financialMetrics']['volatility'] ?? 0, 1) }}%</div>
                <div class="text-xs text-[#747878] mt-1">Coef. de variación</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-teal-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Proyección {{ $nextYear }}</div>
                <div class="text-xl font-bold mt-1 text-teal-600">{{ number_format($results['financialMetrics']['forecast'][$nextYear] ?? 0, 0, ',', '.') }} €</div>
                <div class="text-xs text-[#747878] mt-1">Tendencia lineal</div>
            </div>
            <div class="glass-card rounded-xl p-4 border-l-4 border-indigo-500">
                <div class="text-xs font-semibold text-[#747878] uppercase">Concentración</div>
                <div class="text-xl font-bold mt-1 text-indigo-600">{{ number_format($results['financialMetrics']['concentration_top10'][$lastYear] ?? 0, 1) }}%</div>
                <div class="text-xs text-[#747878] mt-1">Top 10 productos {{ $lastYear }}</div>
            </div>
        </div>

        <!-- Gráfico de Evolución Anual + Tendencia -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Evolución Anual y Tendencia</h2>
                <span class="text-xs text-[#747878]">R² = {{ number_format($results['financialMetrics']['trend']['r2'] ?? 0, 3) }}</span>
            </div>
            <div style="position: relative; height: 350px;">
                <canvas id="yearlyTrendChart"></canvas>
            </div>
        </div>

        <!-- Gráficos de Evolución Mensual y Estacionalidad -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="glass-card rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-[#191c1e]">Evolución Mensual</h2>
                    <span class="text-xs text-[#747878]">Comparativa por año</span>
                </div>
                <div style="position: relative; height: 300px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
            <div class="glass-card rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-[#191c1e]">Índice de Estacionalidad</h2>
                    <span class="text-xs text-[#747878]">Promedio histórico (100 = media)</span>
                </div>
                <div style="position: relative; height: 300px;">
                    <canvas id="seasonalityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico por Familias -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Familias: comparativa {{ $firstYear }}-{{ $lastYear }}</h2>
                <div class="flex items-center gap-3 text-xs">
                    <span class="text-[#747878]">· Top 15 por facturación {{ $lastYear }}</span>
                </div>
            </div>
            <div style="position: relative; height: 450px;">
                <canvas id="familyChart"></canvas>
            </div>
        </div>

        <!-- Tabla Detallada de Familias -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-[#191c1e]">Análisis de Familias</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-[#e1e2e6]">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                            @foreach($selectedYears as $year)
                                <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">{{ $year }}</th>
                            @endforeach
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Δ Total</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">CAGR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results['byFamily'] ?? [] as $family)
                            <tr class="border-b border-[#f2f3f7] hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium">{{ Str::limit($family['familia'], 40) }}</td>
                                @foreach($selectedYears as $year)
                                    <td class="py-3 px-4 text-right text-[#747878]">
                                        {{ number_format($family['year_revenues'][$year] ?? 0, 0, ',', '.') }} €
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-right {{ ($family['growth'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                    {{ ($family['growth'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($family['growth'] ?? 0, 1) }}%
                                </td>
                                <td class="py-3 px-4 text-right text-[#206393] font-semibold">
                                    {{ number_format($family['cagr'] ?? 0, 1) }}%
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
                <div>
                    <h2 class="text-xl font-semibold text-[#191c1e]">Comparativa Top 10 Productos</h2>
                    <p class="text-xs text-[#747878] mt-1">Clica el gráfico o el botón para ver el detalle completo de cada producto.</p>
                </div>
                <button type="button" onclick="openProductModal()" class="px-3 py-1.5 bg-[#206393] text-white text-xs font-semibold rounded-lg hover:bg-[#1a4f78] transition-colors">
                    Ver listado
                </button>
            </div>
            <div style="position: relative; height: 350px;">
                <canvas id="productChart"></canvas>
            </div>
        </div>

        <!-- Modal Top 10 Productos -->
        <div id="productModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-[#191c1e] bg-opacity-60 transition-opacity" onclick="closeProductModal()"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-5xl bg-white rounded-xl shadow-2xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold text-[#191c1e]" id="modal-title">Top 10 Productos comparados</h3>
                            <button type="button" onclick="closeProductModal()" class="text-[#747878] hover:text-[#191c1e] transition-colors">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b-2 border-[#e1e2e6]">
                                        <th class="text-left py-3 px-3 text-xs font-semibold text-[#747878] uppercase"># / Código</th>
                                        <th class="text-left py-3 px-3 text-xs font-semibold text-[#747878] uppercase">Descripción</th>
                                        <th class="text-left py-3 px-3 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                                        @foreach($selectedYears as $year)
                                            <th class="text-right py-3 px-3 text-xs font-semibold text-[#747878] uppercase">{{ $year }}</th>
                                        @endforeach
                                        <th class="text-right py-3 px-3 text-xs font-semibold text-[#747878] uppercase">Δ Total</th>
                                    </tr>
                                </thead>
                                <tbody id="productModalBody">
                                    <!-- Se rellena con JS -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-5 text-right">
                            <button type="button" onclick="closeProductModal()" class="px-4 py-2 bg-[#747878] text-white text-sm font-semibold rounded-lg hover:bg-[#5a5d5f] transition-colors">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
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
                            @foreach($selectedYears as $year)
                                <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">{{ $year }}</th>
                            @endforeach
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Δ</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ticket {{ $lastYear }}</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Ops {{ $lastYear }}</th>
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
                                @foreach($selectedYears as $year)
                                    <td class="py-3 px-4 text-right {{ $year == $lastYear ? 'font-semibold text-[#206393]' : 'text-[#747878]' }}">
                                        {{ number_format($product->year_revenues[$year] ?? 0, 0, ',', '.') }} €
                                        <div class="text-xs">{{ number_format($product->year_qtys[$year] ?? 0, 0) }} uds</div>
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-right {{ $product->growth >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                    {{ $product->growth >= 0 ? '+' : '' }}{{ number_format($product->growth, 1) }}%
                                </td>
                                <td class="py-3 px-4 text-right text-[#747878]">
                                    {{ number_format($product->last_avg_ticket ?? 0, 2, ',', '.') }} €
                                </td>
                                <td class="py-3 px-4 text-right text-[#747878]">
                                    {{ number_format($product->last_orders ?? 0, 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Movers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="glass-card rounded-xl p-5">
                <h3 class="text-lg font-semibold text-[#191c1e] mb-3">Top Crecimiento Productos {{ $firstYear }}-{{ $lastYear }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#e1e2e6]">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">CAGR</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Crecimiento €</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($results['financialMetrics']['top_movers_products']['growth'] ?? []) as $product)
                                <tr class="border-b border-[#f2f3f7]">
                                    <td class="py-2 px-3 font-medium">{{ Str::limit($product['descripcion'] ?? 'N/A', 35) }}</td>
                                    <td class="py-2 px-3 text-right text-green-600 font-semibold">+{{ number_format($product['cagr'] ?? 0, 1) }}%</td>
                                    <td class="py-2 px-3 text-right text-[#206393]">+{{ number_format($product['absolute_growth'] ?? 0, 0, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="glass-card rounded-xl p-5">
                <h3 class="text-lg font-semibold text-[#191c1e] mb-3">Top Caída Productos {{ $firstYear }}-{{ $lastYear }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#e1e2e6]">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">CAGR</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Caída €</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($results['financialMetrics']['top_movers_products']['decline'] ?? []) as $product)
                                <tr class="border-b border-[#f2f3f7]">
                                    <td class="py-2 px-3 font-medium">{{ Str::limit($product['descripcion'] ?? 'N/A', 35) }}</td>
                                    <td class="py-2 px-3 text-right text-red-600 font-semibold">{{ number_format($product['cagr'] ?? 0, 1) }}%</td>
                                    <td class="py-2 px-3 text-right text-red-600">{{ number_format($product['absolute_growth'] ?? 0, 0, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Clientes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            @foreach($selectedYears as $year)
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
            <p class="text-xs mt-1">Análisis financiero comparativo entre varios períodos</p>
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
const years = @json($selectedYears);
const lastYear = years[years.length - 1];
const nextYear = lastYear + 1;

const currencyFmt = (value) => value.toLocaleString('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

// --- Gráfico de Evolución Anual + Tendencia ---
const salesByYear = @json($results['financialMetrics']['total_sales_by_year']);
const trendLine = @json($results['financialMetrics']['trend']['trend_line']);
const forecast = @json($results['financialMetrics']['forecast']);

const yearlyLabels = [...years, nextYear];
const yearlyActuals = years.map(y => parseFloat(salesByYear[y] || 0));
const yearlyTrend = years.map(y => parseFloat(trendLine[y] || 0));
const yearlyForecast = [...Array(years.length).fill(null), parseFloat(forecast[nextYear] || 0)];

new Chart(document.getElementById('yearlyTrendChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: yearlyLabels,
        datasets: [
            {
                label: 'Ventas reales',
                data: [...yearlyActuals, null],
                backgroundColor: 'rgba(32, 99, 147, 0.7)',
                borderColor: '#206393',
                borderWidth: 1,
                order: 2
            },
            {
                type: 'line',
                label: 'Tendencia',
                data: yearlyTrend,
                borderColor: '#747878',
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0,
                order: 1
            },
            {
                type: 'line',
                label: `Proyección ${nextYear}`,
                data: yearlyForecast,
                borderColor: '#14b8a6',
                backgroundColor: 'rgba(20, 184, 166, 0.2)',
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 5,
                order: 0
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': ' + currencyFmt(ctx.raw ?? 0)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => (v / 1000) + 'k €' }
            },
            x: { grid: { display: false } }
        }
    }
});

// --- Gráfico de Evolución Mensual ---
const monthlyData = @json($results['monthly']);
const monthLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const monthlyDatasets = years.map((year, idx) => {
    const data = new Array(12).fill(0);
    monthlyData.filter(m => m.year == year).forEach(m => {
        data[m.month - 1] = parseFloat(m.total);
    });

    const colors = [
        'rgba(116, 120, 120, 0.7)',
        'rgba(32, 99, 147, 0.7)',
        'rgba(20, 184, 166, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(139, 92, 246, 0.7)',
        'rgba(236, 72, 153, 0.7)'
    ];
    const color = colors[idx % colors.length];

    return {
        label: year,
        data: data,
        borderColor: color.replace('0.7', '1'),
        backgroundColor: color,
        borderWidth: 2,
        tension: 0.3,
        fill: false
    };
});

new Chart(document.getElementById('monthlyChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: monthlyDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': ' + currencyFmt(ctx.raw)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => (v / 1000) + 'k €' }
            },
            x: { grid: { display: false } }
        }
    }
});

// --- Gráfico de Estacionalidad ---
const seasonality = @json($results['financialMetrics']['seasonality']);
const seasonalityData = Array.from({length: 12}, (_, i) => parseFloat(seasonality[i + 1] || 100));

new Chart(document.getElementById('seasonalityChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Índice estacional',
            data: seasonalityData,
            backgroundColor: seasonalityData.map(v => v >= 100 ? 'rgba(32, 99, 147, 0.7)' : 'rgba(116, 120, 120, 0.5)'),
            borderColor: '#206393',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => `Índice: ${ctx.raw.toFixed(1)} (media = 100)`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: false,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => v.toFixed(0) }
            },
            x: { grid: { display: false } }
        }
    }
});

// --- Gráfico de Familias ---
const familyData = @json($results['byFamily'] ?? []);
const familyDatasets = years.map((year, idx) => {
    const colors = [
        'rgba(116, 120, 120, 0.35)',
        'rgba(32, 99, 147, 0.7)',
        'rgba(20, 184, 166, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(139, 92, 246, 0.7)',
        'rgba(236, 72, 153, 0.7)'
    ];
    return {
        label: year,
        data: familyData.map(f => f.year_revenues[year] || 0),
        backgroundColor: colors[idx % colors.length],
        borderColor: colors[idx % colors.length].replace(/0\.[0-9]+/, '1'),
        borderWidth: 1,
        borderRadius: 3
    };
});

new Chart(document.getElementById('familyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: familyData.map(f => (f.familia || 'N/A').substring(0, 25)),
        datasets: familyDatasets
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': ' + currencyFmt(ctx.raw)
                }
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => (v / 1000) + 'k €' }
            },
            y: { grid: { display: false } }
        }
    }
});

// --- Gráfico de Productos ---
const productData = @json($results['topProductsCombined'] ?? []);
const top10Products = productData.slice(0, 10);
const productLabels = top10Products.map(p => {
    const desc = (p.descripcion || 'N/A').trim();
    return desc.length > 22 ? desc.substring(0, 22) + '…' : desc;
});

const productDatasets = years.map((year, idx) => {
    const colors = [
        'rgba(116, 120, 120, 0.7)',
        'rgba(32, 99, 147, 0.7)',
        'rgba(20, 184, 166, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(139, 92, 246, 0.7)',
        'rgba(236, 72, 153, 0.7)'
    ];
    return {
        label: year,
        data: top10Products.map(p => parseFloat(p.year_revenues[year] || 0)),
        backgroundColor: colors[idx % colors.length],
        borderColor: colors[idx % colors.length].replace(/0\.[0-9]+/, '1'),
        borderWidth: 1
    };
});

const productChart = new Chart(document.getElementById('productChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: productLabels,
        datasets: productDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': ' + currencyFmt(ctx.raw),
                    title: (ctx) => {
                        const idx = ctx[0].dataIndex;
                        return top10Products[idx].cod_articulo + ' - ' + (top10Products[idx].descripcion || 'N/A').substring(0, 60);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => (v / 1000) + 'k €' }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 30,
                    font: { size: 10 }
                },
                grid: { display: false }
            }
        },
        onClick: (e) => {
            const points = productChart.getElementsAtEventForMode(e, 'index', { intersect: false }, true);
            if (points.length) {
                openProductModal();
            }
        }
    }
});

// --- Modal Top 10 Productos ---
function openProductModal() {
    const tbody = document.getElementById('productModalBody');
    tbody.innerHTML = top10Products.map((p, idx) => {
        const yearCells = years.map(year => {
            const rev = parseFloat(p.year_revenues[year] || 0);
            const badge = rev > 0
                ? ''
                : '<span class="text-[10px] text-[#747878]">(—)</span>';
            return `<td class="py-3 px-3 text-right text-[#747878]">${currencyFmt(rev)} ${badge}</td>`;
        }).join('');

        const growthClass = p.growth >= 0 ? 'text-green-600' : 'text-red-600';
        const growthSign = p.growth >= 0 ? '+' : '';

        return `
            <tr class="border-b border-[#f2f3f7] hover:bg-gray-50">
                <td class="py-3 px-3">
                    <div class="font-semibold text-[#191c1e]">#${idx + 1}</div>
                    <div class="text-xs text-[#747878]">${p.cod_articulo}</div>
                </td>
                <td class="py-3 px-3 font-medium">${p.descripcion || 'N/A'}</td>
                <td class="py-3 px-3 text-xs text-[#747878]">${p.familia || 'N/A'}</td>
                ${yearCells}
                <td class="py-3 px-3 text-right ${growthClass} font-semibold">${growthSign}${(p.growth || 0).toFixed(1)}%</td>
            </tr>
        `;
    }).join('');

    document.getElementById('productModal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}

// Cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeProductModal();
});
</script>
@endif
@endpush
