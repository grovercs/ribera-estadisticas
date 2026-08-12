'use client'

import { useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Calendar, Search, Loader2 } from 'lucide-react'

export default function PurchasesFilters() {
  const router = useRouter()
  const searchParams = useSearchParams()

  const [year, setYear] = useState(searchParams.get('year') || '2026')
  const [loading, setLoading] = useState(false)

  const handleFilter = (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    
    const params = new URLSearchParams()
    if (year !== 'all') {
      params.set('year', year)
    }

    router.push(`/dashboard/compras?${params.toString()}`)
    setTimeout(() => setLoading(false), 500)
  }

  return (
    <form onSubmit={handleFilter} className="flex items-center gap-3 rounded-2xl border border-slate-900 bg-slate-900/20 p-3 backdrop-blur-md">
      <div className="flex items-center space-x-2 text-slate-400">
        <Calendar className="h-4 w-4 text-indigo-400" />
        <span className="text-xs font-semibold text-slate-300">Año de Compra:</span>
      </div>

      <select
        value={year}
        onChange={(e) => setYear(e.target.value)}
        className="rounded-xl border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs text-slate-200 outline-none focus:border-indigo-500 font-semibold"
      >
        <option value="2026">2026 (Actual)</option>
        <option value="2025">2025</option>
        <option value="2024">2024</option>
        <option value="all">Últimos 3 Años (Todos)</option>
      </select>

      <button
        type="submit"
        disabled={loading}
        className="flex items-center space-x-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-1.5 text-xs font-semibold text-white shadow-md hover:shadow-indigo-500/20 hover:brightness-110 transition-all active:scale-[0.97]"
      >
        {loading ? (
          <Loader2 className="h-3.5 w-3.5 animate-spin" />
        ) : (
          <>
            <Search className="h-3.5 w-3.5" />
            <span>Filtrar</span>
          </>
        )}
      </button>
    </form>
  )
}
