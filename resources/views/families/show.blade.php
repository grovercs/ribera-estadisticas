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
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subfamilies as $sub)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $sub->cod_subfamilia }}</td>
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $sub->descripcion ?: '-' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($sub->product_count, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($sub->stock_total, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($sub->total_revenue, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Productos principales -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Productos principales</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Marca</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Subfamilia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cantidad vendida</th>
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
                            <td class="py-3 px-4 text-right">{{ number_format($product->total_qty, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($product->total_revenue, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-[#747878]">No hay productos en esta familia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
