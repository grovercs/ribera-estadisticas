import { redirect } from 'next/navigation'
import { createClient } from '@/lib/supabase/server'
import DashboardShell from './components/DashboardShell'

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const supabase = await createClient()

  // 1. Verificar sesión
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) {
    redirect('/login')
  }

  // 2. Obtener estado de sincronización (sync_runs refleja full + quick; sync_state solo full)
  let lastSuccessAt: string | null = null
  let isSyncing = false

  try {
    const { data: runData } = await supabase
      .from('sync_runs')
      .select('completed_at')
      .eq('dataset', 'sales')
      .eq('status', 'success')
      .order('completed_at', { ascending: false })
      .limit(1)
      .maybeSingle()

    if (runData?.completed_at) {
      lastSuccessAt = runData.completed_at
    } else {
      const { data: syncData } = await supabase
        .from('sync_state')
        .select('last_success_at')
        .eq('dataset', 'sales')
        .maybeSingle()

      if (syncData?.last_success_at) {
        lastSuccessAt = syncData.last_success_at
      }
    }

    // Detectar sync manual activa
    const { data: activeRequest } = await supabase
      .from('sync_requests')
      .select('id')
      .eq('dataset', 'sales')
      .in('status', ['pending', 'running'])
      .eq('source', 'manual')
      .order('requested_at', { ascending: false })
      .limit(1)
      .maybeSingle()

    isSyncing = !!activeRequest
  } catch (err) {
    console.error('Error al leer el estado de sincronización:', err)
  }

  const { syncTimeText, isDelayed } = buildSyncBadgeText(lastSuccessAt, isSyncing)

  // 3. Menú de navegación
  const menuItems = [
    { name: 'Cuadro de Dirección', href: '/dashboard', icon: '📋', active: true },
    { name: 'Resumen Ejecutivo',   href: '/dashboard/resumen', icon: '📊', active: true },
    { name: 'Análisis de Ventas',  href: '/dashboard/ventas', icon: '📈', active: true },
    { name: 'Análisis de Compras', href: '/dashboard/compras', icon: '🚛', active: true },
    { name: 'Rentabilidad',        href: '/dashboard/rentabilidad', icon: '💰', active: true },
    { name: 'Existencias y Stock', href: '#', icon: '📦', active: false },
    { name: 'Cartera de Clientes', href: '#', icon: '👥', active: false },
  ]

  const userInitial = user.email ? user.email.charAt(0).toUpperCase() : 'U'
  const userEmail = user.email || ''

  return (
    <DashboardShell
      menuItems={menuItems}
      userInitial={userInitial}
      userEmail={userEmail}
      syncTimeText={syncTimeText}
      isDelayed={isDelayed}
      isSyncing={isSyncing}
    >
      {children}
    </DashboardShell>
  )
}

function buildSyncBadgeText(lastSuccessAt: string | null, isSyncing: boolean): { syncTimeText: string; isDelayed: boolean } {
  if (isSyncing) {
    return { syncTimeText: 'Sincronizando...', isDelayed: false }
  }

  if (!lastSuccessAt) {
    return { syncTimeText: 'Desconocido', isDelayed: false }
  }

  const lastSuccess = new Date(lastSuccessAt)
  const now = new Date()
  const diffMs = now.getTime() - lastSuccess.getTime()
  const diffMins = Math.floor(diffMs / 60000)

  if (diffMins < 60) {
    return { syncTimeText: `hace ${diffMins} min`, isDelayed: false }
  }

  const diffHours = Math.floor(diffMins / 60)
  const syncTimeText = `hace ${diffHours} ${diffHours === 1 ? 'hora' : 'horas'}`
  return { syncTimeText, isDelayed: diffHours >= 2 }
}
