import { createClient } from '@/lib/supabase/server'
import DashboardFilters from './components/DashboardFilters'
import ExecutiveCombinedChart from './components/ExecutiveCombinedChart'
import Link from 'next/link'
import { 
  DollarSign, 
  ShoppingCart, 
  Percent, 
  CreditCard, 
  Tag, 
  TrendingUp, 
  ChevronRight, 
  Truck, 
  ShieldCheck 
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

export default async function DashboardPage({ searchParams }: PageProps) {
  const resolvedSearchParams = await searchParams
  const yf = parseInt(resolvedSearchParams.year_from || '2026')
  const mf = parseInt(resolvedSearchParams.month_from || '1')
  const yt = parseInt(resolvedSearchParams.year_to || '2026')
  const mt = parseInt(resolvedSearchParams.month_to || '12')

  const supabase = await createClient()

  // Ejecutar RPCs de Ventas, Compras y Cartera en paralelo
  const [
    salesKpiRes,
    salesEvolutionRes,
    purchasesKpiRes,
    purchasesEvolutionRes,
    receivablesSummaryRes
  ] = await Promise.all([
    supabase.rpc('get_dashboard_kpis', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_sales_evolution', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_purchases_kpis', { p_year: yf }),
    supabase.rpc('get_purchases_evolution'),
    supabase.rpc('get_receivables_summary')
  ])

  const salesKpi = salesKpiRes.data?.[0] || { 
    total_sales: 0, 
    total_orders: 0, 
    avg_ticket: 0, 
    total_cost: 0,
    gross_profit: 0
  }

  const purchasesKpi = purchasesKpiRes.data?.[0] || {
    total_compras: 0,
    documentos_count: 0,
    var_pct_interanual: 0
  }

  const recSummary = receivablesSummaryRes.data?.[0] || {
    pendiente_total: 0,
    vencido_total: 0
  }

  const salesEvolution = salesEvolutionRes.data || []
  const purchasesEvolution = purchasesEvolutionRes.data || []

  // Formateadores
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })

  const totalSalesVal = parseFloat(salesKpi.total_sales || 0)
  const totalCostVal = parseFloat(salesKpi.total_cost || 0)
  const grossProfitVal = parseFloat(salesKpi.gross_profit || 0)
  const marginPercentage = totalSalesVal > 0 ? (grossProfitVal / totalSalesVal) * 100 : 0
  const totalPurchasesVal = parseFloat(purchasesKpi.total_compras || 0)
  const pendingDebtVal = parseFloat(recSummary.pendiente_total || 0)

  const kpiCards = [
    { 
      name: 'Ventas del Período', 
      value: currencyFormatter.format(totalSalesVal), 
      icon: DollarSign, 
      color: 'text-blue-500',
      sub: `${salesKpi.total_orders || 0} pedidos expedidos`
    },
    { 
      name: 'Compras del Período', 
      value: currencyFormatter.format(totalPurchasesVal), 
      icon: ShoppingCart, 
      color: 'text-indigo-400',
      sub: `${purchasesKpi.documentos_count || 0} albaranes a proveedores`
    },
    { 
      name: 'Margen Bruto (€)', 
      value: currencyFormatter.format(grossProfitVal), 
      icon: TrendingUp, 
      color: 'text-emerald-400',
      sub: `Ventas - COGS (${currencyFormatter.format(totalCostVal)})`
    },
    { 
      name: 'Margen % Comercial', 
      value: `${marginPercentage.toFixed(2)} %`, 
      icon: Percent, 
      color: 'text-emerald-400',
      sub: 'Sobre facturación de ventas'
    },
    { 
      name: 'Ticket Medio Venta', 
      value: currencyFormatter.format(parseFloat(salesKpi.avg_ticket || 0)), 
      icon: Tag, 
      color: 'text-sky-400',
      sub: 'Promedio por pedido'
    },
    { 
      name: 'Deuda Pendiente Cobro', 
      value: currencyFormatter.format(pendingDebtVal), 
      icon: CreditCard, 
      color: 'text-rose-400',
      sub: `Vencido: ${currencyFormatter.format(parseFloat(recSummary.vencido_total || 0))}`
    },
  ]

  return (
    <div className="space-y-8">
      {/* Header & Filtros */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white">Resumen Ejecutivo del Negocio</h1>
          <p className="text-xs text-slate-400 mt-1">Cuadro de mando consolidado: Ventas, Compras, Margen y Cartera</p>
        </div>
        <DashboardFilters />
      </div>

      {/* KPI Cards Grid (6 KPIs principales) */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {kpiCards.map((c) => (
          <div key={c.name} className="relative overflow-hidden rounded-2xl border border-slate-900 bg-slate-900/20 p-6 backdrop-blur-md transition-all hover:border-slate-800">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-slate-400">{c.name}</span>
              <c.icon className={`h-5 w-5 ${c.color}`} />
            </div>
            <div className="mt-4 flex flex-col">
              <span className="text-2xl font-bold tracking-tight text-white">{c.value}</span>
              {c.sub && <span className="text-[10px] text-slate-500 mt-1">{c.sub}</span>}
            </div>
          </div>
        ))}
      </div>

      {/* Gráfico Combinado Ventas vs Compras */}
      <ExecutiveCombinedChart 
        salesEvolution={salesEvolution} 
        purchasesEvolution={purchasesEvolution} 
      />

      {/* Acceso Rápido a Secciones de Análisis */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        
        <Link 
          href={`/dashboard/ventas?year_from=${yf}&month_from=${mf}&year_to=${yt}&month_to=${mt}`}
          className="group rounded-2xl border border-slate-900 bg-slate-900/30 p-5 backdrop-blur-md hover:border-blue-500/40 transition-all flex items-center justify-between"
        >
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all">
              <TrendingUp className="h-5 w-5" />
            </div>
            <div>
              <div className="text-sm font-bold text-slate-200 group-hover:text-white">Análisis de Ventas</div>
              <div className="text-[10px] text-slate-500">Vendedores, clientes y productos</div>
            </div>
          </div>
          <ChevronRight className="h-4 w-4 text-slate-600 group-hover:text-blue-400 group-hover:translate-x-1 transition-all" />
        </Link>

        <Link 
          href={`/dashboard/compras?year=${yf}`}
          className="group rounded-2xl border border-slate-900 bg-slate-900/30 p-5 backdrop-blur-md hover:border-indigo-500/40 transition-all flex items-center justify-between"
        >
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-400 group-hover:bg-indigo-500 group-hover:text-white transition-all">
              <ShoppingCart className="h-5 w-5" />
            </div>
            <div>
              <div className="text-sm font-bold text-slate-200 group-hover:text-white">Análisis de Compras</div>
              <div className="text-[10px] text-slate-500">Proveedores, gamas y almacenes</div>
            </div>
          </div>
          <ChevronRight className="h-4 w-4 text-slate-600 group-hover:text-indigo-400 group-hover:translate-x-1 transition-all" />
        </Link>

        <Link 
          href={`/dashboard/rentabilidad?year_from=${yf}&month_from=${mf}&year_to=${yt}&month_to=${mt}`}
          className="group rounded-2xl border border-slate-900 bg-slate-900/30 p-5 backdrop-blur-md hover:border-emerald-500/40 transition-all flex items-center justify-between"
        >
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-all">
              <Percent className="h-5 w-5" />
            </div>
            <div>
              <div className="text-sm font-bold text-slate-200 group-hover:text-white">Rentabilidad & COGS</div>
              <div className="text-[10px] text-slate-500">Beneficio bruto y márgenes</div>
            </div>
          </div>
          <ChevronRight className="h-4 w-4 text-slate-600 group-hover:text-emerald-400 group-hover:translate-x-1 transition-all" />
        </Link>

      </div>

      {/* Pie de página con nota semántica */}
      <div className="rounded-2xl border border-slate-900 bg-slate-900/20 p-4 text-xs text-slate-500 flex items-center space-x-3">
        <ShieldCheck className="h-4 w-4 text-indigo-400 shrink-0" />
        <span>
          <strong>Nota de Rigor Financiero:</strong> La cifra de <em>Compras</em> representa los aprovisionamientos a proveedores del período, mientras que el <em>Margen Bruto</em> se calcula sobre el coste real de los artículos vendidos (COGS).
        </span>
      </div>

    </div>
  )
}
