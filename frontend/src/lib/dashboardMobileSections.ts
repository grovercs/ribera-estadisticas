export interface DashboardMobileRow {
  label: string
  vielhaValue?: number
  pontValue?: number
  vielhaCount?: number
  pontCount?: number
  totalValue?: number
  totalCount?: number
  format?: 'eur' | 'pct' | 'num'
  highlight?: boolean
  muted?: boolean
  totalOnly?: boolean
  subheader?: boolean
}

export interface DashboardMobileSection {
  id: 'sales' | 'invoices' | 'receivables' | 'margins' | 'delivery-notes' | 'purchases' | 'payables'
  title: string
  rows: DashboardMobileRow[]
}

interface DashboardMobileSectionsInput {
  year: number
  salesDataRaw: any
  salesPeriodsRaw: any
  marginsDataRaw: any
  impagadosDataRaw: any
  albaranesDataRaw: any
  purchasesPeriodsRaw: any
  payablesDataRaw: any
}

const PONT = '1'
const VIELHA = '2'

const toNum = (value: any) => {
  if (value === null || value === undefined || value === '') return 0
  const parsed = typeof value === 'string' ? parseFloat(value.replace(/\s/g, '').replace(',', '.')) : Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

const toInt = (value: any) => {
  if (value === null || value === undefined || value === '') return 0
  const parsed = typeof value === 'string' ? parseInt(value.replace(/\s/g, ''), 10) : Number(value)
  return Number.isFinite(parsed) ? Math.trunc(parsed) : 0
}

const findStore = (rows: any[], storeCode: string) =>
  Array.isArray(rows) ? rows.find((row) => String(row?.cod_almacen ?? '') === storeCode) || null : null

const storeValue = (rows: any[], storeCode: string, field: string) => toNum(findStore(rows, storeCode)?.[field])
const storeCount = (rows: any[], storeCode: string, field: string) => toInt(findStore(rows, storeCode)?.[field])

const formatDashDate = (dateValue: string | null | undefined) => {
  if (!dateValue) return 'Sin datos'
  const [year, month, day] = dateValue.split('-').map(Number)
  if (!year || !month || !day) return String(dateValue)

  return new Date(year, month - 1, day, 12).toLocaleDateString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
}

export function createDashboardMobileSections({
  year,
  salesDataRaw,
  salesPeriodsRaw,
  marginsDataRaw,
  impagadosDataRaw,
  albaranesDataRaw,
  purchasesPeriodsRaw,
  payablesDataRaw,
}: DashboardMobileSectionsInput): DashboardMobileSection[] {
  const salesData = {
    ultimo_dia: salesDataRaw?.ultimo_dia || null,
    penultimo_dia: salesDataRaw?.penultimo_dia || null,
    hoy: salesDataRaw?.hoy || [],
    ayer: salesDataRaw?.ayer || [],
    quincena_actual: salesDataRaw?.quincena_actual || [],
    quincena_anterior: salesDataRaw?.quincena_anterior || [],
    anteriores: salesDataRaw?.anteriores || [],
  }
  const salesPeriods = salesPeriodsRaw || {}
  const marginsData = marginsDataRaw || { hoy_rows: [], year_rows: [] }
  const impagadosData = impagadosDataRaw || { impagados_por_almacen: [], pendientes_por_almacen: [], totales: {} }
  const albaranesData = albaranesDataRaw || []
  const purchasesPeriods = purchasesPeriodsRaw || {}
  const payablesData = payablesDataRaw || { periodos: [], total_importe: 0, total_ops: 0 }

  const salesRow = (period: keyof typeof salesData) => {
    const rows = salesData[period] as any[]
    const vielhaValue = storeValue(rows, VIELHA, 'importe')
    const pontValue = storeValue(rows, PONT, 'importe')
    const vielhaCount = storeCount(rows, VIELHA, 'tickets')
    const pontCount = storeCount(rows, PONT, 'tickets')
    return { vielhaValue, pontValue, vielhaCount, pontCount }
  }

  const salesPeriodTotal = (period: string, field: 'importe' | 'tickets') =>
    field === 'importe'
      ? storeValue(salesPeriods[period] || [], PONT, field) + storeValue(salesPeriods[period] || [], VIELHA, field)
      : storeCount(salesPeriods[period] || [], PONT, field) + storeCount(salesPeriods[period] || [], VIELHA, field)

  const marginStore = (rows: any[], storeCode: string) => {
    const venta = storeValue(rows, storeCode, 'venta')
    const coste = storeValue(rows, storeCode, 'coste')
    const margen = venta - coste
    return { venta, coste, margen, margen_porcentaje: venta > 0 ? (margen / venta) * 100 : 0 }
  }
  const marginTotal = (rows: any[]) => {
    const venta = storeValue(rows, PONT, 'venta') + storeValue(rows, VIELHA, 'venta')
    const coste = storeValue(rows, PONT, 'coste') + storeValue(rows, VIELHA, 'coste')
    const margen = venta - coste
    return { venta, coste, margen, margen_porcentaje: venta > 0 ? (margen / venta) * 100 : 0 }
  }

  const marginVielhaHoy = marginStore(marginsData.hoy_rows || [], VIELHA)
  const marginPontHoy = marginStore(marginsData.hoy_rows || [], PONT)
  const marginVielhaYear = marginStore(marginsData.year_rows || [], VIELHA)
  const marginPontYear = marginStore(marginsData.year_rows || [], PONT)
  const marginsHoy = marginTotal(marginsData.hoy_rows || [])
  const marginsYear = marginTotal(marginsData.year_rows || [])

  const impagadosRow = (field: 'impagados_por_almacen' | 'pendientes_por_almacen') => ({
    vielhaValue: storeValue(impagadosData[field], VIELHA, 'importe'),
    pontValue: storeValue(impagadosData[field], PONT, 'importe'),
    vielhaCount: storeCount(impagadosData[field], VIELHA, 'tickets'),
    pontCount: storeCount(impagadosData[field], PONT, 'tickets'),
  })
  const albaranesVielha = storeValue(albaranesData, VIELHA, 'importe')
  const albaranesPont = storeValue(albaranesData, PONT, 'importe')
  const albaranesVielhaCount = storeCount(albaranesData, VIELHA, 'albaranes')
  const albaranesPontCount = storeCount(albaranesData, PONT, 'albaranes')
  const purchaseValue = (period: string, field: 'importe' | 'count') =>
    field === 'importe' ? toNum(purchasesPeriods[period]?.[field]) : toInt(purchasesPeriods[period]?.[field])
  const payablePeriods = ['Mes Actual', 'Mes Siguiente', 'En 2 meses', 'En 3 meses']
    .map((label) => (payablesData.periodos || []).find((period: any) => period?.periodo === label))
    .filter(Boolean)

  const todayLabel = formatDashDate(salesData.ultimo_dia)
  const yesterdayLabel = formatDashDate(salesData.penultimo_dia)

  return [
    {
      id: 'sales',
      title: '1 · Ventas',
      rows: [
        { label: todayLabel, ...salesRow('hoy') },
        { label: yesterdayLabel, ...salesRow('ayer') },
        { label: 'Quincena Actual', ...salesRow('quincena_actual'), highlight: true },
        { label: 'Quincena Anterior', ...salesRow('quincena_anterior') },
        { label: 'Anteriores', ...salesRow('anteriores'), muted: true },
      ],
    },
    {
      id: 'invoices',
      title: '2 · Facturas de Venta',
      rows: [
        { label: 'Quincena Actual', totalValue: salesPeriodTotal('quincena_actual', 'importe'), totalCount: salesPeriodTotal('quincena_actual', 'tickets'), totalOnly: true },
        { label: 'Quincena Anterior', totalValue: salesPeriodTotal('quincena_anterior', 'importe'), totalCount: salesPeriodTotal('quincena_anterior', 'tickets'), totalOnly: true },
        { label: `Año ${year}`, totalValue: salesPeriodTotal('year', 'importe'), totalCount: salesPeriodTotal('year', 'tickets'), totalOnly: true, highlight: true },
        { label: 'Año Ant. (mismo período)', totalValue: salesPeriodTotal('year_ant_periodo', 'importe'), totalCount: salesPeriodTotal('year_ant_periodo', 'tickets'), totalOnly: true, muted: true },
        { label: 'Año Anterior', totalValue: salesPeriodTotal('year_anterior', 'importe'), totalCount: salesPeriodTotal('year_anterior', 'tickets'), totalOnly: true, muted: true },
      ],
    },
    {
      id: 'receivables',
      title: '3 · Impagados y Pendientes de Cobro',
      rows: [
        { label: 'Impagados', ...impagadosRow('impagados_por_almacen') },
        { label: 'Pendientes', ...impagadosRow('pendientes_por_almacen') },
        { label: 'Cartera Pendiente Total', totalValue: toNum(impagadosData.totales?.impagados_importe) + toNum(impagadosData.totales?.pendientes_importe), totalOnly: true },
      ],
    },
    {
      id: 'margins',
      title: '4 · Márgenes Comerciales',
      rows: [
        { label: `Márgenes ${todayLabel}`, subheader: true },
        { label: 'Venta', vielhaValue: marginVielhaHoy.venta, pontValue: marginPontHoy.venta, totalValue: marginsHoy.venta },
        { label: 'Coste', vielhaValue: marginVielhaHoy.coste, pontValue: marginPontHoy.coste, totalValue: marginsHoy.coste },
        { label: 'Margen %', vielhaValue: marginVielhaHoy.margen_porcentaje, pontValue: marginPontHoy.margen_porcentaje, totalValue: marginsHoy.margen_porcentaje, format: 'pct' },
        { label: `Año ${year}`, subheader: true },
        { label: 'Venta', vielhaValue: marginVielhaYear.venta, pontValue: marginPontYear.venta, totalValue: marginsYear.venta },
        { label: 'Coste', vielhaValue: marginVielhaYear.coste, pontValue: marginPontYear.coste, totalValue: marginsYear.coste },
        { label: 'Margen %', vielhaValue: marginVielhaYear.margen_porcentaje, pontValue: marginPontYear.margen_porcentaje, totalValue: marginsYear.margen_porcentaje, format: 'pct' },
      ],
    },
    {
      id: 'delivery-notes',
      title: '5 · Albaranes de Compra — Mes Actual',
      rows: [
        { label: 'Operaciones', totalValue: albaranesVielhaCount + albaranesPontCount, totalOnly: true, format: 'num' },
        { label: 'Importe', vielhaValue: albaranesVielha, pontValue: albaranesPont, totalValue: albaranesVielha + albaranesPont },
      ],
    },
    {
      id: 'purchases',
      title: '6 · Facturas de Compras y Gastos',
      rows: [
        { label: 'Mes Actual', totalValue: purchaseValue('mes_actual', 'importe'), totalCount: purchaseValue('mes_actual', 'count'), totalOnly: true, highlight: true },
        { label: 'Mes Anterior', totalValue: purchaseValue('mes_anterior', 'importe'), totalCount: purchaseValue('mes_anterior', 'count'), totalOnly: true },
        { label: 'Año Actual', totalValue: purchaseValue('year_actual', 'importe'), totalCount: purchaseValue('year_actual', 'count'), totalOnly: true, highlight: true },
        { label: 'Año Ant. (mismo período)', totalValue: purchaseValue('year_anterior_periodo', 'importe'), totalCount: purchaseValue('year_anterior_periodo', 'count'), totalOnly: true, muted: true },
      ],
    },
    {
      id: 'payables',
      title: '7 · Pagos Pendientes Proveedores',
      rows: [
        ...payablePeriods.map((period: any) => ({ label: period.periodo, totalValue: toNum(period.importe), totalCount: toInt(period.ops), totalOnly: true })),
        { label: 'Total Pagos', totalValue: toNum(payablesData.total_importe), totalCount: toInt(payablesData.total_ops), totalOnly: true },
      ],
    },
  ]
}
