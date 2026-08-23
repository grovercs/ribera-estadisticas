import ExecutiveMobileV2 from '../components/ExecutiveMobileV2'
import MobileDashboardHeader from '../components/MobileDashboardHeader'
import { createDashboardMobileSections } from '@/lib/dashboardMobileSections'
import { createClient } from '@/lib/supabase/server'

export const dynamic = 'force-dynamic'

interface MobileV2PageProps {
  searchParams: Promise<{ year?: string; anio_ant?: string }>
}

export default async function MobileV2Page({ searchParams }: MobileV2PageProps) {
  const resolvedSearchParams = await searchParams
  const year = parseInt(resolvedSearchParams.year || '2026')
  const anioAnt = resolvedSearchParams.anio_ant || 'todos'
  const supabase = await createClient()
  const { data: { user } } = await supabase.auth.getUser()

  const [salesRes, salesPeriodsRes, marginsRes, impagadosRes, albaranesRes, purchasesPeriodsRes, payablesRes, activeSyncRes] = await Promise.all([
    supabase.rpc('get_store_dashboard_sales', { p_year: year, p_anio_ant: anioAnt }),
    supabase.rpc('get_store_dashboard_sales_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_margins', { p_periodo: 'year' }),
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

  const sections = createDashboardMobileSections({
    year,
    salesDataRaw: salesRes.data,
    salesPeriodsRaw: salesPeriodsRes.data,
    marginsDataRaw: marginsRes.data,
    impagadosDataRaw: impagadosRes.data,
    albaranesDataRaw: albaranesRes.data,
    purchasesPeriodsRaw: purchasesPeriodsRes.data,
    payablesDataRaw: payablesRes.data,
  })

  return (
    <div className="w-full pt-3">
      <div className="mx-auto w-full max-w-[430px] px-3">
        <MobileDashboardHeader
          referenceDate={sections.find((section) => section.id === 'sales')?.rows[0]?.label || 'Últimos datos disponibles'}
          userId={user?.id || null}
          activeSyncRequest={activeSyncRes.data || null}
        />
      </div>
      <ExecutiveMobileV2 sections={sections} />
    </div>
  )
}
