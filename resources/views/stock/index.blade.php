@extends('layouts.app')

@section('title', 'Stock - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Gestión de Stock</h1>
            <p class="text-sm text-[#747878] mt-1">Inventario y alertas de umbral.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                {{ $lowStockCount }} productos bajo stock
            </span>
        </div>
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

    <div class="glass-card rounded-xl p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">SKU</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Categoría</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Mínimo</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Precio</th>
                        <th class="text-center py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors {{ $product->isLowStock() ? 'bg-red-50' : '' }}">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $product->sku }}</td>
                            <td class="py-3 px-4 font-medium">{{ $product->name }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->category }}</td>
                            <td class="py-3 px-4 text-right font-semibold {{ $product->isLowStock() ? 'text-red-600' : 'text-[#191c1e]' }}">{{ number_format($product->stock_quantity) }}</td>
                            <td class="py-3 px-4 text-right text-[#747878]">{{ number_format($product->min_stock) }}</td>
                            <td class="py-3 px-4 text-right font-semibold">${{ number_format($product->sale_price, 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($product->status)
                                        @case('active') bg-green-100 text-green-800 @break
                                        @case('discontinued') bg-gray-100 text-gray-800 @break
                                        @case('out_of_stock') bg-red-100 text-red-800 @break
                                    @endswitch"
                                >
                                    {{ ucfirst($product->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
@endsection
