@extends('layouts.app')

@section('title', 'Familias - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Familias</h1>
            <p class="text-sm text-[#747878] mt-1">Exploración por familias y subfamilias de producto.</p>
        </div>
        <div class="flex gap-2">
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar familia..."
                       class="px-4 py-2 border border-[#e1e2e6] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
                <select name="sort" class="px-4 py-2 border border-[#e1e2e6] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="revenue" {{ $sortBy === 'revenue' ? 'selected' : '' }}>Ordenar por facturación</option>
                    <option value="products" {{ $sortBy === 'products' ? 'selected' : '' }}>Ordenar por productos</option>
                    <option value="stock" {{ $sortBy === 'stock' ? 'selected' : '' }}>Ordenar por stock</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-[#206393] text-white rounded-lg text-sm font-medium hover:bg-[#1a507a] transition-colors">
                    Filtrar
                </button>
            </form>
        </div>
    </div>

    <!-- Tarjetas de totales -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Facturación</div>
            <div class="text-3xl font-bold text-[#206393]">{{ number_format($totalRevenue, 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Acumulado histórico</div>
        </div>
    </div>

    <!-- Top 10 Familias - Gráfico -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top 10 Familias por Facturación</h2>
        <div class="h-64">
            <canvas id="topFamiliesChart"></canvas>
        </div>
    </div>

    <!-- Debug: eliminar después -->
    {{--
    <div class="bg-red-100 p-4 rounded mb-6">
        <code>TopFamilies es: {{ gettype($topFamilies) }}</code><br>
        <code>Count: {{ is_array($topFamilies) ? count($topFamilies) : $topFamilies->count() }}</code>
    </div>
    --}}

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
                <div class="grid grid-cols-2 gap-4">
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
                        <div class="text-xs text-[#747878]">Facturación</div>
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
        labels: {!! json_encode($topFamilies instanceof \Illuminate\Support\Collection ? $topFamilies->pluck('descripcion')->map(fn($d) => Str::limit($d ?: 'N/A', 20))->toArray() : array_map(fn($f) => Str::limit($f->descripcion ?: 'N/A', 20), $topFamilies)) !!},
        datasets: [{
            label: 'Facturación (€)',
            data: {!! json_encode($topFamilies instanceof \Illuminate\Support\Collection ? $topFamilies->pluck('total_revenue')->toArray() : array_column($topFamilies, 'total_revenue')) !!},
            backgroundColor: [
                'rgba(32, 99, 147, 0.8)',
                'rgba(32, 99, 147, 0.7)',
                'rgba(32, 99, 147, 0.6)',
                'rgba(32, 99, 147, 0.5)',
                'rgba(32, 99, 147, 0.45)',
                'rgba(32, 99, 147, 0.4)',
                'rgba(32, 99, 147, 0.35)',
                'rgba(32, 99, 147, 0.3)',
                'rgba(32, 99, 147, 0.25)',
                'rgba(32, 99, 147, 0.2)'
            ],
            borderColor: 'rgba(32, 99, 147, 1)',
            borderWidth: 1
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
                            return '€' + context.raw.toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
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
                    }
                }
            }
        }
    };
    new Chart(ctx, config);
</script>
@endpush
