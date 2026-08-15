'use client'

import { useState } from 'react'

interface PurchasesRankingsProps {
  families: any[]
  suppliers: any[]
  taxSummary: any[]
  totalPurchases: number
}

export default function PurchasesRankings({
  families,
  suppliers,
  taxSummary,
  totalPurchases,
}: PurchasesRankingsProps) {
  const [activeTab, setActiveTab] = useState<'families' | 'suppliers' | 'taxes'>('families')

  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })
  const numberFormatter = new Intl.NumberFormat('es-ES')

  const getPercentage = (value: number) => {
    return totalPurchases > 0 ? `${((value / totalPurchases) * 100).toFixed(1)}%` : '0.0%'
  }

  const tabs = [
    { id: 'families', label: 'Top Familias' },
    { id: 'suppliers', label: 'Top Proveedores' },
    { id: 'taxes', label: 'Desglose IVA' },
  ] as const

  return (
    <div className="rounded-xl border border-[#e1e2e6] bg-white p-5 shadow-sm">
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
      </div>

      <div className="hidden sm:block overflow-x-auto">
        {activeTab === 'families' && families.length === 0 && (
          <div className="flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {activeTab === 'families' && families.length > 0 && (
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Familia</th>
                <th className="py-2.5 px-3 text-right">Líneas</th>
                <th className="py-2.5 px-3 text-right">Contribución</th>
                <th className="py-2.5 px-3 text-right">Total Compras</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {families.map((f) => (
                <tr key={f.cod_familia} className="hover:bg-[#f8f9fc]">
                  <td className="py-3 px-3">
                    <div className="font-semibold text-[#191c1e]">{f.familia_nombre || `Fam. ${f.cod_familia}`}</div>
                    <div className="text-sm text-[#747878] font-medium">Código {f.cod_familia}</div>
                  </td>
                  <td className="py-3 px-3 text-right font-medium">{numberFormatter.format(f.line_count)}</td>
                  <td className="py-3 px-3 text-right text-sm text-[#747878] font-semibold">{getPercentage(f.total_purchases)}</td>
                  <td className="py-3 px-3 text-right font-bold text-[#206393]">{currencyFormatter.format(f.total_purchases)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {activeTab === 'suppliers' && suppliers.length === 0 && (
          <div className="flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {activeTab === 'suppliers' && suppliers.length > 0 && (
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Proveedor</th>
                <th className="py-2.5 px-3 text-right">Documentos</th>
                <th className="py-2.5 px-3 text-right">Contribución</th>
                <th className="py-2.5 px-3 text-right">Total Compras</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {suppliers.map((s) => (
                <tr key={s.cod_proveedor} className="hover:bg-[#f8f9fc]">
                  <td className="py-3 px-3">
                    <div className="font-semibold text-[#191c1e]">{s.proveedor_nombre || `Proveedor ${s.cod_proveedor}`}</div>
                  </td>
                  <td className="py-3 px-3 text-right font-medium">{numberFormatter.format(s.document_count)}</td>
                  <td className="py-3 px-3 text-right text-sm text-[#747878] font-semibold">{getPercentage(s.total_purchases)}</td>
                  <td className="py-3 px-3 text-right font-bold text-[#206393]">{currencyFormatter.format(s.total_purchases)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {activeTab === 'taxes' && taxSummary.length === 0 && (
          <div className="flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {activeTab === 'taxes' && taxSummary.length > 0 && (
          <table className="w-full text-sm md:text-base text-left">
            <thead className="text-[#747878] text-sm uppercase border-b border-[#e1e2e6]">
              <tr>
                <th className="py-2.5 px-3">Tipo IVA</th>
                <th className="py-2.5 px-3 text-right">Líneas</th>
                <th className="py-2.5 px-3 text-right">Base Imponible</th>
                <th className="py-2.5 px-3 text-right">Cuota</th>
                <th className="py-2.5 px-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#f2f3f7]">
              {taxSummary.map((t, idx) => (
                <tr key={idx} className="hover:bg-[#f8f9fc]">
                  <td className="py-3 px-3 font-semibold text-[#191c1e]">{t.porcentaje.toFixed(2)} %</td>
                  <td className="py-3 px-3 text-right font-medium">{numberFormatter.format(t.lineas)}</td>
                  <td className="py-3 px-3 text-right font-medium">{currencyFormatter.format(t.base)}</td>
                  <td className="py-3 px-3 text-right font-medium">{currencyFormatter.format(t.cuota)}</td>
                  <td className="py-3 px-3 text-right font-bold text-[#206393]">{currencyFormatter.format(t.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <div className="block sm:hidden space-y-3">
        {activeTab === 'families' && families.length === 0 && (
          <div className="flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {activeTab === 'families' && families.length > 0 && families.map((f) => (
          <div key={f.cod_familia} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6] space-y-1">
            <div className="flex justify-between items-start">
              <span className="font-semibold text-sm text-[#191c1e] truncate max-w-[70%]">{f.familia_nombre || `Fam. ${f.cod_familia}`}</span>
              <span className="text-xs bg-white border border-[#e1e2e6] text-[#206393] px-2 py-0.5 rounded font-bold">{getPercentage(f.total_purchases)}</span>
            </div>
            <div className="text-xs text-[#747878]">Líneas: {numberFormatter.format(f.line_count)}</div>
            <div className="text-right text-base font-bold text-[#206393]">{currencyFormatter.format(f.total_purchases)}</div>
          </div>
        ))}

        {activeTab === 'suppliers' && suppliers.length === 0 && (
          <div className="flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {activeTab === 'suppliers' && suppliers.length > 0 && suppliers.map((s) => (
          <div key={s.cod_proveedor} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6] space-y-1">
            <div className="flex justify-between items-start">
              <span className="font-semibold text-sm text-[#191c1e] truncate max-w-[70%]">{s.proveedor_nombre || `Proveedor ${s.cod_proveedor}`}</span>
              <span className="text-xs bg-white border border-[#e1e2e6] text-[#206393] px-2 py-0.5 rounded font-bold">{getPercentage(s.total_purchases)}</span>
            </div>
            <div className="text-xs text-[#747878]">Docs: {numberFormatter.format(s.document_count)}</div>
            <div className="text-right text-base font-bold text-[#206393]">{currencyFormatter.format(s.total_purchases)}</div>
          </div>
        ))}

        {activeTab === 'taxes' && taxSummary.length === 0 && (
          <div className="flex h-32 items-center justify-center text-sm text-[#747878]">Sin datos registrados</div>
        )}
        {activeTab === 'taxes' && taxSummary.length > 0 && taxSummary.map((t, idx) => (
          <div key={idx} className="p-3 bg-[#f8f9fc] rounded-lg border border-[#e1e2e6] space-y-1">
            <div className="flex justify-between items-start">
              <span className="font-semibold text-sm text-[#191c1e]">IVA {t.porcentaje.toFixed(2)} %</span>
              <span className="text-xs bg-white border border-[#e1e2e6] text-[#747878] px-2 py-0.5 rounded font-bold">{numberFormatter.format(t.lineas)} líneas</span>
            </div>
            <div className="flex justify-between text-sm">
              <span>Base: <strong className="text-[#191c1e]">{currencyFormatter.format(t.base)}</strong></span>
              <span>Cuota: <strong className="text-[#191c1e]">{currencyFormatter.format(t.cuota)}</strong></span>
            </div>
            <div className="text-right text-base font-bold text-[#206393]">Total: {currencyFormatter.format(t.total)}</div>
          </div>
        ))}
      </div>
    </div>
  )
}
