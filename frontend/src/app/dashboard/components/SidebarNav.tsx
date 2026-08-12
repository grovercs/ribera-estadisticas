'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { 
  BarChart3, 
  TrendingUp, 
  ShoppingCart, 
  Percent, 
  CreditCard, 
  Wallet, 
  FileText 
} from 'lucide-react'

export default function SidebarNav() {
  const pathname = usePathname()

  const mainItems = [
    { name: 'Resumen Ejecutivo', href: '/dashboard', icon: BarChart3 },
    { name: 'Ventas', href: '/dashboard/ventas', icon: TrendingUp },
    { name: 'Compras', href: '/dashboard/compras', icon: ShoppingCart },
    { name: 'Rentabilidad', href: '/dashboard/rentabilidad', icon: Percent },
  ]

  const disabledItems = [
    { name: 'Cobros & Cartera', icon: CreditCard, tag: 'Fase 5A OK' },
    { name: 'Tesorería', icon: Wallet, tag: 'Próximamente' },
    { name: 'Cuenta Explotación', icon: FileText, tag: 'Próximamente' },
  ]

  return (
    <nav className="flex-1 space-y-6 px-4 py-6 overflow-y-auto">
      {/* Módulos Activos */}
      <div className="space-y-1">
        <div className="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
          Cuadro de Mando
        </div>
        {mainItems.map((item) => {
          const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href))
          return (
            <Link
              key={item.name}
              href={item.href}
              className={`group flex items-center justify-between rounded-xl px-3.5 py-2.5 text-xs font-semibold transition-all duration-200 ${
                isActive
                  ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-500/20'
                  : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200'
              }`}
            >
              <div className="flex items-center space-x-3">
                <item.icon className={`h-4 w-4 shrink-0 transition-transform group-hover:scale-110 ${
                  isActive ? 'text-white' : 'text-slate-400 group-hover:text-indigo-400'
                }`} />
                <span>{item.name}</span>
              </div>
              {isActive && (
                <div className="h-1.5 w-1.5 rounded-full bg-white shadow-sm" />
              )}
            </Link>
          )
        })}
      </div>

      {/* Módulos Deshabilitados / Futuros */}
      <div className="space-y-1 pt-2 border-t border-slate-900">
        <div className="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-600">
          Futuras Integraciones
        </div>
        {disabledItems.map((item) => (
          <div
            key={item.name}
            className="flex items-center justify-between rounded-xl px-3.5 py-2 text-xs font-medium text-slate-600 opacity-60 cursor-not-allowed select-none"
          >
            <div className="flex items-center space-x-3">
              <item.icon className="h-4 w-4 shrink-0 opacity-40" />
              <span>{item.name}</span>
            </div>
            <span className="rounded bg-slate-800/80 px-1.5 py-0.5 text-[9px] font-semibold text-slate-400">
              {item.tag}
            </span>
          </div>
        ))}
      </div>
    </nav>
  )
}
