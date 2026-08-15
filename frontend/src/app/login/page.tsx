'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { createClient } from '@/lib/supabase/client'
import { Loader2 } from 'lucide-react'

export default function LoginPage() {
  const router = useRouter()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const supabase = createClient()
      const { error: signInError } = await supabase.auth.signInWithPassword({
        email,
        password,
      })

      if (signInError) {
        console.error('[SUPABASE AUTH ERROR]:', signInError)
        setError(
          signInError.message === 'Invalid login credentials'
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
      setError(err.message || 'Ha ocurrido un error inesperado al conectar.')
      setLoading(false)
    }
  }

  return (
    <main className="min-h-screen flex items-center justify-center bg-[#f8f9fc] px-4 font-sans antialiased text-[#191c1e]">
      <div className="w-full max-w-md">
        {/* Encabezado Logo */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 rounded-xl bg-[#181919] flex items-center justify-center text-white mx-auto mb-4 shadow-md">
            {/* SVG original de barras de estadística */}
            <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-[#191c1e] tracking-tight">Ribera Estadísticas</h1>
          <p className="text-sm text-[#747878] mt-1">Sistema de inteligencia de negocio</p>
        </div>

        {/* Tarjeta Glass-Card Clara */}
        <div className="rounded-2xl p-8 bg-white border border-[#e1e2e6] shadow-sm">
          <form onSubmit={handleLogin} className="space-y-5">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-[#191c1e] mb-1">
                Correo electrónico
              </label>
              <input
                id="email"
                type="email"
                required
                placeholder="nombre@empresa.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={loading}
                className="w-full px-4 py-2.5 rounded-lg border border-[#e1e2e6] bg-[#f8f9fc] text-[#191c1e] placeholder-[#747878] outline-none transition-all duration-200 focus:ring-2 focus:ring-[#206393] focus:border-transparent disabled:opacity-50"
              />
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-[#191c1e] mb-1">
                Contraseña
              </label>
              <input
                id="password"
                type="password"
                required
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={loading}
                className="w-full px-4 py-2.5 rounded-lg border border-[#e1e2e6] bg-[#f8f9fc] text-[#191c1e] placeholder-[#747878] outline-none transition-all duration-200 focus:ring-2 focus:ring-[#206393] focus:border-transparent disabled:opacity-50"
              />
            </div>

            {error && (
              <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-600 font-medium">
                {error}
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full flex items-center justify-center bg-[#181919] hover:bg-[#2d2e2e] text-white font-semibold py-2.5 rounded-lg transition-colors duration-200 disabled:opacity-50 active:scale-[0.99]"
            >
              {loading ? (
                <Loader2 className="h-5 w-5 animate-spin" />
              ) : (
                'Iniciar sesión'
              )}
            </button>
          </form>
        </div>
      </div>
    </main>
  )
}
