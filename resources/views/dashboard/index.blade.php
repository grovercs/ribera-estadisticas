@extends('layouts.app')

@section('title', 'Dashboard - Ribera Estadísticas')

@section('content')
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Resumen</h1>
            <p class="text-sm text-[#747878] mt-1">Datos reales importados desde el ERP.</p>
        </div>
        <div class="flex gap-2">
            <span class="px-3 py-1.5 bg-[#206393] text-white rounded-lg text-xs font-semibold">
                {{ number_format($totalOrders) }} ventas importadas
            </span>
        </div>
    </div>

    <!-- Alertas -->
    @if($alerts->count() > 0)
        <div class="bg-[#ffe16d] border border-[#705d00] rounded-xl p-4 flex items-start gap-4 shadow-sm">
            <div class="mt-0.5 text-[#4c3f00]">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">warning</span>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-[#4c3f00] mb-1">Alertas Operacionales</h3>
                <ul class="text-sm text-[#544600] space-y-1 list-disc list-inside">
                    @foreach($alerts->take(2) as $alert)
                        <li><strong>{{ $alert->title }}</strong>: {{ $alert->description }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ventas Totales</span>
                <span class="material-symbols-outlined text-[#747878]">payments</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ number_format($totalSales, 0, ',', '.') }} €</div>
                <div class="flex items-center gap-1 text-[#206393]">
                    <span class="material-symbols-outlined text-[16px]">trending_up</span>
                    <span class="text-sm font-semibold">{{ number_format($totalOrders) }} operaciones</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ticket Medio</span>
                <span class="material-symbols-outlined text-[#747878]">receipt_long</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ number_format($avgTicket, 2, ',', '.') }} €</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">Por operación</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between border-l-4 border-l-[#206393]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Pendiente Cobro</span>
                <span class="material-symbols-outlined text-[#206393]">account_balance_wallet</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ number_format($pendingAmount, 0, ',', '.') }} €</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">Importe pendiente</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Clientes Únicos</span>
                <span class="material-symbols-outlined text-[#747878]">groups</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ \App\Models\ErpSale::distinct('cod_cliente')->count('cod_cliente') }}</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">En el período</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="glass-card rounded-xl p-5">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-semibold text-[#191c1e]">Evolución de Ventas</h2>
                <p class="text-sm text-[#747878]">Volumen mensual importado desde ERP</p>
            </div>
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Top Clients -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Clientes por Facturación</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cliente</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Población</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Provincia</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Vendedor</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topClients as $client)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $client->razon_social }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->poblacion ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->provincia ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $client->vendedor ?? '-' }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($client->total_spent, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Top Productos por Facturación</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Código</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Marca</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Familia</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Stock</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cantidad</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topProducts as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-mono text-[#206393]">{{ $product->cod_articulo }}</td>
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $product->descripcion }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->marca ?? '-' }}</td>
                            <td class="py-3 px-4 text-[#747878]">{{ $product->cod_familia }}{{ $product->cod_subfamilia ? ' / ' . $product->cod_subfamilia : '' }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product->stock_total, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format($product->total_qty, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($product->total_revenue, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesData = @json($salesByMonth);
    const labels = Object.keys(salesData);
    const data = Object.values(salesData);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(l => {
                const [year, month] = l.split('-');
                return `${month}/${year}`;
            }),
            datasets: [{
                label: 'Ventas (€)',
                data: data,
                borderColor: '#206393',
                backgroundColor: 'rgba(32, 99, 147, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#206393',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
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
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
@endpush
