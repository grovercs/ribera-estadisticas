import SyncButton from './SyncButton'

interface ActiveSyncRequest {
  id: string
  status: 'pending' | 'running' | 'success' | 'failed'
  source: string
  requested_at: string
  started_at: string | null
  finished_at: string | null
  error_message: string | null
}

interface MobileDashboardHeaderProps {
  referenceDate: string
  userId: string | null
  activeSyncRequest: ActiveSyncRequest | null
}

function capitalizeFirst(value: string) {
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : value
}

export default function MobileDashboardHeader({ referenceDate, userId, activeSyncRequest }: MobileDashboardHeaderProps) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-x-3 gap-y-1 px-1 pb-1">
      <h1 className="text-[21px] font-bold tracking-tight text-[#191c1e]">Cuadro de Dirección</h1>
      {userId && <SyncButton initialActiveRequest={activeSyncRequest} userId={userId} />}
      <p className="w-full text-[13px] text-[#747878]">{capitalizeFirst(referenceDate)}</p>
    </header>
  )
}
