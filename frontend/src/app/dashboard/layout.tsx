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

  // 2. Obtener estado de última sincronización
  let syncTimeText = 'Desconocido'
  let isDelayed = false

  try {
    const { data: syncData } = await supabase
      .from('sync_state')
      .select('last_success_at')
      .eq('dataset', 'sales')
      .maybeSingle()

    if (syncData?.last_success_at) {
      const lastSuccess = new Date(syncData.last_success_at)
      const now = new Date()
      const diffMs = now.getTime() - lastSuccess.getTime()
      const diffMins = Math.floor(diffMs / 60000)

      if (diffMins < 60) {
        syncTimeText = `hace ${diffMins} min`
      } else {
        const diffHours = Math.floor(diffMins / 60)
        syncTimeText = `hace ${diffHours} ${diffHours === 1 ? 'hora' : 'horas'}`
        if (diffHours >= 2) {
          isDelayed = true
        }
      }
    }
  } catch (err) {
    console.error('Error al leer el estado de sincronización:', err)
  }

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
    >
      {children}
    </DashboardShell>
  )
}
