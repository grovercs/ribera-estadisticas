@extends('layouts.app')

@section('title', 'Proveedores - Ribera Estadísticas')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Proveedores</h1>
            <p class="text-sm text-[#747878] mt-1">Maestro de proveedores importado desde el ERP.</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="glass-card rounded-xl p-4 mb-6">
        <form method="GET" action="{{ route('suppliers') }}" class="flex flex-col md:flex-row gap-3">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por razón social, CIF, población, código..."
                    class="w-full bg-[#f8f9fc] border border-[#e1e2e6] rounded-lg px-4 py-2 text-sm text-[#191c1e] placeholder-[#747878] outline-none focus:border-[#206393]">
            </div>
            <button type="submit" class="px-4 py-2 bg-[#206393] text-white rounded-lg text-sm font-semibold hover:bg-[#1a527a] transition-colors">
                Buscar
            </button>
            @if(request('search'))
                <a href="{{ route('suppliers') }}" class="px-4 py-2 bg-[#f2f3f7] text-[#747878] rounded-lg text-sm font-semibold hover:bg-[#e1e2e6] transition-colors">Limpiar</a>
            @endif
        </form>
    </div>

    <!-- Tabla -->
    <div class="glass-card rounded-xl p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Razón Social</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Nombre Comercial</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">CIF</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Población</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Provincia</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Teléfono</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Crédito</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $supplier->cod_proveedor }}</td>
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $supplier->razon_social ?: '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $supplier->nombre_comercial ?: '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $supplier->cif ?: '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $supplier->poblacion ?: '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $supplier->provincia ?: '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $supplier->telefono ?: '-' }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ $supplier->credito_otorgado ? number_format($supplier->credito_otorgado, 2, ',', '.') . ' €' : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-[#747878]">No se encontraron proveedores.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $suppliers->links() }}
        </div>
    </div>
@endsection
