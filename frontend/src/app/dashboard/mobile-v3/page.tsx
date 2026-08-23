import ExecutiveMobileV3 from '../components/ExecutiveMobileV3'
import MobileDashboardHeader from '../components/MobileDashboardHeader'
import { createDashboardMobileSections } from '@/lib/dashboardMobileSections'
import { getDashboardData } from '@/lib/data-provider'
import { createClient } from '@/lib/supabase/server'

export const dynamic = 'force-dynamic'

interface MobileV3PageProps {
  searchParams: Promise<{ year?: string; anio_ant?: string }>
}

export default async function MobileV3Page({ searchParams }: MobileV3PageProps) {
  const resolvedSearchParams = await searchParams
  const year = parseInt(resolvedSearchParams.year || '2026')
  const anioAnt = resolvedSearchParams.anio_ant || 'todos'

  const supabase = await createClient()
  const { data: { user } } = await supabase.auth.getUser()

  const dashboardPayload = await getDashboardData({ year, anioAnt, periodo: 'year' })

  const sections = createDashboardMobileSections({
    year,
    salesDataRaw: dashboardPayload.sales,
    salesPeriodsRaw: dashboardPayload.sales_periods,
    marginsDataRaw: dashboardPayload.margins,
    impagadosDataRaw: dashboardPayload.impagados,
    albaranesDataRaw: dashboardPayload.albaranes,
    purchasesPeriodsRaw: dashboardPayload.purchases_periods,
    payablesDataRaw: dashboardPayload.payables,
  })

  return (
    <div className="w-full py-1">
      <div className="mx-auto w-full max-w-[450px] px-4 pt-3">
        <MobileDashboardHeader
          referenceDate={sections.find((section) => section.id === 'sales')?.rows[0]?.label || 'Últimos datos disponibles'}
          userId={user?.id || null}
          activeSyncRequest={dashboardPayload.active_sync || null}
          mode={dashboardPayload.mode}
        />
      </div>
      <ExecutiveMobileV3 sections={sections} />
    </div>
  )
}
