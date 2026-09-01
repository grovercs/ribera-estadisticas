import { createClient } from '@/lib/supabase/server'
import DashboardCharts from '../components/DashboardCharts'
import PurchasesRankings from '../components/PurchasesRankings'
import { TrendingUp, TrendingDown, Minus } from 'lucide-react'

export const dynamic = 'force-dynamic'

interface PageProps {
  searchParams: Promise<{
    year?: string
    month?: string
  }>
}

export default async function ComprasPage({ searchParams }: PageProps) {
  const resolvedSearchParams = await searchParams
  const year = parseInt(resolvedSearchParams.year || '2026')
  const month = parseInt(resolvedSearchParams.month || '7')

  const supabase = await createClient()

  const [
    kpisRes,
    evolutionRes,
    warehouseRes,
    familiesRes,
    suppliersRes,
    taxRes,
    payablesRes,
  ] = await Promise.all([
    supabase.rpc('get_purchases_kpis', { p_year: year, p_month: month }),
    supabase.rpc('get_purchases_evolution'),
    supabase.rpc('get_purchases_by_warehouse', { p_year: year }),
    supabase.rpc('get_purchases_top_families', { p_limit: 10, p_year: year }),
    supabase.rpc('get_purchases_top_suppliers', { p_limit: 10, p_year: year }),
    supabase.rpc('get_purchases_tax_summary', { p_year: year }),
    supabase.rpc('get_store_dashboard_payables'),
  ])

  const kpis = kpisRes.data?.[0] || {
    year,
    total_compras: 0,
    documentos_count: 0,
    proveedores_count: 0,
    ticket_medio: 0,
    total_compras_prev_year: 0,
    var_pct_interanual: 0,
  }

  const evolutionRaw = evolutionRes.data || []
  const warehouseRaw = warehouseRes.data || []
  const families = familiesRes.data || []
  const suppliers = suppliersRes.data || []

  let taxSummary: any[] = []
  try {
    const raw = taxRes.data?.[0]?.get_purchases_tax_summary
    taxSummary = typeof raw === 'string' ? JSON.parse(raw) : raw || []
  } catch {
    taxSummary = []
  }

  // get_store_dashboard_payables() devuelve el objeto JSON directamente en data
  let payables: any = { periodos: [], total_importe: null, total_ops: null }
  try {
    const raw = payablesRes.data
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
      payables = raw
    } else if (typeof raw === 'string') {
      payables = JSON.parse(raw)
    }
  } catch {
    payables = { periodos: [], total_importe: null, total_ops: null }
  }

  const totalPurchases = parseFloat(kpis.total_compras || 0)
  const prevPurchases = parseFloat(kpis.total_compras_prev_year || 0)
  const varPct = parseFloat(kpis.var_pct_interanual || 0)
  const ticketMedio = parseFloat(kpis.ticket_medio || 0)
  const documentos = parseInt(kpis.documentos_count || 0)
  const proveedores = parseInt(kpis.proveedores_count || 0)

  // Convertir evolución de compras al formato genérico de DashboardCharts
  const evolutionData = evolutionRaw.map((d: any) => ({
    year: d.year,
    month: d.month,
    total_sales: parseFloat(d.total_purchases || 0),
  }))

  // Convertir almacenes de compras al formato genérico
  const warehouseData = warehouseRaw.map((w: any) => ({
    cod_almacen: String(w.cod_almacen),
    total_sales: parseFloat(w.total_purchases || 0),
  }))

  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 })
  const numberFormatter = new Intl.NumberFormat('es-ES')
  const percentFormatter = new Intl.NumberFormat('es-ES', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 })

  // Null handling explícito: diferenciar dato real 0 de campo inexistente
  const totalPayables = payables.total_importe != null ? parseFloat(payables.total_importe) : null

  const orderedPeriods = ['Mes Actual', 'Mes Siguiente', 'En 2 meses', 'En 3 meses']
  const periodos = orderedPeriods
    .map((label) => {
      const found = (payables.periodos || []).find((p: any) => p?.periodo === label)
      if (!found) return null
      return {
        periodo: found.periodo,
        importe: found.importe != null ? parseFloat(found.importe) : null,
      }
    })
    .filter((p): p is { periodo: string; importe: number | null } => p !== null)

  const years = Array.from({ length: 15 }, (_, i) => 2012 + i)
  const months = Array.from({ length: 12 }, (_, i) => i + 1)

  return (
    <div className="space-y-6">

      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-[#e1e2e6] pb-4">
        <div>
          <h1 className="text-3xl md:text-4xl font-black text-[#191c1e] tracking-tight">Análisis de Compras</h1>
          <p className="text-base text-[#747878] mt-1">Albaranes, facturas de proveedor, pagos pendientes y desglose impositivo</p>
        </div>

        {/* Filtro simple año/mes */}
        <form method="GET" action="/dashboard/compras" className="flex flex-wrap gap-2 items-center">
          <div className="flex items-center gap-1">
            <label className="text-sm font-semibold text-[#747878] uppercase">Año</label>
            <select name="year" defaultValue={year} className="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-base text-[#191c1e] outline-none focus:ring-1 focus:ring-[#206393]">
              {years.map(y => <option key={y} value={y}>{y}</option>)}
            </select>
          </div>
          <div className="flex items-center gap-1">
            <label className="text-sm font-semibold text-[#747878] uppercase">Mes</label>
            <select name="month" defaultValue={month} className="px-2 py-1.5 bg-white border border-[#e1e2e6] rounded-lg text-base text-[#191c1e] outline-none focus:ring-1 focus:ring-[#206393]">
              {months.map(m => <option key={m} value={m}>{m.toString().padStart(2, '0')}</option>)}
            </select>
          </div>
          <button type="submit" className="p-2 bg-[#206393] text-white rounded-lg hover:bg-[#1a5078] transition-colors active:scale-[0.98]" title="Aplicar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor" className="w-4 h-4">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.637z" />
            </svg>
          </button>
        </form>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Compras {month.toString().padStart(2, '0')}/{year}</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(totalPurchases)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            <span className={`text-xs font-bold flex items-center gap-1 mt-1 ${varPct >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
              {varPct >= 0 ? <TrendingUp className="h-3.5 w-3.5 shrink-0" /> : <TrendingDown className="h-3.5 w-3.5 shrink-0" />}
              <span>{varPct > 0 ? '+' : ''}{varPct.toFixed(2)} % vs {year - 1}</span>
            </span>
            <span className="text-xs text-[#747878] block mt-0.5 font-semibold">{currencyFormatter.format(prevPurchases)} año ant.</span>
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Ticket Medio</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(ticketMedio)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            <span className="text-xs text-[#747878] font-semibold">Por documento de compra</span>
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Documentos</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{numberFormatter.format(documentos)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            <span className="text-xs text-[#747878] font-semibold">Facturas y albaranes</span>
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Proveedores</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{numberFormatter.format(proveedores)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            <span className="text-xs text-[#747878] font-semibold">Con movimiento en el mes</span>
          </div>
        </div>

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Pagos Pendientes</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{totalPayables != null ? currencyFormatter.format(totalPayables) : '—'}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7] space-y-1">
            {periodos.map((p) => (
              <div key={p.periodo} className="flex justify-between text-xs text-[#747878] font-semibold">
                <span>{p.periodo}</span>
                <span className="text-[#191c1e]">{p.importe != null ? currencyFormatter.format(p.importe) : '—'}</span>
              </div>
            ))}
          </div>
        </div>

      </div>

      {/* Gráficos */}
      <DashboardCharts
        evolutionData={evolutionData}
        warehouseData={warehouseData}
        chartTitle="Evolución de compras"
        comparisonLabel="Histórico (€)"
      />

      {/* Rankings de compras + Payables */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <PurchasesRankings
          families={families}
          suppliers={suppliers}
          taxSummary={taxSummary}
          totalPurchases={totalPurchases}
        />

        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm">
          <h2 className="text-xl font-bold text-[#191c1e] mb-4">Pagos pendientes a proveedores</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm md:text-base text-left">
              <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
                <tr>
                  <th className="py-2.5 px-3">Periodo</th>
                  <th className="py-2.5 px-3 text-right">Importe</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f2f3f7]">
                {periodos.map((p) => (
                  <tr key={p.periodo} className="hover:bg-[#f8f9fc]">
                    <td className="py-3 px-3 font-semibold text-[#191c1e]">{p.periodo}</td>
                    <td className="py-3 px-3 text-right font-bold text-[#206393]">{p.importe != null ? currencyFormatter.format(p.importe) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="mt-4 pt-3 border-t border-[#e1e2e6]">
            <div className="flex justify-between items-center mb-2">
              <span className="text-sm font-bold text-[#191c1e]">Total pendiente</span>
              <span className="text-xl font-black text-amber-700">{totalPayables != null ? currencyFormatter.format(totalPayables) : '—'}</span>
            </div>
            <div className="text-xs text-[#747878] font-medium">
              {
                (() => {
                  const sumaPeriodos = periodos.reduce((acc, p) => p.importe != null ? acc + p.importe : acc, 0)
                  const tieneDatos = periodos.some((p) => p.importe != null)
                  return tieneDatos
                    ? `Suma de vencimientos activos: ${currencyFormatter.format(sumaPeriodos)}`
                    : 'Suma de vencimientos activos: —'
                })()
              }
            </div>
          </div>
        </div>
      </div>

    </div>
  )
}
