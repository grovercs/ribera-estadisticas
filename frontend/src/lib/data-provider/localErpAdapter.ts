import { DashboardCommonPayload, DataProviderOptions } from './types'

export async function fetchFromLocalErp(options: DataProviderOptions = {}): Promise<DashboardCommonPayload> {
  const year = options.year || 2026
  const anioAnt = options.anioAnt || 'todos'
  const periodo = options.periodo || 'hoy'
  const startTime = Date.now()

  const localBaseUrl = process.env.LOCAL_ERP_API_URL || 'http://127.0.0.1:8088/api/local/dashboard-summary'
  const url = new URL(localBaseUrl)
  url.searchParams.set('year', String(year))
  url.searchParams.set('anio_ant', anioAnt)
  url.searchParams.set('periodo', periodo)

  // Timeout: respetar variable de entorno; si no está definida, usar 15000 ms
  // para no abortar respuestas locales que realmente tardan varios segundos.
  const timeoutMs = process.env.LOCAL_ERP_TIMEOUT_MS ? parseInt(process.env.LOCAL_ERP_TIMEOUT_MS, 10) : 15000

  const res = await fetch(url.toString(), {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
    cache: 'no-store',
    signal: AbortSignal.timeout(timeoutMs),
  })

  if (!res.ok) {
    throw new Error(`Local ERP endpoint returned HTTP status ${res.status}`)
  }

  const data = await res.json()

  if (!data || !data.ok) {
    throw new Error('Local ERP response payload indicates failure')
  }

  const executionTimeMs = Date.now() - startTime

  return {
    mode: 'local_erp',
    source: data.source || 'local_erp',
    generated_at: data.generated_at || new Date().toISOString(),
    execution_time_ms: data.execution_time_ms || executionTimeMs,
    reference_date: data.reference_date || data.ultimo_dia_ventas || new Date().toISOString().split('T')[0],
    year,
    sales: data.sales || {},
    sales_periods: data.sales_periods || {},
    margins: data.margins || {},
    impagados: data.impagados || {},
    albaranes: data.albaranes || [],
    purchases_periods: data.purchases_periods || {},
    payables: data.payables || { periodos: [], total_importe: 0, total_ops: 0 },
    active_sync: null,
  }
}
