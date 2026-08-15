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
  const currencyFormatter = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
  const numberFormatter = new Intl.NumberFormat('es-ES')
  const percentFormatter = new Intl.NumberFormat('es-ES', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 })

  const fmt = (value: number | undefined, format?: MobileRow['format']) => {
    const v = Number(value) || 0
    if (format === 'pct') return percentFormatter.format(v / 100)
    if (format === 'num') return numberFormatter.format(v)
    return currencyFormatter.format(v)
  }

  return (
    <div className="block sm:hidden space-y-4">
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
              const total = v + p
              const c1 = Number(row.vielhaCount) || 0
              const c2 = Number(row.pontCount) || 0
              const totalCount = c1 + c2

              return (
                <div key={idx} className="p-3">
                  <div className={`font-bold text-sm mb-2 ${row.highlight ? 'text-[#206393]' : row.muted ? 'text-[#747878]' : 'text-[#191c1e]'}`}>
                    {row.label}
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-xs">
                    <div className="text-right">
                      <div className="text-[#747878] font-semibold">Vielha</div>
                      <div className="font-bold text-[#191c1e]">
                        {fmt(v, row.format)}
                        {row.vielhaCount != null && (
                          <span className="text-[#9aa0a6] font-normal ml-1">({numberFormatter.format(c1)})</span>
                        )}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-[#747878] font-semibold">Pont</div>
                      <div className="font-bold text-[#191c1e]">
                        {fmt(p, row.format)}
                        {row.pontCount != null && (
                          <span className="text-[#9aa0a6] font-normal ml-1">({numberFormatter.format(c2)})</span>
                        )}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-[#191c1e] font-semibold">Total</div>
                      <div className="font-bold text-[#191c1e]">
                        {fmt(total, row.format)}
                        {row.vielhaCount != null && row.pontCount != null && (
                          <span className="text-[#9aa0a6] font-normal ml-1">({numberFormatter.format(totalCount)})</span>
                        )}
                      </div>
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
