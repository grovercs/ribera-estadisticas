@extends('layouts.app')

@section('title', 'Familias - Ribera Estadísticas')

@section('content')
    @php
        $periodLabel = $monthFrom == 1 && $monthTo == 12
            ? $yearFrom == $yearTo ? (string)$yearFrom : "$yearFrom - $yearTo"
            : ($yearFrom == $yearTo
                ? sprintf('%s %02d-%02d', $yearFrom, $monthFrom, $monthTo)
                : sprintf('%d-%02d → %d-%02d', $yearFrom, $monthFrom, $yearTo, $monthTo));

        $prevLabel = $monthFrom == 1 && $monthTo == 12
            ? ($yearFrom == $yearTo ? ($yearFrom - 1) : sprintf('%d - %d', $yearFrom - ($yearTo - $yearFrom) - 1, $yearTo - ($yearTo - $yearFrom) - 1))
            : ($yearFrom == $yearTo
                ? sprintf('%d %02d-%02d', $yearFrom - 1, $monthFrom, $monthTo)
                : sprintf('%d-%02d → %d-%02d', $yearFrom - ($yearTo - $yearFrom) - 1, $monthFrom, $yearTo - ($yearTo - $yearFrom) - 1, $monthTo));
    @endphp

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Familias</h1>
            <p class="text-sm text-[#747878] mt-1">Facturación por familia de producto desde el ERP en tiempo real.</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <form method="GET" action="{{ route('families') }}" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Buscar</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar familia..."
                       class="px-3 py-2 border border-[#e1e2e6] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Desde</label>
                <div class="flex gap-2">
                    <select name="year_from" class="px-3 py-2 border border-[#e1e2e6] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        @foreach($yearRange as $year)
                            <option value="{{ $year }}" {{ $yearFrom == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month_from" class="px-3 py-2 border border-[#e1e2e6] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $monthFrom == $m ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Hasta</label>
                <div class="flex gap-2">
                    <select name="year_to" class="px-3 py-2 border border-[#e1e2e6] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        @foreach($yearRange as $year)
                            <option value="{{ $year }}" {{ $yearTo == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month_to" class="px-3 py-2 border border-[#e1e2e6] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $monthTo == $m ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878] uppercase mb-1">Ordenar</label>
                <select name="sort" class="px-3 py-2 border border-[#e1e2e6] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="revenue" {{ $sortBy === 'revenue' ? 'selected' : '' }}>Por facturación</option>
                    <option value="growth" {{ $sortBy === 'growth' ? 'selected' : '' }}>Por crecimiento %</option>
                    <option value="products" {{ $sortBy === 'products' ? 'selected' : '' }}>Por productos</option>
                    <option value="stock" {{ $sortBy === 'stock' ? 'selected' : '' }}>Por stock</option>
                </select>
            </div>

            <button type="submit" class="px-5 py-2 bg-[#206393] text-white rounded-lg text-sm font-medium hover:bg-[#1a507a] transition-colors">
                Aplicar
            </button>

            @if($search || $yearFrom != date('Y') || $yearTo != date('Y') || $monthFrom != 1 || $monthTo != (int) date('m'))
                <a href="{{ route('families') }}" class="px-5 py-2 bg-[#747878] text-white rounded-lg text-sm font-medium hover:bg-[#5a5d5f] transition-colors">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Tarjetas de totales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Total Familias</div>
            <div class="text-3xl font-bold text-[#191c1e]">{{ count($metrics) }}</div>
            <div class="text-xs text-[#747878] mt-1">{{ $totalSubfamilies }} subfamilias</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Productos</div>
            <div class="text-3xl font-bold text-[#191c1e]">{{ number_format($totalProducts, 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Referencias activas</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Stock Total</div>
            <div class="text-3xl font-bold text-[#191c1e]">{{ number_format($totalStock, 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Unidades en almacén</div>
        </div>
        <div class="glass-card rounded-xl p-5 border-l-4 border-[#206393]">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Facturación {{ $periodLabel }}</div>
            <div class="text-3xl font-bold text-[#206393]">{{ number_format($totalRevenue, 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Datos ERP en tiempo real</div>
        </div>
        <div class="glass-card rounded-xl p-5 border-l-4 border-{{ $totalRevenueGrowth >= 0 ? 'green' : 'red' }}-500">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">vs {{ $prevLabel }}</div>
            <div class="text-3xl font-bold {{ $totalRevenueGrowth >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $totalRevenueGrowth >= 0 ? '+' : '' }}{{ number_format($totalRevenueGrowth, 1) }}%
            </div>
            <div class="text-xs text-[#747878] mt-1">
                {{ number_format($totalRevenuePrev, 0, ',', '.') }} € anterior
            </div>
        </div>
    </div>

    <!-- Top 10 Familias - Gráfico -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-[#191c1e]">Top 10 Familias por Facturación</h2>
            <span class="text-xs text-[#747878]">{{ $periodLabel }}</span>
        </div>
        <div class="h-80">
            <canvas id="topFamiliesChart"></canvas>
        </div>
    </div>

    <!-- Listado de familias -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($metrics as $family)
            <a href="{{ route('families.show', $family->cod_familia) }}" class="glass-card rounded-xl p-5 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider">{{ $family->cod_familia }}</div>
                        <h3 class="text-lg font-semibold text-[#191c1e] mt-1">{{ Str::limit($family->descripcion ?: 'Sin descripción', 50) }}</h3>
                    </div>
                    <span class="material-symbols-outlined text-[#206393]">chevron_right</span>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <div class="text-2xl font-bold text-[#191c1e]">{{ number_format($family->product_count, 0, ',', '.') }}</div>
                        <div class="text-xs text-[#747878]">Productos</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-[#191c1e]">{{ number_format($family->subfamily_count, 0, ',', '.') }}</div>
                        <div class="text-xs text-[#747878]">Subfamilias</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-[#191c1e]">{{ number_format($family->stock_total, 0, ',', '.') }}</div>
                        <div class="text-xs text-[#747878]">Stock</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-[#206393]">{{ number_format($family->total_revenue, 0, ',', '.') }} €</div>
                        <div class="text-xs text-[#747878]">Fact. {{ $periodLabel }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-[#e1e2e6]">
                    <div class="text-xs text-[#747878]">
                        Año ant.: {{ number_format($family->total_revenue_prev, 0, ',', '.') }} €
                    </div>
                    <div class="text-sm font-semibold {{ $family->growth >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $family->growth >= 0 ? '+' : '' }}{{ number_format($family->growth, 1) }}%
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('topFamiliesChart').getContext('2d');
    const data = {
        labels: {!! json_encode($topFamilies instanceof \Illuminate\Support\Collection ? $topFamilies->pluck('descripcion')->map(fn($d) => Str::limit($d ?: 'N/A', 25))->toArray() : array_map(fn($f) => Str::limit($f->descripcion ?: 'N/A', 25), $topFamilies)) !!},
        datasets: [{
            label: 'Facturación {{ $periodLabel }} (€)',
            data: {!! json_encode($topFamilies instanceof \Illuminate\Support\Collection ? $topFamilies->pluck('total_revenue')->toArray() : array_column($topFamilies, 'total_revenue')) !!},
            backgroundColor: [
                'rgba(32, 99, 147, 0.85)',
                'rgba(32, 99, 147, 0.75)',
                'rgba(32, 99, 147, 0.65)',
                'rgba(32, 99, 147, 0.55)',
                'rgba(32, 99, 147, 0.5)',
                'rgba(32, 99, 147, 0.45)',
                'rgba(32, 99, 147, 0.4)',
                'rgba(32, 99, 147, 0.35)',
                'rgba(32, 99, 147, 0.3)',
                'rgba(32, 99, 147, 0.25)'
            ],
            borderColor: 'rgba(32, 99, 147, 1)',
            borderWidth: 1,
            borderRadius: 4
        }]
    };
    const config = {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: function(value) {
                            return '€' + (value / 1000) + 'k';
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y: { grid: { display: false } }
            }
        }
    };
    new Chart(ctx, config);
</script>
@endpush
