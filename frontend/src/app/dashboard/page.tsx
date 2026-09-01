import { createClient } from '@/lib/supabase/server'
import ExecutiveMobileV3 from './components/ExecutiveMobileV3'
import MobileDashboardHeader from './components/MobileDashboardHeader'
import DirectionSnapshotModal from './components/DirectionSnapshotModal'
import SyncButton from './components/SyncButton'
import Link from 'next/link'
import { Search } from 'lucide-react'
import { getCurrentFortnightRange } from '@/lib/salesPeriods'
import { createDashboardMobileSections } from '@/lib/dashboardMobileSections'
import { getDashboardData } from '@/lib/data-provider'

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

  // Usuario autenticado y última sincronización registrada
  const [{ data: { user } }, { data: syncRun }, dashboardPayload] = await Promise.all([
    supabase.auth.getUser(),
    supabase.from('sync_runs')
      .select('completed_at')
      .eq('dataset', 'sales')
      .eq('status', 'success')
      .order('completed_at', { ascending: false })
      .limit(1)
      .maybeSingle(),
    getDashboardData({ year, anioAnt, periodo: 'year' }),
  ])

  const salesDataRaw = dashboardPayload.sales || {}
  const salesData = {
    ultimo_dia: salesDataRaw.ultimo_dia || null,
    penultimo_dia: salesDataRaw.penultimo_dia || null,
    hoy: salesDataRaw.hoy || [],
    ayer: salesDataRaw.ayer || [],
    quincena_actual: salesDataRaw.quincena_actual || [],
    quincena_anterior: salesDataRaw.quincena_anterior || [],
    anteriores: salesDataRaw.anteriores || [],
  }

  const salesPeriods = dashboardPayload.sales_periods || {
    quincena_actual: [],
    quincena_anterior: [],
    year: [],
    year_ant_periodo: [],
    year_anterior: [],
  }
  const marginsData = dashboardPayload.margins || { periodo: 'year', periodo_rows: [], hoy_rows: [], year_rows: [] }
  const impagadosData = dashboardPayload.impagados || {
    impagados_por_almacen: [],
    pendientes_por_almacen: [],
    totales: {},
  }
  const albaranesData = dashboardPayload.albaranes || []
  const purchasesPeriods = dashboardPayload.purchases_periods || {
    mes_actual: { count: 0, importe: 0 },
    mes_anterior: { count: 0, importe: 0 },
    year_actual: { count: 0, importe: 0 },
    year_anterior_periodo: { count: 0, importe: 0 },
    year_anterior: { count: 0, importe: 0 },
  }
  const payablesData = dashboardPayload.payables || { periodos: [], total_importe: 0, total_ops: 0 }
  const lastSyncAt = syncRun?.completed_at
  const lastSync = lastSyncAt
    ? new Date(lastSyncAt).toLocaleTimeString('es-ES', {
        timeZone: 'Europe/Madrid',
        hour: '2-digit',
        minute: '2-digit',
      })
    : 'Reciente'

  const activeSyncRequest = dashboardPayload.active_sync || null

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

  const getStoreValue = (arr: any[] | undefined, storeCode: string | number, key: string) => {
    const item = findStore(arr || [], storeCode)
    return toNum(item?.[key])
  }

  const getStoreCount = (arr: any[] | undefined, storeCode: string | number, key: string) => {
    const item = findStore(arr || [], storeCode)
    return toInt(item?.[key])
  }

  const fmtEur = (val: number) =>
    new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number.isFinite(val) ? val : 0)
  const fmtNum = (val: number) =>
    new Intl.NumberFormat('es-ES').format(Number.isFinite(val) ? val : 0)
  const fmtPct = (val: number) => {
    const safeValue = Number.isFinite(val) ? val : 0
    const truncatedValue = Math.trunc(safeValue * 100) / 100

    return new Intl.NumberFormat('es-ES', {
      style: 'percent',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(truncatedValue / 100)
  }

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
  const purchaseValue = (key: string, field: 'importe' | 'count') => {
    const val = (purchasesPeriods as any)[key]?.[field]
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

  const marginVielhaHoy = marginStore(marginsData.hoy_rows || [], VIELHA)
  const marginPontHoy = marginStore(marginsData.hoy_rows || [], PONT)
  const marginVielhaYear = marginStore(marginsData.year_rows || [], VIELHA)
  const marginPontYear = marginStore(marginsData.year_rows || [], PONT)

  const formatDashDate = (dateStr: string | null | undefined) => {
    if (!dateStr) return 'Sin datos'
    // Asumir fecha local YYYY-MM-DD; evitar conversion de zona horaria
    const [y, m, day] = dateStr.split('-').map(Number)
    if (!y || !m || !day) return String(dateStr)
    const d = new Date(y, m - 1, day, 12, 0, 0)
    return d.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' })
  }
  const todayLabel = formatDashDate(salesData.ultimo_dia)
  const yesterdayLabel = formatDashDate(salesData.penultimo_dia)
  const currentFortnight = getCurrentFortnightRange()
  const currentMonth = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Europe/Madrid',
    month: '2-digit',
  }).formatToParts(new Date()).find((part) => part.type === 'month')?.value || '01'

  const mobileSections = createDashboardMobileSections({
    year,
    salesDataRaw,
    salesPeriodsRaw: salesPeriods,
    marginsDataRaw: marginsData,
    impagadosDataRaw: impagadosData,
    albaranesDataRaw: albaranesData,
    purchasesPeriodsRaw: purchasesPeriods,
    payablesDataRaw: payablesData,
  })

  return (
    <div className="dashboard-direction w-full max-w-none space-y-5 text-sm text-[#191c1e] md:space-y-2 lg:space-y-2 xl:space-y-3 2xl:space-y-5">
      {/* Cabecera Móvil Oficial (V3) */}
      <div className="mx-auto w-full max-w-[450px] px-1 pt-1 md:hidden">
        <MobileDashboardHeader
          referenceDate={mobileSections.find((section) => section.id === 'sales')?.rows[0]?.label || todayLabel}
          userId={user?.id || null}
          activeSyncRequest={activeSyncRequest}
          mode={dashboardPayload.mode}
        />
      </div>

      {/* Tabla principal VIELHA | PONT | TOTAL */}
      <div className="hidden">
        <div className="min-w-[720px] lg:min-w-[768px]">
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
                <div className="font-bold text-[#191c1e] text-sm">{todayLabel}</div>
              </td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(hoyVielhaImp)} <span className="text-xs text-[#9aa0a6]">({hoyVielhaCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums">{fmtEur(hoyPontImp)} <span className="text-xs text-[#9aa0a6]">({hoyPontCnt})</span></td>
              <td className="px-3 py-2 text-right tabular-nums font-bold">{fmtEur(hoyTotalImp)} <span className="text-xs text-[#9aa0a6]">({hoyTotalCnt})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left">
                <div className="font-bold text-[#191c1e] text-sm">{yesterdayLabel}</div>
              </td>
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
          </tbody>

          {/* Estos bloques se presentan en el grid desktop inferior. */}
          <tbody className="hidden">

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
              const vielha = marginVielhaHoy
              const pont = marginPontHoy
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
              const vielha = marginVielhaYear
              const pont = marginPontYear
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
              <td className="px-3 py-2 text-right tabular-nums text-[#747878]">{fmtEur(purchaseValue('year_anterior_periodo', 'importe'))} <span className="text-xs text-[#9aa0a6]">({purchaseValue('year_anterior_periodo', 'count')})</span></td>
            </tr>
            <tr className="hover:bg-[#f8f9fc]/60">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#747878] text-sm">Año Anterior</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums text-[#747878]">{fmtEur(purchaseValue('year_anterior', 'importe'))} <span className="text-xs text-[#9aa0a6]">({purchaseValue('year_anterior', 'count')})</span></td>
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
                <td className="px-3 py-2 text-right tabular-nums font-bold text-amber-700">{fmtEur(p.importe)}</td>
              </tr>
            ))}
            <tr className="hover:bg-[#f8f9fc]/60 bg-amber-50/50">
              <td className="px-3 py-2 text-left"><div className="font-bold text-[#191c1e] text-sm">Total Pagos</div></td>
              <td colSpan={2} className="px-3 py-2" />
              <td className="px-3 py-2 text-right tabular-nums font-black text-amber-700">{fmtEur(payablesData.total_importe || 0)}</td>
            </tr>

          </tbody>
        </table>
      </div>
      </div>

      <div className="dashboard-direction-top-grid hidden space-y-2 md:block lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:gap-5">
        <section className="dashboard-sales-card overflow-hidden rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
          <div className="min-w-0">
            <table className="dashboard-sales-table w-full table-fixed border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] lg:[&_td]:px-2 lg:[&_th]:px-2 xl:text-sm xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:text-base 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <colgroup>
                <col className="w-[34%]" />
                <col className="w-[22%]" />
                <col className="w-[22%]" />
                <col className="w-[22%]" />
              </colgroup>
              <thead>
                <tr>
                  <th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">
                    <div className="flex items-center justify-between gap-2">
                      <span>1 · Ventas</span>
                      <span className="flex items-center gap-1 whitespace-nowrap text-[10px] font-bold normal-case tracking-normal text-white/90">
                        Cuadro de Dirección
                        <span className="rounded-full border border-white/25 bg-white/10 px-1 py-px text-[9px] font-semibold">Sinc: {lastSync}</span>
                        <DirectionSnapshotModal sections={mobileSections} lastDataLabel={todayLabel} lastSync={lastSync} />
                      </span>
                    </div>
                  </th>
                </tr>
                <tr className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-[11px] font-bold uppercase tracking-wide text-[#747878]">
                  <th className="w-1/4 text-left">Concepto</th>
                  <th className="text-right">Vielha</th>
                  <th className="text-right">Pont de Suert</th>
                  <th className="bg-[#e3eaf1] text-right text-[#191c1e]">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f0f4f8]">
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td>
                    <div className="flex items-center gap-1.5">
                      <div className="text-sm font-bold text-[#191c1e]">{todayLabel}</div>
                      {salesData.ultimo_dia && (
                        <Link
                          href={`/dashboard/ventas/detalle?period=today&date=${encodeURIComponent(salesData.ultimo_dia)}`}
                          className="hidden rounded p-1 text-[#206393] transition-colors hover:bg-[#e3eaf1] hover:text-[#1a5078] md:inline-flex"
                          aria-label={`Ver detalle de ventas de ${todayLabel}`}
                          title="Ver detalle de ventas"
                        >
                          <Search className="h-3.5 w-3.5" aria-hidden="true" />
                        </Link>
                      )}
                    </div>
                  </td>
                  <td className="text-right">{fmtEur(hoyVielhaImp)} <span className="text-[11px] text-[#9aa0a6]">({hoyVielhaCnt})</span></td>
                  <td className="text-right">{fmtEur(hoyPontImp)} <span className="text-[11px] text-[#9aa0a6]">({hoyPontCnt})</span></td>
                  <td className="text-right font-bold">{fmtEur(hoyTotalImp)} <span className="text-[11px] text-[#9aa0a6]">({hoyTotalCnt})</span></td>
                </tr>
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td>
                    <div className="flex items-center gap-1.5">
                      <div className="text-sm font-bold text-[#191c1e]">{yesterdayLabel}</div>
                      {salesData.penultimo_dia && (
                        <Link
                          href={`/dashboard/ventas/detalle?period=yesterday&date=${encodeURIComponent(salesData.penultimo_dia)}`}
                          className="hidden rounded p-1 text-[#206393] transition-colors hover:bg-[#e3eaf1] hover:text-[#1a5078] md:inline-flex"
                          aria-label={`Ver detalle de ventas de ${yesterdayLabel}`}
                          title="Ver detalle de ventas"
                        >
                          <Search className="h-3.5 w-3.5" aria-hidden="true" />
                        </Link>
                      )}
                    </div>
                  </td>
                  <td className="text-right">{fmtEur(ayerVielhaImp)} <span className="text-[11px] text-[#9aa0a6]">({ayerVielhaCnt})</span></td>
                  <td className="text-right">{fmtEur(ayerPontImp)} <span className="text-[11px] text-[#9aa0a6]">({ayerPontCnt})</span></td>
                  <td className="text-right font-bold">{fmtEur(ayerTotalImp)} <span className="text-[11px] text-[#9aa0a6]">({ayerTotalCnt})</span></td>
                </tr>
                <tr className="bg-blue-50/30">
                  <td>
                    <div className="flex items-center gap-1.5">
                      <div className="text-sm font-bold text-[#206393]">Quincena Actual</div>
                      <Link
                        href={`/dashboard/ventas/detalle?period=current_fortnight&start=${currentFortnight.start}&end=${currentFortnight.end}`}
                        className="hidden rounded p-1 text-[#206393] transition-colors hover:bg-[#e3eaf1] hover:text-[#1a5078] md:inline-flex"
                        aria-label="Ver detalle de ventas de la quincena actual"
                        title="Ver detalle de ventas"
                      >
                        <Search className="h-3.5 w-3.5" aria-hidden="true" />
                      </Link>
                    </div>
                  </td>
                  <td className="text-right">{fmtEur(qActVielhaImp)} <span className="text-[11px] text-[#9aa0a6]">({qActVielhaCnt})</span></td>
                  <td className="text-right">{fmtEur(qActPontImp)} <span className="text-[11px] text-[#9aa0a6]">({qActPontCnt})</span></td>
                  <td className="text-right font-bold">{fmtEur(qActTotalImp)} <span className="text-[11px] text-[#9aa0a6]">({qActTotalCnt})</span></td>
                </tr>
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td><div className="text-sm font-bold text-[#191c1e]">Quincena Anterior</div></td>
                  <td className="text-right">{fmtEur(qAntVielhaImp)} <span className="text-[11px] text-[#9aa0a6]">({qAntVielhaCnt})</span></td>
                  <td className="text-right">{fmtEur(qAntPontImp)} <span className="text-[11px] text-[#9aa0a6]">({qAntPontCnt})</span></td>
                  <td className="text-right font-bold">{fmtEur(qAntTotalImp)} <span className="text-[11px] text-[#9aa0a6]">({qAntTotalCnt})</span></td>
                </tr>
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td><div className="text-sm font-bold text-[#747878]">Anteriores</div></td>
                  <td className="text-right text-[#747878]">{fmtEur(antVielhaImp)} <span className="text-[11px]">({antVielhaCnt})</span></td>
                  <td className="text-right text-[#747878]">{fmtEur(antPontImp)} <span className="text-[11px]">({antPontCnt})</span></td>
                  <td className="text-right font-bold text-[#747878]">{fmtEur(antTotalImp)} <span className="text-[11px]">({antTotalCnt})</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section className="overflow-x-auto rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
          <div className="min-w-[560px] lg:min-w-[500px]">
            <table className="w-full border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] lg:[&_td]:px-2 lg:[&_th]:px-2 xl:text-sm xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:text-base 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <thead>
                <tr>
                  <th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">
                    <div className="flex items-center justify-between gap-2">
                      <span>2 · Facturas de Venta</span>
                      {user && (
                        <SyncButton
                          initialActiveRequest={activeSyncRequest}
                          userId={user.id}
                          variant="header"
                          mode={dashboardPayload.mode}
                        />
                      )}
                    </div>
                  </th>
                </tr>
                <tr className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-[11px] font-bold uppercase tracking-wide text-[#747878]">
                  <th className="w-1/4 text-left">Concepto</th>
                  <th className="text-right">Vielha</th>
                  <th className="text-right">Pont de Suert</th>
                  <th className="bg-[#e3eaf1] text-right text-[#191c1e]">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f0f4f8]">
                {[
                  ['Quincena Actual', 'quincena_actual'],
                  ['Quincena Anterior', 'quincena_anterior'],
                  [`Año ${year}`, 'year'],
                  ['Año Ant. (mismo período)', 'year_ant_periodo'],
                  ['Año Anterior', 'year_anterior'],
                ].map(([label, key]) => (
                  <tr key={key} className={key === 'year' ? 'bg-blue-50/30' : 'hover:bg-[#f8f9fc]/60'}>
                    <td><div className={`text-sm font-bold ${key === 'year' ? 'text-[#206393]' : 'text-[#191c1e]'}`}>{label}</div></td>
                    <td className="text-right">{fmtEur(salesPeriodValue(key, VIELHA, 'importe'))} <span className="text-[11px] text-[#9aa0a6]">({salesPeriodCount(key, VIELHA, 'tickets')})</span></td>
                    <td className="text-right">{fmtEur(salesPeriodValue(key, PONT, 'importe'))} <span className="text-[11px] text-[#9aa0a6]">({salesPeriodCount(key, PONT, 'tickets')})</span></td>
                    <td className="text-right font-bold">{fmtEur(factTotalImp(key))} <span className="text-[11px] text-[#9aa0a6]">({factTotalCnt(key)})</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div className="dashboard-direction-bottom-grid hidden grid-cols-2 gap-2 md:grid lg:gap-3 xl:gap-5">
        <div className="space-y-2 xl:space-y-4">
          <section className="overflow-hidden rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
            <table className="w-full border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <thead>
                <tr>
                  <th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">
                    3 · Impagados y Pendientes de Cobro
                  </th>
                </tr>
                <tr className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-[11px] font-bold uppercase tracking-wide text-[#747878]">
                  <th className="text-left">Concepto</th>
                  <th className="text-right">Vielha</th>
                  <th className="text-right">Pont</th>
                  <th className="text-right">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f0f4f8]">
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td><div className="text-sm font-bold text-[#191c1e]">Impagados (Vía Judicial/Devol.)</div></td>
                  <td className="text-right font-bold text-rose-700">{fmtEur(impVielhaImp)} <span className="text-[11px] text-[#9aa0a6]">({impVielhaCnt})</span></td>
                  <td className="text-right font-bold text-rose-700">{fmtEur(impPontImp)} <span className="text-[11px] text-[#9aa0a6]">({impPontCnt})</span></td>
                  <td className="text-right font-black text-rose-700">{fmtEur(impTotalImp)} <span className="text-[11px] text-[#9aa0a6]">({impTotalCnt})</span></td>
                </tr>
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td><div className="text-sm font-bold text-[#191c1e]">Pendientes de Cobro</div></td>
                  <td className="text-right">{fmtEur(pendVielhaImp)} <span className="text-[11px] text-[#9aa0a6]">({pendVielhaCnt})</span></td>
                  <td className="text-right">{fmtEur(pendPontImp)} <span className="text-[11px] text-[#9aa0a6]">({pendPontCnt})</span></td>
                  <td className="text-right font-bold">{fmtEur(pendTotalImp)} <span className="text-[11px] text-[#9aa0a6]">({pendTotalCnt})</span></td>
                </tr>
                <tr className="bg-amber-50/50">
                  <td><div className="text-sm font-bold text-[#191c1e]">Cartera Pendiente Total</div></td>
                  <td colSpan={2} />
                  <td className="text-right font-black text-amber-700">{fmtEur(carteraImpagada)}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section className="overflow-hidden rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
            <table className="w-full border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <thead>
                <tr>
                  <th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">
                    4 · Márgenes Comerciales
                  </th>
                </tr>
                <tr className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-[11px] font-bold uppercase tracking-wide text-[#747878]">
                  <th className="text-left">Concepto</th>
                  <th className="text-right">Vielha</th>
                  <th className="text-right">Pont</th>
                  <th className="text-right">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f0f4f8]">
                {(() => {
                  const vielha = marginVielhaHoy
                  const pont = marginPontHoy
                  return (
                    <>
                      <tr className="bg-[#f8f9fc]">
                        <td colSpan={4} className="text-[11px] font-black uppercase tracking-wider text-[#206393]">Hoy</td>
                      </tr>
                      <tr className="hover:bg-[#f8f9fc]/60">
                        <td><div className="text-sm font-bold text-[#191c1e]">Venta</div></td>
                        <td className="text-right">{fmtEur(vielha.venta)}</td>
                        <td className="text-right">{fmtEur(pont.venta)}</td>
                        <td className="text-right font-bold">{fmtEur(marginsHoy.venta)}</td>
                      </tr>
                      <tr className="hover:bg-[#f8f9fc]/60">
                        <td><div className="text-sm font-bold text-[#191c1e]">Coste</div></td>
                        <td className="text-right">{fmtEur(vielha.coste)}</td>
                        <td className="text-right">{fmtEur(pont.coste)}</td>
                        <td className="text-right font-bold">{fmtEur(marginsHoy.coste)}</td>
                      </tr>
                      <tr className="bg-emerald-50/40">
                        <td><div className="text-sm font-bold text-[#191c1e]">Margen %</div></td>
                        <td className="text-right font-bold text-emerald-700">{fmtPct(vielha.margen_porcentaje)}</td>
                        <td className="text-right font-bold text-emerald-700">{fmtPct(pont.margen_porcentaje)}</td>
                        <td className="text-right font-black text-emerald-700">{fmtPct(marginsHoy.margen_porcentaje)}</td>
                      </tr>
                    </>
                  )
                })()}
                {(() => {
                  const vielha = marginVielhaYear
                  const pont = marginPontYear
                  return (
                    <>
                      <tr className="bg-[#f8f9fc]">
                        <td colSpan={4} className="text-[11px] font-black uppercase tracking-wider text-[#206393]">Año {year}</td>
                      </tr>
                      <tr className="hover:bg-[#f8f9fc]/60">
                        <td><div className="text-sm font-bold text-[#191c1e]">Venta</div></td>
                        <td className="text-right">{fmtEur(vielha.venta)}</td>
                        <td className="text-right">{fmtEur(pont.venta)}</td>
                        <td className="text-right font-bold">{fmtEur(marginsYear.venta)}</td>
                      </tr>
                      <tr className="hover:bg-[#f8f9fc]/60">
                        <td><div className="text-sm font-bold text-[#191c1e]">Coste</div></td>
                        <td className="text-right">{fmtEur(vielha.coste)}</td>
                        <td className="text-right">{fmtEur(pont.coste)}</td>
                        <td className="text-right font-bold">{fmtEur(marginsYear.coste)}</td>
                      </tr>
                      <tr className="bg-emerald-50/40">
                        <td><div className="text-sm font-bold text-[#191c1e]">Margen %</div></td>
                        <td className="text-right font-bold text-emerald-700">{fmtPct(vielha.margen_porcentaje)}</td>
                        <td className="text-right font-bold text-emerald-700">{fmtPct(pont.margen_porcentaje)}</td>
                        <td className="text-right font-black text-emerald-700">{fmtPct(marginsYear.margen_porcentaje)}</td>
                      </tr>
                    </>
                  )
                })()}
              </tbody>
            </table>
          </section>
        </div>

        <div className="space-y-2 xl:space-y-4">
          <section className="overflow-hidden rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
            <table className="w-full border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <tbody className="divide-y divide-[#f0f4f8]">
                <tr>
                  <th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">
                    5 · Albaranes de Compra — Mes Actual
                  </th>
                </tr>
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td>
                    <div className="flex items-center gap-1.5">
                      <div className="text-sm font-bold text-[#191c1e]">Operaciones</div>
                      <Link
                        href={`/dashboard/compras/albaranes/detalle?year=${year}&month=${currentMonth}`}
                        className="hidden rounded p-1 text-[#206393] transition-colors hover:bg-[#e3eaf1] hover:text-[#1a5078] md:inline-flex"
                        aria-label="Ver detalle de albaranes de compra del mes actual"
                        title="Ver detalle de albaranes de compra"
                      >
                        <Search className="h-3.5 w-3.5" aria-hidden="true" />
                      </Link>
                    </div>
                  </td>
                  <td className="text-right">{albVielhaCnt}</td>
                  <td className="text-right">{albPontCnt}</td>
                  <td className="text-right font-bold">{albTotalCnt}</td>
                </tr>
                <tr className="hover:bg-[#f8f9fc]/60">
                  <td><div className="text-sm font-bold text-[#191c1e]">Importe</div></td>
                  <td className="text-right">{fmtEur(albVielhaImp)}</td>
                  <td className="text-right">{fmtEur(albPontImp)}</td>
                  <td className="text-right font-bold">{fmtEur(albTotalImp)}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section className="overflow-hidden rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
            <table className="w-full border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <tbody className="divide-y divide-[#f0f4f8]">
                <tr><th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">6 · Facturas de Compras y Gastos</th></tr>
                {[
                  ['Mes Actual', 'mes_actual', 'text-[#206393]'],
                  ['Mes Anterior', 'mes_anterior', 'text-[#191c1e]'],
                  ['Año Actual', 'year_actual', 'text-[#206393]'],
                  ['Año Ant. (mismo período)', 'year_anterior_periodo', 'text-[#747878]'],
                  ['Año Anterior', 'year_anterior', 'text-[#747878]'],
                ].map(([label, key, color]) => (
                  <tr key={key} className={key === 'mes_actual' ? 'bg-blue-50/30' : 'hover:bg-[#f8f9fc]/60'}>
                    <td><div className={`text-sm font-bold ${color}`}>{label}</div></td>
                    <td colSpan={2} />
                    <td className={`text-right ${color === 'text-[#747878]' ? 'text-[#747878]' : key === 'mes_actual' || key === 'year_actual' ? 'font-bold' : ''}`}>
                      {fmtEur(purchaseValue(key, 'importe'))}
                      <span className="text-[11px] text-[#9aa0a6]"> ({purchaseValue(key, 'count')})</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section className="overflow-hidden rounded-lg border border-[#e1e2e6] bg-white shadow-sm xl:rounded-xl">
            <table className="w-full border-collapse text-xs tabular-nums md:[&_td]:px-1.5 md:[&_th]:px-1.5 md:[&_td]:py-1 md:[&_th]:py-1.5 md:[&_td>div]:text-[13px] xl:[&_td]:px-3 xl:[&_th]:px-3 xl:[&_td]:py-2 xl:[&_th]:py-2 xl:[&_td>div]:text-sm 2xl:[&_td]:px-4 2xl:[&_th]:px-4 2xl:[&_td]:py-2.5 2xl:[&_th]:py-2.5">
              <tbody className="divide-y divide-[#f0f4f8]">
                <tr><th colSpan={4} className="bg-[#206393] text-left text-[11px] font-black uppercase tracking-wider text-white">7 · Pagos Pendientes Proveedores</th></tr>
                {orderedPeriodos.map((p) => (
                  <tr key={p.periodo} className="hover:bg-[#f8f9fc]/60">
                    <td><div className="text-sm font-bold text-[#191c1e]">{p.periodo}</div></td>
                    <td colSpan={2} />
                    <td className="text-right font-bold text-amber-700">{fmtEur(p.importe)}</td>
                  </tr>
                ))}
                <tr className="bg-amber-50/50">
                  <td><div className="text-sm font-bold text-[#191c1e]">Total Pagos</div></td>
                  <td colSpan={2} />
                  <td className="text-right font-black text-amber-700">{fmtEur(payablesData.total_importe || 0)}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>
      </div>

      {/* Vista móvil oficial (V3) */}
      <div className="md:hidden">
        <ExecutiveMobileV3 sections={mobileSections} />
      </div>

      <p className="dashboard-direction-footer hidden whitespace-nowrap px-1 text-xs font-medium text-[#747878] md:block">
        Fuente: ERP INTEGRAL · Últimos datos: {todayLabel} · Sync {lastSync}
      </p>
    </div>
  )
}
