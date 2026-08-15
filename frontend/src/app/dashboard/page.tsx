import { createClient } from '@/lib/supabase/server'

export const dynamic = 'force-dynamic'

interface PageProps {
  searchParams: Promise<{
    year?: string
    anio_ant?: string
  }>
}

export default async function ExecutiveDashboardPage({ searchParams }: PageProps) {
  const resolvedSearchParams = await searchParams
  const year = parseInt(resolvedSearchParams.year || '2026')
  const anioAnt = resolvedSearchParams.anio_ant || 'todos'

  const supabase = await createClient()

  // 7 RPCs reales validados
  const [
    salesRes,
    salesPeriodsRes,
    marginsRes,
    impagadosRes,
    albaranesRes,
    purchasesPeriodsRes,
    payablesRes,
    syncStateRes,
  ] = await Promise.all([
    supabase.rpc('get_store_dashboard_sales', { p_year: year, p_anio_ant: anioAnt }),
    supabase.rpc('get_store_dashboard_sales_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_margins', { p_periodo: 'year' }),
    supabase.rpc('get_store_dashboard_impagados'),
    supabase.rpc('get_store_dashboard_albaranes', { p_year: year }),
    supabase.rpc('get_store_dashboard_purchases_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_payables'),
    supabase.from('sync_state').select('dataset,last_success_at,last_run_status').eq('dataset', 'sales').maybeSingle(),
  ])

  const salesData = salesRes.data || { hoy: [], ayer: [], quincena_actual: [], quincena_anterior: [], anteriores: [] }
  const salesPeriods = salesPeriodsRes.data || {
    quincena_actual: [],
    quincena_anterior: [],
    year: [],
    year_ant_periodo: [],
    year_anterior: [],
  }
  const marginsData = marginsRes.data || { periodo: 'year', periodo_rows: [], hoy_rows: [], year_rows: [] }
  const impagadosData = impagadosRes.data || {
    impagados_por_almacen: [],
    pendientes_por_almacen: [],
    totales: {},
  }
  const albaranesData = albaranesRes.data || []
  const purchasesPeriods = purchasesPeriodsRes.data || {
    mes_actual: { count: 0, importe: 0 },
    mes_anterior: { count: 0, importe: 0 },
    year_actual: { count: 0, importe: 0 },
    year_anterior_periodo: { count: 0, importe: 0 },
    year_anterior: { count: 0, importe: 0 },
  }
  const payablesData = payablesRes.data || { periodos: [], total_importe: 0, total_ops: 0 }
  const syncState = syncStateRes.data
  const lastSync = syncState?.last_success_at
    ? new Date(syncState.last_success_at).toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
      })
    : 'Reciente'

  // Mapeo definitivo: cod_almacen 1 = Pont de Suert, 2 = Vielha
  const PONT = '1'
  const VIELHA = '2'

  // Helpers de agregación por tienda
  const findStore = (arr: any[], storeCode: string | number) => {
    if (!Array.isArray(arr)) return null
    return arr.find((x) => String(x?.cod_almacen ?? '') === String(storeCode)) || null
  }

  const toNum = (v: any) => {
    if (v === null || v === undefined || v === '') return 0
    const n = typeof v === 'string' ? parseFloat(v.replace(/\s/g, '').replace(',', '.')) : Number(v)
    return Number.isFinite(n) ? n : 0
  }

  const toInt = (v: any) => {
    if (v === null || v === undefined || v === '') return 0
    const n = typeof v === 'string' ? parseInt(v.replace(/\s/g, ''), 10) : Number(v)
    return Number.isFinite(n) ? Math.trunc(n) : 0
  }

  const getStoreValue = (arr: any[], storeCode: string | number, key: string) => {
    const item = findStore(arr, storeCode)
    return toNum(item?.[key])
  }

  const getStoreCount = (arr: any[], storeCode: string | number, key: string) => {
    const item = findStore(arr, storeCode)
    return toInt(item?.[key])
  }

  const fmtEur = (val: number) =>
    new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number.isFinite(val) ? val : 0)
  const fmtNum = (val: number) =>
    new Intl.NumberFormat('es-ES').format(Number.isFinite(val) ? val : 0)
  const fmtPct = (val: number) =>
    new Intl.NumberFormat('es-ES', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(
      Number.isFinite(val) ? val / 100 : 0,
    )

  // --- VENTAS (Hoy / Ayer / Quincena Actual / Quincena Anterior / Anteriores) ---
  const hoyPontImp = getStoreValue(salesData.hoy, PONT, 'importe')
  const hoyVielhaImp = getStoreValue(salesData.hoy, VIELHA, 'importe')
  const hoyTotalImp = hoyPontImp + hoyVielhaImp
  const hoyPontCnt = getStoreCount(salesData.hoy, PONT, 'tickets')
  const hoyVielhaCnt = getStoreCount(salesData.hoy, VIELHA, 'tickets')
  const hoyTotalCnt = hoyPontCnt + hoyVielhaCnt

  const ayerPontImp = getStoreValue(salesData.ayer, PONT, 'importe')
  const ayerVielhaImp = getStoreValue(salesData.ayer, VIELHA, 'importe')
  const ayerTotalImp = ayerPontImp + ayerVielhaImp
  const ayerPontCnt = getStoreCount(salesData.ayer, PONT, 'tickets')
  const ayerVielhaCnt = getStoreCount(salesData.ayer, VIELHA, 'tickets')
  const ayerTotalCnt = ayerPontCnt + ayerVielhaCnt

  const qActPontImp = getStoreValue(salesData.quincena_actual, PONT, 'importe')
  const qActVielhaImp = getStoreValue(salesData.quincena_actual, VIELHA, 'importe')
  const qActTotalImp = qActPontImp + qActVielhaImp
  const qActPontCnt = getStoreCount(salesData.quincena_actual, PONT, 'tickets')
  const qActVielhaCnt = getStoreCount(salesData.quincena_actual, VIELHA, 'tickets')
  const qActTotalCnt = qActPontCnt + qActVielhaCnt

  const qAntPontImp = getStoreValue(salesData.quincena_anterior, PONT, 'importe')
  const qAntVielhaImp = getStoreValue(salesData.quincena_anterior, VIELHA, 'importe')
  const qAntTotalImp = qAntPontImp + qAntVielhaImp
  const qAntPontCnt = getStoreCount(salesData.quincena_anterior, PONT, 'tickets')
  const qAntVielhaCnt = getStoreCount(salesData.quincena_anterior, VIELHA, 'tickets')
  const qAntTotalCnt = qAntPontCnt + qAntVielhaCnt

  const antPontImp = getStoreValue(salesData.anteriores, PONT, 'importe')
  const antVielhaImp = getStoreValue(salesData.anteriores, VIELHA, 'importe')
  const antTotalImp = antPontImp + antVielhaImp
  const antPontCnt = getStoreCount(salesData.anteriores, PONT, 'tickets')
  const antVielhaCnt = getStoreCount(salesData.anteriores, VIELHA, 'tickets')
  const antTotalCnt = antPontCnt + antVielhaCnt

  // --- FACTURAS DE VENTA (5 periodos) ---
  const salesPeriodValue = (period: string, storeCode: string | number, key: string) =>
    getStoreValue(salesPeriods[period as keyof typeof salesPeriods] || [], storeCode, key)
  const salesPeriodCount = (period: string, storeCode: string | number, key: string) =>
    getStoreCount(salesPeriods[period as keyof typeof salesPeriods] || [], storeCode, key)

  const factTotalImp = (period: string) =>
    salesPeriodValue(period, PONT, 'importe') + salesPeriodValue(period, VIELHA, 'importe')
  const factTotalCnt = (period: string) =>
    salesPeriodCount(period, PONT, 'tickets') + salesPeriodCount(period, VIELHA, 'tickets')

  // --- MÁRGENES COMERCIALES ---
  const marginTotal = (rows: any[]) => {
    const v = getStoreValue(rows, PONT, 'venta') + getStoreValue(rows, VIELHA, 'venta')
    const c = getStoreValue(rows, PONT, 'coste') + getStoreValue(rows, VIELHA, 'coste')
    const m = v - c
    const p = v > 0 ? (m / v) * 100 : 0
    return { venta: v, coste: c, margen: m, margen_porcentaje: Number.isFinite(p) ? p : 0 }
  }
  const marginsHoy = marginTotal(marginsData.hoy_rows || [])
  const marginsYear = marginTotal(marginsData.year_rows || [])

  const marginStore = (rows: any[], storeCode: string | number) => {
    const v = getStoreValue(rows, storeCode, 'venta')
    const c = getStoreValue(rows, storeCode, 'coste')
    const m = v - c
    const p = v > 0 ? (m / v) * 100 : 0
    return { venta: v, coste: c, margen: m, margen_porcentaje: Number.isFinite(p) ? p : 0 }
  }

  // --- IMPAGADOS / PENDIENTES ---
  const impPontImp = getStoreValue(impagadosData.impagados_por_almacen, PONT, 'importe')
  const impVielhaImp = getStoreValue(impagadosData.impagados_por_almacen, VIELHA, 'importe')
  const impTotalImp = impPontImp + impVielhaImp
  const impPontCnt = getStoreCount(impagadosData.impagados_por_almacen, PONT, 'tickets')
  const impVielhaCnt = getStoreCount(impagadosData.impagados_por_almacen, VIELHA, 'tickets')
  const impTotalCnt = impPontCnt + impVielhaCnt

  const pendPontImp = getStoreValue(impagadosData.pendientes_por_almacen, PONT, 'importe')
  const pendVielhaImp = getStoreValue(impagadosData.pendientes_por_almacen, VIELHA, 'importe')
  const pendTotalImp = pendPontImp + pendVielhaImp
  const pendPontCnt = getStoreCount(impagadosData.pendientes_por_almacen, PONT, 'tickets')
  const pendVielhaCnt = getStoreCount(impagadosData.pendientes_por_almacen, VIELHA, 'tickets')
  const pendTotalCnt = pendPontCnt + pendVielhaCnt

  const carteraImpagada = toNum(impagadosData.totales?.impagados_importe) + toNum(impagadosData.totales?.pendientes_importe)

  // --- ALBARANES DE COMPRA MES ---
  const albPontImp = getStoreValue(albaranesData, PONT, 'importe')
  const albVielhaImp = getStoreValue(albaranesData, VIELHA, 'importe')
  const albTotalImp = albPontImp + albVielhaImp
  const albPontCnt = getStoreCount(albaranesData, PONT, 'albaranes')
  const albVielhaCnt = getStoreCount(albaranesData, VIELHA, 'albaranes')
  const albTotalCnt = albPontCnt + albVielhaCnt

  // --- FACTURAS DE COMPRAS Y GASTOS ---
  const purchaseValue = (key: keyof typeof purchasesPeriods, field: 'importe' | 'count') => {
    const val = purchasesPeriods[key]?.[field]
    return field === 'importe' ? toNum(val) : toInt(val)
  }

  // --- PAGOS PENDIENTES: orden fijo ---
  const orderedPayablePeriods = ['Mes Actual', 'Mes Siguiente', 'En 2 meses', 'En 3 meses']
  const orderedPeriodos = orderedPayablePeriods
    .map((label) => {
      const found = (payablesData.periodos || []).find((p: any) => p?.periodo === label)
      return found ? { periodo: found.periodo, importe: toNum(found.importe), ops: toInt(found.ops) } : null
    })
    .filter(Boolean) as Array<{ periodo: string; importe: number; ops: number }>

  return (
    <div className="space-y-5 text-[#191c1e] text-sm max-w-7xl mx-auto">
      <div className="flex flex-col gap-1 border-b border-[#e1e2e6] pb-4">
        <h1 className="text-3xl md:text-4xl font-black text-[#191c1e] tracking-tight">Cuadro de Dirección</h1>
        <p className="text-base text-[#747878] font-medium">
          Fotografía diaria del negocio · {new Date().toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} · Sync {lastSync}
        </p>
      </div>

      {/* Tabla principal VIELHA | PONT | TOTAL */}
      <div className="overflow-x-auto rounded-xl border border-[#e1e2e6] shadow-sm bg-white min-w-0">
        <div className="min-w-[768px]">
        <table className="w-full border-collapse text-sm md:text-base">
          <thead>
            <tr className="bg-[#f0f4f8]">
              <th className="px-3 py-2 text-left text-xs font-bold text-[#747878] uppercase tracking-wider border-b border-[#e1e2e6] w-1/4">
                Concepto
              </th>
              <th className="px-3 py-1.5 text-right text-xs font-extrabold text-[#5a5e60] uppercase tracking-wider bg-[#f0f4f8] border-b border-[#e1e2e6]">
                Vielha
              </th>
              <th className="px-3 py-1.5 text-right text-xs font-extrabold text-[#5a5e60] uppercase tracking-wider bg-[#f0f4f8] border-b border-[#e1e2e6]">
                Pont de Suert
              </th>
              <th className="px-3 py-1.5 text-right text-xs font-black text-[#191c1e] uppercase tracking-wider bg-[#e3eaf1] border-b border-[#e1e2e6]">
                Total
              </th>
            </tr>
          </thead>

          <tbody className="divide-y divide-[#f0f4f8]">

            {/* 1. VENTAS */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                1 · Ventas
              </th>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left">
                <div className="font-bold text-[#191c1e] text-sm">Hoy</div>
              </td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(hoyVielhaImp)} <span className="text-xs text-[#9aa0a6]">({hoyVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(hoyPontImp)} <span className="text-xs text-[#9aa0a6]">({hoyPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(hoyTotalImp)} <span className="text-xs text-[#9aa0a6]">({hoyTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Ayer</div></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(ayerVielhaImp)} <span className="text-xs text-[#9aa0a6]">({ayerVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(ayerPontImp)} <span className="text-xs text-[#9aa0a6]">({ayerPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(ayerTotalImp)} <span className="text-xs text-[#9aa0a6]">({ayerTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60 bg-blue-50/30">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#206393] text-sm">Quincena Actual</div></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(qActVielhaImp)} <span className="text-xs text-[#9aa0a6]">({qActVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(qActPontImp)} <span className="text-xs text-[#9aa0a6]">({qActPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(qActTotalImp)} <span className="text-xs text-[#9aa0a6]">({qActTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Quincena Anterior</div></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(qAntVielhaImp)} <span className="text-xs text-[#9aa0a6]">({qAntVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(qAntPontImp)} <span className="text-xs text-[#9aa0a6]">({qAntPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(qAntTotalImp)} <span className="text-xs text-[#9aa0a6]">({qAntTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#747878] text-sm">Anteriores</div></td>
              <td className="px-3 py-2 text-right tabular-nums text-[#747878]">{fmtEur(antVielhaImp)} <span className="text-xs">({antVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums text-[#747878]">{fmtEur(antPontImp)} <span className="text-xs">({antPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold text-[#747878]">{fmtEur(antTotalImp)} <span className="text-xs">({antTotalCnt})</span></td>
            </tr>

            <tr><td colSpan={4} className="h-px bg-[#e1e2e6]" /></tr>

            {/* 2. FACTURAS DE VENTA */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                2 · Facturas de Venta
              </th>
            </tr>
            {[
              ['Quincena Actual', 'quincena_actual'],
              ['Quincena Anterior', 'quincena_anterior'],
              [`Año ${year}`, 'year'],
              ['Año Ant. (mismo período)', 'year_ant_periodo'],
              ['Año Anterior', 'year_anterior'],
            ].map(([label, key]) => (
              <tr key={key} className={key === 'year' ? 'bg-blue-50/30' : 'hover:bg-[#f8f9fc]/60'}>
                <td className="px-3 py-2 text-left"><div className={`font-bold text-sm ${key === 'year' ? 'text-[#206393]' : 'text-[#191c1e]'}`}>{label}</div></td>
                <td className="px-3 py-2 text-right tabular-nums">{fmtEur(salesPeriodValue(key, VIELHA, 'importe'))} <span className="text-xs text-[#9aa0a6]">({salesPeriodCount(key, VIELHA, 'tickets')})</span></td>
                <td className="px-3 py-2 text-right tabular-nums">{fmtEur(salesPeriodValue(key, PONT, 'importe'))} <span className="text-xs text-[#9aa0a6]">({salesPeriodCount(key, PONT, 'tickets')})</span></td>
                <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(factTotalImp(key))} <span className="text-xs text-[#9aa0a6]">({factTotalCnt(key)})</span></td>
              </tr>
            ))}

            <tr><td colSpan={4} className="h-px bg-[#e1e2e6]" /></tr>

            {/* 3. IMPAGADOS Y PENDIENTES */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                3 · Impagados y Pendientes de Cobro
              </th>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Impagados (Vía Judicial/Devol.)</div></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold text-rose-700">{fmtEur(impVielhaImp)} <span className="text-xs text-[#9aa0a6]">({impVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold text-rose-700">{fmtEur(impPontImp)} <span className="text-xs text-[#9aa0a6]">({impPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-black text-rose-700">{fmtEur(impTotalImp)} <span className="text-xs text-[#9aa0a6]">({impTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Pendientes de Cobro</div></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(pendVielhaImp)} <span className="text-xs text-[#9aa0a6]">({pendVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(pendPontImp)} <span className="text-xs text-[#9aa0a6]">({pendPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(pendTotalImp)} <span className="text-xs text-[#9aa0a6]">({pendTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60 bg-amber-50/50">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Cartera Pendiente Total</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums font-black text-amber-700">{fmtEur(carteraImpagada)}</td>
            </tr>

            <tr><td colSpan={4} className="h-px bg-[#e1e2e6]" /></tr>

            {/* 4. MÁRGENES COMERCIALES */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                4 · Márgenes Comerciales
              </th>
            </tr>

            {/* HOY */}
            {(() => {
              const vielha = marginStore(marginsData.hoy_rows || [], VIELHA)
              const pont = marginStore(marginsData.hoy_rows || [], PONT)
              return (
                <>
                  <tr className="bg-[#f8f9fc]">
                    <td colSpan={4} className="px-3 py-1.5 text-xs font-black text-[#206393] uppercase tracking-wider">
                      Hoy
                    </td>
                  </tr>
                  <tr className="hover:bg-[#f8f9fc]/60">
                    <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Venta</div></td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(vielha.venta)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(pont.venta)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(marginsHoy.venta)}</td>
                  </tr>
                  <tr className="hover:bg-[#f8f9fc]/60">
                    <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Coste</div></td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(vielha.coste)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(pont.coste)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(marginsHoy.coste)}</td>
                  </tr>
                  <tr className="hover:bg-[#f8f9fc]/60 bg-emerald-50/40">
                    <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Margen %</div></td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold text-emerald-700">{fmtPct(vielha.margen_porcentaje)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold text-emerald-700">{fmtPct(pont.margen_porcentaje)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-black text-emerald-700">{fmtPct(marginsHoy.margen_porcentaje)}</td>
                  </tr>
                </>
              )
            })()}

            {/* AÑO 2026 */}
            {(() => {
              const vielha = marginStore(marginsData.year_rows || [], VIELHA)
              const pont = marginStore(marginsData.year_rows || [], PONT)
              return (
                <>
                  <tr className="bg-[#f8f9fc]">
                    <td colSpan={4} className="px-3 py-1.5 text-xs font-black text-[#206393] uppercase tracking-wider">
                      Año {year}
                    </td>
                  </tr>
                  <tr className="hover:bg-[#f8f9fc]/60">
                    <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Venta</div></td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(vielha.venta)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(pont.venta)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(marginsYear.venta)}</td>
                  </tr>
                  <tr className="hover:bg-[#f8f9fc]/60">
                    <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Coste</div></td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(vielha.coste)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{fmtEur(pont.coste)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(marginsYear.coste)}</td>
                  </tr>
                  <tr className="hover:bg-[#f8f9fc]/60 bg-emerald-50/40">
                    <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Margen %</div></td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold text-emerald-700">{fmtPct(vielha.margen_porcentaje)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-bold text-emerald-700">{fmtPct(pont.margen_porcentaje)}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-black text-emerald-700">{fmtPct(marginsYear.margen_porcentaje)}</td>
                  </tr>
                </>
              )
            })()}

            <tr><td colSpan={4} className="h-px bg-[#e1e2e6]" /></tr>

            {/* 5. ALBARANES DE COMPRA MES */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                5 · Albaranes de Compra — Mes Actual
              </th>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Operaciones</div></td>
              <td className="px-3 py-2 text-right tabular-nums">{albVielhaCnt}</td>
              <td className="px-3 py-2 text-right tabular-nums">{albPontCnt}</td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{albTotalCnt}</td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Importe</div></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(albVielhaImp)}</td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(albPontImp)}</td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(albTotalImp)}</td>
            </tr>

            <tr><td colSpan={4} className="h-px bg-[#e1e2e6]" /></tr>

            {/* 6. FACTURAS DE COMPRAS Y GASTOS */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                6 · Facturas de Compras y Gastos
              </th>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60 bg-blue-50/30">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#206393] text-sm">Mes Actual</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(purchaseValue('mes_actual', 'importe'))} <span className="text-xs text-[#9aa0a6]">({purchaseValue('mes_actual', 'count')})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Mes Anterior</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(purchaseValue('mes_anterior', 'importe'))} <span className="text-xs text-[#9aa0a6]">({purchaseValue('mes_anterior', 'count')})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#206393] text-sm">Año Actual</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(purchaseValue('year_actual', 'importe'))} <span className="text-xs text-[#9aa0a6]">({purchaseValue('year_actual', 'count')})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#747878] text-sm">Año Ant. (mismo período)</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums text-[#747878]">{fmtEur(purchaseValue('year_anterior_periodo', 'importe'))}</td>
            </tr>

            <tr><td colSpan={4} className="h-px bg-[#e1e2e6]" /></tr>

            {/* 7. PAGOS PENDIENTES */}
            <tr>
              <th colSpan={4} className="px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
                7 · Pagos Pendientes Proveedores
              </th>
            </tr>
            {orderedPeriodos.map((p) => (
              <tr key={p.periodo} className="hover:bg-[#f8f9fc]/60">
                <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">{p.periodo}</div></td>
                <td colSpan={2} className="px-3 py-2" />
                <td className="px-3 py-2 text-right tabular-nums font-bold text-amber-700">{fmtEur(p.importe)} <span className="text-xs text-[#9aa0a6]">({p.ops})</span></td>
              </tr>
            ))}
            <tr className="hover:bg-[#f8f9fc]/60 bg-amber-50/50">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Total Pagos</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums font-black text-amber-700">{fmtEur(payablesData.total_importe || 0)} <span className="text-xs text-[#9aa0a6]">({payablesData.total_ops || 0})</span></td>
            </tr>

          </tbody>
        </table>
      </div>
      </div>

      <p className="text-sm text-[#747878] font-medium px-1">
        Fuente: ERP INTEGRAL (SQL Server) · Sincronizado vía Supabase · Datos en tiempo real diferido · Sync {lastSync}
      </p>
    </div>
  )
}
