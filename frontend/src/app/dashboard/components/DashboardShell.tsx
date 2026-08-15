'use client'

import { useState, useEffect } from 'react'
import LogoutButton from './LogoutButton'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { X, Menu } from 'lucide-react'

interface MenuItem {
  name: string
  href: string
  icon: string
  active: boolean
}

interface MobileDrawerProps {
  menuItems: MenuItem[]
  userInitial: string
  userEmail: string
  syncTimeText: string
  isDelayed: boolean
  onClose: () => void
}

function MobileDrawer({ menuItems, userInitial, userEmail, syncTimeText, isDelayed, onClose }: MobileDrawerProps) {
  const pathname = usePathname()

  return (
    <>
      {/* Overlay */}
      <div
        className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Drawer */}
      <aside className="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#f8f9fc] border-r border-[#e1e2e6] shadow-2xl lg:hidden animate-in slide-in-from-left duration-200">

        {/* Logo + cerrar */}
        <div className="flex items-center justify-between p-5 border-b border-[#e1e2e6]">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded bg-[#181919] flex items-center justify-center text-white shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="w-5 h-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A4.86 4.86 0 0012 8c-2.316 0-4.329.805-5.918 2.148V21M3 9.75V21h18V9.75" />
              </svg>
            </div>
            <div>
              <span className="text-xl font-black text-[#191c1e] tracking-tight leading-none">Ribera</span>
              <p className="text-xs text-[#747878] font-semibold uppercase tracking-wider">Estadísticas</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg hover:bg-[#e1e2e6] transition-colors"
            aria-label="Cerrar menú"
          >
            <X className="w-5 h-5 text-[#747878]" />
          </button>
        </div>

        {/* Nav */}
        <nav className="flex-1 py-4 space-y-1 overflow-y-auto px-3">
          {menuItems.map((item) => {
            const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href))
            return item.active ? (
              <Link
                key={item.name}
                href={item.href}
                onClick={onClose}
                className={`flex items-center gap-3 py-3 px-4 rounded-xl transition-all duration-100 ${
                  isActive
                    ? 'bg-white text-[#206393] border-l-4 border-[#206393] shadow-sm font-black'
                    : 'text-[#191c1e] hover:bg-white/70 font-semibold'
                }`}
              >
                <span className="text-2xl shrink-0">{item.icon}</span>
                <span className="text-base">{item.name}</span>
              </Link>
            ) : (
              <div
                key={item.name}
                className="flex items-center gap-3 py-2.5 px-4 rounded-xl text-[#c8cacc] cursor-not-allowed opacity-50"
              >
                <span className="text-xl shrink-0 opacity-40">{item.icon}</span>
                <span className="text-sm font-medium">{item.name}</span>
              </div>
            )
          })}
        </nav>

        {/* Footer */}
        <div className="p-4 border-t border-[#e1e2e6] space-y-3">
          <div className="flex items-center gap-3 px-2">
            <div className="h-8 w-8 rounded-full bg-[#206393] text-white flex items-center justify-center font-bold text-sm shrink-0">
              {userInitial}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-bold text-[#191c1e] leading-none truncate">{userEmail.split('@')[0]}</p>
              <p className="text-xs text-[#747878] mt-0.5 truncate">{userEmail}</p>
            </div>
          </div>
          <div className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs ${
            isDelayed ? 'bg-amber-50 border border-amber-200 text-amber-600' : 'bg-emerald-50 border border-emerald-200 text-emerald-600'
          }`}>
            <span className={`h-1.5 w-1.5 rounded-full ${isDelayed ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'}`} />
            <span className="text-xs font-bold uppercase tracking-wider">Sinc: {syncTimeText}</span>
          </div>
          <LogoutButton />
        </div>

      </aside>
    </>
  )
}



interface ShellProps {
  menuItems: MenuItem[]
  userInitial: string
  userEmail: string
  syncTimeText: string
  isDelayed: boolean
  children: React.ReactNode
}

export default function DashboardShell({ menuItems, userInitial, userEmail, syncTimeText, isDelayed, children }: ShellProps) {
  const [mobileOpen, setMobileOpen] = useState(false)
  const pathname = usePathname()

  // Cerrar drawer al cambiar de ruta
  useEffect(() => {
    setMobileOpen(false)
  }, [pathname])

  return (
    <div className="flex h-screen overflow-hidden bg-[#f8f9fc] text-[#191c1e] font-sans antialiased">

      {/* ── Sidebar Desktop ───────────────────────────────────────────── */}
      <aside className="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#f8f9fc] border-r border-[#e1e2e6] hidden lg:flex text-base font-semibold">

        {/* Logo */}
        <div className="flex items-center gap-3.5 p-6 border-b border-[#e1e2e6]">
          <div className="w-11 h-11 rounded bg-[#181919] flex items-center justify-center text-white shadow-sm shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="w-6 h-6">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A4.86 4.86 0 0012 8c-2.316 0-4.329.805-5.918 2.148V21M3 9.75V21h18V9.75" />
            </svg>
          </div>
          <div>
            <h1 className="text-2xl font-black text-[#191c1e] tracking-tight leading-none">Ribera</h1>
            <p className="text-sm text-[#747878] font-semibold mt-0.5 uppercase tracking-wider">Estadísticas</p>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 py-6 space-y-1.5 overflow-y-auto pr-3">
          {menuItems.map((item) => {
            const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href))
            return item.active ? (
              <Link
                key={item.name}
                href={item.href}
                className={`flex items-center gap-3.5 py-3 px-5 rounded-r-xl transition-all duration-100 ${
                  isActive
                    ? 'bg-white text-[#206393] border-l-[5px] border-[#206393] shadow-md font-black'
                    : 'text-[#191c1e] hover:bg-white/70 hover:text-[#206393] font-semibold'
                }`}
              >
                <span className="shrink-0 text-2xl">{item.icon}</span>
                <span className="text-base tracking-tight">{item.name}</span>
              </Link>
            ) : (
              <div
                key={item.name}
                className="flex items-center justify-between py-2.5 px-5 rounded-r-xl text-[#c8cacc] cursor-not-allowed select-none opacity-60"
              >
                <div className="flex items-center gap-3.5">
                  <span className="shrink-0 text-xl opacity-30">{item.icon}</span>
                  <span className="font-medium text-sm">{item.name}</span>
                </div>
                <span className="text-xs bg-slate-200/50 text-[#747878] px-1.5 py-0.5 rounded font-normal uppercase scale-90">
                  Pronto
                </span>
              </div>
            )
          })}
        </nav>

        {/* Footer */}
        <div className="p-5 border-t border-[#e1e2e6]">
          <LogoutButton />
        </div>
      </aside>

      {/* ── Mobile Drawer ─────────────────────────────────────────────── */}
      {mobileOpen && (
        <MobileDrawer
          menuItems={menuItems}
          userInitial={userInitial}
          userEmail={userEmail}
          syncTimeText={syncTimeText}
          isDelayed={isDelayed}
          onClose={() => setMobileOpen(false)}
        />
      )}

      {/* ── Área Principal ────────────────────────────────────────────── */}
      <div className="flex-1 flex flex-col lg:ml-72 relative min-w-0">

        {/* Topbar */}
        <header className="flex justify-between items-center w-full h-14 px-4 md:px-6 bg-white border-b border-[#e1e2e6] shadow-sm z-40 flex-shrink-0">
          <div className="flex items-center gap-3">
            {/* Hamburger (solo mobile) */}
            <button
              className="p-2 rounded-lg hover:bg-[#f0f4f8] transition-colors lg:hidden"
              onClick={() => setMobileOpen(true)}
              aria-label="Abrir menú"
            >
              <Menu className="w-5 h-5 text-[#191c1e]" />
            </button>

            <span className="text-lg font-black tracking-tight text-[#191c1e] lg:hidden">Ribera</span>

            {/* Badge de sincronización */}
            <div className="hidden sm:flex items-center">
              {isDelayed ? (
                <div className="flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-amber-600">
                  <span className="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />
                  <span className="text-xs font-bold uppercase tracking-wider">Sinc: {syncTimeText}</span>
                </div>
              ) : (
                <div className="flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-emerald-600">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                  <span className="text-xs font-bold uppercase tracking-wider">Sinc: {syncTimeText}</span>
                </div>
              )}
            </div>
          </div>

          {/* Usuario */}
          <div className="flex items-center gap-3">
            <div className="text-right hidden sm:block">
              <p className="text-sm font-bold text-[#191c1e] leading-none">{userEmail.split('@')[0]}</p>
              <p className="text-xs text-[#747878] mt-0.5">{userEmail}</p>
            </div>
            <div className="h-8 w-8 rounded-full bg-[#206393] text-white flex items-center justify-center font-bold text-sm shrink-0">
              {userInitial}
            </div>
          </div>
        </header>

        {/* Main content */}
        <main className="flex-1 overflow-y-auto p-4 md:p-6 bg-[#f8f9fc] relative z-0">
          <div className="w-full space-y-6">
            {children}
          </div>
        </main>
      </div>

    </div>
  )
}


