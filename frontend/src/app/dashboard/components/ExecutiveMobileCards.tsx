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
    const epsilon = Number.EPSILON * Math.max(1, Math.abs(safeValue))

    return Math.trunc((safeValue + Math.sign(safeValue || 1) * epsilon) * 100) / 100
  }

  const currencyFormatter = new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  const numberFormatter = new Intl.NumberFormat('es-ES')
  const percentFormatter = new Intl.NumberFormat('es-ES', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 })

  const fmt = (value: number | undefined, format?: MobileRow['format']) => {
    const v = Number(value) || 0
    if (format === 'pct') return percentFormatter.format(v / 100)
    if (format === 'num') return numberFormatter.format(v)
    return currencyFormatter.format(truncateTo2Decimals(v))
  }

  const ValueLine = ({
    label,
    value,
    count,
    format,
    isTotal = false,
  }: {
    label: string
    value: number
    count?: number
    format?: MobileRow['format']
    isTotal?: boolean
  }) => (
    <div className={`flex justify-between items-baseline ${isTotal ? 'border-t border-[#f2f3f7] pt-1 mt-1' : ''}`}>
      <span className={`font-semibold ${isTotal ? 'text-[#191c1e]' : 'text-[#747878]'}`}>{label}</span>
      <span className={`font-bold ${isTotal ? 'text-[#191c1e]' : 'text-[#191c1e]'}`}>
        {fmt(value, format)}
        {count != null && (
          <span className="text-[10px] text-[#9aa0a6] font-normal ml-1">({numberFormatter.format(count)})</span>
        )}
      </span>
    </div>
  )

  return (
    <div className="block md:hidden space-y-4">
      {sections.map((section) => (
        <div key={section.title} className="rounded-xl border border-[#e1e2e6] bg-white shadow-sm overflow-hidden">
          <div className="px-3 py-2 text-xs font-black uppercase tracking-widest text-white bg-[#206393]">
            {section.title}
          </div>
          <div className="divide-y divide-[#f2f3f7]">
            {section.rows.map((row, idx) => {
              if (row.subheader) {
                return (
                  <div key={idx} className="px-3 py-1.5 text-xs font-black text-[#206393] uppercase tracking-wider bg-[#f8f9fc]">
                    {row.label}
                  </div>
                )
              }

              if (row.totalOnly) {
                return (
                  <div key={idx} className="p-3 flex justify-between items-center">
                    <span className={`font-bold text-sm ${row.muted ? 'text-[#747878]' : 'text-[#191c1e]'}`}>
                      {row.label}
                    </span>
                    <span className={`font-bold text-sm ${row.muted ? 'text-[#747878]' : row.highlight ? 'text-[#206393]' : 'text-[#191c1e]'}`}>
                      {fmt(row.totalValue, row.format)}
                      {row.totalCount != null && (
                        <span className="text-xs text-[#9aa0a6] font-normal ml-1">({numberFormatter.format(row.totalCount)})</span>
                      )}
                    </span>
                  </div>
                )
              }

              const v = Number(row.vielhaValue) || 0
              const p = Number(row.pontValue) || 0
              const total = row.totalValue !== undefined ? Number(row.totalValue) || 0 : v + p
              const c1 = Number(row.vielhaCount) || 0
              const c2 = Number(row.pontCount) || 0
              const totalCount = c1 + c2

              return (
                <div key={idx} className="p-3">
                  <div className={`font-bold text-sm mb-2 ${row.highlight ? 'text-[#206393]' : row.muted ? 'text-[#747878]' : 'text-[#191c1e]'}`}>
                    {row.label}
                  </div>
                  <div className="space-y-0.5 text-xs">
                    <ValueLine label="Vielha" value={v} count={c1} format={row.format} />
                    <ValueLine label="Pont" value={p} count={c2} format={row.format} />
                    <ValueLine label="Total" value={total} count={totalCount} format={row.format} isTotal />
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
