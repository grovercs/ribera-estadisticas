@extends('layouts.app')

@section('title', 'Ventas - Ribera Estadísticas')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Módulo de Ventas</h1>
            <p class="text-sm text-[#747878] mt-1">Facturación y operaciones desde el ERP en tiempo real.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar CSV
            </a>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="glass-card rounded-xl p-5 mb-6">
        <form method="GET" action="{{ route('sales.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <div class="xl:col-span-6">
                <p class="text-xs text-[#747878] mb-2">Período: <span class="font-medium text-[#191c1e]">{{ str_pad($dayFrom, 2, '0', STR_PAD_LEFT) }}/{{ str_pad($monthFrom, 2, '0', STR_PAD_LEFT) }}/{{ $yearFrom }} — {{ str_pad($dayTo, 2, '0', STR_PAD_LEFT) }}/{{ str_pad($monthTo, 2, '0', STR_PAD_LEFT) }}/{{ $yearTo }}</span></p>
            </div>

            {{-- Desde: día / mes / año --}}
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Día desde</label>
                <select name="day_from" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach (range(1, 31) as $d)
                        <option value="{{ $d }}" {{ $d == $dayFrom ? 'selected' : '' }}>{{ str_pad($d, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Mes desde</label>
                <select name="month_from" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $m == $monthFrom ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Año desde</label>
                <select name="year_from" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach ($yearRange as $y)
                        <option value="{{ $y }}" {{ $y == $yearFrom ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Hasta: día / mes / año --}}
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Día hasta</label>
                <select name="day_to" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach (range(1, 31) as $d)
                        <option value="{{ $d }}" {{ $d == $dayTo ? 'selected' : '' }}>{{ str_pad($d, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Mes hasta</label>
                <select name="month_to" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $m == $monthTo ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Año hasta</label>
                <select name="year_to" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    @foreach ($yearRange as $y)
                        <option value="{{ $y }}" {{ $y == $yearTo ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Tipo venta</label>
                <select name="tipo_venta" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="2,4,5" {{ $tipoVenta === '2,4,5' ? 'selected' : '' }}>Facturas de venta (2,4,5)</option>
                    <option value="" {{ $tipoVenta === '' ? 'selected' : '' }}>Todos</option>
                    @foreach ($tiposVenta as $t)
                        <option value="{{ $t }}" {{ $t == $tipoVenta ? 'selected' : '' }}>Tipo {{ $t }}</option>
                    @endforeach
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
                <label class="block text-xs font-semibold text-[#747878]">Top vendedores</label>
                <select name="cod_vendedor" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todos</option>
                    @foreach ($vendedores as $v)
                        <option value="{{ $v->cod_vendedor }}" {{ $v->cod_vendedor == $codVendedor ? 'selected' : '' }}>{{ $v->nombre_vendedor ?: $v->cod_vendedor }} ({{ number_format($v->total, 0, ',', '.') }} €)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Forma de pago</label>
                <select name="cod_forma_pago" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todas</option>
                    @foreach ($formasPago as $f)
                        <option value="{{ $f }}" {{ $f == $codFormaPago ? 'selected' : '' }}>{{ $f }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#747878]">Código cliente</label>
                <input type="text" name="cod_cliente" value="{{ $codCliente }}" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2" placeholder="Código...">
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Razón social / nombre</label>
                <input type="text" name="razon_social" value="{{ $razonSocial }}" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2" placeholder="Buscar cliente...">
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#747878]">Estado</label>
                <select name="estado" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2">
                    <option value="">Todos</option>
                    <option value="pendiente" {{ $estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="cobrada" {{ $estado === 'cobrada' ? 'selected' : '' }}>Cobrada</option>
                    <option value="anulada" {{ $estado === 'anulada' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-[#747878]">Importe min</label>
                    <input type="number" step="0.01" name="min_importe" value="{{ $minImporte }}" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2" placeholder="0">
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-[#747878]">Importe max</label>
                    <input type="number" step="0.01" name="max_importe" value="{{ $maxImporte }}" class="w-full border border-[#e1e2e6] rounded-lg mt-1 text-sm px-3 py-2" placeholder="∞">
                </div>
            </div>

            <div class="flex items-end gap-2 lg:col-span-1">
                <a href="{{ route('sales.index') }}" class="px-4 py-2 text-sm text-[#747878] border border-[#e1e2e6] rounded-lg hover:bg-[#f8f9fc]">Limpiar</a>
                <button type="submit" class="px-4 py-2 text-sm bg-[#206393] text-white rounded-lg hover:bg-[#184b70]">Aplicar filtros</button>
            </div>
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Ventas totales</p>
            <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_sales'], 0, ',', '.') }} €</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Operaciones</p>
            <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_orders'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Ticket medio</p>
            <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['avg_ticket'], 2, ',', '.') }} €</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Artículos vendidos</p>
            <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['total_qty'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Pendiente</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($kpis['total_pending'], 0, ',', '.') }} €</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">% cobrado</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($kpis['collected_pct'], 1, ',', '.') }}%</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Clientes únicos</p>
            <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($kpis['unique_clients'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-5">
            <p class="text-xs text-[#747878] uppercase font-semibold">Total listado</p>
            <p class="text-2xl font-bold text-[#191c1e] mt-1">{{ number_format($total, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Gráficos y resúmenes --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="glass-card rounded-xl p-5 lg:col-span-2">
            <h2 class="text-sm font-bold text-[#191c1e] mb-4">Ventas por tipo</h2>
            <canvas id="chartByType" height="140"></canvas>
        </div>
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-sm font-bold text-[#191c1e] mb-4">Ventas por almacén</h2>
            <canvas id="chartByWarehouse" height="220"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
        {{-- Top clientes --}}
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-sm font-bold text-[#191c1e] mb-3">Top clientes</h2>
            <table class="w-full text-xs">
                <thead class="text-[#747878] border-b border-[#e1e2e6]">
                    <tr><th class="text-left py-2">Cliente</th><th class="text-right py-2">Ventas</th><th class="text-right py-2">Ops</th></tr>
                </thead>
                <tbody>
                    @forelse ($topClients as $c)
                        <tr class="border-b border-[#f2f3f7] last:border-0">
                            <td class="py-2 truncate max-w-[120px]" title="{{ $c->razon_social }}">{{ $c->razon_social ?: $c->cod_cliente }}</td>
                            <td class="py-2 text-right font-semibold">{{ number_format($c->total, 0, ',', '.') }} €</td>
                            <td class="py-2 text-right text-[#747878]">{{ $c->orders }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-[#747878]">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top productos --}}
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-sm font-bold text-[#191c1e] mb-3">Top productos</h2>
            <table class="w-full text-xs">
                <thead class="text-[#747878] border-b border-[#e1e2e6]">
                    <tr><th class="text-left py-2">Producto</th><th class="text-right py-2">Uds</th><th class="text-right py-2">Ventas</th></tr>
                </thead>
                <tbody>
                    @forelse ($topProducts as $p)
                        <tr class="border-b border-[#f2f3f7] last:border-0">
                            <td class="py-2 truncate max-w-[120px]" title="{{ $p->descripcion }}">{{ $p->descripcion ?: $p->cod_articulo }}</td>
                            <td class="py-2 text-right">{{ number_format($p->qty, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold">{{ number_format($p->total, 0, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-[#747878]">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top vendedores --}}
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-sm font-bold text-[#191c1e] mb-3">Top vendedores</h2>
            <table class="w-full text-xs">
                <thead class="text-[#747878] border-b border-[#e1e2e6]">
                    <tr><th class="text-left py-2">Vendedor</th><th class="text-right py-2">Ventas</th><th class="text-right py-2">Ops</th></tr>
                </thead>
                <tbody>
                    @forelse ($summaryBySeller as $s)
                        <tr class="border-b border-[#f2f3f7] last:border-0">
                            <td class="py-2 truncate max-w-[120px]" title="{{ $s->nombre_vendedor }}">{{ $s->nombre_vendedor ?: $s->cod_vendedor }}</td>
                            <td class="py-2 text-right font-semibold">{{ number_format($s->total, 0, ',', '.') }} €</td>
                            <td class="py-2 text-right text-[#747878]">{{ $s->orders }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-[#747878]">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Resumen por almacén --}}
        <div class="glass-card rounded-xl p-5">
            <h2 class="text-sm font-bold text-[#191c1e] mb-3">Resumen almacén</h2>
            <table class="w-full text-xs">
                <thead class="text-[#747878] border-b border-[#e1e2e6]">
                    <tr><th class="text-left py-2">Almacén</th><th class="text-right py-2">Ventas</th><th class="text-right py-2">Ops</th></tr>
                </thead>
                <tbody>
                    @forelse ($summaryByWarehouse as $w)
                        <tr class="border-b border-[#f2f3f7] last:border-0">
                            <td class="py-2">{{ $w->cod_almacen }}</td>
                            <td class="py-2 text-right font-semibold">{{ number_format($w->total, 0, ',', '.') }} €</td>
                            <td class="py-2 text-right text-[#747878]">{{ $w->orders }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-[#747878]">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabla de ventas --}}
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="p-5 border-b border-[#e1e2e6] flex items-center justify-between">
            <h2 class="text-sm font-bold text-[#191c1e]">Listado de ventas</h2>
            <span class="text-xs text-[#747878]">Mostrando {{ count($orders) }} de {{ $total }} resultados</span>
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
                            <a href="{{ route('sales.index', array_merge($query, ['sort' => 'cod_venta', 'direction' => ($sort == 'cod_venta' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Venta {!! $sort == 'cod_venta' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-left">
                            <a href="{{ route('sales.index', array_merge($query, ['sort' => 'fecha_venta', 'direction' => ($sort == 'fecha_venta' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Fecha {!! $sort == 'fecha_venta' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <a href="{{ route('sales.index', array_merge($query, ['sort' => 'razon_social', 'direction' => ($sort == 'razon_social' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Cliente {!! $sort == 'razon_social' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <a href="{{ route('sales.index', array_merge($query, ['sort' => 'cod_almacen', 'direction' => ($sort == 'cod_almacen' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Alm {!! $sort == 'cod_almacen' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-left">Vendedor</th>
                        <th class="px-4 py-3 text-left">FP</th>
                        <th class="px-4 py-3 text-right">
                            <a href="{{ route('sales.index', array_merge($query, ['sort' => 'importe_impuestos', 'direction' => ($sort == 'importe_impuestos' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Total {!! $sort == 'importe_impuestos' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-right">
                            <a href="{{ route('sales.index', array_merge($query, ['sort' => 'importe_pendiente', 'direction' => ($sort == 'importe_pendiente' && $direction == 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-[#206393]">Pendiente {!! $sort == 'importe_pendiente' ? $directionIcon : '' !!}</a>
                        </th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f2f3f7]">
                    @forelse ($orders as $o)
                        @php
                            $estado = (is_null($o->anulada) || $o->anulada === '' || $o->anulada === 'N')
                                ? ($o->importe_pendiente > 0 ? 'Pendiente' : 'Cobrada')
                                : 'Anulada';
                            $estadoClass = match($estado) {
                                'Cobrada' => 'bg-green-100 text-green-800',
                                'Pendiente' => 'bg-yellow-100 text-yellow-800',
                                'Anulada' => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <tr class="hover:bg-[#f8f9fc] transition-colors">
                            <td class="px-4 py-3 font-mono text-[#206393] font-medium">{{ $o->cod_venta }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $o->tipo_venta }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $o->fecha_venta ? \Carbon\Carbon::parse($o->fecha_venta)->format('d/m/Y H:i') : '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-[#191c1e]">{{ $o->razon_social ?: $o->nombre_comercial }}</div>
                                @if ($o->cod_cliente)
                                    <div class="text-xs text-[#747878]">{{ $o->cod_cliente }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#747878]">{{ $o->cod_almacen }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $o->nombre_vendedor ?: $o->cod_vendedor }}</td>
                            <td class="px-4 py-3 text-[#747878]">{{ $o->cod_forma_liquidacion }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-[#191c1e]">{{ number_format($o->importe_impuestos, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-right {{ $o->importe_pendiente > 0 ? 'text-red-600' : 'text-[#747878]' }}">{{ number_format($o->importe_pendiente, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs {{ $estadoClass }}">{{ $estado }}</span></td>
                            <td class="px-4 py-3 text-center">
                                <button type="button"
                                        class="text-[#206393] hover:text-[#184b70] text-xs underline"
                                        onclick="loadLines('{{ $o->cod_venta }}', '{{ $o->tipo_venta }}', '{{ $o->cod_empresa }}', '{{ $o->cod_caja }}')">
                                    Ver líneas
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-[#747878]">
                                No hay ventas para los filtros seleccionados.
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
                        <a href="{{ route('sales.index', array_merge($query, ['page' => $page - 1])) }}" class="px-3 py-1 border border-[#e1e2e6] rounded-lg hover:bg-[#f8f9fc] text-xs text-[#191c1e]">← Anterior</a>
                    @endif
                    @if ($page < $totalPages)
                        <a href="{{ route('sales.index', array_merge($query, ['page' => $page + 1])) }}" class="px-3 py-1 border border-[#e1e2e6] rounded-lg hover:bg-[#f8f9fc] text-xs text-[#191c1e]">Siguiente →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Modal líneas de venta --}}
<div id="linesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl mx-4 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-[#e1e2e6] flex items-center justify-between">
            <h3 class="text-lg font-bold text-[#191c1e]">Líneas de venta <span id="modalVentaTitle" class="text-[#206393]"></span></h3>
            <button onclick="closeLinesModal()" class="text-[#747878] hover:text-[#191c1e] text-2xl">×</button>
        </div>
        <div class="p-6 overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f8f9fc] text-[#747878] text-xs uppercase">
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="px-3 py-2 text-left">Línea</th>
                        <th class="px-3 py-2 text-left">Artículo</th>
                        <th class="px-3 py-2 text-left">Descripción</th>
                        <th class="px-3 py-2 text-right">Cantidad</th>
                        <th class="px-3 py-2 text-right">Precio</th>
                        <th class="px-3 py-2 text-right">Dto</th>
                        <th class="px-3 py-2 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody id="linesTableBody" class="divide-y divide-[#f2f3f7]">
                    <tr><td colspan="7" class="px-3 py-4 text-center text-[#747878]">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const byTypeLabels = @json(array_map(fn($x) => $x->tipo_venta, $summaryByType));
    const byTypeTotals = @json(array_map(fn($x) => $x->total, $summaryByType));
    const byWarehouseLabels = @json(array_map(fn($x) => $x->cod_almacen, $summaryByWarehouse));
    const byWarehouseTotals = @json(array_map(fn($x) => $x->total, $summaryByWarehouse));

    new Chart(document.getElementById('chartByType'), {
        type: 'bar',
        data: {
            labels: byTypeLabels,
            datasets: [{
                label: 'Ventas (€)',
                data: byTypeTotals,
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

    function loadLines(codVenta, tipoVenta, codEmpresa, codCaja) {
        const modal = document.getElementById('linesModal');
        const tbody = document.getElementById('linesTableBody');
        document.getElementById('modalVentaTitle').textContent = codVenta;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-4 text-center text-[#747878]">Cargando...</td></tr>';

        const url = new URL('{{ route("sales.lines") }}', window.location.origin);
        url.searchParams.set('cod_venta', codVenta);
        url.searchParams.set('tipo_venta', tipoVenta);
        url.searchParams.set('cod_empresa', codEmpresa);
        url.searchParams.set('cod_caja', codCaja);

        fetch(url)
            .then(r => r.json())
            .then(lines => {
                if (!Array.isArray(lines) || lines.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-4 text-center text-[#747878]">Sin líneas</td></tr>';
                    return;
                }
                tbody.innerHTML = lines.map(l => `
                    <tr class="hover:bg-[#f8f9fc]">
                        <td class="px-3 py-2">${l.linea}</td>
                        <td class="px-3 py-2 font-medium">${l.cod_articulo || '-'}</td>
                        <td class="px-3 py-2">${l.descripcion || '-'}</td>
                        <td class="px-3 py-2 text-right">${parseFloat(l.cantidad || 0).toLocaleString('es-ES', {minimumFractionDigits: 2})}</td>
                        <td class="px-3 py-2 text-right">${parseFloat(l.precio || 0).toLocaleString('es-ES', {minimumFractionDigits: 2})} €</td>
                        <td class="px-3 py-2 text-right">${(l.dto1 || 0)}% ${(l.dto2 || 0) > 0 ? '+' + (l.dto2 || 0) + '%' : ''}</td>
                        <td class="px-3 py-2 text-right font-semibold">${parseFloat(l.importe_impuestos || l.importe || 0).toLocaleString('es-ES', {minimumFractionDigits: 2})} €</td>
                    </tr>
                `).join('');
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="7" class="px-3 py-4 text-center text-red-500">Error: ${err.message}</td></tr>`;
            });
    }

    function closeLinesModal() {
        const modal = document.getElementById('linesModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.getElementById('linesModal').addEventListener('click', function (e) {
        if (e.target === this) closeLinesModal();
    });
</script>
@endsection
