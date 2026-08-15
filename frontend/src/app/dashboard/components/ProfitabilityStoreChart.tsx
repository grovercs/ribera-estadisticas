'use client'

import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import { Bar } from 'react-chartjs-2'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

interface StoreMargin {
  cod_almacen: string
  venta: number
  coste: number
  margen: number
  margen_porcentaje: number
}

interface ProfitabilityStoreChartProps {
  storeMargins: StoreMargin[]
  subtitle?: string
}

export default function ProfitabilityStoreChart({ storeMargins, subtitle }: ProfitabilityStoreChartProps) {
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
  const percentFormatter = new Intl.NumberFormat('es-ES', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 })

  const getStoreName = (code: string) => {
    if (code === '1') return 'Pont de Suert'
    if (code === '2') return 'Vielha'
    return `Otros (Alm. ${code})`
  }

  const sorted = [...storeMargins].sort((a, b) => {
    if (a.cod_almacen === '1') return -1
    if (b.cod_almacen === '1') return 1
    if (a.cod_almacen === '2') return -1
    if (b.cod_almacen === '2') return 1
    return 0
  })

  const labels = sorted.map(s => getStoreName(s.cod_almacen))

  const barData = {
    labels,
    datasets: [
      {
        label: 'Venta (€)',
        data: sorted.map(s => s.venta),
        backgroundColor: '#206393',
        borderRadius: 4,
      },
      {
        label: 'Coste (€)',
        data: sorted.map(s => s.coste),
        backgroundColor: '#ef4444',
        borderRadius: 4,
      },
      {
        label: 'Margen (€)',
        data: sorted.map(s => s.margen),
        backgroundColor: '#10b981',
        borderRadius: 4,
      },
    ],
  }

  const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top' as const,
        labels: { color: '#191c1e', font: { size: 12, weight: 'bold' as const } },
      },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            const val = context.parsed.y
            return `${context.dataset.label}: ${currencyFormatter.format(val)}`
          },
        },
      },
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#747878', font: { size: 12, weight: 'bold' as const } } },
      y: { beginAtZero: true, grid: { color: '#e1e2e6' }, ticks: { color: '#747878', font: { size: 11 }, callback: (value: any) => `${(value / 1000).toFixed(0)}k €` } },
    },
  }

  return (
    <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm">
      <div className="mb-4">
        <h2 className="text-xl font-bold text-[#191c1e]">Rentabilidad por almacén</h2>
        {subtitle && <p className="text-sm text-[#747878] font-medium mt-0.5">{subtitle}</p>}
      </div>
      <div className="h-[220px] relative">
        {sorted.length === 0 ? (
          <div className="flex h-full items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        ) : (
          <Bar data={barData} options={barOptions} />
        )}
      </div>
      <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {sorted.length === 0 && (
          <div className="col-span-full flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {sorted.map((s) => (
          <div key={s.cod_almacen} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6]">
            <div className="text-sm font-bold text-[#191c1e]">{getStoreName(s.cod_almacen)}</div>
            <div className="text-xs text-[#747878] mt-1">Margen: <span className="font-bold text-emerald-700">{percentFormatter.format(s.margen_porcentaje / 100)}</span></div>
            <div className="text-xs text-[#747878]">Venta: {currencyFormatter.format(s.venta)}</div>
            <div className="text-xs text-[#747878]">Coste: {currencyFormatter.format(s.coste)}</div>
          </div>
        ))}
      </div>
    </div>
  )
}
