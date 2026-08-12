'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { createClient } from '@/lib/supabase/client'
import { BarChart3, Lock, Mail, Loader2 } from 'lucide-react'

export default function LoginPage() {
  const router = useRouter()
  const supabase = createClient()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const { error: signInError } = await supabase.auth.signInWithPassword({
        email,
        password,
      })

      if (signInError) {
        console.error('[SUPABASE AUTH ERROR]:', signInError)
        setError(signInError.message === 'Invalid login credentials' 
          ? 'Credenciales de acceso inválidas. Por favor, compruébelas.' 
          : signInError.message
        )
        setLoading(false)
        return
      }

      router.refresh()
      router.push('/dashboard')
    } catch (err: any) {
      console.error('[LOGIN EXCEPTION]:', err)
      setError(err.message || 'Ha ocurrido un error inesperado.')
      setLoading(false)
    }
  }

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4">
      {/* Luces decorativas de fondo */}
      <div className="absolute top-1/4 left-1/4 h-[350px] w-[350px] rounded-full bg-violet-600/20 blur-[100px] pointer-events-none" />
      <div className="absolute bottom-1/4 right-1/4 h-[350px] w-[350px] rounded-full bg-indigo-600/20 blur-[100px] pointer-events-none" />

      {/* Tarjeta de login esmerilada */}
      <div className="relative w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/60 p-8 shadow-2xl backdrop-blur-xl transition-all duration-300 hover:border-slate-700/80">
        <div className="flex flex-col items-center justify-center space-y-2 text-center">
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-600 shadow-lg shadow-indigo-500/20">
            <BarChart3 className="h-6 w-6 text-white" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white mt-4">
            Ribera Estadísticas
          </h1>
          <p className="text-sm text-slate-400">
            Control de mandos comercial e histórico
          </p>
        </div>

        <form onSubmit={handleLogin} className="mt-8 space-y-6">
          <div className="space-y-4">
            {/* Campo Email */}
            <div className="space-y-1">
              <label htmlFor="email" className="text-xs font-medium text-slate-300">
                Correo electrónico
              </label>
              <div className="relative">
                <Mail className="absolute top-3 left-3 h-4 w-4 text-slate-500" />
                <input
                  id="email"
                  type="email"
                  required
                  placeholder="nombre@empresa.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  disabled={loading}
                  className="w-full rounded-xl border border-slate-800 bg-slate-950 py-2.5 pl-10 pr-4 text-sm text-slate-100 placeholder-slate-600 outline-none transition-all duration-200 focus:border-indigo-500/80 focus:ring-1 focus:ring-indigo-500/40 disabled:opacity-50"
                />
              </div>
            </div>

            {/* Campo Password */}
            <div className="space-y-1">
              <label htmlFor="password" className="text-xs font-medium text-slate-300">
                Contraseña
              </label>
              <div className="relative">
                <Lock className="absolute top-3 left-3 h-4 w-4 text-slate-500" />
                <input
                  id="password"
                  type="password"
                  required
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  disabled={loading}
                  className="w-full rounded-xl border border-slate-800 bg-slate-950 py-2.5 pl-10 pr-4 text-sm text-slate-100 placeholder-slate-600 outline-none transition-all duration-200 focus:border-indigo-500/80 focus:ring-1 focus:ring-indigo-500/40 disabled:opacity-50"
                />
              </div>
            </div>
          </div>

          {error && (
            <div className="rounded-xl border border-red-900/30 bg-red-950/20 p-3 text-xs text-red-400">
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={loading}
            className="group relative flex w-full justify-center rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/10 hover:shadow-indigo-500/20 transition-all duration-300 hover:brightness-110 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50"
          >
            {loading ? (
              <Loader2 className="h-5 w-5 animate-spin" />
            ) : (
              'Iniciar sesión'
            )}
          </button>
        </form>
      </div>
    </main>
  )
}
