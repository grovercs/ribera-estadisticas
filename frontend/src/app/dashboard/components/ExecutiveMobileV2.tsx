'use client'

import { ChevronDown } from 'lucide-react'
import { useRef, useState } from 'react'
import type { DashboardMobileRow, DashboardMobileSection } from '@/lib/dashboardMobileSections'

interface ExecutiveMobileV2Props {
  sections: DashboardMobileSection[]
}

const truncateTo2Decimals = (value: number) => Math.trunc((Number.isFinite(value) ? value : 0) * 100) / 100
const roundTo2Decimals = (value: number) => Math.round((Number.isFinite(value) ? value : 0) * 100) / 100

const currencyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2,
})
const numberFormatter = new Intl.NumberFormat('es-ES')
const percentFormatter = new Intl.NumberFormat('es-ES', {
  style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2,
})

function formatValue(value: number | undefined, format: DashboardMobileRow['format']) {
  const safeValue = Number(value) || 0
  if (format === 'num') return numberFormatter.format(safeValue)
  if (format === 'pct') return percentFormatter.format(roundTo2Decimals(safeValue) / 100)
  return currencyFormatter.format(truncateTo2Decimals(safeValue))
}

function rowTotal(row: DashboardMobileRow) {
  if (row.totalValue !== undefined) return Number(row.totalValue) || 0
  return (Number(row.vielhaValue) || 0) + (Number(row.pontValue) || 0)
}

function rowCount(row: DashboardMobileRow) {
  if (row.totalCount !== undefined) return Number(row.totalCount) || 0
  return (Number(row.vielhaCount) || 0) + (Number(row.pontCount) || 0)
}

function summaryRow(section: DashboardMobileSection) {
  const summaryIndex: Record<DashboardMobileSection['id'], number> = {
    sales: 0,
    invoices: 0,
    receivables: section.rows.findIndex((row) => row.label === 'Cartera Pendiente Total'),
    margins: section.rows.findIndex((row) => row.label === 'Margen %'),
    'delivery-notes': section.rows.findIndex((row) => row.label === 'Importe'),
    purchases: section.rows.findIndex((row) => row.label === 'Mes Actual'),
    payables: section.rows.findIndex((row) => row.label === 'Total Pagos'),
  }
  return section.rows[summaryIndex[section.id]] || section.rows[0]
}

