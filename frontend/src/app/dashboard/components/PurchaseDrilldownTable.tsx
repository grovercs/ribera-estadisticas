'use client'

import { useEffect, useMemo, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Search } from 'lucide-react'
import PurchaseLinesDrawer, { type PurchaseDocumentKey } from './PurchaseLinesDrawer'

export type PurchaseSortColumn = 'cod_compra' | 'fecha_compra' | 'cod_almacen' | 'cod_proveedor' | 'razon_social' | 'importe'

export interface PurchaseHeaderRow extends PurchaseDocumentKey {
  cod_almacen: number | null
  fecha_compra: string | null
  importe: string | number | null
}

interface PurchaseDrilldownTableProps {
  rows: PurchaseHeaderRow[]
  totalCount: number
  currentPage: number
  pageSize: number
  sort: PurchaseSortColumn
  direction: 'asc' | 'desc'
  search: string
  periodLabel: string
}

const columns: Array<{ key: PurchaseSortColumn; label: string; align?: 'right' }> = [
  { key: 'cod_compra', label: 'Albarán' },
  { key: 'fecha_compra', label: 'Fecha' },
  { key: 'cod_almacen', label: 'Almacén' },
  { key: 'cod_proveedor', label: 'Proveedor' },
  { key: 'razon_social', label: 'Razón social' },
  { key: 'importe', label: 'Importe', align: 'right' },
]

const moneyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

function toNumber(value: string | number | null): number {
  const numeric = typeof value === 'string' ? Number.parseFloat(value) : Number(value)
  return Number.isFinite(numeric) ? numeric : 0
}

function warehouseLabel(code: number | null): string {
  if (code === 1) return 'Pont de Suert'
  if (code === 2) return 'Vielha'
  return code === null ? '—' : String(code)
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const [year, month, day] = value.slice(0, 10).split('-').map(Number)
  if (!year || !month || !day) return value
  return new Date(year, month - 1, day, 12).toLocaleDateString('es-ES')
}

