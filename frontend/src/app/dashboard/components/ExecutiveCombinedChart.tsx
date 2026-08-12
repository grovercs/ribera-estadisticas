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
import { Line } from 'react-chartjs-2'

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

interface ExecutiveCombinedChartProps {
  salesEvolution: Array<{ year: number; month: number; total_sales: number }>
  purchasesEvolution: Array<{ year: number; month: number; total_purchases: number }>
}

export default function ExecutiveCombinedChart({
  salesEvolution,
  purchasesEvolution
}: ExecutiveCombinedChartProps) {
  const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']

  // Combinar timeline único
  const mapKey = (y: number, m: number) => `${y}-${m.toString().padStart(2, '0')}`
  const salesMap = new Map(salesEvolution.map(d => [mapKey(d.year, d.month), d.total_sales]))
  const purchasesMap = new Map(purchasesEvolution.map(d => [mapKey(d.year, d.month), d.total_purchases]))

  // Obtener claves únicas ordenadas
  const allKeys = Array.from(new Set([...salesMap.keys(), ...purchasesMap.keys()])).sort()

  const labels = allKeys.map(k => {
    const [y, m] = k.split('-')
    return `${monthNames[parseInt(m) - 1]} ${y}`
  })

  const salesValues = allKeys.map(k => salesMap.get(k) || 0)
  const purchasesValues = allKeys.map(k => purchasesMap.get(k) || 0)

  const data = {
    labels,
    datasets: [
      {
        label: 'Ventas Mensuales (€)',
        data: salesValues,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37, 99, 235, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2.5,
        pointRadius: 3,
        pointBackgroundColor: '#2563eb',
      },
      {
        label: 'Compras Mensuales (€)',
        data: purchasesValues,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99, 102, 241, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2.5,
        pointRadius: 3,
        pointBackgroundColor: '#6366f1',
      }
    ]
  }

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top' as const,
        labels: {
          color: '#cbd5e1',
          font: { size: 12, weight: 'bold' as const },
          usePointStyle: true,
          padding: 20
        }
      },
      tooltip: {
        mode: 'index' as const,
        intersect: false,
        backgroundColor: '#0f172a',
        titleColor: '#f8fafc',
        bodyColor: '#cbd5e1',
        borderColor: '#334155',
        borderWidth: 1,
        padding: 12,
        callbacks: {
          label: (context: any) => {
            const val = context.parsed.y
            const formatted = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(val)
            return `${context.dataset.label}: ${formatted}`
          }
        }
      }
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#64748b', font: { size: 11 } }
      },
      y: {
        grid: { color: 'rgba(51, 65, 85, 0.25)' },
        ticks: { 
          color: '#64748b', 
          font: { size: 11 },
          callback: (value: any) => `${(value / 1000).toFixed(0)}k €`
        }
      }
    }
  }

  return (
    <div className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
        <div>
          <h3 className="text-base font-bold text-slate-200">Evolución Comparativa Ventas vs Compras</h3>
          <p className="text-xs text-slate-500">Mismo eje temporal para análisis de estacionalidad y aprovisionamientos</p>
        </div>
        <div className="flex items-center space-x-3 text-xs">
          <span className="flex items-center space-x-1.5 font-semibold text-blue-400">
            <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
            <span>Ventas</span>
          </span>
          <span className="flex items-center space-x-1.5 font-semibold text-indigo-400">
            <span className="h-2.5 w-2.5 rounded-full bg-indigo-500" />
            <span>Compras</span>
          </span>
        </div>
      </div>
      <div className="h-80 relative">
        <Line data={data} options={options} />
      </div>
    </div>
  )
}
