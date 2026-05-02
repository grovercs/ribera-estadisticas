@extends('layouts.app')

@section('title', 'Familias - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Familias</h1>
            <p class="text-sm text-[#747878] mt-1">Exploración por familias y subfamilias de producto.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($families as $family)
            <a href="{{ route('families.show', $family->cod_familia) }}" class="glass-card rounded-xl p-5 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="text-xs font-semibold text-[#747878] uppercase tracking-wider">{{ $family->cod_familia }}</div>
                        <h3 class="text-lg font-semibold text-[#191c1e] mt-1">{{ $family->descripcion ?: 'Sin descripción' }}</h3>
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
                        <div class="text-xs text-[#747878]">Stock total</div>
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
