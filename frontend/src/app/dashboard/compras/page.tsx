import { createClient } from '@/lib/supabase/server'
import PurchasesFilters from './components/PurchasesFilters'
import PurchasesCharts from './components/PurchasesCharts'
import { 
  ShoppingCart, 
  FileText, 
  Tag, 
  TrendingUp, 
  Truck, 
  Layers 
} from 'lucide-react'

export const dynamic = 'force-dynamic'

interface PageProps {
  searchParams: Promise<{
    year?: string
  }>
}

export default async function PurchasesPage({ searchParams }: PageProps) {
  const resolvedParams = await searchParams
  const selectedYear = resolvedParams.year === 'all' ? null : parseInt(resolvedParams.year || '2026')

  const supabase = await createClient()

  // Ejecutar las 5 RPCs de compras en paralelo
  const [
    kpiRes,
    evolutionRes,
    warehouseRes,
    suppliersRes,
    familiesRes
  ] = await Promise.all([
    supabase.rpc('get_purchases_kpis', { p_year: selectedYear }),
    supabase.rpc('get_purchases_evolution'),
    supabase.rpc('get_purchases_by_warehouse', { p_year: selectedYear }),
    supabase.rpc('get_purchases_top_suppliers', { p_limit: 10, p_year: selectedYear }),
    supabase.rpc('get_purchases_top_families', { p_limit: 10, p_year: selectedYear })
  ])

  const kpi = kpiRes.data?.[0] || {
    year: selectedYear || 2026,
    total_compras: 0,
    documentos_count: 0,
    proveedores_count: 0,
    ticket_medio: 0,
    total_compras_prev_year: 0,
    var_pct_interanual: 0
  }

  const evolutionData = evolutionRes.data || []
  const warehouseData = warehouseRes.data || []
  const topSuppliers = suppliersRes.data || []
  const topFamilies = familiesRes.data || []

  // Formateadores
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })
  const numberFormatter = new Intl.NumberFormat('es-ES')

  const totalCompras = parseFloat(kpi.total_compras || 0)
  const ticketMedio = parseFloat(kpi.ticket_medio || 0)
  const varPct = parseFloat(kpi.var_pct_interanual || 0)

  const kpiCards = [
    {
      name: 'Compras Totales',
      value: currencyFormatter.format(totalCompras),
      icon: ShoppingCart,
      color: 'text-indigo-400',
      sub: selectedYear ? `Año ${selectedYear}` : 'Ventana 3 Años'
    },
    {
      name: 'Documentos Registrados',
      value: numberFormatter.format(parseInt(kpi.documentos_count || 0)),
      icon: FileText,
      color: 'text-violet-400',
      sub: `${kpi.proveedores_count || 0} proveedores activos`
    },
    {
      name: 'Ticket Medio Compra',
      value: currencyFormatter.format(ticketMedio),
      icon: Tag,
      color: 'text-sky-400',
      sub: 'Importe medio por albarán/factura'
    },
    {
      name: 'Variación Interanual',
      value: `${varPct > 0 ? '+' : ''}${varPct.toFixed(2)} %`,
      icon: TrendingUp,
      color: varPct >= 0 ? 'text-emerald-400' : 'text-rose-400',
      sub: `vs Año anterior (${currencyFormatter.format(parseFloat(kpi.total_compras_prev_year || 0))})`
    }
  ]

  return (
    <div className="space-y-8">
      {/* Header & Filtros */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <ShoppingCart className="h-6 w-6 text-indigo-500" />
            <span>Análisis de Compras y Aprovisionamientos</span>
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Gestión de compras a proveedores por almacén, gama y volumen
          </p>
        </div>
        <PurchasesFilters />
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {kpiCards.map((c) => (
          <div key={c.name} className="relative overflow-hidden rounded-2xl border border-slate-900 bg-slate-900/20 p-5 backdrop-blur-md transition-all hover:border-slate-800">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-slate-400">{c.name}</span>
              <c.icon className={`h-5 w-5 ${c.color}`} />
            </div>
            <div className="mt-3 flex flex-col">
              <span className="text-2xl font-bold tracking-tight text-white">{c.value}</span>
              <span className="text-[10px] text-slate-500 mt-1">{c.sub}</span>
            </div>
          </div>
        ))}
      </div>

      {/* Gráficos */}
      <PurchasesCharts 
        evolutionData={evolutionData} 
        warehouseData={warehouseData} 
        topFamilies={topFamilies} 
      />

      {/* Rankings Grid (Proveedores & Familias) */}
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        
        {/* Top Proveedores */}
        <div className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center space-x-2">
              <Truck className="h-4 w-4 text-indigo-400" />
              <h3 className="text-sm font-bold text-slate-200">Top 10 Proveedores por Compra</h3>
            </div>
            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
              {selectedYear ? `Año ${selectedYear}` : '3 Años'}
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-400">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] uppercase font-bold text-slate-500">
                  <th className="py-2.5">Proveedor</th>
                  <th className="py-2.5 text-right">Docs</th>
                  <th className="py-2.5 text-right">Total Compras</th>
                  <th className="py-2.5 text-right">% Cuota</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900/80">
                {topSuppliers.map((s: any, idx: number) => (
                  <tr key={s.cod_proveedor} className="hover:bg-slate-900/30">
                    <td className="py-3">
                      <div className="flex flex-col">
                        <span className="font-semibold text-slate-200 truncate max-w-[200px]">
                          #{idx + 1} {s.proveedor_nombre}
                        </span>
                        <span className="text-[10px] text-slate-500">Cód: {s.cod_proveedor}</span>
                      </div>
                    </td>
                    <td className="py-3 text-right text-slate-400 font-medium">
                      {numberFormatter.format(s.document_count)}
                    </td>
                    <td className="py-3 text-right font-bold text-white">
                      {currencyFormatter.format(s.total_purchases)}
                    </td>
                    <td className="py-3 text-right">
                      <div className="flex items-center justify-end space-x-2">
                        <span className="font-semibold text-indigo-400">{s.pct_sobre_total} %</span>
                        <div className="w-12 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                          <div 
                            className="h-full bg-indigo-500 rounded-full" 
                            style={{ width: `${Math.min(s.pct_sobre_total * 4, 100)}%` }} 
                          />
                        </div>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Top Familias de Compra */}
        <div className="rounded-2xl border border-slate-900 bg-slate-900/30 p-6 backdrop-blur-md">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center space-x-2">
              <Layers className="h-4 w-4 text-violet-400" />
              <h3 className="text-sm font-bold text-slate-200">Top 10 Familias de Productos</h3>
            </div>
            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
              {selectedYear ? `Año ${selectedYear}` : '3 Años'}
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-400">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] uppercase font-bold text-slate-500">
                  <th className="py-2.5">Familia</th>
                  <th className="py-2.5 text-right">Líneas</th>
                  <th className="py-2.5 text-right">Total Compras</th>
                  <th className="py-2.5 text-right">% Cuota</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900/80">
                {topFamilies.map((f: any, idx: number) => (
                  <tr key={f.cod_familia} className="hover:bg-slate-900/30">
                    <td className="py-3">
                      <div className="flex flex-col">
                        <span className="font-semibold text-slate-200 truncate max-w-[200px]">
                          #{idx + 1} {f.familia_nombre}
                        </span>
                        <span className="text-[10px] text-slate-500">Familia: {f.cod_familia}</span>
                      </div>
                    </td>
                    <td className="py-3 text-right text-slate-400 font-medium">
                      {numberFormatter.format(f.line_count)}
                    </td>
                    <td className="py-3 text-right font-bold text-white">
                      {currencyFormatter.format(f.total_purchases)}
                    </td>
                    <td className="py-3 text-right">
                      <div className="flex items-center justify-end space-x-2">
                        <span className="font-semibold text-violet-400">{f.pct_sobre_total} %</span>
                        <div className="w-12 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                          <div 
                            className="h-full bg-violet-500 rounded-full" 
                            style={{ width: `${Math.min(f.pct_sobre_total * 4, 100)}%` }} 
                          />
                        </div>
                      </div>
                    </td>
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
