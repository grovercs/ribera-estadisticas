import { createClient as createServerSupabaseClient } from '@/lib/supabase/server'
import { createClient as createBaseSupabaseClient } from '@supabase/supabase-js'
import { DashboardCommonPayload, DataProviderOptions } from './types'

async function getSupabaseClient() {
  try {
    return await createServerSupabaseClient()
  } catch {
    return createBaseSupabaseClient(
      process.env.NEXT_PUBLIC_SUPABASE_URL!,
      process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY!
    )
  }
}

export async function fetchFromSupabase(options: DataProviderOptions = {}): Promise<DashboardCommonPayload> {
  const year = options.year || 2026
  const anioAnt = options.anioAnt || 'todos'
  const periodo = options.periodo || 'hoy'
  const startTime = Date.now()

  const supabase = await getSupabaseClient()

  const [salesRes, salesPeriodsRes, marginsRes, impagadosRes, albaranesRes, purchasesPeriodsRes, payablesRes, activeSyncRes] = await Promise.all([
    supabase.rpc('get_store_dashboard_sales', { p_year: year, p_anio_ant: anioAnt }),
    supabase.rpc('get_store_dashboard_sales_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_margins', { p_periodo: periodo === 'hoy' ? 'year' : periodo }),
    supabase.rpc('get_store_dashboard_impagados'),
    supabase.rpc('get_store_dashboard_albaranes', { p_year: year }),
    supabase.rpc('get_store_dashboard_purchases_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_payables'),
    supabase.from('sync_requests')
      .select('id, status, source, requested_at, started_at, finished_at, error_message')
      .eq('dataset', 'sales')
      .in('status', ['pending', 'running'])
      .order('requested_at', { ascending: false })
      .maybeSingle(),
  ])

  const salesData = salesRes.data || {}
  const executionTimeMs = Date.now() - startTime

  return {
    mode: 'supabase',
    source: 'supabase_rpc',
    generated_at: new Date().toISOString(),
    execution_time_ms: executionTimeMs,
    reference_date: salesData.ultimo_dia || new Date().toISOString().split('T')[0],
    year,
    sales: salesData,
    sales_periods: salesPeriodsRes.data || {},
    margins: marginsRes.data || {},
    impagados: impagadosRes.data || {},
    albaranes: albaranesRes.data || [],
    purchases_periods: purchasesPeriodsRes.data || {},
    payables: payablesRes.data || { periodos: [], total_importe: 0, total_ops: 0 },
    active_sync: (activeSyncRes?.data as any) || null,
  }
}
