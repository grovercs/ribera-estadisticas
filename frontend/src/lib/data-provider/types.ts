export interface DashboardStoreValue {
  cod_almacen: number | string
  tickets?: number
  importe?: number
  albaranes?: number
  venta?: number
  coste?: number
  margen?: number
  margen_porcentaje?: number
}

export interface DashboardSalesPayload {
  ultimo_dia: string | null
  penultimo_dia: string | null
  hoy: DashboardStoreValue[]
  ayer: DashboardStoreValue[]
  quincena_actual: DashboardStoreValue[]
  quincena_anterior: DashboardStoreValue[]
  anteriores: DashboardStoreValue[]
}

export interface DashboardSalesPeriodsPayload {
  quincena_actual: DashboardStoreValue[]
  quincena_anterior: DashboardStoreValue[]
  year: DashboardStoreValue[]
  year_ant_periodo: DashboardStoreValue[]
  year_anterior: DashboardStoreValue[]
}

export interface DashboardMarginsPayload {
  periodo?: string
  periodo_rows?: DashboardStoreValue[]
  hoy_rows?: DashboardStoreValue[]
  year_rows?: DashboardStoreValue[]
}

export interface DashboardImpagadosPayload {
  impagados_por_almacen: DashboardStoreValue[]
  pendientes_por_almacen?: DashboardStoreValue[]
  totales?: {
    impagados_tickets?: number
    impagados_importe?: number
    impagados_devueltos_tickets?: number
    impagados_devueltos_importe?: number
    pendientes_tickets?: number
    pendientes_importe?: number
  }
}

export interface DashboardPurchasesPeriodsPayload {
  mes_actual?: { count: number; importe: number }
  mes_anterior?: { count: number; importe: number }
  year_actual?: { count: number; importe: number }
  year_anterior_periodo?: { count: number; importe: number }
  year_anterior?: { count: number; importe: number }
}

export interface DashboardPayablePeriod {
  periodo: string
  importe: number
  ops: number
}

export interface DashboardPayablesPayload {
  periodos: DashboardPayablePeriod[]
  total_importe: number
  total_ops: number
}

export interface ActiveSyncRequest {
  id: string
  status: 'pending' | 'running' | 'success' | 'failed'
  source: string
  requested_at: string
  started_at: string | null
  finished_at: string | null
  error_message: string | null
}

export interface DashboardCommonPayload {
  mode: 'local_erp' | 'supabase'
  source: string
  generated_at: string
  execution_time_ms?: number
  reference_date: string
  year: number
  sales: DashboardSalesPayload
  sales_periods: DashboardSalesPeriodsPayload
  margins: DashboardMarginsPayload
  impagados: DashboardImpagadosPayload
  albaranes: DashboardStoreValue[]
  purchases_periods: DashboardPurchasesPeriodsPayload
  payables: DashboardPayablesPayload
  active_sync?: ActiveSyncRequest | null
}

export interface DataProviderOptions {
  year?: number
  anioAnt?: string
  periodo?: string
}
