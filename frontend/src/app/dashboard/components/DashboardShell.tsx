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
  isSyncing: boolean
  onClose: () => void
}

function MobileDrawer({ menuItems, userInitial, userEmail, syncTimeText, isDelayed, isSyncing, onClose }: MobileDrawerProps) {
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
          <SyncBadge syncTimeText={syncTimeText} isDelayed={isDelayed} isSyncing={isSyncing} />
          <LogoutButton />
        </div>

      </aside>
    </>
  )
}

interface DesktopDrawerProps {
  menuItems: MenuItem[]
  userInitial: string
  userEmail: string
  onClose: () => void
}

function DesktopDrawer({ menuItems, userInitial, userEmail, onClose }: DesktopDrawerProps) {
  const pathname = usePathname()

  return (
    <>
      <div
        className="fixed inset-0 z-40 hidden bg-black/40 backdrop-blur-sm lg:block"
        onClick={onClose}
        aria-hidden="true"
      />

      <aside className="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col border-r border-[#e1e2e6] bg-[#f8f9fc] text-base font-semibold shadow-2xl animate-in slide-in-from-left duration-200 lg:flex">
        <div className="flex items-center justify-between gap-3.5 border-b border-[#e1e2e6] p-6">
          <div className="flex items-center gap-3.5">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-[#181919] text-white shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-6 w-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A4.86 4.86 0 0012 8c-2.316 0-4.329.805-5.918 2.148V21M3 9.75V21h18V9.75" />
              </svg>
            </div>
            <div>
              <h1 className="text-2xl font-black leading-none tracking-tight text-[#191c1e]">Ribera</h1>
              <p className="mt-0.5 text-sm font-semibold uppercase tracking-wider text-[#747878]">Estadísticas</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-2 transition-colors hover:bg-[#e1e2e6]"
            aria-label="Cerrar menú"
          >
            <X className="h-5 w-5 text-[#747878]" />
          </button>
        </div>

        <nav className="flex-1 space-y-1.5 overflow-y-auto py-6 pr-3">
          {menuItems.map((item) => {
            const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href))
            return item.active ? (
              <Link
                key={item.name}
                href={item.href}
                onClick={onClose}
                className={`flex items-center gap-3.5 rounded-r-xl py-3 px-5 transition-all duration-100 ${
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
                className="flex cursor-not-allowed select-none items-center justify-between rounded-r-xl py-2.5 px-5 text-[#c8cacc] opacity-60"
              >
                <div className="flex items-center gap-3.5">
                  <span className="shrink-0 text-xl opacity-30">{item.icon}</span>
                  <span className="text-sm font-medium">{item.name}</span>
                </div>
                <span className="scale-90 rounded bg-slate-200/50 px-1.5 py-0.5 text-xs font-normal uppercase text-[#747878]">
                  Pronto
                </span>
              </div>
            )
          })}
        </nav>

        <div className="space-y-3 border-t border-[#e1e2e6] p-5">
          <div className="flex items-center gap-3 px-2">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#206393] text-sm font-bold text-white">
              {userInitial}
            </div>
            <div className="min-w-0">
              <p className="truncate text-sm font-bold leading-none text-[#191c1e]">{userEmail.split('@')[0]}</p>
              <p className="mt-0.5 truncate text-xs text-[#747878]">{userEmail}</p>
            </div>
          </div>
          <LogoutButton />
        </div>
      </aside>
    </>
  )
}

function SyncBadge({ syncTimeText, isDelayed, isSyncing }: { syncTimeText: string; isDelayed: boolean; isSyncing: boolean }) {
  let badgeClass: string
  let dotClass: string
  let label: string

  if (isSyncing) {
    badgeClass = 'bg-amber-50 border border-amber-200 text-amber-600'
    dotClass = 'bg-amber-500 animate-pulse'
    label = 'Sincronizando...'
  } else if (isDelayed) {
    badgeClass = 'bg-amber-50 border border-amber-200 text-amber-600'
    dotClass = 'bg-amber-500 animate-pulse'
    label = `Sinc: ${syncTimeText}`
  } else {
    badgeClass = 'bg-emerald-50 border border-emerald-200 text-emerald-600'
    dotClass = 'bg-emerald-500'
    label = `Sinc: ${syncTimeText}`
  }

  return (
    <div className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs md:gap-1 md:px-2 md:py-0.5 md:text-[10px] ${badgeClass}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${dotClass}`} />
      <span className="text-xs font-bold uppercase tracking-wider md:text-[10px]">{label}</span>
    </div>
  )
}



interface ShellProps {
  menuItems: MenuItem[]
  userInitial: string
  userEmail: string
  syncTimeText: string
  isDelayed: boolean
  isSyncing: boolean
  children: React.ReactNode
}

export default function DashboardShell({ menuItems, userInitial, userEmail, syncTimeText, isDelayed, isSyncing, children }: ShellProps) {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [desktopOpen, setDesktopOpen] = useState(false)
  const pathname = usePathname()
  const isDirectionDashboard = pathname === '/dashboard'

  // Cerrar drawers al cambiar de ruta
  useEffect(() => {
    setMobileOpen(false)
    setDesktopOpen(false)
  }, [pathname])

  useEffect(() => {
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMobileOpen(false)
        setDesktopOpen(false)
      }
    }

    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [])

  return (
    <div className="flex h-screen overflow-hidden bg-[#f8f9fc] text-[#191c1e] font-sans antialiased">

      {/* ── Sidebar Desktop ───────────────────────────────────────────── */}
      <aside className="hidden">

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
          isSyncing={isSyncing}
          onClose={() => setMobileOpen(false)}
        />
      )}

      {desktopOpen && (
        <DesktopDrawer
          menuItems={menuItems}
          userInitial={userInitial}
          userEmail={userEmail}
          onClose={() => setDesktopOpen(false)}
        />
      )}

      {/* ── Área Principal ────────────────────────────────────────────── */}
      <div className="flex-1 flex flex-col relative min-w-0">

        {/* Topbar */}
        <header className={`z-40 flex h-14 w-full flex-shrink-0 items-center justify-between border-b border-[#e1e2e6] bg-white px-4 shadow-sm md:h-9 md:px-2 lg:h-10 lg:px-3 ${isDirectionDashboard ? 'dashboard-direction-topbar' : ''}`}>
          <div className="flex items-center gap-3 md:gap-2">
            {/* Hamburger (solo mobile) */}
            <button
              className="rounded-lg p-2 transition-colors hover:bg-[#f0f4f8] md:p-1.5 lg:hidden"
              onClick={() => setMobileOpen(true)}
              aria-label="Abrir menú"
            >
              <Menu className="w-5 h-5 text-[#191c1e]" />
            </button>

            <button
              className="hidden rounded-lg p-2 transition-colors hover:bg-[#f0f4f8] lg:inline-flex lg:p-1.5"
              onClick={() => setDesktopOpen(true)}
              aria-label="Abrir menú de navegación"
            >
              <Menu className="h-5 w-5 text-[#191c1e]" />
            </button>

            <span className="text-lg font-black tracking-tight text-[#191c1e] md:hidden">Ribera</span>

            {/* Badge de sincronización */}
            <div className="hidden items-center sm:flex md:hidden">
              <SyncBadge syncTimeText={syncTimeText} isDelayed={isDelayed} isSyncing={isSyncing} />
            </div>
          </div>

          {/* Usuario */}
          <div className="flex items-center gap-3 md:hidden">
            <div className="hidden text-right sm:block">
              <p className="text-sm font-bold leading-none text-[#191c1e] md:text-xs">{userEmail.split('@')[0]}</p>
              <p className="mt-0.5 text-xs text-[#747878] md:mt-0 md:text-[10px]">{userEmail}</p>
            </div>
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#206393] text-sm font-bold text-white md:h-7 md:w-7 md:text-xs">
              {userInitial}
            </div>
          </div>
        </header>

        {/* Main content */}
        <main className={`relative z-0 flex-1 overflow-y-auto bg-[#f8f9fc] p-4 md:px-3 md:py-2 lg:px-4 lg:py-2.5 xl:px-6 xl:py-3 2xl:p-8 ${isDirectionDashboard ? 'dashboard-direction-main' : ''}`}>
          <div className="w-full space-y-6">
            {children}
          </div>
        </main>
      </div>

    </div>
  )
}
