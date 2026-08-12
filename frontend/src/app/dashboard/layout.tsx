import { redirect } from 'next/navigation'
import { createClient } from '@/lib/supabase/server'
import { 
  BarChart3, 
  TrendingUp, 
  Warehouse, 
  Users, 
  Building2, 
  Package, 
  Layers, 
  LogOut, 
  User as UserIcon,
  RefreshCw,
  AlertTriangle
} from 'lucide-react'
import Link from 'next/link'
import LogoutButton from './components/LogoutButton'

import SidebarNav from './components/SidebarNav'

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
        
        // Retraso si es mayor o igual a 2 horas
        if (diffHours >= 2) {
          isDelayed = true
        }
      }
    }
  } catch (err) {
    console.error('Error al leer el estado de sincronización:', err)
  }

  return (
    <div className="flex min-h-screen bg-slate-950 text-slate-100 antialiased">
      {/* Sidebar Fija */}
      <aside className="fixed inset-y-0 left-0 z-20 flex w-64 flex-col border-r border-slate-900 bg-slate-900/40 backdrop-blur-md">
        {/* Header/Logo */}
        <div className="flex h-16 items-center border-b border-slate-900 px-6 space-x-2">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-tr from-violet-600 to-indigo-600 shadow-md shadow-indigo-500/10">
            <BarChart3 className="h-4 w-4 text-white" />
          </div>
          <span className="text-lg font-bold text-white tracking-wide">Ribera Estadísticas</span>
        </div>

        {/* Links navegación Client Component */}
        <SidebarNav />

        {/* Footer/Usuario */}
        <div className="flex flex-col border-t border-slate-900 p-4 space-y-3">
          <div className="flex items-center space-x-3 px-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 text-slate-300">
              <UserIcon className="h-4 w-4" />
            </div>
            <div className="flex flex-col min-w-0">
              <span className="text-xs font-semibold text-slate-200 truncate">
                {user.email?.split('@')[0]}
              </span>
              <span className="text-[10px] text-slate-500 truncate">
                {user.email}
              </span>
            </div>
          </div>
          <LogoutButton />
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex flex-1 flex-col pl-64">
        {/* Topbar/Header */}
        <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-900/80 bg-slate-950/70 px-8 backdrop-blur-md">
          <h2 className="text-sm font-semibold text-slate-300">
            Panel de Estadísticas
          </h2>
          <div className="flex items-center space-x-4">
            {isDelayed ? (
              <div className="flex items-center space-x-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 px-3 py-1 text-amber-500">
                <AlertTriangle className="h-3.5 w-3.5" />
                <span className="text-[10px] font-semibold tracking-wide">
                  Retraso de datos: última sinc. {syncTimeText}
                </span>
              </div>
            ) : (
              <div className="flex items-center space-x-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 text-emerald-500">
                <RefreshCw className="h-3 w-3 animate-spin [animation-duration:10s]" />
                <span className="text-[10px] font-semibold tracking-wide">
                  Sincronizado: {syncTimeText}
                </span>
              </div>
            )}
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 p-8">
          {children}
        </main>
      </div>
    </div>
  )
}
