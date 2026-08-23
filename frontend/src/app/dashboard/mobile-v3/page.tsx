import Link from 'next/link'
import ExecutiveMobileV3 from '../components/ExecutiveMobileV3'
import { createDashboardMobileSections } from '@/lib/dashboardMobileSections'
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

  const [salesRes, salesPeriodsRes, marginsRes, impagadosRes, albaranesRes, purchasesPeriodsRes, payablesRes] = await Promise.all([
    supabase.rpc('get_store_dashboard_sales', { p_year: year, p_anio_ant: anioAnt }),
    supabase.rpc('get_store_dashboard_sales_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_margins', { p_periodo: 'year' }),
    supabase.rpc('get_store_dashboard_impagados'),
    supabase.rpc('get_store_dashboard_albaranes', { p_year: year }),
    supabase.rpc('get_store_dashboard_purchases_periods', { p_year: year }),
    supabase.rpc('get_store_dashboard_payables'),
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
    <div className="w-full py-1">
      <div className="mx-auto w-full max-w-[450px] px-4 pt-3 text-right">
        <Link href="/dashboard" className="text-[13px] font-semibold text-[#206393] underline-offset-2 hover:underline">Volver al dashboard</Link>
      </div>
      <ExecutiveMobileV3 sections={sections} />
    </div>
  )
}
