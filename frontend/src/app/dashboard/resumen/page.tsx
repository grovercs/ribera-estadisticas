import { createClient } from '@/lib/supabase/server'
import DashboardFilters from '../components/DashboardFilters'
import DashboardCharts, { DashboardFamilyChart } from '../components/DashboardCharts'
import DashboardRankings from '../components/DashboardRankings'
import { TrendingUp, TrendingDown, Minus } from 'lucide-react'

// Forzar renderizado dinámico en servidor
export const dynamic = 'force-dynamic'

interface PageProps {
  searchParams: Promise<{
    year_from?: string
    month_from?: string
    year_to?: string
    month_to?: string
    hide_no_stock?: string
  }>
}

export default async function ResumenPage({ searchParams }: PageProps) {
  const resolvedSearchParams = await searchParams
  const yf = parseInt(resolvedSearchParams.year_from || '2026')
  const mf = parseInt(resolvedSearchParams.month_from || '1')
  const yt = parseInt(resolvedSearchParams.year_to || '2026')
  const mt = parseInt(resolvedSearchParams.month_to || '12')
  const hideNoStock = resolvedSearchParams.hide_no_stock === 'true'

  const supabase = await createClient()

  // Calcular rango equivalente del año anterior para comparación
  const prevYf = yf - 1
  const prevYt = yt - 1

  // Validación de comparabilidad semántica de datos interanuales
  const isComparable = prevYf >= 2012 && (yt - yf < 3)
  const comparabilityMessage = prevYf < 2012
    ? 'Sin datos hist. de 2011'
    : 'Rango amplio'

  // Consultas RPC en paralelo (incluyendo datos de comparación del año anterior)
  const [
    kpiActRes,
    kpiPrevRes,
    netMarginActRes,
    netMarginPrevRes,
    evolutionRes,
    warehouseRes,
    sellersRes,
    clientsRes,
    productsRes,
    familiesRes,
    receivablesSummaryRes
  ] = await Promise.all([
    supabase.rpc('get_dashboard_kpis', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_kpis', { p_year_from: prevYf, p_month_from: mf, p_year_to: prevYt, p_month_to: mt }),
    supabase.rpc('get_dashboard_net_margins', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_net_margins', { p_year_from: prevYf, p_month_from: mf, p_year_to: prevYt, p_month_to: mt }),
    supabase.rpc('get_dashboard_sales_evolution', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_sales_by_warehouse', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_top_sellers', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_top_clients', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_top_products', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt, p_hide_no_stock: hideNoStock }),
    supabase.rpc('get_dashboard_top_families', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_receivables_summary')
  ])

  // Tratar KPIs del período actual
  const kpiAct = kpiActRes.data?.[0] || {
    total_sales: 0,
    total_orders: 0,
    avg_ticket: 0,
    pending_amount: 0,
    unique_clients: 0,
    total_cost: 0,
    gross_profit: 0
  }

  // Tratar KPIs del período comparable anterior
  const kpiPrev = kpiPrevRes.data?.[0] || {
    total_sales: 0,
    total_orders: 0,
    avg_ticket: 0,
    pending_amount: 0,
    unique_clients: 0,
    total_cost: 0,
    gross_profit: 0
  }

  // Margen comercial NETO (sin IVA) con la misma definición empresarial que /dashboard
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

  const evolutionData = evolutionRes.data || []
  const warehouseData = warehouseRes.data || []
  const sellers = sellersRes.data || []
  const clients = clientsRes.data || []
  const products = productsRes.data || []
  const families = familiesRes.data || []

  // Formateadores
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 })
  const numberFormatter = new Intl.NumberFormat('es-ES')

  // Fórmulas financieras detalladas
  const actSales = parseFloat(kpiAct.total_sales || 0)
  const prevSales = parseFloat(kpiPrev.total_sales || 0)

  const actProfit = parseFloat(kpiAct.gross_profit || 0)
  const prevProfit = parseFloat(kpiPrev.gross_profit || 0)

  const actMargPct = actSales > 0 ? (actProfit / actSales) * 100 : 0
  const prevMargPct = prevSales > 0 ? (prevProfit / prevSales) * 100 : 0

  const actNetProfit = parseFloat(netMarginAct.margen || 0)
  const prevNetProfit = parseFloat(netMarginPrev.margen || 0)
  const actNetMargPct = parseFloat(netMarginAct.margen_porcentaje || 0)
  const prevNetMargPct = parseFloat(netMarginPrev.margen_porcentaje || 0)

  const actAvgTicket = parseFloat(kpiAct.avg_ticket || 0)
  const prevAvgTicket = parseFloat(kpiPrev.avg_ticket || 0)

  const actClients = parseInt(kpiAct.unique_clients || 0)
  const prevClients = parseInt(kpiPrev.unique_clients || 0)

  const actOrders = parseInt(kpiAct.total_orders || 0)
  const prevOrders = parseInt(kpiPrev.total_orders || 0)

  // Cartera Viva: fotografía actual de receivables, independiente del filtro de ventas
  const receivablesSummary = receivablesSummaryRes.data?.[0] || {
    pendiente_total: 0,
    vencido_total: 0,
    no_vencido_total: 0,
    abonos_pendientes: 0
  }

  const carteraViva = parseFloat(receivablesSummary.pendiente_total || 0)
  const carteraVencido = parseFloat(receivablesSummary.vencido_total || 0)
  const carteraNoVencido = parseFloat(receivablesSummary.no_vencido_total || 0)
  const carteraAbonos = parseFloat(receivablesSummary.abonos_pendientes || 0)
  const carteraRatio = actSales > 0 ? carteraViva / actSales : 0

  // Función auxiliar para renderizar el badge de variación
  const renderVariance = (
    current: number,
    previous: number,
    format: 'currency' | 'percent' | 'number',
    labelSuffix = '',
    higherIsBetter = true
  ) => {
    if (!isComparable) {
      return (
        <span className="text-xs font-semibold text-[#747878] block mt-1">
          ⚠️ {comparabilityMessage}
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
          <Minus className="h-3 w-3" /> Sin cambios vs período ant.
        </span>
      )
    }

    let diffText = ''
    if (format === 'currency') {
      diffText = (diff > 0 ? '+' : '') + currencyFormatter.format(diff)
    } else if (format === 'number') {
      diffText = (diff > 0 ? '+' : '') + numberFormatter.format(diff)
    } else {
      diffText = (diff > 0 ? '+' : '') + diff.toFixed(2) + ' p.p.'
    }

    return (
      <span className={`text-xs font-bold flex items-center gap-1 mt-1 ${colorClass}`}>
        <Icon className="h-3.5 w-3.5 shrink-0" />
        <span>{diffText} ({pct > 0 ? '+' : ''}{pct.toFixed(1)}%){labelSuffix}</span>
      </span>
    )
  }

  return (
    <div className="space-y-6">

      {/* Header del Dashboard */}
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-[#e1e2e6] pb-4">
        <div>
          <h1 className="text-3xl md:text-4xl font-black text-[#191c1e] tracking-tight">Resumen Ejecutivo</h1>
          <p className="text-base text-[#747878] mt-1">Consola de Control · Datos consolidados en tiempo real</p>
        </div>
        <DashboardFilters basePath="/dashboard/resumen" />
      </div>

      {/* BLOQUE A: KPIs Analíticos */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">

        {/* KPI 1: Ventas Período */}
        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Ventas Período</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(actSales)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actSales, prevSales, 'currency', ' vs año ant.', true)}
          </div>
        </div>

        {/* KPI 2: Margen Comercial % */}
        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Margen Comercial</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{actNetMargPct.toFixed(2)} %</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actNetMargPct, prevNetMargPct, 'percent', ' vs año ant.', true)}
            <span className="text-xs text-[#747878] block mt-0.5 font-semibold">Margen bruto: {currencyFormatter.format(actNetProfit)}</span>
          </div>
        </div>

        {/* KPI 3: Cartera Viva (receivables, foto actual) */}
        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Cartera Viva</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(carteraViva)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7] space-y-1">
            <div className="flex justify-between text-xs text-[#747878] font-semibold">
              <span>Vencido</span>
              <span className="text-[#191c1e]">{currencyFormatter.format(carteraVencido)}</span>
            </div>
            <div className="flex justify-between text-xs text-[#747878] font-semibold">
              <span>No vencido</span>
              <span className="text-[#191c1e]">{currencyFormatter.format(carteraNoVencido)}</span>
            </div>
            <div className="flex justify-between text-xs text-[#747878] font-semibold">
              <span>Abonos</span>
              <span className="text-[#191c1e]">{currencyFormatter.format(carteraAbonos)}</span>
            </div>
            {carteraRatio >= 0.20 && (
              <span className="text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded block text-center">
                ⚠️ Deuda elevada ({(carteraRatio * 100).toFixed(0)}% de ventas)
              </span>
            )}
          </div>
        </div>

        {/* KPI 4: Ticket Medio */}
        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Ticket Medio</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{currencyFormatter.format(actAvgTicket)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actAvgTicket, prevAvgTicket, 'currency', ' vs año ant.', true)}
          </div>
        </div>

        {/* KPI 5: Clientes Únicos */}
        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Clientes Únicos</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{numberFormatter.format(actClients)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actClients, prevClients, 'number', ' vs año ant.', true)}
          </div>
        </div>

        {/* KPI 6: Operaciones / Pedidos */}
        <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm flex flex-col justify-between">
          <div>
            <p className="text-xs font-bold text-[#747878] uppercase tracking-wider">Operaciones</p>
            <p className="text-2xl md:text-3xl font-black text-[#191c1e] tracking-tight mt-1">{numberFormatter.format(actOrders)}</p>
          </div>
          <div className="mt-2 pt-2 border-t border-[#f2f3f7]">
            {renderVariance(actOrders, prevOrders, 'number', ' vs año ant.', true)}
          </div>
        </div>

      </div>

      {/* BLOQUE B y C: Gráficos de Evolución y Almacenes */}
      <DashboardCharts
        evolutionData={evolutionData}
        warehouseData={warehouseData}
      />

      {/* BLOQUE D: Top Familias + Rankings */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <DashboardFamilyChart familyData={families} />
        <DashboardRankings
          sellers={sellers}
          clients={clients}
          products={products}
          totalSales={actSales}
          yf={yf}
          mf={mf}
          yt={yt}
          mt={mt}
          hideNoStock={hideNoStock}
        />
      </div>

    </div>
  )
}
