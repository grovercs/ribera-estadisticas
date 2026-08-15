'use client'

import { useState, useEffect } from 'react'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler,
  ArcElement
} from 'chart.js'
import { Line, Doughnut, Bar } from 'react-chartjs-2'
import { useIsMobile } from '@/lib/hooks/useIsMobile'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

interface DashboardChartsProps {
  evolutionData: Array<{ year: number; month: number; total_sales: number; prev_total_sales?: number }>
  warehouseData: Array<{ cod_almacen: string; total_sales: number }>
  chartTitle?: string
  comparisonLabel?: string
}

export default function DashboardCharts({
  evolutionData,
  warehouseData,
  chartTitle = 'Evolución de ventas',
  comparisonLabel = 'Año anterior (€)'
}: DashboardChartsProps) {
  const [mounted, setMounted] = useState(false)
  useEffect(() => {
    setMounted(true)
  }, [])

  const isMobile = useIsMobile()
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
  const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']

  // Mapear y ordenar almacenes según el ERP real: Pont de Suert (1), Vielha (2)
  const getWarehouseName = (code: string) => {
    if (code === '1') return 'Pont de Suert'
    if (code === '2') return 'Vielha'
    return `Otros (Alm. ${code})`
  }
  const getWarehouseOrder = (code: string) => {
    if (code === '1') return 0
    if (code === '2') return 1
    return 99
  }
  const sortedWarehouseData = [...warehouseData].sort(
    (a, b) => getWarehouseOrder(a.cod_almacen) - getWarehouseOrder(b.cod_almacen)
  )

  // ------------------------------------------------------------
  // 1. Line Chart: Evolución Interanual de Ventas
  // ------------------------------------------------------------
  // Truncar la serie del año actual al último mes con datos para evitar la caída artificial a cero en meses futuros
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() + 1

  const truncatedEvolution = evolutionData.filter((d) => {
    if (d.year !== currentYear) return true
    return d.month <= currentMonth
  })

  const evolutionLabels = truncatedEvolution.map(d => `${monthNames[d.month - 1]} ${d.year}`)
  const currentSales = truncatedEvolution.map(d => d.total_sales)
  const prevSales = truncatedEvolution.map(d => d.prev_total_sales)

  const lineData = {
    labels: evolutionLabels,
    datasets: [
      {
        label: chartTitle === 'Evolución de compras' ? 'Compras actuales (€)' : 'Ventas actuales (€)',
        data: currentSales,
        borderColor: '#206393',
        backgroundColor: 'rgba(32, 99, 147, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 3,
      },
      {
        label: comparisonLabel,
        data: prevSales,
        borderColor: '#f59e0b',
        backgroundColor: 'transparent',
        borderDash: [5, 5],
        tension: 0.35,
        borderWidth: 1.5,
        pointRadius: 3,
      }
    ]
  }

  const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top' as const,
        labels: { color: '#191c1e', font: { size: 12, weight: 'bold' as const } }
      },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            const val = context.parsed.y
            const label = context.dataset.label.split(' ')[0]
            return `${label}: ${currencyFormatter.format(val)}`
          }
        }
      }
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#747878', font: { size: 11 } }
      },
      y: {
        beginAtZero: true,
        grid: { color: '#e1e2e6' },
        ticks: { 
          color: '#747878', 
          font: { size: 11 },
          callback: (value: any) => `${(value / 1000).toFixed(0)}k €`
        }
      }
    }
  }

  // ------------------------------------------------------------
  // 2. Doughnut Chart: Ventas por Almacén
  // ------------------------------------------------------------
  const warehouseColors = ['#206393', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
  const totalWarehouseSales = sortedWarehouseData.reduce((acc, w) => acc + w.total_sales, 0)
  
  const doughnutData = {
    labels: sortedWarehouseData.map(w => getWarehouseName(w.cod_almacen)),
    datasets: [{
      data: sortedWarehouseData.map(w => w.total_sales),
      backgroundColor: warehouseColors,
      borderWidth: 1,
      borderColor: '#ffffff'
    }]
  }

  const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: isMobile ? ('bottom' as const) : ('right' as const),
        labels: {
          color: '#191c1e',
          font: { size: 12, weight: 'bold' as const },
          generateLabels: (chart: any) => {
            const data = chart.data;
            if (data.labels.length && data.datasets.length) {
              return data.labels.map((label: string, i: number) => {
                const value = data.datasets[0].data[i];
                const percentage = totalWarehouseSales > 0 ? ((value / totalWarehouseSales) * 100).toFixed(1) : '0';
                return {
                  text: `${label}: ${currencyFormatter.format(value)} (${percentage}%)`,
                  fillStyle: data.datasets[0].backgroundColor[i],
                  index: i
                };
              });
            }
            return [];
          }
        }
      },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            const value = context.parsed;
            const pct = totalWarehouseSales > 0 ? ((value / totalWarehouseSales) * 100).toFixed(1) : '0';
            return ` Ventas: ${currencyFormatter.format(value)} (${pct}%)`
          }
        }
      }
    }
  }

  return (
    <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
      {/* Evolución de ventas (2/3 de ancho en desktop) */}
      <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm xl:col-span-2">
        <h2 className="text-xl font-bold text-[#191c1e] mb-4">{chartTitle}</h2>
        <div className="h-[220px] sm:h-[280px] relative">
          {mounted ? (
            <Line data={lineData} options={lineOptions} />
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-[#747878]">Cargando gráfico…</div>
          )}
        </div>
      </div>

      {/* Ventas por almacén (1/3 de ancho en desktop) */}
      <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm">
        <h2 className="text-xl font-bold text-[#191c1e] mb-4">Por almacén</h2>
        <div className="h-[240px] sm:h-[280px] relative">
          {sortedWarehouseData.length === 0 ? (
            <div className="flex h-full items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
          ) : mounted ? (
            <Doughnut data={doughnutData} options={doughnutOptions} />
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-[#747878]">Cargando gráfico…</div>
          )}
        </div>
      </div>
    </div>
  )
}

// ------------------------------------------------------------
// 3. Bar Chart: Top Familias (Horizontal) - Componente Exportado
// ------------------------------------------------------------
interface DashboardFamilyChartProps {
  familyData: Array<{ cod_familia: string; family_name: string; total: number }>
}

export function DashboardFamilyChart({ familyData }: DashboardFamilyChartProps) {
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
  const familyColors = ['#206393', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1']
  
  const normalizedFamilies = familyData.map(f => ({
    ...f,
    total: Number(f.total) || 0
  }))
  const totalFamilySales = normalizedFamilies.reduce((acc, f) => acc + f.total, 0)

  const barData = {
    labels: normalizedFamilies.map(f => f.family_name || `Fam. ${f.cod_familia}`),
    datasets: [{
      label: 'Ventas (€)',
      data: normalizedFamilies.map(f => f.total),
      backgroundColor: normalizedFamilies.map((_, i) => familyColors[i % familyColors.length]),
      borderRadius: 4
    }]
  }

  const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y' as const,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            const value = context.parsed.x
            const pct = totalFamilySales > 0 ? ((value / totalFamilySales) * 100).toFixed(1) : '0'
            return ` ${context.label}: ${currencyFormatter.format(value)} (${pct}%)`
          }
        }
      }
    },
    scales: {
      x: { beginAtZero: true, grid: { color: '#e1e2e6' }, ticks: { color: '#747878', font: { size: 11 } } },
      y: { ticks: { color: '#191c1e', font: { size: 10, weight: 'bold' as const } } }
    }
  }

  return (
    <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between h-full">
      <div>
        <h2 className="text-xl font-bold text-[#191c1e] mb-3">Top familias</h2>
        <div className="h-[220px] relative">
          {normalizedFamilies.length === 0 ? (
            <div className="flex h-full items-center justify-center text-sm text-[#747878]">Sin datos en el período</div>
          ) : (
            <Bar data={barData} options={barOptions} />
          )}
        </div>
      </div>
      
      {/* Leyenda compacta */}
      <div className="mt-3 border-t border-[#e1e2e6] pt-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-1 text-sm">
          {normalizedFamilies.slice(0, 8).map((item, i) => {
            const pct = totalFamilySales > 0 ? ((item.total / totalFamilySales) * 100).toFixed(1) : '0.0'
            const color = familyColors[i % familyColors.length]
            const label = item.family_name || `Fam. ${item.cod_familia}`
            return (
              <div key={item.cod_familia} className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-[#f8f9fc]">
                <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: color }}></span>
                <div className="flex-1 min-w-0">
                  <div className="font-bold text-[#191c1e] truncate" title={label}>{label}</div>
                  <div className="text-xs text-[#747878]">
                    {currencyFormatter.format(item.total)} · <span className="font-bold text-[#206393]">{pct}%</span>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
