import { DashboardCommonPayload, DataProviderOptions } from './types'
import { fetchFromSupabase } from './supabaseAdapter'
import { fetchFromLocalErp } from './localErpAdapter'

export * from './types'
export { fetchFromSupabase } from './supabaseAdapter'
export { fetchFromLocalErp } from './localErpAdapter'

/**
 * Obtiene los datos del Dashboard usando el origen configurado en el servidor (Local ERP o Supabase)
 * con fallback automático a Supabase en caso de indisponibilidad del ERP local.
 */
export async function getDashboardData(options: DataProviderOptions = {}): Promise<DashboardCommonPayload> {
  const mode = process.env.DATA_SOURCE_MODE || 'supabase'

  if (mode === 'local') {
    try {
      return await fetchFromLocalErp(options)
    } catch (err: any) {
      console.warn('[DataProvider] Local ERP endpoint unavailable. Falling back to Supabase.', err?.message || '')
      return await fetchFromSupabase(options)
    }
  }

  return await fetchFromSupabase(options)
}
