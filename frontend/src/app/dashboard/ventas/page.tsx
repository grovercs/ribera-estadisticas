import { createClient } from '@/lib/supabase/server'
import DashboardFilters from '../components/DashboardFilters'
import DashboardCharts from '../components/DashboardCharts'
import Link from 'next/link'
import { 
  DollarSign, 
  ShoppingCart, 
  Tag, 
  CreditCard, 
  Users2, 
  Percent,
  TrendingUp,
  Users,
  Building2,
  Package,
  Layers
} from 'lucide-react'

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

export default async function SalesPage({ searchParams }: PageProps) {
  const resolvedSearchParams = await searchParams
  const yf = parseInt(resolvedSearchParams.year_from || '2026')
  const mf = parseInt(resolvedSearchParams.month_from || '1')
  const yt = parseInt(resolvedSearchParams.year_to || '2026')
  const mt = parseInt(resolvedSearchParams.month_to || '12')
  const hideNoStock = resolvedSearchParams.hide_no_stock === 'true'

  const supabase = await createClient()

  // Ejecutar todas las RPCs de ventas en paralelo
  const [
    kpiRes,
    evolutionRes,
    warehouseRes,
    sellersRes,
    clientsRes,
    productsRes,
    familiesRes
  ] = await Promise.all([
    supabase.rpc('get_dashboard_kpis', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_sales_evolution', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_sales_by_warehouse', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_top_sellers', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_top_clients', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt }),
    supabase.rpc('get_dashboard_top_products', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt, p_hide_no_stock: hideNoStock }),
    supabase.rpc('get_dashboard_top_families', { p_year_from: yf, p_month_from: mf, p_year_to: yt, p_month_to: mt })
  ])

  const kpi = kpiRes.data?.[0] || { 
    total_sales: 0, 
    total_orders: 0, 
    avg_ticket: 0, 
    pending_amount: 0, 
    unique_clients: 0,
    total_cost: 0,
    gross_profit: 0
  }

  const evolutionData = evolutionRes.data || []
  const warehouseData = warehouseRes.data || []
  const sellers = sellersRes.data || []
  const clients = clientsRes.data || []
  const products = productsRes.data || []
  const families = familiesRes.data || []

  // Formateadores
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })
  const numberFormatter = new Intl.NumberFormat('es-ES')

  const totalSalesVal = parseFloat(kpi.total_sales || 0)
  const grossProfitVal = parseFloat(kpi.gross_profit || 0)
  const marginPercentage = totalSalesVal > 0 ? (grossProfitVal / totalSalesVal) * 100 : 0

  const kpiCards = [
    { name: 'Ventas Totales', value: currencyFormatter.format(totalSalesVal), icon: DollarSign, color: 'text-blue-500' },
    { name: 'Total Pedidos', value: numberFormatter.format(parseInt(kpi.total_orders || 0)), icon: ShoppingCart, color: 'text-violet-500' },
    { name: 'Ticket Medio', value: currencyFormatter.format(parseFloat(kpi.avg_ticket || 0)), icon: Tag, color: 'text-sky-500' },
    { name: 'Pendiente Cobro', value: currencyFormatter.format(parseFloat(kpi.pending_amount || 0)), icon: CreditCard, color: 'text-rose-500' },
    { name: 'Clientes Únicos', value: numberFormatter.format(parseInt(kpi.unique_clients || 0)), icon: Users2, color: 'text-amber-500' },
    { name: 'Margen Comercial', value: `${marginPercentage.toFixed(2)} %`, icon: Percent, color: 'text-emerald-500', sub: `Beneficio: ${currencyFormatter.format(grossProfitVal)}` },
  ]

  return (
    <div className="space-y-8">
      {/* Header & Filtros */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <TrendingUp className="h-6 w-6 text-blue-500" />
            <span>Análisis Analítico de Ventas</span>
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Desglose detallado por clientes, vendedores, productos y almacenes
          </p>
        </div>
        <DashboardFilters />
      </div>

      {/* KPI Cards Grid */}
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

      {/* Gráficos */}
      <DashboardCharts evolutionData={evolutionData} warehouseData={warehouseData} />

      {/* Rankings Grid */}
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        
        {/* Vendedores */}
        <div id="sellers" className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
          <div className="flex items-center space-x-2 mb-4">
            <Users className="h-4 w-4 text-violet-500" />
            <h3 className="text-sm font-semibold text-slate-300">Top Vendedores</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-400">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] uppercase font-bold text-slate-500">
                  <th className="py-2.5">Código</th>
                  <th className="py-2.5">Nombre</th>
                  <th className="py-2.5 text-right">Pedidos</th>
                  <th className="py-2.5 text-right">Facturación</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900">
                {sellers.map((s: any) => (
                  <tr key={s.cod_vendedor} className="hover:bg-slate-900/20">
                    <td className="py-3 font-semibold text-slate-300">{s.cod_vendedor}</td>
                    <td className="py-3 truncate max-w-[150px]">{s.nombre_vendedor}</td>
                    <td className="py-3 text-right">{numberFormatter.format(s.orders_count)}</td>
                    <td className="py-3 text-right font-semibold text-white">{currencyFormatter.format(s.total_sales)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Clientes */}
        <div id="clients" className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
          <div className="flex items-center space-x-2 mb-4">
            <Building2 className="h-4 w-4 text-indigo-500" />
            <h3 className="text-sm font-semibold text-slate-300">Top Clientes</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-400">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] uppercase font-bold text-slate-500">
                  <th className="py-2.5">Código</th>
                  <th className="py-2.5">Razón Social</th>
                  <th className="py-2.5">Población / Prov.</th>
                  <th className="py-2.5 text-right">Gasto Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900">
                {clients.map((c: any) => (
                  <tr key={c.cod_cliente} className="hover:bg-slate-900/20">
                    <td className="py-3 font-semibold text-slate-300">{c.cod_cliente}</td>
                    <td className="py-3 truncate max-w-[150px]">{c.razon_social}</td>
                    <td className="py-3 text-slate-500">{c.poblacion || 'N/D'}, {c.provincia || 'N/D'}</td>
                    <td className="py-3 text-right font-semibold text-white">{currencyFormatter.format(c.total_spent)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Productos */}
        <div id="products" className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center space-x-2">
              <Package className="h-4 w-4 text-amber-500" />
              <h3 className="text-sm font-semibold text-slate-300">Top Productos</h3>
            </div>
            <Link
              href={`/dashboard/ventas?year_from=${yf}&month_from=${mf}&year_to=${yt}&month_to=${mt}&hide_no_stock=${!hideNoStock}`}
              className={`text-[10px] font-semibold px-2 py-0.5 rounded-lg border transition-all ${
                hideNoStock 
                  ? 'bg-amber-500/10 border-amber-500/20 text-amber-500 hover:bg-amber-500/25' 
                  : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800'
              }`}
            >
              {hideNoStock ? '✓ Ocultando sin stock' : 'Ocultar sin stock'}
            </Link>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-400">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] uppercase font-bold text-slate-500">
                  <th className="py-2.5">Código</th>
                  <th className="py-2.5">Descripción</th>
                  <th className="py-2.5 text-right">Cantidad</th>
                  <th className="py-2.5 text-right">Ingresos</th>
                  <th className="py-2.5 text-right">Stock Actual</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900">
                {products.map((p: any) => (
                  <tr key={p.cod_articulo} className="hover:bg-slate-900/20">
                    <td className="py-3 font-semibold text-slate-300">{p.cod_articulo}</td>
                    <td className="py-3 truncate max-w-[150px]">{p.descripcion}</td>
                    <td className="py-3 text-right">{numberFormatter.format(p.total_qty)}</td>
                    <td className="py-3 text-right font-semibold text-white">{currencyFormatter.format(p.total_revenue)}</td>
                    <td className={`py-3 text-right font-semibold ${p.stock_total > 0 ? 'text-emerald-500' : 'text-slate-600'}`}>
                      {numberFormatter.format(p.stock_total)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Familias */}
        <div id="families" className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
          <div className="flex items-center space-x-2 mb-4">
            <Layers className="h-4 w-4 text-emerald-500" />
            <h3 className="text-sm font-semibold text-slate-300">Top Familias</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-400">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] uppercase font-bold text-slate-500">
                  <th className="py-2.5">Código</th>
                  <th className="py-2.5">Nombre Familia</th>
                  <th className="py-2.5 text-right">Facturación</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900">
                {families.map((f: any) => (
                  <tr key={f.cod_familia} className="hover:bg-slate-900/20">
                    <td className="py-3 font-semibold text-slate-300">{f.cod_familia}</td>
                    <td className="py-3 truncate max-w-[150px]">{f.family_name || `Familia ${f.cod_familia}`}</td>
                    <td className="py-3 text-right font-semibold text-white">{currencyFormatter.format(f.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  )
}
