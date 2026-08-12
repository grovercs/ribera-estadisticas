'use client'

import { useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Calendar, Search, Loader2 } from 'lucide-react'

export default function DashboardFilters() {
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

    router.push(`/dashboard?${params.toString()}`)
    setTimeout(() => setLoading(false), 800)
  }

  return (
    <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-900 bg-slate-900/20 p-4 backdrop-blur-md">
      <div className="flex items-center space-x-2 text-slate-400">
        <Calendar className="h-4 w-4" />
        <span className="text-xs font-semibold uppercase tracking-wider">Período:</span>
      </div>

      <div className="flex items-center gap-2">
        {/* Desde */}
        <select
          value={yf}
          onChange={(e) => setYf(e.target.value)}
          className="rounded-xl border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs text-slate-200 outline-none focus:border-indigo-500"
        >
          {years.map(y => <option key={y} value={y}>{y}</option>)}
        </select>
        <select
          value={mf}
          onChange={(e) => setMf(e.target.value)}
          className="rounded-xl border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs text-slate-200 outline-none focus:border-indigo-500"
        >
          {months.map(m => <option key={m} value={m}>{m.padStart(2, '0')}</option>)}
        </select>

        <span className="text-xs text-slate-500">hasta</span>

        {/* Hasta */}
        <select
          value={yt}
          onChange={(e) => setYt(e.target.value)}
          className="rounded-xl border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs text-slate-200 outline-none focus:border-indigo-500"
        >
          {years.map(y => <option key={y} value={y}>{y}</option>)}
        </select>
        <select
          value={mt}
          onChange={(e) => setMt(e.target.value)}
          className="rounded-xl border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs text-slate-200 outline-none focus:border-indigo-500"
        >
          {months.map(m => <option key={m} value={m}>{m.padStart(2, '0')}</option>)}
        </select>
      </div>

      <button
        type="submit"
        disabled={loading}
        className="flex items-center space-x-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-md hover:shadow-indigo-500/20 hover:brightness-110 transition-all duration-200 active:scale-[0.97]"
      >
        {loading ? (
          <Loader2 className="h-3.5 w-3.5 animate-spin" />
        ) : (
          <>
            <Search className="h-3.5 w-3.5" />
            <span>Consultar</span>
          </>
        )}
      </button>
    </form>
  )
}
