@extends('layouts.app')

@section('title', 'Productos - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Análisis de Productos</h1>
            <p class="text-sm text-[#747878] mt-1">Maestro de artículos con stock, ventas y precios.</p>
        </div>
        @if($topProduct)
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#206393] text-white">
                Top: {{ $topProduct->marca }}
            </span>
        </div>
        @endif
    </div>

    @if($alerts->count() > 0)
        <div class="space-y-2">
            @foreach($alerts as $alert)
                <div class="bg-[#ffdad6] border border-[#ba1a1a] rounded-lg p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-[#ba1a1a]">warning</span>
                    <span class="text-sm text-[#93000a]"><strong>{{ $alert->title }}</strong> — {{ $alert->description }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('stock') }}" class="glass-card rounded-xl p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-[#747878] mb-1">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Código, marca, familia..." class="w-full rounded-lg border border-[#e1e2e6] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
        </div>
        <div>
            <label class="block text-xs font-medium text-[#747878] mb-1">Familia</label>
            <select name="familia" class="rounded-lg border border-[#e1e2e6] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
                <option value="">Todas</option>
                @foreach($families as $family)
                    <option value="{{ $family->cod_familia }}" {{ request('familia') == $family->cod_familia ? 'selected' : '' }}>{{ $family->descripcion }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-[#747878] mb-1">Subfamilia</label>
            <select name="subfamilia" class="rounded-lg border border-[#e1e2e6] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
                <option value="">Todas</option>
                @foreach($subfamilies as $subfamily)
                    <option value="{{ $subfamily->cod_subfamilia }}" {{ request('subfamilia') == $subfamily->cod_subfamilia ? 'selected' : '' }}>{{ $subfamily->descripcion }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="stock_bajo" id="stock_bajo" value="1" {{ request('stock_bajo') ? 'checked' : '' }} class="rounded border-[#e1e2e6] text-[#206393] focus:ring-[#206393]">
            <label for="stock_bajo" class="text-sm text-[#191c1e]">Sin stock</label>
        </div>
        <div>
            <label class="block text-xs font-medium text-[#747878] mb-1">Ordenar por</label>
            <select name="order" class="rounded-lg border border-[#e1e2e6] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#206393]">
                <option value="revenue" {{ request('order') == 'revenue' ? 'selected' : '' }}>Facturación</option>
                <option value="stock" {{ request('order') == 'stock' ? 'selected' : '' }}>Stock</option>
                <option value="coste" {{ request('order') == 'coste' ? 'selected' : '' }}>Precio coste</option>
                <option value="pvp" {{ request('order') == 'pvp' ? 'selected' : '' }}>PVP</option>
            </select>
        </div>
        <button type="submit" class="bg-[#206393] hover:bg-[#164868] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Filtrar
        </button>
        <a href="{{ route('stock') }}" class="text-sm text-[#206393] hover:underline">Limpiar</a>
    </form>

    <div class="glass-card rounded-xl p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Marca</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Subfamilia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Unid. Vendidas</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">P. Coste</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">PVP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productItems as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $product->cod_articulo }}</td>
                            <td class="py-3 px-4 font-medium">{{ $product->marca }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->familia }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->subfamilia }}</td>
                            <td class="py-3 px-4 text-right {{ $product->stock_total <= 0 ? 'text-[#ba1a1a] font-semibold' : '' }}">{{ number_format($product->stock_total, 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product->total_qty, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-[#191c1e]">{{ number_format($product->total_revenue, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($product->precio_coste, 2, ',', '.') }} €</td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($product->precio_venta_publico, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-[#747878]">No se encontraron productos con los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
@endsection
