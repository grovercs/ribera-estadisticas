'use client'

import { LayoutPanelTop, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { fmtEur, fmtPct, fmtCount } from '@/lib/formatters'

type SnapshotFormat = 'eur' | 'pct' | 'num'

interface SnapshotRow {
  label: string
  vielhaValue?: number
  pontValue?: number
  vielhaCount?: number
  pontCount?: number
  totalValue?: number
  totalCount?: number
  format?: SnapshotFormat
  highlight?: boolean
  muted?: boolean
  totalOnly?: boolean
  subheader?: boolean
}

interface SnapshotSection {
  title: string
  rows: SnapshotRow[]
}

interface DirectionSnapshotModalProps {
  sections: SnapshotSection[]
  lastDataLabel: string
  lastSync: string
}

function formatValue(value: number | undefined, format?: SnapshotFormat) {
  if (format === 'num') return fmtCount(value)
  if (format === 'pct') return fmtPct(value)
  return fmtEur(value)
}

function SnapshotCard({ section, className = '' }: { section: SnapshotSection; className?: string }) {
  const hasStoreColumns = section.rows.some((row) => !row.totalOnly && !row.subheader)

  return (
    <section className={`flex h-full flex-col overflow-hidden rounded-md border border-[#d7e1e9] bg-[#fbfcfd] shadow-none ${className}`}>
      <h2 className="shrink-0 truncate bg-[#206393] px-2 py-1 text-[10px] font-black uppercase tracking-wide text-white">
        {section.title}
      </h2>
      <div className="flex flex-1 flex-col justify-center divide-y divide-[#edf1f5] text-[10px] leading-tight tabular-nums">
        {hasStoreColumns && (
          <div className="grid grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] bg-[#f0f4f8] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-[#747878]">
            <span>Concepto</span>
            <span className="text-right">Vielha</span>
            <span className="text-right">Pont</span>
            <span className="text-right text-[#191c1e]">Total</span>
          </div>
        )}
        {section.rows.map((row, index) => {
          if (row.subheader) {
            return (
              <div key={`${row.label}-${index}`} className="truncate bg-[#f8f9fc] px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-[#206393]">
                {row.label}
              </div>
            )
          }

          const textTone = row.muted ? 'text-[#747878]' : row.highlight ? 'text-[#206393]' : 'text-[#191c1e]'

          if (row.totalOnly) {
            return (
              <div key={`${row.label}-${index}`} className={`grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-2 px-2 py-0.5 ${textTone}`}>
                <span className="truncate font-semibold">{row.label}</span>
                <span className="whitespace-nowrap text-right font-bold">
                  {formatValue(row.totalValue, row.format)}
                  {row.totalCount != null && <span className="ml-1 text-[9px] font-normal text-[#8a929a]">({fmtCount(row.totalCount)})</span>}
                </span>
              </div>
            )
          }

          const vielha = Number(row.vielhaValue) || 0
          const pont = Number(row.pontValue) || 0
          const total = row.totalValue !== undefined ? Number(row.totalValue) || 0 : vielha + pont
          const totalCount = (Number(row.vielhaCount) || 0) + (Number(row.pontCount) || 0)
          const isMarginPercentageRow = section.title.startsWith('4 · Márgenes Comerciales')
            && row.label === 'Margen %'
            && row.format === 'pct'

          return (
            <div key={`${row.label}-${index}`} className={`grid grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] items-baseline gap-1 px-2 py-0.5 ${textTone} ${isMarginPercentageRow ? 'bg-emerald-50/70' : ''}`}>
              <span className="truncate font-semibold">{row.label}</span>
              <span className={`whitespace-nowrap text-right ${isMarginPercentageRow ? 'text-[12px] font-bold text-emerald-700' : ''}`}>{formatValue(vielha, row.format)}{row.vielhaCount != null && <span className="ml-0.5 text-[9px] text-[#8a929a]">({fmtCount(row.vielhaCount)})</span>}</span>
              <span className={`whitespace-nowrap text-right ${isMarginPercentageRow ? 'text-[12px] font-bold text-emerald-700' : ''}`}>{formatValue(pont, row.format)}{row.pontCount != null && <span className="ml-0.5 text-[9px] text-[#8a929a]">({fmtCount(row.pontCount)})</span>}</span>
              <span className={`whitespace-nowrap text-right ${isMarginPercentageRow ? 'text-[13px] font-black text-emerald-800' : 'font-bold'}`}>
                {formatValue(total, row.format)}{totalCount > 0 && <span className="ml-0.5 text-[9px] font-normal text-[#8a929a]">({fmtCount(totalCount)})</span>}
              </span>
            </div>
          )
        })}
      </div>
    </section>
  )
}

export default function DirectionSnapshotModal({ sections, lastDataLabel, lastSync }: DirectionSnapshotModalProps) {
  const [isOpen, setIsOpen] = useState(false)

  useEffect(() => {
    if (!isOpen) return

    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setIsOpen(false)
    }

    const originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', closeOnEscape)

    return () => {
      document.body.style.overflow = originalOverflow
      window.removeEventListener('keydown', closeOnEscape)
    }
  }, [isOpen])

  return (
    <>
      <button
        type="button"
        onClick={() => setIsOpen(true)}
        className="hidden h-[18px] items-center gap-1 rounded border border-[#d4e3ee] bg-white px-1.5 text-[9px] font-bold leading-none normal-case tracking-normal text-[#174f75] shadow-sm transition-colors hover:border-white hover:bg-[#edf5fa] focus:outline-none focus:ring-2 focus:ring-white/75 md:inline-flex"
        aria-label="Abrir vista compacta del Cuadro de Dirección"
      >
        <LayoutPanelTop className="h-3 w-3" aria-hidden="true" />
        Vista compacta
      </button>

      {isOpen && (
        <div
          className="fixed inset-0 z-[70] hidden items-center justify-center bg-[#102536]/60 p-3 backdrop-blur-[1px] md:flex"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setIsOpen(false)
          }}
        >
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="direction-snapshot-title"
            className="flex h-[88vh] max-h-[88vh] w-[92vw] max-w-[1160px] flex-col overflow-x-hidden overflow-y-auto rounded-xl border border-[#afc0cf] bg-[#f2f6f9] shadow-[0_24px_60px_rgba(15,38,58,0.28)]"
          >
            <header className="flex h-7 flex-none items-center justify-between border-b border-[#d1dde6] bg-[#edf3f7] px-3">
              <div className="flex min-w-0 items-baseline gap-2">
                <h1 id="direction-snapshot-title" className="truncate text-[12px] font-black uppercase tracking-wide text-[#206393]">Cuadro de Dirección · Vista compacta</h1>
                <p className="truncate text-[10px] font-medium text-[#747878]">Últimos datos: {lastDataLabel} · Sync {lastSync}</p>
              </div>
              <button type="button" onClick={() => setIsOpen(false)} className="ml-3 rounded p-1 text-[#52616d] transition-colors hover:bg-[#e3eaf1] hover:text-[#191c1e] focus:outline-none focus:ring-2 focus:ring-[#206393]" aria-label="Cerrar vista compacta">
                <X className="h-4 w-4" aria-hidden="true" />
              </button>
            </header>

            <div className="min-h-0 flex-1 p-2">
              <div className="grid min-h-full grid-cols-2 gap-2">
                <div className="grid min-h-0 grid-rows-[minmax(min-content,1.15fr)_minmax(min-content,0.7fr)_minmax(min-content,1.45fr)] gap-2">
                  <SnapshotCard section={sections[0]} />
                  <SnapshotCard section={sections[2]} />
                  <SnapshotCard section={sections[3]} />
                </div>
                <div className="grid min-h-0 grid-rows-[minmax(min-content,1.2fr)_minmax(min-content,0.45fr)_minmax(min-content,0.9fr)_minmax(min-content,1.1fr)] gap-2">
                  <SnapshotCard section={sections[1]} />
                  <SnapshotCard section={sections[4]} />
                  <SnapshotCard section={sections[5]} />
                  <SnapshotCard section={sections[6]} />
                </div>
              </div>
            </div>

            <footer className="flex h-5 flex-none items-center border-t border-[#d1dde6] bg-[#edf3f7] px-3 text-[9px] font-medium text-[#747878]">
              Fuente: ERP INTEGRAL
            </footer>
          </div>
        </div>
      )}
    </>
  )
}
