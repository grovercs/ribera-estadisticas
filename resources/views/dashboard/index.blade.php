@extends('layouts.app')

@section('title', 'Dashboard - Ribera Estadísticas')

@section('content')
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-bold text-[#191c1e] tracking-tight">Resumen</h1>
            <p class="text-sm text-[#747878] mt-1">Operaciones diarias y métricas financieras.</p>
        </div>
        <button class="px-4 py-2 bg-white border border-[#e1e2e6] rounded-lg text-[#191c1e] text-xs font-semibold hover:bg-[#f2f3f7] transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Exportar Reporte
        </button>
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Ventas Totales</span>
                <span class="material-symbols-outlined text-[#747878]">payments</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">${{ number_format($totalSales, 0) }}</div>
                <div class="flex items-center gap-1 text-[#206393]">
                    <span class="material-symbols-outlined text-[16px]">trending_up</span>
                    <span class="text-sm font-semibold">+8.4% YoY</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Margen Bruto</span>
                <span class="material-symbols-outlined text-[#747878]">percent</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">24.0%</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="material-symbols-outlined text-[16px]">horizontal_rule</span>
                    <span class="text-sm">Estable</span>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 flex flex-col justify-between border-l-4 border-l-[#206393]">
            <div class="flex justify-between items-start mb-4">
                <span class="text-xs font-semibold text-[#747878] uppercase tracking-wider">Envíos Activos</span>
                <span class="material-symbols-outlined text-[#206393]">local_shipping</span>
            </div>
            <div>
                <div class="text-[36px] font-bold text-[#191c1e] mb-1">{{ $activeShipments }}</div>
                <div class="flex items-center gap-1 text-[#747878]">
                    <span class="text-sm">3 marcados para revisión</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="glass-card rounded-xl p-5">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-semibold text-[#191c1e]">Evolución de Ventas</h2>
                <p class="text-sm text-[#747878]">Volumen vs Objetivo (últimos 12 meses)</p>
            </div>
        </div>
        <canvas id="salesChart" height="80"></canvas>
    </div>

    <!-- Top Products -->
    <div class="glass-card rounded-xl p-5">
        <h2 class="text-xl font-semibold text-[#191c1e] mb-4">Productos más Vendidos</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e1e2e6]">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Producto</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-[#747878] uppercase">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topProducts as $product)
                        <tr class="border-b border-[#f2f3f7] hover:bg-[#f8f9fc] transition-colors">
                            <td class="py-3 px-4 font-medium text-[#191c1e]">{{ $product->name }}</td>
                            <td class="py-3 px-4 text-right text-[#206393] font-semibold">{{ number_format($product->total_qty) }}</td>
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
                label: 'Ventas ($)',
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
                            return '$' + value.toLocaleString();
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
