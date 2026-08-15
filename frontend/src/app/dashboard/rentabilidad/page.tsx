import { createClient } from '@/lib/supabase/server'
import DashboardFilters from '../components/DashboardFilters'
import ProfitabilityStoreChart from '../components/ProfitabilityStoreChart'
import { TrendingUp, TrendingDown, Minus } from 'lucide-react'

export const dynamic = 'force-dynamic'

interface PageProps {
  searchParams: Promise<{
    year_from?: string
    month_from?: string
    year_to?: string
    month_to?: string
  }>
}

export default async function RentabilidadPage({ searchParams }: PageProps) {
  const resolvedSearchParams = await searchParams
  const yf = parseInt(resolvedSearchParams.year_from || '2026')
  const mf = parseInt(resolvedSearchParams.month_from || '1')
  const yt = parseInt(resolvedSearchParams.year_to || '2026')
  const mt = parseInt(resolvedSearchParams.month_to || '12')

  const supabase = await createClient()

  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() + 1

  // Si el rango final cae en el año actual, recortar al mes actual para no incluir meses futuros,
  // pero conservar siempre el año original.
  const effectiveMt = yt === currentYear ? Math.min(mt, currentMonth) : mt

  // Período anterior: mismo rango desplazado un año
  const prevYf = yf - 1
  const prevYt = yt - 1
  const prevMt = effectiveMt

  const isComparable = prevYf >= 2012 && (yt - yf < 3)
  const wasTrimmed = yt === currentYear && mt > currentMonth

  const [netMarginActRes, netMarginPrevRes, storeMarginsRes] = await Promise.all([
    supabase.rpc('get_dashboard_net_margins', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: effectiveMt }),
    supabase.rpc('get_dashboard_net_margins', { p_year_from: prevYf, p_month_from: mf, p_year_to: prevYt, p_month_to: prevMt }),
    supabase.rpc('get_store_dashboard_margins', { p_periodo: 'year' }),
  ])

  const netMarginAct = netMarginActRes.data?.[0] || {
    venta: 0,
    coste: 0,
    margen: 0,
    margen_porcentaje: 0,
  }

  const netMarginPrev = netMarginPrevRes.data?.[0] || {
    venta: 0,
    coste: 0,
    margen: 0,
    margen_porcentaje: 0,
  }

  // get_store_dashboard_margins devuelve un objeto JSON con year_rows
  let storeMarginsRaw: any = null
  try {
    const raw = storeMarginsRes.data
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
      storeMarginsRaw = raw
    } else if (typeof raw === 'string') {
      storeMarginsRaw = JSON.parse(raw)
    }
  } catch {
    storeMarginsRaw = null
  }

  const storeMargins = ((storeMarginsRaw?.year_rows || []) as any[]).map((s) => ({
    cod_almacen: String(s.cod_almacen || ''),
    venta: parseFloat(s.venta || 0),
    coste: parseFloat(s.coste || 0),
    margen: parseFloat(s.margen || 0),
    margen_porcentaje: parseFloat(s.margen_porcentaje || 0),
  }))

  const actVenta = parseFloat(netMarginAct.venta || 0)
  const actCoste = parseFloat(netMarginAct.coste || 0)
  const actMargen = parseFloat(netMarginAct.margen || 0)
  const actMargenPct = parseFloat(netMarginAct.margen_porcentaje || 0)

  const prevVenta = parseFloat(netMarginPrev.venta || 0)
  const prevCoste = parseFloat(netMarginPrev.coste || 0)
  const prevMargen = parseFloat(netMarginPrev.margen || 0)
  const prevMargenPct = parseFloat(netMarginPrev.margen_porcentaje || 0)

  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 })
  const numberFormatter = new Intl.NumberFormat('es-ES')
  const percentFormatter = new Intl.NumberFormat('es-ES', { style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2 })

  const renderVariance = (
    current: number,
    previous: number,
    format: 'currency' | 'percent',
    higherIsBetter = true
  ) => {
    if (!isComparable) {
      return (
        <span className="text-xs font-semibold text-[#747878] block mt-1">
          ⚠️ Sin comparación interanual
        </span>
      )
    }

    const diff = current - previous
    const pct = previous > 0 ? (diff / previous) * 100 : 0

    let isFavorable = diff >= 0
    if (!higherIsBetter) {
      isFavorable = diff <= 0
    }

    const colorClass = isFavorable ? 'text-emerald-600' : 'text-red-600'
    const Icon = isFavorable ? TrendingUp : TrendingDown

    if (diff === 0) {
      return (
        <span className="text-xs font-semibold text-[#747878] flex items-center gap-1 mt-1">
          <Minus className="h-3 w-3" /> Sin cambios vs año ant.
        </span>
      )
    }

    let diffText = ''
    if (format === 'currency') {
      diffText = (diff > 0 ? '+' : '') + currencyFormatter.format(diff)
    } else {
      diffText = (diff > 0 ? '+' : '') + diff.toFixed(2) + ' p.p.'
    }

    return (
      <span className={`text-xs font-bold flex items-center gap-1 mt-1 ${colorClass}`}>
        <Icon className="h-3.5 w-3.5 shrink-0" />
        <span>{diffText} ({pct > 0 ? '+' : ''}{pct.toFixed(1)}%)</span>
      </span>
    )
  }

  return (
    <div className="space-y-6">

      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-[#e1e2e6] pb-4">
        <div>
          <h1 className="text-3xl md:text-4xl font-black text-[#191c1e] tracking-tight">Rentabilidad</h1>
          <p className="text-base text-[#747878] mt-1">Márgenes, coste de ventas y rentabilidad por período</p>
          {wasTrimmed && (
            <p className="text-sm text-amber-700 font-semibold mt-1">
              ⚠️ Período ajustado a {currentMonth.toString().padStart(2, '0')}/{currentYear} por incluir meses futuros
            </p>
          )}
        </div>
        <DashboardFilters basePath="/dashboard/rentabilidad" />
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Venta Neta</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(actVenta)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actVenta, prevVenta, 'currency', true)}
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Coste de Ventas</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(actCoste)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actCoste, prevCoste, 'currency', false)}
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Margen Bruto</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(actMargen)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actMargen, prevMargen, 'currency', true)}
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Margen %</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{actMargenPct.toFixed(2)} %</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actMargenPct, prevMargenPct, 'percent', true)}
          </div>
        </div>

      </div>

      {/* Comparativa período actual vs año anterior */}
      <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm">
        <div className="mb-4">
          <h2 className="text-xl font-bold text-[#191c1e]">Comparativa interanual</h2>
          {wasTrimmed && (
            <p className="text-sm text-[#747878] font-medium mt-0.5">
              Período efectivo: {yf}-{mf.toString().padStart(2, '0')} → {yt}-{effectiveMt.toString().padStart(2, '0')} vs {prevYf}-{mf.toString().padStart(2, '0')} → {prevYt}-{prevMt.toString().padStart(2, '0')}
            </p>
          )}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Concepto</th>
                <th className="py-2.5 px-3 text-right">Período actual</th>
                <th className="py-2.5 px-3 text-right">Año anterior</th>
                <th className="py-2.5 px-3 text-right">Diferencia</th>
                <th className="py-2.5 px-3 text-right">Var. %</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {[
                { label: 'Venta neta', act: actVenta, prev: prevVenta, format: 'currency' as const },
                { label: 'Coste de ventas', act: actCoste, prev: prevCoste, format: 'currency' as const },
                { label: 'Margen bruto', act: actMargen, prev: prevMargen, format: 'currency' as const },
                { label: 'Margen %', act: actMargenPct, prev: prevMargenPct, format: 'percent' as const },
              ].map((row) => {
                const diffValue = isComparable ? row.act - row.prev : null
                const pct = isComparable && row.prev !== 0 ? ((row.act - row.prev) / row.prev) * 100 : null
                const isFavorable = row.label === 'Coste de ventas' ? (diffValue ?? 0) <= 0 : (diffValue ?? 0) >= 0
                const colorClass = !isComparable ? 'text-[#747878]' : isFavorable ? 'text-emerald-600' : 'text-red-600'
                const sign = diffValue != null && diffValue > 0 ? '+' : ''
                const formattedDiff = diffValue != null
                  ? (row.format === 'currency'
                      ? `${sign}${currencyFormatter.format(diffValue)}`
                      : `${sign}${diffValue.toFixed(2)} p.p.`)
                  : '—'

                return (
                  <tr key={row.label} className="hover:bg-[#f8f9fc]">
                    <td className="py-3 px-3 font-semibold text-[#191c1e]">{row.label}</td>
                    <td className="py-3 px-3 text-right font-bold text-[#191c1e]">
                      {row.format === 'currency' ? currencyFormatter.format(row.act) : `${row.act.toFixed(2)} %`}
                    </td>
                    <td className="py-3 px-3 text-right font-medium text-[#747878]">
                      {row.format === 'currency' ? currencyFormatter.format(row.prev) : `${row.prev.toFixed(2)} %`}
                    </td>
                    <td className={`py-3 px-3 text-right font-bold ${colorClass}`}>
                      {formattedDiff}
                    </td>
                    <td className={`py-3 px-3 text-right font-bold ${colorClass}`}>
                      {isComparable && pct != null ? `${pct > 0 ? '+' : ''}${pct.toFixed(1)} %` : '—'}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Rentabilidad por almacén */}
      <ProfitabilityStoreChart
        storeMargins={storeMargins}
        subtitle="Margen por almacén — Año actual (la RPC no permite filtrar por rango personalizado)"
      />

    </div>
  )
}
