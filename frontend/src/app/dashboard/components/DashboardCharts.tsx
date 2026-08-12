'use client'

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
  Filler
} from 'chart.js'
import { Line, Bar } from 'react-chartjs-2'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

interface DashboardChartsProps {
  evolutionData: Array<{ year: number; month: number; total_sales: number; prev_total_sales: number }>
  warehouseData: Array<{ cod_almacen: string; total_sales: number }>
}

export default function DashboardCharts({ evolutionData, warehouseData }: DashboardChartsProps) {
  // 1. Configuración Gráfico Evolución
  const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
  
  const evolutionLabels = evolutionData.map(d => `${monthNames[d.month - 1]} ${d.year}`)
  const currentSales = evolutionData.map(d => d.total_sales)
  const prevSales = evolutionData.map(d => d.prev_total_sales)

  const lineData = {
    labels: evolutionLabels,
    datasets: [
      {
        label: 'Período Actual',
        data: currentSales,
        borderColor: 'rgb(99, 102, 241)',
        backgroundColor: 'rgba(99, 102, 241, 0.1)',
        fill: true,
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 3,
      },
      {
        label: 'Año Anterior',
        data: prevSales,
        borderColor: 'rgba(148, 163, 184, 0.5)',
        backgroundColor: 'transparent',
        borderDash: [5, 5],
        tension: 0.3,
        borderWidth: 1.5,
        pointRadius: 2,
      }
    ]
  }

  const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top' as const,
        labels: {
          color: '#94a3b8',
          font: { size: 11 }
        }
      },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            return `${context.dataset.label}: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(context.parsed.y)}`
          }
        }
      }
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#64748b', font: { size: 10 } }
      },
      y: {
        grid: { color: 'rgba(51, 65, 85, 0.2)' },
        ticks: { 
          color: '#64748b', 
          font: { size: 10 },
          callback: (value: any) => `${(value / 1000).toFixed(0)}k €`
        }
      }
    }
  }

  // 2. Configuración Gráfico Almacenes
  const barLabels = warehouseData.map(w => `Alm. ${w.cod_almacen}`)
  const barSales = warehouseData.map(w => w.total_sales)

  const barData = {
    labels: barLabels,
    datasets: [
      {
        label: 'Ventas por Almacén',
        data: barSales,
        backgroundColor: 'rgba(129, 140, 248, 0.85)',
        hoverBackgroundColor: 'rgba(99, 102, 241, 1)',
        borderRadius: 8,
        borderWidth: 0,
        barPercentage: 0.6,
      }
    ]
  }

  const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            return `Ventas: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(context.parsed.y)}`
          }
        }
      }
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#64748b', font: { size: 10 } }
      },
      y: {
        grid: { color: 'rgba(51, 65, 85, 0.2)' },
        ticks: { 
          color: '#64748b', 
          font: { size: 10 },
          callback: (value: any) => `${(value / 1000).toFixed(0)}k €`
        }
      }
    }
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      {/* Evolución temporal */}
      <div id="evolution" className="lg:col-span-2 rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
        <h3 className="text-sm font-semibold text-slate-300 mb-4">Evolución Comercial Histórica</h3>
        <div className="h-72 relative">
          <Line data={lineData} options={lineOptions} />
        </div>
      </div>

      {/* Almacenes */}
      <div id="warehouses" className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
        <h3 className="text-sm font-semibold text-slate-300 mb-4">Distribución por Almacén</h3>
        <div className="h-72 relative">
          {warehouseData.length === 0 ? (
            <div className="flex h-full items-center justify-center text-xs text-slate-500">Sin datos registrados</div>
          ) : (
            <Bar data={barData} options={barOptions} />
          )}
        </div>
      </div>
    </div>
  )
}
