'use client'

import { ChevronRight } from 'lucide-react'
import { useState } from 'react'
import type { DashboardMobileRow, DashboardMobileSection } from '@/lib/dashboardMobileSections'

interface ExecutiveMobileV3Props {
  sections: DashboardMobileSection[]
}

type Tone = 'blue' | 'green' | 'red' | 'orange'

const currencyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2,
})
const numberFormatter = new Intl.NumberFormat('es-ES')
const percentFormatter = new Intl.NumberFormat('es-ES', {
  style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2,
})

function truncateTo2Decimals(value: number) {
  return Math.trunc((Number.isFinite(value) ? value : 0) * 100) / 100
}

function formatValue(value: number | undefined, format: DashboardMobileRow['format']) {
  const safeValue = Number(value) || 0
  if (format === 'num') return numberFormatter.format(safeValue)
  if (format === 'pct') return percentFormatter.format(truncateTo2Decimals(safeValue) / 100)
  return currencyFormatter.format(truncateTo2Decimals(safeValue))
}

function totalValue(row: DashboardMobileRow) {
  return row.totalValue !== undefined
    ? Number(row.totalValue) || 0
    : (Number(row.vielhaValue) || 0) + (Number(row.pontValue) || 0)
}

function totalCount(row: DashboardMobileRow) {
  return row.totalCount !== undefined
    ? Number(row.totalCount) || 0
    : (Number(row.vielhaCount) || 0) + (Number(row.pontCount) || 0)
}

function cardMeta(section: DashboardMobileSection): { title: string; descriptor: string; row: DashboardMobileRow; tone: Tone } {
  const rows = section.rows
  const byLabel = (label: string) => rows.find((row) => row.label === label) || rows[0]

  switch (section.id) {
    case 'sales':
      return { title: 'Ventas', descriptor: 'Hoy · Total', row: rows[0], tone: 'blue' }
    case 'invoices':
      return { title: 'Facturas de Venta', descriptor: 'Quincena actual', row: rows[0], tone: 'blue' }
    case 'receivables':
      return { title: 'Impagados', descriptor: 'Total impagados', row: byLabel('Impagados'), tone: 'red' }
    case 'margins':
      return { title: 'Margen comercial', descriptor: 'Hoy · Total', row: byLabel('Margen %'), tone: 'green' }
    case 'delivery-notes':
      return { title: 'Albaranes de compra', descriptor: 'Importe mes actual', row: byLabel('Importe'), tone: 'blue' }
    case 'purchases':
      return { title: 'Compras y gastos', descriptor: 'Mes actual', row: byLabel('Mes Actual'), tone: 'blue' }
    case 'payables':
      return { title: 'Pagos pendientes', descriptor: 'Total pendiente', row: byLabel('Total Pagos'), tone: 'orange' }
  }
}

const toneClasses: Record<Tone, { metric: string; detail: string }> = {
  blue: { metric: 'text-[#206393]', detail: 'text-[#466276]' },
  green: { metric: 'text-emerald-700', detail: 'text-emerald-800' },
  red: { metric: 'text-red-700', detail: 'text-red-800' },
  orange: { metric: 'text-orange-700', detail: 'text-orange-800' },
}

