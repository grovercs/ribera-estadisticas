@extends('layouts.app')

@section('title', $titulo . ' - Ribera Estadísticas')

@section('content')
    @php
        // Construye una URL preservando los filtros activos y aplicando overrides.
        $baseQ = array_filter([
            'year_from' => $selectedYearFrom ?? null,
            'year_to' => $selectedYearTo ?? null,
            'month_from' => $selectedMonthFrom ?? null,
            'month_to' => $selectedMonthTo ?? null,
            'sort' => $sort ?? null,
            'dir' => $dir ?? null,
            'search' => $search ?? null,
        ], fn($v) => $v !== null && $v !== '');
        $link = function (array $overrides = []) use ($baseQ) {
            return request()->url() . '?' . http_build_query(array_merge($baseQ, $overrides));
        };
        // Formateadores por tipo de celda.
        $fmtEuro = fn($v) => number_format((float)$v, 2, ',', '.') . ' €';
        $fmtPrice = fn($v) => number_format((float)$v, 3, ',', '.');
        $fmtInt = fn($v) => number_format((float)$v, 0, ',', '.');
        $cell = function ($row, $col) use ($fmtEuro, $fmtPrice, $fmtInt) {
            $v = $row[$col['key']] ?? null;
            switch ($col['type'] ?? 'text') {
                case 'euro':
                case 'euro2':
                    return $fmtEuro($v);
                case 'price':
                    return $fmtPrice($v);
                case 'int':
                    return $fmtInt($v);
                case 'pctbadge':
                    $m = (float)$v;
                    $color = $m >= 25 ? 'bg-emerald-100 text-emerald-700' : ($m >= 12 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
                    return '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold ' . $color . '">' . number_format($m, 1, ',', '.') . '%</span>';
                case 'varbadge':
                    $p = (float)$v;
                    $up = $p >= 0;
                    $color = $up ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700';
                    $icon = $up ? '▲' : '▼';
                    return '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold ' . $color . '">' . $icon . ' ' . number_format(abs($p), 1, ',', '.') . '%</span>';
                case 'product':
                    $code = $row['cod_articulo'] ?? $row['cod'] ?? '';
                    $desc = $row['descripcion'] ?? $row[$col['key']] ?? '';
                    return '<div class="max-w-[280px]"><div class="font-medium text-[#191c1e] truncate">' . e($desc) . '</div>'
                        . '<div class="text-[11px] text-[#747878] font-mono">' . e($code) . '</div></div>';
                default:
                    return e((string)$v);
            }
        };
        $routeIndex = route('financial') . (!empty($qParams) ? '?' . $qParams : '');
    @endphp

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ $routeIndex }}" class="inline-flex items-center gap-1 text-sm text-[#206393] hover:text-[#1a5078] mb-2 font-medium">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Volver a Análisis Financiero
            </a>
            <h1 class="text-[28px] font-bold text-[#191c1e] tracking-tight">{{ $titulo }}</h1>
            @isset($subtitulo)<p class="text-sm text-[#747878] mt-1">{{ $subtitulo }}</p>@endisset
        </div>
        <!-- Filtros de rango de fechas -->
        <form method="GET" action="{{ request()->url() }}" class="flex gap-2 items-center flex-wrap">
            <div class="flex items-center gap-1">
                <label class="text-xs font-semibold text-[#747878] uppercase">Desde:</label>
                <select name="year_from" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="all" {{ ($selectedYearFrom ?? '') === 'all' ? 'selected' : '' }}>Todo</option>
                    @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                        <option value="{{ $year }}" {{ ($selectedYearFrom ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
                <select name="month_from" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="all" {{ ($selectedMonthFrom ?? 'all') === 'all' ? 'selected' : '' }}>Todos</option>
                    @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                        <option value="{{ $num }}" {{ ($selectedMonthFrom ?? '') == $num ? 'selected' : '' }}>{{ $mes }}</option>
                    @endforeach
                </select>
            </div>
            <span class="text-[#747878]">→</span>
            <div class="flex items-center gap-1">
                <label class="text-xs font-semibold text-[#747878] uppercase">Hasta:</label>
                <select name="year_to" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="all" {{ ($selectedYearTo ?? '') === 'all' ? 'selected' : '' }}>Todo</option>
                    @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                        <option value="{{ $year }}" {{ ($selectedYearTo ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
                <select name="month_to" class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                    <option value="all" {{ ($selectedMonthTo ?? 'all') === 'all' ? 'selected' : '' }}>Todos</option>
                    @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                        <option value="{{ $num }}" {{ ($selectedMonthTo ?? '') == $num ? 'selected' : '' }}>{{ $mes }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="p-1.5 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors">
                <span class="material-symbols-outlined text-[18px]">search</span>
            </button>
        </form>
    </div>

    <!-- Tabla + buscador -->
    <div class="glass-card rounded-xl p-5">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
            <p class="text-sm text-[#747878]">
                <span class="font-semibold text-[#191c1e]">{{ number_format($total, 0, ',', '.') }}</span> registros
                @if(isset($page) && $totalPages > 1) · Página {{ $page }} de {{ $totalPages }}@endif
            </p>
            <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2">
                <input type="hidden" name="year_from" value="{{ $selectedYearFrom ?? '' }}">
                <input type="hidden" name="year_to" value="{{ $selectedYearTo ?? '' }}">
                <input type="hidden" name="month_from" value="{{ $selectedMonthFrom ?? '' }}">
                <input type="hidden" name="month_to" value="{{ $selectedMonthTo ?? '' }}">
                <input type="hidden" name="sort" value="{{ $sort ?? '' }}">
                <input type="hidden" name="dir" value="{{ $dir ?? '' }}">
                <div class="relative">
                    <span class="material-symbols-outlined text-[18px] absolute left-2 top-1/2 -translate-y-1/2 text-[#747878]">search</span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar…"
                        class="pl-8 pr-3 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#206393] w-48">
                </div>
                <button type="submit" class="px-3 py-1.5 bg-[#206393] text-white rounded-lg text-sm font-semibold hover:bg-[#1a5078]">Filtrar</button>
                @if(!empty($search))
                    <a href="{{ $link(['search' => null, 'page' => 1]) }}" class="p-1.5 bg-[#747878] text-white rounded-lg hover:bg-[#5a5d5f]">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="tabla-detalle">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6] text-[#747878]">
                        @foreach($columnas as $col)
                            <th class="text-{{ $col['align'] ?? 'left' }} px-3 py-2 font-semibold uppercase text-xs whitespace-nowrap">
                                @if($col['sortable'] ?? false)
                                    @php
                                        $newDir = (($sort ?? '') === $col['key'] && ($dir ?? 'desc') === 'desc') ? 'asc' : 'desc';
                                        $isSorted = ($sort ?? '') === $col['key'];
                                    @endphp
                                    <a href="{{ $link(['sort' => $col['key'], 'dir' => $newDir, 'page' => 1]) }}"
                                       class="inline-flex items-center gap-1 hover:text-[#191c1e] {{ $isSorted ? 'text-[#191c1e]' : '' }}">
                                        {{ $col['label'] }}
                                        @if($isSorted)
                                            <span class="text-[10px]">{{ $dir === 'asc' ? '▲' : '▼' }}</span>
                                        @else
                                            <span class="text-[10px] opacity-30">⇅</span>
                                        @endif
                                    </a>
                                @else
                                    {{ $col['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-b border-[#f0f1f4] hover:bg-[#f8f9fc] transition-colors">
                            @foreach($columnas as $col)
                                <td class="px-3 py-2.5 text-{{ $col['align'] ?? 'left' }} {{ in_array($col['type'] ?? '', ['euro','euro2','price','int','pctbadge','varbadge']) ? 'tabular-nums text-right' : '' }}">
                                    @if(in_array($col['type'] ?? '', ['product','pctbadge','varbadge']))
                                        {!! $cell($row, $col) !!}
                                    @else
                                        {{ $cell($row, $col) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columnas) }}" class="px-3 py-10 text-center text-[#747878]">
                                No hay datos para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($totalPages > 1)
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-5 pt-4 border-t border-[#e1e2e6]">
                <p class="text-xs text-[#747878]">Página {{ $page }} de {{ $totalPages }}</p>
                <div class="flex items-center gap-1">
                    @if($page > 1)
                        <a href="{{ $link(['page' => $page - 1]) }}" class="px-2.5 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm hover:bg-[#f2f3f7]">‹ Anterior</a>
                    @else
                        <span class="px-2.5 py-1.5 bg-[#f2f3f7] border border-[#e1e2e6] rounded-lg text-sm text-[#747878] opacity-50">‹ Anterior</span>
                    @endif
                    @php
                        $startp = max(1, $page - 2);
                        $endp = min($totalPages, $page + 2);
                    @endphp
                    @if($startp > 1)
                        <a href="{{ $link(['page' => 1]) }}" class="px-2.5 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm hover:bg-[#f2f3f7]">1</a>
                        @if($startp > 2)<span class="px-1 text-[#747878]">…</span>@endif
                    @endif
                    @for($p = $startp; $p <= $endp; $p++)
                        @if($p == $page)
                            <span class="px-2.5 py-1.5 bg-[#206393] text-white rounded-lg text-sm font-semibold">{{ $p }}</span>
                        @else
                            <a href="{{ $link(['page' => $p]) }}" class="px-2.5 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm hover:bg-[#f2f3f7]">{{ $p }}</a>
                        @endif
                    @endfor
                    @if($endp < $totalPages)
                        @if($endp < $totalPages - 1)<span class="px-1 text-[#747878]">…</span>@endif
                        <a href="{{ $link(['page' => $totalPages]) }}" class="px-2.5 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm hover:bg-[#f2f3f7]">{{ $totalPages }}</a>
                    @endif
                    @if($page < $totalPages)
                        <a href="{{ $link(['page' => $page + 1]) }}" class="px-2.5 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm hover:bg-[#f2f3f7]">Siguiente ›</a>
                    @else
                        <span class="px-2.5 py-1.5 bg-[#f2f3f7] border border-[#e1e2e6] rounded-lg text-sm text-[#747878] opacity-50">Siguiente ›</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection