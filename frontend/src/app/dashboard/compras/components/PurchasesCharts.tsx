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
  Filler,
  ArcElement
} from 'chart.js'
import { Line, Bar, Doughnut } from 'react-chartjs-2'

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

interface PurchasesChartsProps {
  evolutionData: Array<{ year: number; month: number; total_purchases: number; document_count: number }>
  warehouseData: Array<{ cod_almacen: number; almacen_nombre: string; total_purchases: number; pct_sobre_total: number }>
  topFamilies: Array<{ cod_familia: string; familia_nombre: string; total_purchases: number }>
}

export default function PurchasesCharts({
  evolutionData,
  warehouseData,
  topFamilies
}: PurchasesChartsProps) {
  const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })

  // 1. Line Chart Data (Evolución)
  const evolutionLabels = evolutionData.map(d => `${monthNames[d.month - 1]} ${d.year}`)
  const evolutionValues = evolutionData.map(d => d.total_purchases)

  const lineData = {
    labels: evolutionLabels,
    datasets: [
      {
        label: 'Compras Mensuales (€)',
        data: evolutionValues,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99, 102, 241, 0.12)',
        fill: true,
        tension: 0.35,
        borderWidth: 2.5,
        pointRadius: 3.5,
        pointBackgroundColor: '#6366f1',
      }
    ]
  }

  const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx: any) => `Compras: ${currencyFormatter.format(ctx.parsed.y)}`
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } },
      y: { 
        grid: { color: 'rgba(51, 65, 85, 0.25)' }, 
        ticks: { 
          color: '#64748b', 
          font: { size: 10 },
          callback: (val: any) => `${(val / 1000).toFixed(0)}k €` 
        } 
      }
    }
  }

  // 2. Bar Chart Data (Almacenes)
  const whLabels = warehouseData.map(w => w.almacen_nombre)
  const whValues = warehouseData.map(w => w.total_purchases)

  const barData = {
    labels: whLabels,
    datasets: [
      {
        label: 'Compras por Almacén (€)',
        data: whValues,
        backgroundColor: ['rgba(99, 102, 241, 0.85)', 'rgba(139, 92, 246, 0.85)'],
        hoverBackgroundColor: ['#4f46e5', '#7c3aed'],
        borderRadius: 8,
        barThickness: 32,
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
          label: (ctx: any) => `Compras: ${currencyFormatter.format(ctx.parsed.y)}`
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11, weight: 'bold' as const } } },
      y: { 
        grid: { color: 'rgba(51, 65, 85, 0.25)' }, 
        ticks: { 
          color: '#64748b', 
          font: { size: 10 },
          callback: (val: any) => `${(val / 1000).toFixed(0)}k €` 
        } 
      }
    }
  }

  // 3. Doughnut Chart (Top Familias)
  const famSlice = topFamilies.slice(0, 5)
  const famLabels = famSlice.map(f => f.familia_nombre)
  const famValues = famSlice.map(f => f.total_purchases)

  const doughnutData = {
    labels: famLabels,
    datasets: [
      {
        data: famValues,
        backgroundColor: [
          '#6366f1',
          '#8b5cf6',
          '#ec4899',
          '#f59e0b',
          '#10b981'
        ],
        borderWidth: 0,
      }
    ]
  }

  const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom' as const,
        labels: {
          color: '#cbd5e1',
          font: { size: 10 },
          padding: 10,
          boxWidth: 10
        }
      },
      tooltip: {
        callbacks: {
          label: (ctx: any) => `${ctx.label}: ${currencyFormatter.format(ctx.parsed)}`
        }
      }
    }
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      {/* Evolución Temporal */}
      <div className="lg:col-span-2 rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
        <h3 className="text-sm font-bold text-slate-200 mb-4">Evolución Mensual de Compras</h3>
        <div className="h-72 relative">
          <Line data={lineData} options={lineOptions} />
        </div>
      </div>

      {/* Distribución Almacenes */}
      <div className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md flex flex-col justify-between">
        <div>
          <h3 className="text-sm font-bold text-slate-200 mb-4">Compras por Almacén</h3>
          <div className="h-52 relative">
            <Bar data={barData} options={barOptions} />
          </div>
        </div>
        <div className="mt-4 pt-3 border-t border-slate-800/60 grid grid-cols-2 gap-2 text-center text-xs">
          {warehouseData.map(w => (
            <div key={w.cod_almacen} className="rounded-xl bg-slate-950/50 p-2">
              <div className="text-[10px] text-slate-500 font-semibold">{w.almacen_nombre}</div>
              <div className="text-sm font-bold text-indigo-400 mt-0.5">{w.pct_sobre_total} %</div>
              <div className="text-[10px] text-slate-400 mt-0.5">{currencyFormatter.format(w.total_purchases)}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
