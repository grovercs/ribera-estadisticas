@extends('layouts.app')

@section('title', 'Análisis Financiero - Ribera Estadísticas')

@section('content')
    @php
        // Query string de filtros para reutilizar en enlaces "Ver todo".
        $qParams = http_build_query(array_filter([
            'year_from' => $selectedYearFrom ?? null,
            'year_to' => $selectedYearTo ?? null,
            'month_from' => $selectedMonthFrom ?? null,
            'month_to' => $selectedMonthTo ?? null,
        ], fn($v) => $v !== null && $v !== ''));
        $verTodo = fn($ruta) => route($ruta) . ($qParams !== '' ? '?' . $qParams : '');
    @endphp

    @push('styles')
        <style>
            main { scroll-behavior: smooth; }
            .sort-arrow { font-size: 9px; opacity: 0.5; margin-left: 2px; }
            .sortable-th.sorted { color: #191c1e !important; }
            .sortable-th.sorted .sort-arrow { opacity: 1; }
            .glos-highlight { animation: glosflash 1.5s ease-out; }
            @keyframes glosflash { 0% { background-color: #f3e8ff; } 100% { background-color: transparent; } }
        </style>
    @endpush

    <!-- Header con Filtros -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Análisis Financiero</h1>
            <p class="text-sm text-[#747878] mt-1">Márgenes, rentabilidad y salud del negocio.</p>
        </div>
        <div class="flex gap-2 items-center">
            <button type="button" id="btnGlosario"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-[#6f42c1] text-white rounded-lg text-sm font-semibold hover:bg-[#5a3699] transition-colors">
                <span class="material-symbols-outlined text-[18px]">menu_book</span>
                <span class="hidden sm:inline">Glosario</span>
            </button>
            <!-- Filtros de Rango de Fechas -->
            <form method="GET" action="{{ route('financial') }}" class="flex gap-2 items-center flex-wrap">
                <div class="flex items-center gap-1">
                    <label for="year_from" class="text-xs font-semibold text-[#747878] uppercase">Desde:</label>
                    <select name="year_from" id="year_from"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ (isset($selectedYearFrom) && $selectedYearFrom === 'all') ? 'selected' : '' }}>Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}" {{ (!isset($selectedYearFrom) || $selectedYearFrom == $year) ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month_from" id="month_from"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ !isset($selectedMonthFrom) || $selectedMonthFrom === 'all' ? 'selected' : '' }}>Todos</option>
                        @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                            <option value="{{ $num }}" {{ (isset($selectedMonthFrom) && $selectedMonthFrom == $num) ? 'selected' : '' }}>{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="text-[#747878]">→</span>
                <div class="flex items-center gap-1">
                    <label for="year_to" class="text-xs font-semibold text-[#747878] uppercase">Hasta:</label>
                    <select name="year_to" id="year_to"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ (isset($selectedYearTo) && $selectedYearTo === 'all') ? 'selected' : '' }}>Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}" {{ (!isset($selectedYearTo) || $selectedYearTo == $year) ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month_to" id="month_to"
                        class="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-sm font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all" {{ !isset($selectedMonthTo) || $selectedMonthTo === 'all' ? 'selected' : '' }}>Todos</option>
                        @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                            <option value="{{ $num }}" {{ (isset($selectedMonthTo) && $selectedMonthTo == $num) ? 'selected' : '' }}>{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="p-1.5 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors">
                    <span class="material-symbols-outlined text-[18px]">search</span>
                </button>
                @if(isset($selectedYearFrom) && $selectedYearFrom !== $maxYear)
                    <a href="{{ route('financial') }}" class="p-1.5 bg-[#747878] text-white rounded-lg hover:bg-[#5a5d5f] transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </a>
                @endif
            </form>
        </div>
    </div>

    @if(isset($error))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 font-semibold">Error: {{ $error }}</p>
        </div>
    @endif

    <!-- ===== PANEL GLOSARIO (desplegable) ===== -->
    <div id="panelGlosario" class="hidden glass-card rounded-xl p-6 mb-6">
        <div class="flex justify-between items-center mb-5">
            <h2 class="text-xl font-bold text-[#191c1e] flex items-center gap-2">
                <span class="material-symbols-outlined text-[#6f42c1]">menu_book</span> Glosario y metodología
            </h2>
            <button type="button" onclick="document.getElementById('panelGlosario').classList.add('hidden')"
                class="p-1.5 text-[#747878] hover:text-[#191c1e] rounded-lg hover:bg-[#f2f3f7]">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <p class="text-xs text-[#747878] mb-4">Fuente de datos: ERP SQL Server (hist_ventas_linea/cabecera, hist_compras_linea/cabecera). Se excluyen facturas anuladas y los pseudocódigos de sección (ALMACEN, FERRETERIA…). Todas las cifras respetan el filtro de rango de fechas de la cabecera.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Ventas y márgenes -->
            <div>
                <h3 class="text-sm font-bold text-[#206393] uppercase tracking-wider mb-3">Ventas y márgenes</h3>
                <dl class="space-y-3 text-sm">
                    <div id="glos-revenue"><dt class="font-semibold text-[#191c1e]">Ventas Netas (Facturación)</dt><dd class="text-[#747878]">Suma de <code>importe_impuestos</code> de las líneas de venta. <span class="text-[#191c1e]">Fórmula: SUM(importe_impuestos)</span>.</dd></div>
                    <div id="glos-cost"><dt class="font-semibold text-[#191c1e]">Coste de Ventas</dt><dd class="text-[#747878]">Coste de la mercancía vendida, a precio de coste. <span class="text-[#191c1e]">Fórmula: SUM(precio_coste × cantidad)</span>.</dd></div>
                    <div id="glos-profit"><dt class="font-semibold text-[#191c1e]">Beneficio Bruto</dt><dd class="text-[#747878]">Lo que queda tras el coste de mercancía. <span class="text-[#191c1e]">Fórmula: Ventas Netas − Coste de Ventas</span>.</dd></div>
                    <div id="glos-margin"><dt class="font-semibold text-[#191c1e]">Margen Bruto %</dt><dd class="text-[#747878]">Rentabilidad sobre venta. <span class="text-[#191c1e]">Fórmula: Beneficio Bruto / Ventas Netas × 100</span>.</dd></div>
                    <div><dt class="font-semibold text-[#191c1e]">Pedidos</dt><dd class="text-[#747878]">Número de operaciones de venta distintas (COUNT DISTINCT cod_venta).</dd></div>
                    <div><dt class="font-semibold text-[#191c1e]">Clientes Activos</dt><dd class="text-[#747878]">Clientes distintos que compraron en el periodo (COUNT DISTINCT cod_cliente).</dd></div>
                    <div id="glos-ticket"><dt class="font-semibold text-[#191c1e]">Ticket Medio</dt><dd class="text-[#747878]">Venta media por operación. <span class="text-[#191c1e]">Fórmula: Ventas Netas / Pedidos</span>.</dd></div>
                    <div><dt class="font-semibold text-[#191c1e]">Por Cliente</dt><dd class="text-[#747878]">Facturación media por cliente. <span class="text-[#191c1e]">Fórmula: Ventas Netas / Clientes Activos</span>.</dd></div>
                    <div id="glos-familia"><dt class="font-semibold text-[#191c1e]">Rentabilidad por Familia / Subfamilia</dt><dd class="text-[#747878]">Mismas métricas (facturación, coste, beneficio, margen %) agregadas agrupando por familia o subfamilia de producto.</dd></div>
                    <div id="glos-star"><dt class="font-semibold text-[#191c1e]">Productos Estrella</dt><dd class="text-[#747878]">Productos ordenados por beneficio bruto; solo los que vendieron más de 10 unidades. <span class="text-[#191c1e]">Margen/Und: precio − coste unitario</span>.</dd></div>
                </dl>
            </div>
            <!-- Compras -->
            <div>
                <h3 class="text-sm font-bold text-[#6f42c1] uppercase tracking-wider mb-3">Compras</h3>
                <dl class="space-y-3 text-sm">
                    <div id="glos-compra-kpi"><dt class="font-semibold text-[#191c1e]">KPIs de Compra</dt><dd class="text-[#747878]">Del histórico de albaranes de compra. Importe = SUM(importe); Artículos/Proveedores = COUNT DISTINCT; Líneas = COUNT de líneas.</dd></div>
                    <div id="glos-index"><dt class="font-semibold text-[#191c1e]">Índice de precio de compra</dt><dd class="text-[#747878]">Índice <strong>Laspeyres</strong> con cesta fija de artículos (base 100 = ene–mar del año inicial). Mide la evolución del <strong>precio puro</strong>, aislando el cambio de mix de producto. Los artículos no comprados un mes se mantienen a precio base (carry base); los ratios de precio extremos se acotan a [0,1–10] para descartar notas de abono o cambios de unidad.</dd></div>
                    <div id="glos-ppv"><dt class="font-semibold text-[#191c1e]">Variación de precio (PPV)</dt><dd class="text-[#747878]">Comparación del precio de compra medio por artículo entre el periodo actual (A) y el mismo periodo desplazado años atrás (B). <span class="text-[#191c1e]">Fórmula: (Precio A / Precio B − 1) × 100</span>. Solo artículos con &gt;20 unidades en ambos periodos; se excluyen variaciones fuera de [−80%, +400%] (cambio de envase/unidad, no precio real).</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <!-- ===== SUB-NAV DE SECCIONES ===== -->
    <nav id="subnav" class="sticky top-0 z-30 -mx-6 px-6 py-2.5 bg-[#f8f9fc]/90 backdrop-blur border-b border-[#e1e2e6] mb-6 flex items-center gap-1.5 overflow-x-auto">
        <span class="text-xs font-semibold text-[#747878] uppercase mr-1 hidden sm:inline">Ir a:</span>
        <a href="#resumen" class="subnav-link px-3 py-1.5 rounded-full text-xs font-semibold text-[#747878] hover:bg-[#206393] hover:text-white whitespace-nowrap transition-colors">Resumen</a>
        <a href="#evolucion" class="subnav-link px-3 py-1.5 rounded-full text-xs font-semibold text-[#747878] hover:bg-[#206393] hover:text-white whitespace-nowrap transition-colors">Evolución</a>
        <a href="#ventas" class="subnav-link px-3 py-1.5 rounded-full text-xs font-semibold text-[#747878] hover:bg-[#206393] hover:text-white whitespace-nowrap transition-colors">Ventas y Márgenes</a>
        <a href="#compras" class="subnav-link px-3 py-1.5 rounded-full text-xs font-semibold text-[#747878] hover:bg-[#206393] hover:text-white whitespace-nowrap transition-colors">Compras</a>
        <a href="#clientes" class="subnav-link px-3 py-1.5 rounded-full text-xs font-semibold text-[#747878] hover:bg-[#206393] hover:text-white whitespace-nowrap transition-colors">Clientes</a>
    </nav>

    <!-- KPIs Financieros Principales -->
    <div id="resumen" class="scroll-mt-20 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Ventas Netas -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#206393]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ventas Netas</span>
                <span class="material-symbols-outlined text-[#206393]">payments</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['revenue'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Ejercicio {{ $selectedYearFrom }}</div>
        </div>

        <!-- Coste Ventas -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#dc3545]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Coste Ventas</span>
                <span class="material-symbols-outlined text-[#dc3545]">inventory</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['total_cost'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Coste mercancía vendida</div>
        </div>

        <!-- Beneficio Bruto -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#28a745]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Beneficio Bruto</span>
                <span class="material-symbols-outlined text-[#28a745]">trending_up</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['gross_profit'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Margen bruto (ventas - coste)</div>
        </div>

        <!-- Margen % -->
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#ffc107]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Margen Bruto %</span>
                <span class="material-symbols-outlined text-[#ffc107]">pie_chart</span>
            </div>
            <div class="text-[32px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['margin_rate'], 1, ',', '.') }} %</div>
            <div class="text-xs text-[#747878]">Rentabilidad sobre ventas</div>
        </div>
    </div>

    <!-- KPIs Secundarios -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <!-- Pedidos -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Pedidos</span>
                <span class="material-symbols-outlined text-[#747878]">shopping_cart</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['total_orders'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878]">Operaciones realizadas</div>
        </div>

        <!-- Clientes Únicos -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Clientes Activos</span>
                <span class="material-symbols-outlined text-[#747878]">groups</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['unique_clients'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878]">Clientes que compraron</div>
        </div>

        <!-- Ticket Medio -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ticket Medio</span>
                <span class="material-symbols-outlined text-[#747878]">receipt_long</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['avg_ticket'], 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Por operación</div>
        </div>

        <!-- Facturación por Cliente -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Por Cliente</span>
                <span class="material-symbols-outlined text-[#747878]">person</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e] mb-1">{{ number_format($kpis['revenue_per_client'], 2, ',', '.') }} €</div>
            <div class="text-xs text-[#747878]">Facturación media/cliente</div>
        </div>
    </div>

    <!-- Evolución Mensual de Márgenes -->
    <div id="evolucion" class="glass-card rounded-xl p-5 mb-6 scroll-mt-20">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center justify-between gap-2">
            <span class="flex items-center gap-2">Evolución Mensual: Facturación vs Beneficio Bruto</span>
            <button type="button" class="info-btn p-1 text-[#747878] hover:text-[#6f42c1]" title="Ver definición en el glosario" data-gloss="glos-revenue">
                <span class="material-symbols-outlined text-[18px]">help</span>
            </button>
        </h2>
        <!-- Filtro de fechas propio del gráfico (AJAX) -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4 p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6]">
            <div class="flex flex-wrap items-center gap-1.5" id="evoPresets">
                <span class="text-xs font-semibold text-[#747878] uppercase mr-1">Vista:</span>
                @php
                    $presets = [
                        ['label'=>'Este año','yf'=>$maxYear,'yt'=>$maxYear,'mf'=>'all','mt'=>'all'],
                        ['label'=>'Año anterior','yf'=>$maxYear-1,'yt'=>$maxYear-1,'mf'=>'all','mt'=>'all'],
                        ['label'=>'Últimos 2 años','yf'=>$maxYear-1,'yt'=>$maxYear,'mf'=>'all','mt'=>'all'],
                        ['label'=>'Últimos 3 años','yf'=>$maxYear-2,'yt'=>$maxYear,'mf'=>'all','mt'=>'all'],
                        ['label'=>'Todo el histórico','yf'=>'all','yt'=>'all','mf'=>'all','mt'=>'all'],
                    ];
                @endphp
                @foreach($presets as $p)
                    <button type="button" class="evo-preset px-2.5 py-1 bg-white border border-[#e1e2e6] rounded-lg text-xs font-semibold text-[#191c1e] hover:bg-[#206393] hover:text-white transition-colors"
                        data-yf="{{ $p['yf'] }}" data-yt="{{ $p['yt'] }}" data-mf="{{ $p['mf'] }}" data-mt="{{ $p['mt'] }}">{{ $p['label'] }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <div class="flex items-center gap-1">
                    <label class="text-xs font-semibold text-[#747878] uppercase">Desde:</label>
                    <select id="evo_year_from" class="px-2 py-1 bg-white border border-[#e1e2e6] rounded-lg text-xs font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all">Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    <select id="evo_month_from" class="px-2 py-1 bg-white border border-[#e1e2e6] rounded-lg text-xs font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all">Todos</option>
                        @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                            <option value="{{ $num }}">{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="text-[#747878]">→</span>
                <div class="flex items-center gap-1">
                    <label class="text-xs font-semibold text-[#747878] uppercase">Hasta:</label>
                    <select id="evo_year_to" class="px-2 py-1 bg-white border border-[#e1e2e6] rounded-lg text-xs font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all">Todo</option>
                        @foreach(($yearRange ?? range($minYear ?? 2012, $maxYear ?? date('Y'))) as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    <select id="evo_month_to" class="px-2 py-1 bg-white border border-[#e1e2e6] rounded-lg text-xs font-medium text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]">
                        <option value="all">Todos</option>
                        @foreach(['1'=>'Ene','2'=>'Feb','3'=>'Mar','4'=>'Abr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $num => $mes)
                            <option value="{{ $num }}">{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" id="evoApply" class="px-3 py-1 bg-[#206393] text-white rounded-lg text-xs font-semibold hover:bg-[#1a5078] flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">refresh</span> Aplicar
                </button>
                <span id="evoLoading" class="hidden text-xs text-[#747878] flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Cargando…
                </span>
            </div>
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="monthlyMarginChart"></canvas>
        </div>
    </div>

    <!-- Margen por Familia -->
    <div id="ventas" class="glass-card rounded-xl p-5 mb-6 scroll-mt-20">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center justify-between gap-2">
            <span class="flex items-center gap-2">
                <button type="button" class="info-btn p-1 text-[#747878] hover:text-[#6f42c1]" title="Ver definición en el glosario" data-gloss="glos-familia">
                    <span class="material-symbols-outlined text-[18px]">help</span>
                </button>
                Rentabilidad por Familia de Productos
            </span>
            <a href="{{ $verTodo('financial.detalle-familias') }}" class="text-xs font-semibold text-[#206393] hover:underline flex items-center gap-1 whitespace-nowrap">
                Ver todo <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </h2>
        <div class="overflow-x-auto">
            <table id="tabla-familias" class="w-full text-sm sortable" data-default-sort="gross_profit:desc">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th data-sort="revenue" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Facturación <span class="sort-arrow"></span></th>
                        <th data-sort="total_cost" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Coste <span class="sort-arrow"></span></th>
                        <th data-sort="gross_profit" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Beneficio <span class="sort-arrow"></span></th>
                        <th data-sort="margin_rate" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Margen % <span class="sort-arrow"></span></th>
                        <th data-sort="orders" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Pedidos <span class="sort-arrow"></span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($marginByFamily as $family)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $family['familia'] }}</td>
                            <td data-val="{{ $family['revenue'] }}" class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($family['revenue'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $family['total_cost'] }}" class="py-3 px-4 text-right text-[#dc3545]">{{ number_format($family['total_cost'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $family['gross_profit'] }}" class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($family['gross_profit'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $family['margin_rate'] }}" class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $family['margin_rate'] >= 40 ? 'bg-green-100 text-green-700' : ($family['margin_rate'] >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($family['margin_rate'], 1) }}%
                                </span>
                            </td>
                            <td data-val="{{ $family['orders'] }}" class="py-3 px-4 text-right text-[#747878]">{{ number_format($family['orders'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Productos Estrella -->
    <div class="glass-card rounded-xl p-5 mb-6">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center justify-between gap-2">
            <span class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#ffc107]" style="font-variation-settings: 'FILL' 1;">star</span>
                <button type="button" class="info-btn p-1 text-[#747878] hover:text-[#6f42c1]" title="Ver definición en el glosario" data-gloss="glos-star">
                    <span class="material-symbols-outlined text-[18px]">help</span>
                </button>
                Productos Estrella (Alta Rotación + Buen Margen)
            </span>
            <a href="{{ $verTodo('financial.detalle-productos') }}" class="text-xs font-semibold text-[#206393] hover:underline flex items-center gap-1 whitespace-nowrap">
                Ver todo <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </h2>
        <div class="overflow-x-auto">
            <table id="tabla-productos" class="w-full text-sm sortable" data-default-sort="gross_profit:desc">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th data-sort="total_qty" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Und. Vendidas <span class="sort-arrow"></span></th>
                        <th data-sort="revenue" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Facturación <span class="sort-arrow"></span></th>
                        <th data-sort="gross_profit" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Beneficio <span class="sort-arrow"></span></th>
                        <th data-sort="margin_rate" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Margen % <span class="sort-arrow"></span></th>
                        <th data-sort="margin_per_unit" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Margen/Und <span class="sort-arrow"></span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($starProducts as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-medium text-[#191c1e]">{{ $product['descripcion'] }}</div>
                                <div class="text-xs text-[#747878] font-mono">{{ $product['cod_articulo'] }}</div>
                            </td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product['familia'] }}</td>
                            <td data-val="{{ $product['total_qty'] }}" class="py-3 px-4 text-right font-semibold text-[#206393]">{{ number_format($product['total_qty'], 0, ',', '.') }}</td>
                            <td data-val="{{ $product['revenue'] }}" class="py-3 px-4 text-right text-[#206393]">{{ number_format($product['revenue'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $product['gross_profit'] }}" class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($product['gross_profit'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $product['margin_rate'] }}" class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $product['margin_rate'] >= 40 ? 'bg-green-100 text-green-700' : ($product['margin_rate'] >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($product['margin_rate'], 1) }}%
                                </span>
                            </td>
                            <td data-val="{{ $product['margin_per_unit'] }}" class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($product['margin_per_unit'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== ANÁLISIS DE COMPRAS ===== -->
    <div id="compras" class="scroll-mt-20"></div>
    @php
        $purchase = $purchase ?? ['kpi'=>['lineas'=>0,'articulos'=>0,'proveedores'=>0,'importe'=>0,'cantidad'=>0], 'index'=>[], 'indexBaseLabel'=>null, 'periodoA'=>'', 'periodoB'=>'', 'ppvIncreases'=>[], 'ppvDecreases'=>[]];
        $fmtPrice = function($p) { return number_format((float)$p, (float)$p < 10 ? 3 : 2, ',', '.'); };
        $fmtVar = function($v) { return ($v > 0 ? '+' : '') . number_format($v, 1, ',', '.') . '%'; };
    @endphp

    <div class="mt-2 mb-2 flex items-center justify-between gap-2">
        <h2 class="text-2xl font-bold text-[#191c1e] flex items-center gap-2">
            <button type="button" class="info-btn p-1 text-[#747878] hover:text-[#6f42c1]" title="Ver definición en el glosario" data-gloss="glos-compra-kpi">
                <span class="material-symbols-outlined text-[18px]">help</span>
            </button>
            Análisis de Compras
        </h2>
    </div>
    <p class="text-sm text-[#747878] mb-4">Evolución del precio de compra y variaciones por artículo — para decisiones de aprovisionamiento y precio.</p>

    <!-- KPIs de Compras -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-xl p-5 border-l-4 border-l-[#6f42c1]">
            <div class="flex justify-between items-start mb-3">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Importe Comprado</span>
                <span class="material-symbols-outlined text-[#6f42c1]">shopping_bag</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($purchase['kpi']['importe'], 0, ',', '.') }} €</div>
            <div class="text-xs text-[#747878] mt-1">Periodo {{ $purchase['periodoA'] }}</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-3">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Artículos</span>
                <span class="material-symbols-outlined text-[#747878]">category</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($purchase['kpi']['articulos'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Referencias compradas</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-3">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Proveedores</span>
                <span class="material-symbols-outlined text-[#747878]">local_shipping</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($purchase['kpi']['proveedores'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Proveedores activos</div>
        </div>
        <div class="glass-card rounded-xl p-5">
            <div class="flex justify-between items-start mb-3">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Líneas</span>
                <span class="material-symbols-outlined text-[#747878]">list_alt</span>
            </div>
            <div class="text-[28px] font-bold text-[#191c1e]">{{ number_format($purchase['kpi']['lineas'], 0, ',', '.') }}</div>
            <div class="text-xs text-[#747878] mt-1">Líneas de albarán</div>
        </div>
    </div>

    <!-- Índice de precio de compra (Laspeyres base 100) -->
    @if(!empty($purchase['index']))
    <div class="glass-card rounded-xl p-5 mb-6">
        <h3 class="text-lg font-semibold text-[#191c1e] mb-1">Índice de precio de compra</h3>
        <p class="text-xs text-[#747878] mb-4">Índice Laspeyres (cesta fija de artículos, base 100 = {{ $purchase['indexBaseLabel'] }}). Aísla el efecto precio puro del cambio de mix de producto.</p>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="purchaseIndexChart"></canvas>
        </div>
    </div>
    @endif

    <!-- Variación de precio de compra por artículo (PPV) -->
    <div class="flex items-center justify-between gap-2 mb-2">
        <h3 class="text-lg font-semibold text-[#191c1e] flex items-center gap-2">
            <button type="button" class="info-btn p-1 text-[#747878] hover:text-[#6f42c1]" title="Ver definición en el glosario" data-gloss="glos-ppv">
                <span class="material-symbols-outlined text-[18px]">help</span>
            </button>
            Variación de precio de compra por artículo
        </h3>
        <a href="{{ $verTodo('financial.detalle-ppv') }}" class="text-xs font-semibold text-[#206393] hover:underline flex items-center gap-1 whitespace-nowrap">
            Ver todo <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
        </a>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Subidas -->
        <div class="glass-card rounded-xl p-5">
            <h3 class="text-lg font-semibold text-[#dc3545] mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined">trending_up</span> Mayor subida de precio
            </h3>
            <p class="text-xs text-[#747878] mb-4">{{ $purchase['periodoA'] }} vs {{ $purchase['periodoB'] }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-[#e1e2e6]">
                            <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Artículo</th>
                            <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">P. anterior</th>
                            <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">P. actual</th>
                            <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Var.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase['ppvIncreases'] as $item)
                            <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                                <td class="py-2 px-3">
                                    <div class="font-medium text-[#191c1e] truncate max-w-[180px]">{{ $item['desc'] }}</div>
                                    <div class="text-[11px] text-[#747878] font-mono">{{ $item['cod'] }}</div>
                                </td>
                                <td class="py-2 px-3 text-right text-[#747878]">{{ $fmtPrice($item['pB']) }}</td>
                                <td class="py-2 px-3 text-right text-[#191c1e] font-semibold">{{ $fmtPrice($item['pA']) }}</td>
                                <td class="py-2 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">{{ $fmtVar($item['var']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                        @if(empty($purchase['ppvIncreases']))
                            <tr><td colspan="4" class="py-4 px-3 text-center text-[#747878] text-sm">Sin datos suficientes en el rango seleccionado.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bajadas -->
        <div class="glass-card rounded-xl p-5">
            <h3 class="text-lg font-semibold text-[#28a745] mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined">trending_down</span> Mayor bajada de precio
            </h3>
            <p class="text-xs text-[#747878] mb-4">{{ $purchase['periodoA'] }} vs {{ $purchase['periodoB'] }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-[#e1e2e6]">
                            <th class="text-left py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Artículo</th>
                            <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">P. anterior</th>
                            <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">P. actual</th>
                            <th class="text-right py-2 px-3 text-xs font-semibold text-[#747878] uppercase">Var.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase['ppvDecreases'] as $item)
                            <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                                <td class="py-2 px-3">
                                    <div class="font-medium text-[#191c1e] truncate max-w-[180px]">{{ $item['desc'] }}</div>
                                    <div class="text-[11px] text-[#747878] font-mono">{{ $item['cod'] }}</div>
                                </td>
                                <td class="py-2 px-3 text-right text-[#747878]">{{ $fmtPrice($item['pB']) }}</td>
                                <td class="py-2 px-3 text-right text-[#191c1e] font-semibold">{{ $fmtPrice($item['pA']) }}</td>
                                <td class="py-2 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">{{ $fmtVar($item['var']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                        @if(empty($purchase['ppvDecreases']))
                            <tr><td colspan="4" class="py-4 px-3 text-center text-[#747878] text-sm">Sin datos suficientes en el rango seleccionado.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Clientes Top por Rentabilidad -->
    <div id="clientes" class="glass-card rounded-xl p-5 scroll-mt-20">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4 flex items-center justify-between gap-2">
            <span class="flex items-center gap-2">
                <button type="button" class="info-btn p-1 text-[#747878] hover:text-[#6f42c1]" title="Ver definición en el glosario" data-gloss="glos-revenue">
                    <span class="material-symbols-outlined text-[18px]">help</span>
                </button>
                Clientes Más Rentables
            </span>
            <a href="{{ $verTodo('financial.detalle-clientes') }}" class="text-xs font-semibold text-[#206393] hover:underline flex items-center gap-1 whitespace-nowrap">
                Ver todo <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </h2>
        <div class="overflow-x-auto">
            <table id="tabla-clientes" class="w-full text-sm sortable" data-default-sort="gross_profit:desc">
                <thead>
                    <tr class="border-b-2 border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Población</th>
                        <th data-sort="revenue" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Facturación <span class="sort-arrow"></span></th>
                        <th data-sort="gross_profit" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Beneficio <span class="sort-arrow"></span></th>
                        <th data-sort="margin_rate" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Margen % <span class="sort-arrow"></span></th>
                        <th data-sort="orders" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Pedidos <span class="sort-arrow"></span></th>
                        <th data-sort="avg_order_value" class="sortable-th text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase cursor-pointer select-none hover:text-[#191c1e]">Ticket Medio <span class="sort-arrow"></span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topClientsByProfit as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $client['razon_social'] }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client['poblacion'] ?? '-' }}</td>
                            <td data-val="{{ $client['revenue'] }}" class="py-3 px-4 text-right text-[#206393]">{{ number_format($client['revenue'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $client['gross_profit'] }}" class="py-3 px-4 text-right text-[#28a745] font-semibold">{{ number_format($client['gross_profit'], 0, ',', '.') }} €</td>
                            <td data-val="{{ $client['margin_rate'] }}" class="py-3 px-4 text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $client['margin_rate'] >= 40 ? 'bg-green-100 text-green-700' : ($client['margin_rate'] >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($client['margin_rate'], 1) }}%
                                </span>
                            </td>
                            <td data-val="{{ $client['orders'] }}" class="py-3 px-4 text-right text-[#747878]">{{ number_format($client['orders'], 0, ',', '.') }}</td>
                            <td data-val="{{ $client['avg_order_value'] }}" class="py-3 px-4 text-right text-[#747878]">{{ number_format($client['avg_order_value'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const ctx = document.getElementById('monthlyMarginChart').getContext('2d');
    const monthlyData = @json($monthlyMargin);
    const labels = Object.keys(monthlyData);

    const evolutionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(l => {
                const [year, month] = l.split('-');
                const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                return months[parseInt(month)-1] + '/' + year;
            }),
            datasets: [
                {
                    label: 'Facturación (€)',
                    data: labels.map(l => monthlyData[l].revenue),
                    borderColor: '#206393',
                    backgroundColor: 'rgba(32, 99, 147, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Beneficio Bruto (€)',
                    data: labels.map(l => monthlyData[l].profit),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Margen %',
                    data: labels.map(l => monthlyData[l].margin_rate),
                    borderColor: '#ffc107',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('es-ES') + ' €';
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    max: 100,
                    grid: { display: false },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // === Filtro de fechas del gráfico de evolución (AJAX) ===
    const evoUrl = '{{ route("financial.evolucion-data") }}';
    const evoLoading = document.getElementById('evoLoading');
    const yfSel = document.getElementById('evo_year_from');
    const ytSel = document.getElementById('evo_year_to');
    const mfSel = document.getElementById('evo_month_from');
    const mtSel = document.getElementById('evo_month_to');

    function evoHighlightPreset(yf, yt) {
        document.querySelectorAll('.evo-preset').forEach(b => {
            const match = b.dataset.yf == String(yf) && b.dataset.yt == String(yt)
                && b.dataset.mf == 'all' && b.dataset.mt == 'all';
            b.classList.toggle('bg-[#206393]', match);
            b.classList.toggle('text-white', match);
            if (!match) { b.classList.add('bg-white'); b.classList.remove('text-white'); }
        });
    }

    function evoLoad(yf, yt, mf, mt) {
        evoLoading.classList.remove('hidden');
        const qs = new URLSearchParams({ year_from: yf, year_to: yt, month_from: mf, month_to: mt }).toString();
        fetch(evoUrl + '?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                evolutionChart.data.labels = d.labels;
                evolutionChart.data.datasets[0].data = d.revenue;
                evolutionChart.data.datasets[1].data = d.profit;
                evolutionChart.data.datasets[2].data = d.margin;
                evolutionChart.update();
                evoHighlightPreset(yf, yt);
            })
            .catch(() => alert('No se pudo cargar la evolución.'))
            .finally(() => evoLoading.classList.add('hidden'));
    }

    // Presets rápidos
    document.querySelectorAll('.evo-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const { yf, yt, mf, mt } = btn.dataset;
            yfSel.value = yf; ytSel.value = yt; mfSel.value = mf; mtSel.value = mt;
            evoLoad(yf, yt, mf, mt);
        });
    });
    // Aplicar rango personalizado
    document.getElementById('evoApply').addEventListener('click', () => {
        evoLoad(yfSel.value, ytSel.value, mfSel.value, mtSel.value);
    });
    // Estado inicial: marca el preset del rango activo de la página
    evoHighlightPreset('{{ $selectedYearFrom }}', '{{ $selectedYearTo }}');

    // === Índice de precio de compra (Laspeyres base 100) ===
    const pidxCanvas = document.getElementById('purchaseIndexChart');
    if (pidxCanvas) {
        const pidxData = @json($purchase['index']);
        const pidxLabels = Object.keys(pidxData);
        const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        new Chart(pidxCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: pidxLabels.map(l => {
                    const [y, m] = l.split('-');
                    return meses[parseInt(m)-1] + '/' + y.slice(2);
                }),
                datasets: [{
                    label: 'Índice precio de compra (base 100)',
                    data: pidxLabels.map(l => pidxData[l]),
                    borderColor: '#6f42c1',
                    backgroundColor: 'rgba(111, 66, 193, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: ctx => 'Índice: ' + ctx.parsed.y.toFixed(1) } }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: v => v.toFixed(0) }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // === Glosario: botón + iconos ? ===
    const btnG = document.getElementById('btnGlosario');
    const panelG = document.getElementById('panelGlosario');
    if (btnG && panelG) {
        btnG.addEventListener('click', () => {
            panelG.classList.toggle('hidden');
            if (!panelG.classList.contains('hidden')) panelG.scrollIntoView({ block: 'start' });
        });
    }
    document.querySelectorAll('.info-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            if (panelG) panelG.classList.remove('hidden');
            const target = btn.dataset.gloss;
            if (target) {
                const el = document.getElementById(target);
                if (el) {
                    el.classList.remove('glos-highlight');
                    void el.offsetWidth; // reiniciar animación
                    el.classList.add('glos-highlight');
                    el.scrollIntoView({ block: 'center' });
                }
            }
        });
    });

    // === Ordenación de tablas (client-side) ===
    document.querySelectorAll('table.sortable').forEach(table => {
        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('thead th[data-sort]');
        function colIndex(key) {
            const th = table.querySelector('thead th[data-sort="' + key + '"]');
            return th ? Array.from(th.parentNode.children).indexOf(th) : -1;
        }
        function applySort(key, dir) {
            const ci = colIndex(key);
            if (ci < 0) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const av = parseFloat(a.children[ci]?.dataset.val ?? 0) || 0;
                const bv = parseFloat(b.children[ci]?.dataset.val ?? 0) || 0;
                return dir === 'asc' ? av - bv : bv - av;
            });
            rows.forEach(r => tbody.appendChild(r));
            headers.forEach(h => {
                const arr = h.querySelector('.sort-arrow');
                const active = h.dataset.sort === key;
                h.classList.toggle('sorted', active);
                if (arr) arr.textContent = active ? (dir === 'asc' ? '▲' : '▼') : '';
            });
        }
        let current = null;
        headers.forEach(th => {
            th.addEventListener('click', () => {
                const key = th.dataset.sort;
                const dir = current && current.key === key && current.dir === 'desc' ? 'asc' : 'desc';
                current = { key, dir };
                applySort(key, dir);
            });
        });
        const def = (table.dataset.defaultSort || '').split(':');
        if (def[0]) { current = { key: def[0], dir: def[1] || 'desc' }; applySort(def[0], def[1] || 'desc'); }
    });

    // === Sub-nav: resaltar sección activa al hacer scroll ===
    (function () {
        const ids = ['resumen', 'evolucion', 'ventas', 'compras', 'clientes'];
        const links = Array.from(document.querySelectorAll('.subnav-link'));
        function setActive(id) {
            links.forEach(l => {
                const active = l.getAttribute('href') === '#' + id;
                l.style.backgroundColor = active ? '#206393' : '';
                l.style.color = active ? '#fff' : '';
            });
        }
        const sections = ids.map(id => document.getElementById(id)).filter(Boolean);
        if ('IntersectionObserver' in window && sections.length) {
            const obs = new IntersectionObserver(entries => {
                entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id); });
            }, { rootMargin: '-25% 0px -65% 0px' });
            sections.forEach(s => obs.observe(s));
        }
    })();
</script>
@endpush
