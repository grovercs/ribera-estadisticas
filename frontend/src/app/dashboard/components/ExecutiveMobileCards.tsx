interface MobileRow {
  label: string
  vielhaValue?: number
  pontValue?: number
  vielhaCount?: number
  pontCount?: number
  totalValue?: number
  totalCount?: number
  format?: 'eur' | 'pct' | 'num'
  highlight?: boolean
  muted?: boolean
  totalOnly?: boolean
  subheader?: boolean
}

interface MobileSection {
  title: string
  rows: MobileRow[]
}

interface ExecutiveMobileCardsProps {
  sections: MobileSection[]
}

export default function ExecutiveMobileCards({ sections }: ExecutiveMobileCardsProps) {
  const truncateTo2Decimals = (value: number) => {
    const safeValue = Number.isFinite(value) ? value : 0

    return Math.trunc(safeValue * 100) / 100
  }

  const currencyFormatter = new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  const numberFormatter = new Intl.NumberFormat('es-ES')
  const percentFormatter = new Intl.NumberFormat('es-ES', {
    style: 'percent',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

  const fmt = (value: number | undefined, format?: MobileRow['format']) => {
    const v = Number(value) || 0
    if (format === 'pct') return percentFormatter.format(truncateTo2Decimals(v) / 100)
    if (format === 'num') return numberFormatter.format(v)
    return currencyFormatter.format(truncateTo2Decimals(v))
  }

  return (
    <div className="block md:hidden space-y-4">
      {sections.map((section) => (
        <div key={section.title} className="rounded-xl border border-[#e1e2e6] bg-white shadow-sm overflow-hidden">
          <div className="bg-[#206393] px-3 py-2 text-[14px] font-black uppercase tracking-widest text-white">
            {section.title}
          </div>
          <div className="space-y-2 p-2">
            {section.rows.map((row, idx) => {
              if (row.subheader) {
                return (
                  <div key={idx} className="bg-[#f8f9fc] px-3 py-1.5 text-[14px] font-black uppercase tracking-wider text-[#206393]">
                    {row.label}
                  </div>
                )
              }

              if (row.totalOnly) {
                const valueTone = row.muted ? 'text-[#747878]' : row.highlight ? 'text-[#206393]' : 'text-[#191c1e]'
                return (
                  <div key={idx} className={`rounded-lg px-3 py-2.5 ${row.highlight ? 'bg-blue-50/60' : row.muted ? 'bg-[#f8f9fc]' : 'bg-[#f8f9fc]/70'}`}>
                    <p className={`text-[14px] font-semibold ${row.muted ? 'text-[#747878]' : row.highlight ? 'text-[#206393]' : 'text-[#191c1e]'}`}>
                      {row.label}
                    </p>
                    <div className="mt-1 flex items-baseline justify-between gap-3">
                      <span className={`whitespace-nowrap text-[16px] font-black ${valueTone}`}>
                        {fmt(row.totalValue, row.format)}
                        {row.totalCount != null && (
                          <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(row.totalCount)})</span>
                        )}
                      </span>
                    </div>
                  </div>
                )
              }

              const v = Number(row.vielhaValue) || 0
              const p = Number(row.pontValue) || 0
              const total = row.totalValue !== undefined ? Number(row.totalValue) || 0 : v + p
              const c1 = Number(row.vielhaCount) || 0
              const c2 = Number(row.pontCount) || 0
              const totalCount = c1 + c2
              const isMarginRow = section.title.startsWith('4 · Márgenes Comerciales')
                && row.label === 'Margen %'
                && row.format === 'pct'

              const labelTone = isMarginRow ? 'text-emerald-800' : row.highlight ? 'text-[#206393]' : row.muted ? 'text-[#747878]' : 'text-[#191c1e]'
              const totalTone = isMarginRow ? 'text-emerald-800' : row.highlight ? 'text-[#206393]' : row.muted ? 'text-[#747878]' : 'text-[#191c1e]'

              return (
                <div key={idx} className={`rounded-lg px-3 py-2.5 ${isMarginRow ? 'bg-emerald-50/70' : row.highlight ? 'bg-blue-50/60' : row.muted ? 'bg-[#f8f9fc]' : 'bg-[#f8f9fc]/70'}`}>
                  <div className="flex items-start justify-between gap-3">
                    <p className={`text-[14px] font-semibold ${labelTone}`}>{row.label}</p>
                    <div className="text-right">
                      <p className="text-[13px] font-medium text-[#747878]">Total</p>
                      <p className={`whitespace-nowrap text-[16px] font-black ${totalTone}`}>
                        {fmt(total, row.format)}
                        {totalCount > 0 && <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(totalCount)})</span>}
                      </p>
                    </div>
                  </div>
                  <div className="mt-2 grid grid-cols-2 gap-2">
                    <div className="rounded-md bg-white/80 px-2.5 py-2">
                      <p className={`text-[13px] font-medium ${isMarginRow ? 'text-emerald-700' : 'text-[#747878]'}`}>Vielha</p>
                      <p className={`mt-0.5 whitespace-nowrap text-[15px] ${isMarginRow ? 'font-bold text-emerald-700' : 'font-semibold text-[#191c1e]'}`}>
                        {fmt(v, row.format)}
                        {c1 > 0 && <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(c1)})</span>}
                      </p>
                    </div>
                    <div className="rounded-md bg-white/80 px-2.5 py-2">
                      <p className={`text-[13px] font-medium ${isMarginRow ? 'text-emerald-700' : 'text-[#747878]'}`}>Pont</p>
                      <p className={`mt-0.5 whitespace-nowrap text-[15px] ${isMarginRow ? 'font-bold text-emerald-700' : 'font-semibold text-[#191c1e]'}`}>
                        {fmt(p, row.format)}
                        {c2 > 0 && <span className="ml-1 text-[11px] font-normal text-[#9aa0a6]">({numberFormatter.format(c2)})</span>}
                      </p>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  )
}
