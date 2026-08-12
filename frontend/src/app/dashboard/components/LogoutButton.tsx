'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { createClient } from '@/lib/supabase/client'
import { LogOut, Loader2 } from 'lucide-react'

export default function LogoutButton() {
  const router = useRouter()
  const supabase = createClient()
  const [loading, setLoading] = useState(false)

  const handleLogout = async () => {
    setLoading(true)
    try {
      await supabase.auth.signOut()
      router.refresh()
      router.push('/login')
    } catch (err) {
      console.error('Error al cerrar sesión:', err)
      setLoading(false)
    }
  }

  return (
    <button
      onClick={handleLogout}
      disabled={loading}
      className="flex w-full items-center justify-center space-x-2 rounded-xl border border-slate-800 hover:border-red-900/40 bg-slate-950 py-2 text-xs font-semibold text-slate-400 hover:bg-red-950/10 hover:text-red-400 transition-all duration-200 disabled:pointer-events-none disabled:opacity-50"
    >
      {loading ? (
        <Loader2 className="h-3.5 w-3.5 animate-spin" />
      ) : (
        <>
          <LogOut className="h-3.5 w-3.5" />
          <span>Cerrar sesión</span>
        </>
      )}
    </button>
  )
}
