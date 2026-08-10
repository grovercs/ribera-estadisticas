@extends('layouts.app')

@section('title', 'Stock - Ribera Estadísticas')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Análisis de Stock</h1>
            <p class="text-sm text-[#747878] mt-1">Maestro de artículos, existencias y rotación desde el ERP en tiempo real.</p>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
           class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Exportar CSV
        </a>
    </div>

    {{-- Alertas compactas / filtros rápidos --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <a href="{{ route('stock.index', array_merge(request()->query(), ['stock_filter' => $stockFilter === 'sin_stock' ? '' : 'sin_stock', 'page' => 1])) }}"
           class="flex items-center justify-between px-4 py-3 rounded-xl border {{ $stockFilter === 'sin_stock' ? 'bg-red-50 border-red-300' : 'bg-white border-[#e1e2e6]' }} shadow-sm hover:shadow transition">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-red-600">block</span>
                <div>
                    <p class="text-xs text-[#747878]">Sin stock</p>
                    <p class="text-lg font-bold text-[#191c1e]">{{ number_format($kpis['out_of_stock'], 0, ',', '.') }}</p>
                </div>
            </div>
            @if ($stockFilter === 'sin_stock')
                <span class="text-xs text-red-600 font-medium">Activo ✕</span>
            @endif
        </a>

        <a href="{{ route('stock.index', array_merge(request()->query(), ['stock_filter' => $stockFilter === 'bajo_minimo' ? '' : 'bajo_minimo', 'page' => 1])) }}"
           class="flex items-center justify-between px-4 py-3 rounded-xl border {{ $stockFilter === 'bajo_minimo' ? 'bg-orange-50 border-orange-300' : 'bg-white border-[#e1e2e6]' }} shadow-sm hover:shadow transition">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-orange-600">trending_down</span>
                <div>
                    <p class="text-xs text-[#747878]">Bajo mínimo</p>
                    <p class="text-lg font-bold text-[#191c1e]">{{ number_format($kpis['below_minimum'], 0, ',', '.') }}</p>
                </div>
            </div>
            @if ($stockFilter === 'bajo_minimo')
                <span class="text-xs text-orange-600 font-medium">Activo ✕</span>
            @endif
        </a>

        <div class="flex items-center gap-3 px-4 py-3 rounded-xl border bg-white border-[#e1e2e6] shadow-sm">
            <span class="material-symbols-outlined text-[#206393]">inventory_2</span>
            <div>
                <p class="text-xs text-[#747878]">Stock valorado</p>
                <p class="text-lg font-bold text-[#191c1e]">{{ number_format($kpis['stock_valued'], 0, ',', '.') }} €</p>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="glass-card rounded-xl p-5 mb-6">
        <form method="GET" action="{{ route('stock.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-[#747878]">Buscar</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Código, marca, descripción, EAN..." class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Familia</label>
                <select id="filterFamilia" name="cod_familia" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todas</option>
                    @foreach ($families as $f)
                        <option value="{{ $f->cod_familia }}" {{ $f->cod_familia == $codFamilia ? 'selected' : '' }}>{{ $f->descripcion }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Subfamilia</label>
                <select id="filterSubfamilia" name="cod_subfamilia" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todas</option>
                    {{-- Se rellena vía AJAX --}}
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Almacén</label>
                <select name="cod_almacen" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todos</option>
                    @foreach ($almacenes as $a)
                        <option value="{{ $a }}" {{ $a == $codAlmacen ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Estado stock</label>
                <select name="stock_filter" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todos</option>
                    <option value="con_stock" {{ $stockFilter === 'con_stock' ? 'selected' : '' }}>Con stock</option>
                    <option value="sin_stock" {{ $stockFilter === 'sin_stock' ? 'selected' : '' }}>Sin stock</option>
                    <option value="bajo_minimo" {{ $stockFilter === 'bajo_minimo' ? 'selected' : '' }}>Bajo mínimo</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Ventas últimos N meses</label>
                <select name="sales_months" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach ([3, 6, 12, 24, 36] as $m)
                        <option value="{{ $m }}" {{ $m == $salesMonths ? 'selected' : '' }}>{{ $m }} meses</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <a href="{{ route('stock.index') }}" class="px-4 py-2 text-sm text-[#747878] border border-[#e1e2e6] rounded-lg hover:bg-[#f8f9fc]">Limpiar</a>
                <button type="submit" class="px-4 py-2 text-sm bg-[#206393] text-white rounded-lg hover:bg-[#184b70]">Filtrar</button>
            </div>
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-[#747878] uppercase font-semibold">Artículos filtrados</p>
            <p class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_products'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-[#747878] uppercase font-semibold">Unidades en stock</p>
            <p class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_stock'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-[#747878] uppercase font-semibold">Unidades vendidas</p>
            <p class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_qty'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-[#747878] uppercase font-semibold">Facturación últimos {{ $salesMonths }}m</p>
            <p class="text-xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_revenue'], 0, ',', '.') }} €</p>
        </div>
    </div>

    {{-- Gráficos y resúmenes --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="glass-card rounded-xl p-5 lg:col-span-2">
            <h2 class="text-sm font-bold text-[#191c1e] mb-4">Top familias por facturación</h2>
            <canvas id="chartByFamily" height="140"></canvas>
        </div>
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-sm font-bold text-[#191c1e] mb-4">Stock por almacén</h2>
            <canvas id="chartByWarehouse" height="220"></canvas>
        </div>
    </div>

    {{-- Tabla de productos --}}
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="p-5 border-b border-[#e1e2e6] flex items-center justify-between">
            <h2 class="text-sm font-bold text-[#191c1e]">Listado de artículos</h2>
            <span class="text-xs text-[#747878]">Mostrando {{ count($products) }} de {{ $total }} resultados</span>
        </div>

        @php
            $directionIcon = $direction === 'asc' ? '↑' : '↓';
            $query = request()->query();
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f8f9fc] text-[#747878] text-xs uppercase">
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="px-4 py-3 text-left">
                            <a href="{{ route('stock.index', array_merge($query, ['order' => 'cod_articulo', 'direction' => ($order == 'cod_articulo' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Código {!! $order == 'cod_articulo' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <a href="{{ route('stock.index', array_merge($query, ['order' => 'marca', 'direction' => ($order == 'marca' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Marca {!! $order == 'marca' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-left">Descripción</th>
                        <th class="px-4 py-3 text-left">Familia</th>
                        <th class="px-4 py-3 text-left">Subfamilia</th>
                        <th class="px-4 py-3 text-right">
                            <a href="{{ route('stock.index', array_merge($query, ['order' => 'stock', 'direction' => ($order == 'stock' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Stock {!! $order == 'stock' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-right">
                            <a href="{{ route('stock.index', array_merge($query, ['order' => 'qty', 'direction' => ($order == 'qty' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Uds vendidas {!! $order == 'qty' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-right">
                            <a href="{{ route('stock.index', array_merge($query, ['order' => 'revenue', 'direction' => ($order == 'revenue' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Facturación {!! $order == 'revenue' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-right">P. coste</th>
                        <th class="px-4 py-3 text-right">PVP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f2f3f7]">
                    @forelse ($products as $p)
                        <tr class="hover:bg-[#f8f9fc] transition-colors">
                            <td class="px-4 py-3 font-mono text-[#206393] font-medium">{{ $p->cod_articulo }}</td>
                            <td class="px-4 py-3">{{ $p->marca }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $p->descripcion_web }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $p->familia }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $p->subfamilia }}</td>
                            <td class="px-4 py-3 text-right {{ $p->stock_total <= 0 ? 'text-red-600 font-semibold' : 'text-[#191c1e]' }}">{{ number_format($p->stock_total, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($p->total_qty, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-[#191c1e]">{{ number_format($p->total_revenue, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-right text-[#747878]">{{ number_format($p->precio_coste, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-right text-[#747878]">{{ number_format($p->precio_venta_publico, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-[#747878]">
                                No se encontraron artículos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($totalPages > 1)
            <div class="px-5 py-4 border-t border-[#e1e2e6] flex items-center justify-between">
                <div class="text-xs text-[#747878]">Página {{ $page }} de {{ $totalPages }}</div>
                <div class="flex gap-1">
                    @if ($page > 1)
                        <a href="{{ route('stock.index', array_merge($query, ['page' => $page - 1])) }}" class="px-3 py-1 border border-[#e1e2e6] rounded-lg hover:bg-[#f8f9fc] text-xs text-[#191c1e]">← Anterior</a>
                    @endif
                    @if ($page < $totalPages)
                        <a href="{{ route('stock.index', array_merge($query, ['page' => $page + 1])) }}" class="px-3 py-1 border border-[#e1e2e6] rounded-lg hover:bg-[#f8f9fc] text-xs text-[#191c1e]">Siguiente →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const byFamilyLabels = @json(array_map(fn($x) => $x->familia ?: 'Sin familia', $summaryByFamily));
    const byFamilyTotals = @json(array_map(fn($x) => $x->revenue, $summaryByFamily));
    const byWarehouseLabels = @json(array_map(fn($x) => $x->cod_almacen, $summaryByWarehouse));
    const byWarehouseTotals = @json(array_map(fn($x) => $x->valued, $summaryByWarehouse));

    new Chart(document.getElementById('chartByFamily'), {
        type: 'bar',
        data: {
            labels: byFamilyLabels,
            datasets: [{
                label: 'Facturación (€)',
                data: byFamilyTotals,
                backgroundColor: 'rgba(32, 99, 147, 0.7)',
                borderColor: 'rgba(32, 99, 147, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('chartByWarehouse'), {
        type: 'doughnut',
        data: {
            labels: byWarehouseLabels,
            datasets: [{
                data: byWarehouseTotals,
                backgroundColor: [
                    '#206393', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'right' } }
        }
    });

    const familiaSelect = document.getElementById('filterFamilia');
    const subfamiliaSelect = document.getElementById('filterSubfamilia');
    const selectedSubfamilia = @json($codSubfamilia);

    async function loadSubfamilies(codFamilia) {
        subfamiliaSelect.innerHTML = '<option value="">Todas</option>';
        if (!codFamilia) return;

        const url = new URL('{{ route("stock.subfamilies") }}', window.location.origin);
        url.searchParams.set('cod_familia', codFamilia);

        try {
            const res = await fetch(url);
            const data = await res.json();
            data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.cod_subfamilia;
                opt.textContent = s.descripcion;
                if (s.cod_subfamilia === selectedSubfamilia) opt.selected = true;
                subfamiliaSelect.appendChild(opt);
            });
        } catch (e) {
            console.error('Error cargando subfamilias:', e);
        }
    }

    familiaSelect.addEventListener('change', e => loadSubfamilies(e.target.value));

    // Cargar subfamilias al inicio si hay familia seleccionada
    if (familiaSelect.value) {
        loadSubfamilies(familiaSelect.value);
    }
</script>
@endsection