export default function PurchaseDrilldownTable({
  rows,
  totalCount,
  currentPage,
  pageSize,
  sort,
  direction,
  search,
  periodLabel,
}: PurchaseDrilldownTableProps) {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const [searchInput, setSearchInput] = useState(search)
  const [selectedDocument, setSelectedDocument] = useState<PurchaseDocumentKey | null>(null)
  const totalPages = Math.max(1, Math.ceil(totalCount / pageSize))

  useEffect(() => {
    setSearchInput(search)
  }, [search])

  const updateQuery = useMemo(() => {
    return (changes: Record<string, string | null>) => {
      const params = new URLSearchParams(searchParams.toString())
      Object.entries(changes).forEach(([key, value]) => {
        if (value) params.set(key, value)
        else params.delete(key)
      })
      router.push(`${pathname}?${params.toString()}`)
    }
  }, [pathname, router, searchParams])

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      const normalized = searchInput.trim()
      if (normalized !== search) {
        updateQuery({ search: normalized || null, page: null })
      }
    }, 350)

    return () => window.clearTimeout(timeout)
  }, [search, searchInput, updateQuery])

  const handleSort = (column: PurchaseSortColumn) => {
    const nextDirection = sort === column && direction === 'desc' ? 'asc' : 'desc'
    updateQuery({ sort: column, dir: nextDirection, page: null })
  }

  const start = totalCount === 0 ? 0 : (currentPage - 1) * pageSize + 1
  const end = Math.min(currentPage * pageSize, totalCount)

  return (
    <>
      <div className="flex flex-col gap-3 border-b border-[#e1e2e6] pb-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-black uppercase tracking-wider text-[#206393]">Albaranes de compra · {periodLabel}</p>
          <p className="mt-1 text-sm font-medium text-[#747878]">{totalCount} albaranes encontrados</p>
        </div>
        <label className="relative block w-full sm:w-96">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#747878]" aria-hidden="true" />
          <input
            type="search"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Buscar albarán, proveedor o razón social"
            className="w-full rounded-lg border border-[#d5d8dc] bg-white py-2 pl-9 pr-3 text-sm text-[#191c1e] outline-none transition focus:border-[#206393] focus:ring-2 focus:ring-[#206393]/15"
            aria-label="Buscar albaranes de compra"
          />
        </label>
      </div>

      <div className="mt-4 overflow-x-auto rounded-lg border border-[#e1e2e6] bg-white shadow-sm">
        <table className="w-full min-w-[960px] border-collapse text-sm tabular-nums">
          <thead className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-left text-[11px] font-black uppercase tracking-wide text-[#747878]">
            <tr>
              {columns.map((column) => {
                const active = sort === column.key
                const Icon = direction === 'asc' ? ArrowUp : ArrowDown
                return (
                  <th key={column.key} className={`px-3 py-2 ${column.align === 'right' ? 'text-right' : 'text-left'}`}>
                    <button
                      type="button"
                      onClick={() => handleSort(column.key)}
                      className={`inline-flex items-center gap-1 transition-colors hover:text-[#206393] ${column.align === 'right' ? 'justify-end' : ''} ${active ? 'text-[#206393]' : ''}`}
                    >
                      {column.label}
                      {active && <Icon className="h-3.5 w-3.5" aria-label={direction === 'asc' ? 'Orden ascendente' : 'Orden descendente'} />}
                    </button>
                  </th>
                )
              })}
            </tr>
          </thead>
          <tbody className="divide-y divide-[#f0f4f8]">
            {rows.map((row) => {
              const supplierName = row.razon_social || row.nombre_comercial || '—'
              return (
                <tr key={`${row.cod_compra}-${row.tipo_compra}-${row.cod_empresa}-${row.cod_proveedor}`} className="hover:bg-[#f8f9fc]">
                  <td className="px-3 py-2">
                    <button type="button" onClick={() => setSelectedDocument(row)} className="font-bold text-[#206393] hover:underline">
                      {row.cod_compra}
                    </button>
                  </td>
                  <td className="px-3 py-2 text-[#5a5e60]">{formatDate(row.fecha_compra)}</td>
                  <td className="px-3 py-2 text-[#5a5e60]">{warehouseLabel(row.cod_almacen)}</td>
                  <td className="px-3 py-2">
                    <button type="button" onClick={() => setSelectedDocument(row)} className="font-semibold text-[#191c1e] hover:text-[#206393] hover:underline">
                      {row.cod_proveedor}
                    </button>
                  </td>
                  <td className="max-w-[340px] truncate px-3 py-2 text-[#5a5e60]" title={supplierName}>{supplierName}</td>
                  <td className="px-3 py-2 text-right font-bold text-[#191c1e]">{moneyFormatter.format(toNumber(row.importe))}</td>
                </tr>
              )
            })}
            {rows.length === 0 && (
              <tr>
                <td colSpan={6} className="px-3 py-10 text-center text-sm font-medium text-[#747878]">No hay albaranes que coincidan con la búsqueda.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <footer className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-[#747878]">
        <span>Mostrando {start}–{end} de {totalCount}</span>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => updateQuery({ page: String(currentPage - 1) })}
            disabled={currentPage <= 1}
            className="inline-flex items-center gap-1 rounded-lg border border-[#d5d8dc] bg-white px-3 py-1.5 font-semibold text-[#191c1e] transition hover:bg-[#f0f4f8] disabled:cursor-not-allowed disabled:opacity-45"
          >
            <ChevronLeft className="h-4 w-4" aria-hidden="true" /> Anterior
          </button>
          <span className="font-semibold">Página {currentPage} de {totalPages}</span>
          <button
            type="button"
            onClick={() => updateQuery({ page: String(currentPage + 1) })}
            disabled={currentPage >= totalPages}
            className="inline-flex items-center gap-1 rounded-lg border border-[#d5d8dc] bg-white px-3 py-1.5 font-semibold text-[#191c1e] transition hover:bg-[#f0f4f8] disabled:cursor-not-allowed disabled:opacity-45"
          >
            Siguiente <ChevronRight className="h-4 w-4" aria-hidden="true" />
          </button>
        </div>
      </footer>

      <PurchaseLinesDrawer document={selectedDocument} onClose={() => setSelectedDocument(null)} />
    </>
  )
}
