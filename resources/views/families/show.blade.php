@extends('layouts.app')

@section('title', 'Familia ' . $family->cod_familia . ' - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <div class="text-sm text-[#747878] mb-1">
                <a href="{{ route('families') }}" class="text-[#206393] hover:underline">← Familias</a>
            </div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">{{ $family->descripcion ?: 'Familia ' . $family->cod_familia }}</h1>
            <p class="text-sm text-[#747878] mt-1">Código: {{ $family->cod_familia }}</p>
        </div>
    </div>

    <!-- Tarjetas de resumen de la familia -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Productos</div>
            <div class="text-3xl font-bold text-[#191c1e]">{{ number_format($familyTotalProducts, 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Referencias totales</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Subfamilias</div>
            <div class="text-3xl font-bold text-[#191c1e]">{{ $subfamilies->count() }}</div>
            <div class="text-xs text-[#747878] mt-1">{{ $topSubfamily ? 'Top: ' . Str::limit($topSubfamily->descripcion, 15) : '-' }}</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Stock Total</div>
            <div class="text-3xl font-bold text-[#191c1e]">{{ number_format($familyTotalStock, 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Unidades en almacén</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider mb-2">Facturación</div>
            <div class="text-3xl font-bold text-[#206393]">{{ number_format($familyTotalRevenue, 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Acumulado histórico</div>
        </div>
    </div>

    <!-- Subfamilias -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Subfamilias</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Descripción</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Productos</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cant. Vendida</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subfamilies as $sub)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $sub->cod_subfamilia }}</td>
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $sub->descripcion ?: '-' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($sub->product_count, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($sub->total_qty ?? 0, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($sub->stock_total, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($sub->total_revenue, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-[#747878]">No hay subfamilias registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Productos -->
    <div class="glass-card rounded-xl p-5">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-[#191c1e]">Productos</h2>
            <div class="text-sm text-[#747878]">
                Página {{ $page }} de {{ $totalPages }} ({{ $totalProducts }} productos)
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Marca</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Subfamilia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cant. Vendida</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $product->cod_articulo }}</td>
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $product->marca ?: '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->cod_subfamilia }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product->stock_total, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product->total_qty ?? 0, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($product->total_revenue ?? 0, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-[#747878]">No hay productos en esta familia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        @if($totalPages > 1)
            <div class="flex justify-center items-center gap-2 mt-6">
                <a href="{{ route('families.show', ['cod_familia' => $family->cod_familia, 'page' => max(1, $page - 1)]) }}"
                   class="px-3 py-1 rounded-lg border border-[#e1e2e6] text-[#747878] {{ $page <= 1 ? 'opacity-50 pointer-events-none' : 'hover:bg-[#f8f9fc]' }}">
                    Anterior
                </a>

                @for($i = 1; $i <= $totalPages; $i++)
                    @if($i === 1 || $i === $totalPages || ($i >= $page - 2 && $i <= $page + 2))
                        <a href="{{ route('families.show', ['cod_familia' => $family->cod_familia, 'page' => $i]) }}"
                           class="px-3 py-1 rounded-lg {{ $i === $page ? 'bg-[#206393] text-white' : 'border border-[#e1e2e6] text-[#747878] hover:bg-[#f8f9fc]' }}">
                            {{ $i }}
                        </a>
                    @elseif($i === $page - 3 || $i === $page + 3)
                        <span class="px-2 text-[#747878]">...</span>
                    @endif
                @endfor

                <a href="{{ route('families.show', ['cod_familia' => $family->cod_familia, 'page' => min($totalPages, $page + 1)]) }}"
                   class="px-3 py-1 rounded-lg border border-[#e1e2e6] text-[#747878] {{ $page >= $totalPages ? 'opacity-50 pointer-events-none' : 'hover:bg-[#f8f9fc]' }}">
                    Siguiente
                </a>
            </div>
        @endif
    </div>
@endsection
