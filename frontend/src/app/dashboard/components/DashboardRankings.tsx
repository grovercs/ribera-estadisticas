'use client'

import { useState } from 'react'
import Link from 'next/link'

interface DashboardRankingsProps {
  sellers: any[]
  clients: any[]
  products: any[]
  totalSales: number
  yf: number
  mf: number
  yt: number
  mt: number
  hideNoStock: boolean
  basePath?: string
}

export default function DashboardRankings({
  sellers,
  clients,
  products,
  totalSales,
  yf,
  mf,
  yt,
  mt,
  hideNoStock,
  basePath = '/dashboard'
}: DashboardRankingsProps) {
  const [activeTab, setActiveTab] = useState<'clients' | 'products' | 'sellers'>('clients')

  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })
  const numberFormatter = new Intl.NumberFormat('es-ES')

  const getPercentage = (value: number) => {
    return totalSales > 0 ? `${((value / totalSales) * 100).toFixed(1)}%` : '0.0%'
  }

  const tabs = [
    { id: 'clients', label: 'Top Clientes' },
    { id: 'products', label: 'Top Productos' },
    { id: 'sellers', label: 'Top Vendedores' }
  ] as const

  return (
    <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm">
      {/* Cabecera y Selector de Pestañas */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-[#e1e2e6] pb-3 mb-4 gap-3">
        <div className="flex border-b sm:border-b-0 overflow-x-auto sm:overflow-visible w-full sm:w-auto">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`px-3 sm:px-4 py-2 text-sm sm:text-base font-semibold border-b-2 transition-all -mb-px whitespace-nowrap ${
                activeTab === tab.id
                  ? 'border-[#206393] text-[#206393]'
                  : 'border-transparent text-[#747878] hover:text-[#191c1e]'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Botón de Stock interactivo exclusivo de Productos */}
        {activeTab === 'products' && (
          <Link
            href={`${basePath}?year_from=${yf}&month_from=${mf}&year_to=${yt}&month_to=${mt}&hide_no_stock=${!hideNoStock}`}
            className={`px-2.5 sm:px-3 py-1.5 text-xs sm:text-sm font-semibold rounded-lg border transition-all whitespace-nowrap ${
              hideNoStock
                ? 'bg-[#206393] text-white border-[#206393]'
                : 'bg-white text-[#747878] border-[#e1e2e6] hover:bg-[#f8f9fc]'
            }`}
          >
            {hideNoStock ? 'Mostrar también sin stock' : 'Ocultar sin stock'}
          </Link>
        )}
      </div>

      {/* Tabla Desktop (Desktop / Portátil / Tablet) */}
      <div className="hidden sm:block overflow-x-auto">
        
        {/* Pestaña: Clientes */}
        {activeTab === 'clients' && (
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Cliente</th>
                <th className="py-2.5 px-3">Vendedor Principal</th>
                <th className="py-2.5 px-3 text-right">Contribución</th>
                <th className="py-2.5 px-3 text-right">Facturación</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {clients.map((c) => (
                <tr key={c.cod_cliente} className="hover:bg-[#f8f9fc]">
                  <td className="py-3 px-3">
                    <div className="font-semibold text-[#191c1e]">{c.razon_social || `Cliente ${c.cod_cliente}`}</div>
                    {c.poblacion && (
                      <div className="text-sm text-[#747878] font-medium">{c.poblacion}{c.provincia ? `, ${c.provincia}` : ''}</div>
                    )}
                  </td>
                  <td className="py-3 px-3 text-[#747878] font-medium">{c.vendedor_principal || '-'}</td>
                  <td className="py-3 px-3 text-right text-sm text-[#747878] font-semibold">{getPercentage(c.total_spent)}</td>
                  <td className="py-3 px-3 text-right font-bold text-[#206393]">{currencyFormatter.format(c.total_spent)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {/* Pestaña: Productos */}
        {activeTab === 'products' && (
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Artículo</th>
                <th className="py-2.5 px-3 text-right">Stock Actual</th>
                <th className="py-2.5 px-3 text-right">Cantidad</th>
                <th className="py-2.5 px-3 text-right">Contribución</th>
                <th className="py-2.5 px-3 text-right">Facturación</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {products.map((p) => (
                <tr key={p.cod_articulo} className="hover:bg-[#f8f9fc]">
                  <td className="py-3 px-3">
                    <div className="font-mono font-bold text-[#206393]">{p.cod_articulo}</div>
                    <div className="text-sm text-[#747878] truncate max-w-[220px]" title={p.descripcion}>{p.descripcion || '-'}</div>
                  </td>
                  <td className={`py-3 px-3 text-right font-bold ${p.stock_total <= 0 ? 'text-red-600' : 'text-[#191c1e]'}`}>
                    {numberFormatter.format(p.stock_total)}
                  </td>
                  <td className="py-3 px-3 text-right font-medium">{numberFormatter.format(p.total_qty)}</td>
                  <td className="py-3 px-3 text-right text-sm text-[#747878] font-semibold">{getPercentage(p.total_revenue)}</td>
                  <td className="py-3 px-3 text-right font-bold text-[#206393]">{currencyFormatter.format(p.total_revenue)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {/* Pestaña: Vendedores */}
        {activeTab === 'sellers' && (
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Vendedor</th>
                <th className="py-2.5 px-3 text-right">Operaciones</th>
                <th className="py-2.5 px-3 text-right">Contribución</th>
                <th className="py-2.5 px-3 text-right">Total Facturado</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {sellers.map((s) => (
                <tr key={s.cod_vendedor} className="hover:bg-[#f8f9fc]">
                  <td className="py-3 px-3 font-semibold text-[#191c1e]">{s.nombre_vendedor || s.cod_vendedor}</td>
                  <td className="py-3 px-3 text-right font-medium">{numberFormatter.format(s.orders_count)}</td>
                  <td className="py-3 px-3 text-right text-sm text-[#747878] font-semibold">{getPercentage(s.total_sales)}</td>
                  <td className="py-3 px-3 text-right font-bold text-[#206393]">{currencyFormatter.format(s.total_sales)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

      </div>

      {/* Renderizado Móvil (Grid de Cards Compactos para evitar Scroll Horizontal) */}
      <div className="block sm:hidden space-y-3">
        
        {/* Pestaña Móvil: Clientes */}
        {activeTab === 'clients' && clients.map((c) => (
          <div key={c.cod_cliente} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6] space-y-1">
            <div className="flex justify-between items-start">
              <span className="font-semibold text-sm text-[#191c1e] truncate max-w-[70%]">{c.razon_social || `Cliente ${c.cod_cliente}`}</span>
              <span className="text-xs bg-white border border-[#e1e2e6] text-[#206393] px-2 py-0.5 rounded font-bold">{getPercentage(c.total_spent)}</span>
            </div>
            <div className="text-xs text-[#747878]">{c.poblacion || 'Sin localidad'} · Vend: {c.vendedor_principal || '-'}</div>
            <div className="text-right text-base font-bold text-[#206393]">{currencyFormatter.format(c.total_spent)}</div>
          </div>
        ))}

        {/* Pestaña Móvil: Productos */}
        {activeTab === 'products' && products.map((p) => (
          <div key={p.cod_articulo} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6] space-y-1">
            <div className="flex justify-between items-start">
              <span className="font-mono text-sm font-bold text-[#206393]">{p.cod_articulo}</span>
              <span className="text-xs bg-white border border-[#e1e2e6] text-[#206393] px-2 py-0.5 rounded font-bold">{getPercentage(p.total_revenue)}</span>
            </div>
            <div className="text-xs text-[#747878] truncate">{p.descripcion}</div>
            <div className="flex justify-between text-sm pt-1">
              <span>Cant: <strong className="text-[#191c1e]">{numberFormatter.format(p.total_qty)}</strong></span>
              <span>Stock: <strong className={p.stock_total <= 0 ? 'text-red-600' : 'text-[#191c1e]'}>{numberFormatter.format(p.stock_total)}</strong></span>
            </div>
            <div className="text-right text-base font-bold text-[#206393]">{currencyFormatter.format(p.total_revenue)}</div>
          </div>
        ))}

        {/* Pestaña Móvil: Vendedores */}
        {activeTab === 'sellers' && sellers.map((s) => (
          <div key={s.cod_vendedor} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6] space-y-1">
            <div className="flex justify-between items-start">
              <span className="font-semibold text-sm text-[#191c1e]">{s.nombre_vendedor || s.cod_vendedor}</span>
              <span className="text-xs bg-white border border-[#e1e2e6] text-[#206393] px-2 py-0.5 rounded font-bold">{getPercentage(s.total_sales)}</span>
            </div>
            <div className="text-xs text-[#747878]">Ops: {numberFormatter.format(s.orders_count)}</div>
            <div className="text-right text-base font-bold text-[#206393]">{currencyFormatter.format(s.total_sales)}</div>
          </div>
        ))}

      </div>
    </div>
  )
}
