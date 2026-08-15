'use client'

import { useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Loader2 } from 'lucide-react'

interface DashboardFiltersProps {
  basePath?: string
}

export default function DashboardFilters({ basePath = '/dashboard' }: DashboardFiltersProps) {
  const router = useRouter()
  const searchParams = useSearchParams()

  const [yf, setYf] = useState(searchParams.get('year_from') || '2026')
  const [mf, setMf] = useState(searchParams.get('month_from') || '1')
  const [yt, setYt] = useState(searchParams.get('year_to') || '2026')
  const [mt, setMt] = useState(searchParams.get('month_to') || '12')
  const [loading, setLoading] = useState(false)

  const years = Array.from({ length: 15 }, (_, i) => (2012 + i).toString())
  const months = Array.from({ length: 12 }, (_, i) => (i + 1).toString())

  const handleFilter = (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)

    const params = new URLSearchParams()
    params.set('year_from', yf)
    params.set('month_from', mf)
    params.set('year_to', yt)
    params.set('month_to', mt)

    const hideStock = searchParams.get('hide_no_stock')
    if (hideStock) {
      params.set('hide_no_stock', hideStock)
    }

    router.push(`${basePath}?${params.toString()}`)
    setTimeout(() => setLoading(false), 650)
  }

  return (
    <form onSubmit={handleFilter} className="flex flex-wrap gap-2 items-center">
      <div className="flex items-center gap-1">
        <label className="text-sm font-semibold text-[#747878] uppercase">Desde</label>
        <select
          value={yf}
          onChange={(e) => setYf(e.target.value)}
          className="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-base text-[#191c1e] outline-none focus:ring-1 focus:ring-[#206393]"
        >
          {years.map(y => <option key={y} value={y}>{y}</option>)}
        </select>
        <select
          value={mf}
          onChange={(e) => setMf(e.target.value)}
          className="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-base text-[#191c1e] outline-none focus:ring-1 focus:ring-[#206393]"
        >
          {months.map(m => <option key={m} value={m}>{m.padStart(2, '0')}</option>)}
        </select>
      </div>

      <span className="text-[#747878] font-semibold">→</span>

      <div className="flex items-center gap-1">
        <label className="text-sm font-semibold text-[#747878] uppercase">Hasta</label>
        <select
          value={yt}
          onChange={(e) => setYt(e.target.value)}
          className="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-base text-[#191c1e] outline-none focus:ring-1 focus:ring-[#206393]"
        >
          {years.map(y => <option key={y} value={y}>{y}</option>)}
        </select>
        <select
          value={mt}
          onChange={(e) => setMt(e.target.value)}
          className="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-base text-[#191c1e] outline-none focus:ring-1 focus:ring-[#206393]"
        >
          {months.map(m => <option key={m} value={m}>{m.padStart(2, '0')}</option>)}
        </select>
      </div>

      <button
        type="submit"
        disabled={loading}
        className="p-2 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors disabled:opacity-50 active:scale-[0.98]"
        title="Aplicar"
      >
        {loading ? (
          <Loader2 className="h-4.5 w-4.5 animate-spin" />
        ) : (
          /* Icono de búsqueda SVG */
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor" className="w-4 h-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.637z" />
          </svg>
        )}
      </button>
    </form>
  )
}
