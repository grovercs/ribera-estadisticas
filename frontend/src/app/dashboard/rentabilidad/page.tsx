import { createClient } from '@/lib/supabase/server'
import DashboardFilters from '../components/DashboardFilters'
import { 
  Percent, 
  DollarSign, 
  TrendingUp, 
  ShieldCheck, 
  ShoppingCart, 
  AlertCircle 
} from 'lucide-react'

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
  const resolvedParams = await searchParams
  const yf = parseInt(resolvedParams.year_from || '2026')
  const mf = parseInt(resolvedSearchParams_mf(resolvedParams))
  const yt = parseInt(resolvedParams.year_to || '2026')
  const mt = parseInt(resolvedParams.month_to || '12')

  function resolvedSearchParams_mf(p: any) {
    return p.month_from || '1'
  }

  const supabase = await createClient()

  // Ejecutar RPCs de KPIs de ventas y compras en paralelo
  const [salesKpiRes, purchasesKpiRes] = await Promise.all([
    supabase.rpc('get_dashboard_kpis', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_purchases_kpis', { p_year: yf })
  ])

  const salesKpi = salesKpiRes.data?.[0] || { total_sales: 0, total_cost: 0, gross_profit: 0 }
  const purchasesKpi = purchasesKpiRes.data?.[0] || { total_compras: 0 }

  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })

  const totalSales = parseFloat(salesKpi.total_sales || 0)
  const totalCostCOGS = parseFloat(salesKpi.total_cost || 0)
  const grossProfit = parseFloat(salesKpi.gross_profit || 0)
  const marginPct = totalSales > 0 ? (grossProfit / totalSales) * 100 : 0
  const totalCompras = parseFloat(purchasesKpi.total_compras || 0)

  return (
    <div className="space-y-8">
      {/* Header & Filtros */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <Percent className="h-6 w-6 text-emerald-500" />
            <span>Análisis de Rentabilidad y Margen Bruto</span>
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Evaluación del beneficio bruto basado en el Coste Real de Ventas (COGS)
          </p>
        </div>
        <DashboardFilters />
      </div>

      {/* Banner Explicativo de Semántica Financiera */}
      <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 backdrop-blur-md flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-start space-x-3">
          <ShieldCheck className="h-5 w-5 text-emerald-400 shrink-0 mt-0.5" />
          <div className="text-xs space-y-1">
            <span className="font-bold text-emerald-300">Semántica Financiera Garantizada:</span>
            <p className="text-slate-300">
              El <strong className="text-white">Margen Bruto ({grossProfit > 0 ? '+' : ''}{grossProfit.toFixed(2)} €)</strong> se calcula multiplicando cada artículo vendido por su <strong className="text-white">precio de coste real en ERP</strong>. 
              No se resta directamente la cifra total de compras del período (<strong className="text-indigo-400">{currencyFormatter.format(totalCompras)}</strong>), evitando distorsiones por variaciones de inventario.
            </p>
          </div>
        </div>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        
        {/* Ventas Totales */}
        <div className="rounded-2xl border border-slate-900 bg-slate-900/20 p-5 backdrop-blur-md">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-400">Ventas Facturadas</span>
            <DollarSign className="h-5 w-5 text-blue-400" />
          </div>
          <div className="mt-3 flex flex-col">
            <span className="text-2xl font-bold text-white">{currencyFormatter.format(totalSales)}</span>
            <span className="text-[10px] text-slate-500 mt-1">Base imponible sin impuestos</span>
          </div>
        </div>

        {/* Coste Real de Ventas */}
        <div className="rounded-2xl border border-slate-900 bg-slate-900/20 p-5 backdrop-blur-md">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-400">Coste de Ventas (COGS)</span>
            <ShoppingCart className="h-5 w-5 text-amber-400" />
          </div>
          <div className="mt-3 flex flex-col">
            <span className="text-2xl font-bold text-white">{currencyFormatter.format(totalCostCOGS)}</span>
            <span className="text-[10px] text-slate-500 mt-1">Coste ERP real de lo vendido</span>
          </div>
        </div>

        {/* Margen Bruto */}
        <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 backdrop-blur-md">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-emerald-300">Margen Bruto (€)</span>
            <TrendingUp className="h-5 w-5 text-emerald-400" />
          </div>
          <div className="mt-3 flex flex-col">
            <span className="text-2xl font-bold text-emerald-400">{currencyFormatter.format(grossProfit)}</span>
            <span className="text-[10px] text-emerald-500/80 mt-1">Ventas - COGS</span>
          </div>
        </div>

        {/* Margen % */}
        <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 backdrop-blur-md">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-emerald-300">Margen Comercial (%)</span>
            <Percent className="h-5 w-5 text-emerald-400" />
          </div>
          <div className="mt-3 flex flex-col">
            <span className="text-2xl font-bold text-emerald-400">{marginPct.toFixed(2)} %</span>
            <span className="text-[10px] text-emerald-500/80 mt-1">% sobre Facturación Total</span>
          </div>
        </div>

      </div>

      {/* Comparativa con Compras Aprovisionadas */}
      <div className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md space-y-4">
        <h3 className="text-sm font-bold text-slate-200">Comparativa: Compras a Proveedores vs Coste de Ventas</h3>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-4 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-indigo-400">Compras Aprovisionadas del Período</span>
              <span className="text-base font-bold text-white">{currencyFormatter.format(totalCompras)}</span>
            </div>
            <p className="text-[11px] text-slate-400">
              Corresponde a la suma de albaranes y facturas recibidas de proveedores durante el período. Refleja la inversión en stock y aprovisionamiento.
            </p>
          </div>

          <div className="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-amber-400">Coste de Ventas Real (COGS)</span>
              <span className="text-base font-bold text-white">{currencyFormatter.format(totalCostCOGS)}</span>
            </div>
            <p className="text-[11px] text-slate-400">
              Corresponde únicamente al coste de adquisición de los productos efectivamente vendidos a clientes en las facturas expedidas.
            </p>
          </div>
        </div>
      </div>

    </div>
  )
}