export default function ExecutiveMobileV2({ sections }: ExecutiveMobileV2Props) {
  const [openSectionId, setOpenSectionId] = useState<DashboardMobileSection['id'] | null>('sales')
  const sectionRefs = useRef<Partial<Record<DashboardMobileSection['id'], HTMLElement | null>>>({})

  const openSection = (id: DashboardMobileSection['id']) => {
    setOpenSectionId(id)
    window.setTimeout(() => sectionRefs.current[id]?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0)
  }

  return (
    <div className="mx-auto w-full max-w-[430px] space-y-3 px-3 pb-8 pt-3 text-[#191c1e] md:my-6 md:rounded-2xl md:border md:border-[#e1e2e6] md:bg-[#f5f7f9] md:shadow-sm">
      <nav aria-label="Navegación rápida por secciones" className="-mx-3 flex gap-2 overflow-x-auto px-3 pb-1 [scrollbar-width:none]">
        {sections.map((section) => {
          const isOpen = openSectionId === section.id
          return (
            <button key={section.id} type="button" onClick={() => openSection(section.id)} className={`shrink-0 rounded-full border px-3 py-1.5 text-[13px] font-semibold transition-colors ${isOpen ? 'border-[#206393] bg-[#206393] text-white' : 'border-[#d8e0e7] bg-white text-[#466276]'}`}>
              {section.title.replace(/^\d+ · /, '').replace(' y Pendientes de Cobro', '')}
            </button>
          )
        })}
      </nav>

      <div className="space-y-3">
        {sections.map((section) => {
          const isOpen = openSectionId === section.id
          const summary = summaryRow(section)
          const isMarginSection = section.id === 'margins'
          const sectionId = `mobile-v2-${section.id}`

          return (
            <section key={section.id} id={sectionId} ref={(node) => { sectionRefs.current[section.id] = node }} className="scroll-mt-3 overflow-hidden rounded-xl border border-[#dbe3e9] bg-white shadow-sm">
              <button type="button" aria-expanded={isOpen} aria-controls={`${sectionId}-content`} onClick={() => setOpenSectionId(isOpen ? null : section.id)} className="flex w-full items-center gap-3 bg-[#206393] px-3 py-2.5 text-left text-white">
                <span className="min-w-0 flex-1 text-[14px] font-black uppercase tracking-wide">{section.title}</span>
                {!isOpen && <span className={`max-w-[43%] truncate text-right text-[12px] font-semibold ${isMarginSection ? 'text-emerald-100' : 'text-blue-50'}`}>{summary.label}: {formatValue(rowTotal(summary), summary.format)}</span>}
                <ChevronDown aria-hidden="true" className={`h-4 w-4 shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
              </button>

              {isOpen && (
                <div id={`${sectionId}-content`} className="space-y-2.5 p-2.5">
                  {section.rows.map((row, index) => {
                    if (row.subheader) return <p key={`${row.label}-${index}`} className="pt-1 text-[13px] font-bold text-[#466276]">{row.label}</p>

                    const isMarginRow = isMarginSection && row.label === 'Margen %' && row.format === 'pct'
                    const total = rowTotal(row)
                    const totalCount = rowCount(row)
                    const valueClass = isMarginRow ? 'text-emerald-800' : row.highlight ? 'text-[#206393]' : row.muted ? 'text-[#747878]' : 'text-[#191c1e]'

                    if (row.totalOnly) {
                      return (
                        <div key={`${row.label}-${index}`} className={`rounded-lg px-3 py-2.5 ${row.highlight ? 'bg-blue-50' : 'bg-[#f7f8fa]'}`}>
                          <p className={`text-[14px] font-semibold ${row.muted ? 'text-[#747878]' : 'text-[#466276]'}`}>{row.label}</p>
                          <p className={`mt-1 whitespace-nowrap text-[16px] font-black ${valueClass}`}>
                            {formatValue(total, row.format)}{totalCount > 0 && <span className="ml-1 text-[11px] font-medium text-[#9aa0a6]">({numberFormatter.format(totalCount)})</span>}
                          </p>
                        </div>
                      )
                    }

                    return (
                      <div key={`${row.label}-${index}`} className={`rounded-lg px-3 py-2.5 ${isMarginRow ? 'bg-emerald-50' : row.highlight ? 'bg-blue-50' : 'bg-[#f8fafb]'}`}>
                        <div className="flex items-start justify-between gap-3">
                          <p className={`text-[14px] font-semibold ${isMarginRow ? 'text-emerald-800' : row.muted ? 'text-[#747878]' : 'text-[#466276]'}`}>{row.label}</p>
                          <div className="min-w-0 text-right">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-[#8a9298]">Total</p>
                            <p className={`whitespace-nowrap text-[16px] ${isMarginRow ? 'font-black text-emerald-800' : 'font-black'} ${valueClass}`}>
                              {formatValue(total, row.format)}{totalCount > 0 && <span className="ml-1 text-[11px] font-medium text-[#9aa0a6]">({numberFormatter.format(totalCount)})</span>}
                            </p>
                          </div>
                        </div>
                        <div className="mt-2 grid grid-cols-2 gap-2">
                          {[
                            ['Vielha', row.vielhaValue, row.vielhaCount],
                            ['Pont', row.pontValue, row.pontCount],
                          ].map(([store, value, count]) => (
                            <div key={String(store)} className="min-w-0 rounded-md bg-white px-2.5 py-2">
                              <p className={`text-[13px] font-medium ${isMarginRow ? 'text-emerald-700' : 'text-[#747878]'}`}>{store}</p>
                              <p className={`mt-0.5 whitespace-nowrap text-[15px] ${isMarginRow ? 'font-bold text-emerald-700' : 'font-semibold text-[#191c1e]'}`}>
                                {formatValue(Number(value), row.format)}{Number(count) > 0 && <span className="ml-1 text-[11px] font-medium text-[#9aa0a6]">({numberFormatter.format(Number(count))})</span>}
                              </p>
                            </div>
                          ))}
                        </div>
                      </div>
                    )
                  })}
                </div>
              )}
            </section>
          )
        })}
      </div>
    </div>
  )
}