export default function ExecutiveMobileV3({ sections }: ExecutiveMobileV3Props) {
  const [openSectionId, setOpenSectionId] = useState<DashboardMobileSection['id'] | null>(null)

  return (
    <div className="mx-auto w-full max-w-[450px] bg-[#f7f8fa] px-4 pb-8 pt-5 text-[#191c1e] sm:rounded-2xl sm:border sm:border-[#eef0f2]">
      <div className="space-y-3">
        {sections.map((section) => {
          const isOpen = openSectionId === section.id
          const meta = cardMeta(section)
          const tone = toneClasses[meta.tone]
          const metricCount = totalCount(meta.row)
          const sectionDomId = `mobile-v3-${section.id}`

          return (
            <section key={section.id} className="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white shadow-[0_1px_2px_rgba(15,23,42,0.035)]">
              <button
                type="button"
                aria-expanded={isOpen}
                aria-controls={`${sectionDomId}-content`}
                onClick={() => setOpenSectionId(isOpen ? null : section.id)}
                className="flex w-full items-center gap-3 px-4 py-3.5 text-left"
              >
                <span className="min-w-0 flex-1">
                  <span className={`block text-[15px] font-semibold ${tone.detail}`}>{meta.title}</span>
                  <span className="mt-1 block text-[12px] text-[#747878]">{meta.descriptor}</span>
                </span>
                <span className="min-w-0 text-right">
                  <span className={`block whitespace-nowrap text-[21px] font-bold tracking-tight ${tone.metric}`}>{formatValue(totalValue(meta.row), meta.row.format)}</span>
                  {metricCount > 0 && <span className="mt-0.5 block text-[11px] text-[#9aa0a6]">{numberFormatter.format(metricCount)} operaciones</span>}
                </span>
                <ChevronRight aria-hidden="true" className={`h-5 w-5 shrink-0 text-[#9aa0a6] transition-transform ${isOpen ? 'rotate-90' : ''}`} />
              </button>

              {isOpen && (
                <div id={`${sectionDomId}-content`} className="border-t border-[#eef0f2] px-4 pb-4 pt-3">
                  <div className="space-y-3">
                    {section.rows.map((row, index) => {
                      if (row.subheader) {
                        return <p key={`${row.label}-${index}`} className="pt-1 text-[13px] font-semibold text-[#747878]">{row.label}</p>
                      }

                      const isMargin = section.id === 'margins' && row.label === 'Margen %' && row.format === 'pct'
                      const rowTotal = totalValue(row)
                      const rowCount = totalCount(row)

                      if (row.totalOnly) {
                        const totalOnlyTone = section.id === 'receivables'
                          ? 'text-red-700'
                          : section.id === 'payables'
                            ? 'text-orange-700'
                            : 'text-[#206393]'
                        return (
                          <div key={`${row.label}-${index}`} className="rounded-lg bg-[#fafbfc] px-3 py-2.5">
                            <p className="text-[14px] font-medium text-[#466276]">{row.label}</p>
                            <p className={`mt-1 whitespace-nowrap text-[16px] font-bold ${totalOnlyTone}`}>
                              {formatValue(rowTotal, row.format)}{rowCount > 0 && <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(rowCount)})</span>}
                            </p>
                          </div>
                        )
                      }

                      const expandedTotalTone = isMargin
                        ? 'text-emerald-800'
                        : section.id === 'receivables'
                          ? 'text-red-700'
                          : section.id === 'payables'
                            ? 'text-orange-700'
                            : 'text-[#206393]'

                      return (
                        <div key={`${row.label}-${index}`} className={`rounded-lg px-3 py-3 ${isMargin ? 'bg-emerald-50/80' : 'bg-[#fafbfc]'}`}>
                          <div className="flex items-start justify-between gap-3">
                            <p className={`min-w-0 text-[14px] font-semibold ${isMargin ? 'text-emerald-800' : 'text-[#466276]'}`}>{row.label}</p>
                            <div className="shrink-0 text-right">
                              <p className="text-[11px] font-semibold uppercase tracking-wide text-[#8a9298]">Total</p>
                              <p className={`mt-0.5 whitespace-nowrap text-[17px] ${isMargin ? 'font-black' : 'font-bold'} ${expandedTotalTone}`}>
                                {formatValue(rowTotal, row.format)}{rowCount > 0 && <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(rowCount)})</span>}
                              </p>
                            </div>
                          </div>
                          <div className="mt-3 grid grid-cols-2 gap-3">
                            {[
                              ['Vielha', row.vielhaValue, row.vielhaCount],
                              ['Pont', row.pontValue, row.pontCount],
                            ].map(([store, value, count]) => (
                              <div key={String(store)} className="min-w-0">
                                <p className="text-[12px] text-[#747878]">{store}</p>
                                <p className={`mt-0.5 whitespace-nowrap text-[15px] ${isMargin ? 'font-bold text-emerald-700' : 'font-semibold text-[#191c1e]'}`}>
                                  {formatValue(Number(value), row.format)}{Number(count) > 0 && <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(Number(count))})</span>}
                                </p>
                              </div>
                            ))}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              )}
            </section>
          )
        })}
      </div>
    </div>
  )
}
